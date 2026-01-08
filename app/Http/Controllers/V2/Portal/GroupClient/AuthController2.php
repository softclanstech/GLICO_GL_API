<?php

namespace App\Http\Controllers\V2\Portal\GroupClient;

use Illuminate\Http\Request;
use App\Models\ContactPerson;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

class AuthController2 extends Controller
{

    private function InsertLoginTracking($portalUserId, $isSuccessful, $loginRetries, $userAgent, $ipAddress, $loginTime)
    {
        $insertData = [
            'IsLoginSuccessful' => $isSuccessful,
            'LoginTime' => $loginTime,
            'LoginRetries' => $loginRetries,
            'UserAgent' => $userAgent,
            'IPAddress' => $ipAddress,
            'PortalUser' => $portalUserId,
            'created_by' => 'API',
            'created_on' => now()
        ];

        $this->britam_db->table('LoginTracking')->insert($insertData);
    }

    // login
    public function LoginAsContactPersons(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'is_group_client_contact_person' => 'required|boolean',
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid request data',
                'data' => $validator->errors()
            ], 400);
        }

        try {
            $email = $request->input('email');
            $password = $request->input('password');
            $userAgent = null;
            $ipAddress = $request->ip();
            $loginTime = now();
            $maxRetries = 3;
            $loginRetries = 0;
            $is_contact_person = $request->input('is_group_client_contact_person');


            //check if the user is a group client contact person or a broker client contact person
            // 1 on the is_group_client_contact_person means the user is a group client contact person else the user is a broker client contact person
            // if the user specifies that they are a group client contact person, then the user should be able to login only if the IsClientContactPerson is true
            // if the user specifies that they are a broker client contact person, then the user should be able to login only if the IsBroker is true

            $client_results = null;

            if ($is_contact_person == 1) {

                $client_results = $this->britam_db->table('PortalUserLoginInfo AS p')
                    ->join('contactpersoninfo AS c', 'c.id', '=', 'p.ContactPerson')
                    ->where('c.ContactEmail', $email)
                    ->where('p.IsClientContactPerson', 1)
                    ->select('p.*', 'c.Client', 'c.Intermediary', 'c.PopFundAccessLevelId')
                    ->first();
            } else {

                $client_results = $this->britam_db->table('PortalUserLoginInfo AS p')
                    ->join('contactpersoninfo AS c', 'c.id', '=', 'p.ContactPerson')
                    ->where('c.ContactEmail', $email)
                    ->where('p.IsBroker', 1)
                    ->select('p.*', 'c.Client', 'c.Intermediary', 'c.PopFundAccessLevelId')
                    ->first();
            }

            //print_r($client_results);

            if ($client_results == null) {
                return response()->json([
                    'success' => false,
                    'message' => 'No contact person found with the provided email. Please contact the administrator to create an account.',
                ], 400);
            }

            if ($client_results->Password == null) {
                return response()->json([
                    'success' => false,
                    'message' => 'No password set for this contact person. Please contact the administrator to set a password.'
                ], 401);
            }

            //get all schemes for the client and check their status
            //if all schemes are inactive, then the user should not be allowed to login

            //SELECT p.schemeID, g.IsExpired FROM polschemeinfo p 
            // INNER JOIN glifestatus g ON g.status_code = p.StatusCode
            // WHERE g.IsExpired = 1 and p.ClientNumber = 1;

            $client_schemes = $this->britam_db->table('polschemeinfo AS p')
                ->join('glifestatus AS g', 'g.status_code', '=', 'p.StatusCode')
                ->where('g.IsExpired', 1)
                ->where('p.ClientNumber', $client_results->Client)
                ->orWhere('p.interm_ID', $client_results->Intermediary)
                ->get();

            $all_client_schemes = $this->britam_db->table('polschemeinfo AS p')
                ->join('glifestatus AS g', 'g.status_code', '=', 'p.StatusCode')
                ->where('p.ClientNumber', $client_results->Client)
                ->orWhere('p.interm_ID', $client_results->Intermediary)
                ->get();



            if ($all_client_schemes->count() == 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'No schemes found for the client. Please contact the administrator.'
                ], 401);
            }

            // check if all the schemes found on the client are inactive

            $inactive_schemes = $client_schemes->where('IsExpired', 1);

            if ($inactive_schemes->count() == $all_client_schemes->count()) {
                return response()->json([
                    'success' => false,
                    'message' => 'All schemes are inactive. Please contact the administrator.'
                ], 401);
            }

            $cp_user_id = $client_results->Id;

            //check if IsBroker or IsClientContactPerson is true to know the type of client logged in from PortalUserLoginInfo and save it on client type variable
            $client_type = $this->britam_db->table('PortalUserLoginInfo')->select('*')->where('Id', $cp_user_id)->first();

            if ($client_type->IsBroker == 1) {
                $client_type = 'broker client contact person';
            } else if ($client_type->IsClientContactPerson == 1) {
                $client_type = 'group client contact person';
            }

            if (!password_verify($password, $client_results->Password)) {
                // Insert login tracking data for failed login attempt
                $this->insertLoginTracking($cp_user_id, false, $maxRetries, $userAgent, $ipAddress, $loginTime);

                return response()->json([
                    'success' => false,
                    'message' => 'Invalid credentials.'
                ], 401);
            }

            // Successful login attempt
            if (!$client_results->IsActive) {
                return response()->json([
                    'success' => false,
                    'message' => 'User is not active. Please contact the administrator.'
                ], 401);
            }

            // check if the contact person is required to reset pass:: check if PasswordReset is true
            if ($client_results->PasswordReset) {
                return response()->json([
                    'success' => false,
                    'message' => 'Password reset required. Please reset your password.'
                ], 401);
            }

            //check if DisablePortalAccess is true
            if ($client_results->DisablePortalAccess) {
                return response()->json([
                    'success' => false,
                    'message' => 'User portal access is disabled. Please contact the administrator.'
                ], 401);
            }

            //$gc_user = GlifeClientInfo::where('email', $email)->first();

            $cp_user = ContactPerson::where('ContactEmail', $email)->first();

            $cp_user->setConnection('sqlsrv');
            $tokens = $cp_user->createToken('corporate-api-token', expiresAt: now()->addDays(1));

            $accessToken = $tokens->plainTextToken;
            // Set the access token as a cookie
            $accessTokenCookie = cookie('access_token', $accessToken, 60, null, null, false, true);
            // Insert login tracking data for successful login
            $this->insertLoginTracking($cp_user_id, true, $loginRetries, $userAgent, $ipAddress, $loginTime);

            //$request->session()->put('contact_person_id', $cp_user_id);

            return response()->json([
                'success' => true,
                'message' => 'Login successful.',
                'client_type' => $client_type,
                'access_token' => $accessToken,
                'data' => $client_results
            ], 200)->cookie($accessTokenCookie);

        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while processing your request.',
                'data' => $th->getMessage()
            ], 500);
        }
    }

    public function CreateContactPersonsPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'otp' => 'nullable', //this is the OTP
            // Stronger password requirement
            'password' => 'required|string|min:8|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*()\-_=+{};:,<.>]).+$/',
            'confirm_password' => 'required|same:password'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid request data',
                'errors' => $validator->errors()
            ], 400);
        }

        try {

            // transaction to ensure that the password is created only if the OTP is valid
            $this->britam_db->beginTransaction();

            $password = $request->input('password');
            $email = $request->input('email');
            $otp = $request->input('otp');

            $userAgent = null;
            $ipAddress = $request->ip();
            $loginRetries = 0;
            $loginTime = now();

            $user_results = $this->britam_db->table('PortalUserLoginInfo AS p')
                ->join('contactpersoninfo AS c', 'c.id', '=', 'p.ContactPerson')
                ->where('c.ContactEmail', $email)
                ->first();

            //print_r($results);

            if ($user_results == null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid email or password',
                ], 400);
            }

            // $client_type = $this->britam_db->table('glifeclientinfo')->select('*')->where('Id', $user_results->Client)->first();

            // if ($client_type->client_type != 1) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'Client is not a corporate institution.'
            //     ], 401);
            // }

            if ($user_results->Password != null && $user_results->PasswordReset == false) {
                return response()->json([
                    'success' => false,
                    'message' => 'User already exists.'
                ], 401);
            }

            // if ($user_results->Otp != null) {
            //     if ($user_results->Otp != $otp) {
            //         return response()->json([
            //             'success' => false,
            //             'message' => 'Invalid OTP.'
            //         ], 401);
            //     }
            // }

            // use OldPassword field to know if the user is resetting the password or creating a new one
            // if OldPassword is not null, then the user is setting a new pasword, else the user is resetting the password
            // if the user resetting then the value in the OldPassword should not be the same as the new password, should throw an
            // error message telling the user to use a different password

            if ($user_results->OldPassword != null) {
                //the passwords are both hashed so we need to compare the hashed values
                if (password_verify($password, $user_results->OldPassword)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Password cannot be the same as the previous password.'
                    ], 401);
                }
            }

            $cp_id = $user_results->ContactPerson;
            // print_r($cp_id);

            $bcrypt_hashed_password = password_hash($password, PASSWORD_BCRYPT);

            $hashed_string_pass = (string) $bcrypt_hashed_password;

            Log::channel('corporate_api')->info('Hashed password: ' . $hashed_string_pass);

            $results = $this->britam_db->table('PortalUserLoginInfo')
                ->where('ContactPerson', $cp_id)
                ->update(['Password' => $hashed_string_pass, 'IsActive' => true, 'Otp' => null]);

            $cp_user_id = $user_results->Id;


            $this->InsertLoginTracking($cp_user_id, true, $loginRetries, $userAgent, $ipAddress, $loginTime);

            //if PasswordReset is true, set it to false
            $this->britam_db->table('PortalUserLoginInfo')
                ->where('ContactPerson', $cp_id)
                ->update(['PasswordReset' => false]);

            $this->britam_db->commit();

            if ($results) {
                return response()->json([
                    'success' => true,
                    'message' => 'Password created successfully.'
                ], 201);
            } else {

                return response()->json([
                    'success' => false,
                    'message' => 'Error creating password.'
                ], 500);
            }

        } catch (\Throwable $th) {
            //throw $th;
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while processing your request.',
                'data' => $th->getMessage()
            ], 500);
        }

    }

    // forgot password
    public function ForgotCPPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid request data',
                'errors' => $validator->errors()
            ], 400);
        }

        try {

            $email = $request->input('email');

            $results = $this->britam_db->table('PortalUserLoginInfo AS p')
                ->join('contactpersoninfo AS c', 'c.id', '=', 'p.ContactPerson')
                ->where('c.ContactEmail', $email)
                ->first();

            if ($results == null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid email or password',
                ], 400);
            }

            // $client_type = $this->britam_db->table('glifeclientinfo')->select('*')->where('Id', $results->Client)->first();

            // if ($client_type->client_type != 1) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'Client is not a corporate institution.'
            //     ], 401);
            // }

            $cp_id = $results->ContactPerson;

            // move the current password to the OldPassword field
            $this->britam_db->table('PortalUserLoginInfo')
                ->where('ContactPerson', $cp_id)
                ->update(['OldPassword' => $results->Password]);

            //set PasswordReset to true
            $this->britam_db->table('PortalUserLoginInfo')
                ->where('ContactPerson', $cp_id)
                ->update(['PasswordReset' => true]);

            // one time password to contain digits and letters and be 9 characters long
            $otp = substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 9);

            $this->britam_db->table('PortalUserLoginInfo')
                ->where('ContactPerson', $cp_id)
                ->update(['Otp' => $otp]);

            $contactpersoninfo_details = $this->britam_db->table('PortalUserLoginInfo')
                ->join('contactpersoninfo', 'contactpersoninfo.id', '=', 'PortalUserLoginInfo.ContactPerson')
                ->where('PortalUserLoginInfo.ContactPerson', $cp_id)
                ->select('contactpersoninfo.ContactEmail', 'PortalUserLoginInfo.Otp')
                ->first();

            $to = $email;
            $subject = "One Time Password";
            $message = "Your OTP is: " . $otp;
            $token = $request->bearerToken();

            $result = $this->sendEmail($to, $subject, $message, $contactpersoninfo_details);
           // $result = self::britam_email_sending($subject, $message, $to, $token);

            if ($result) {
                return response()->json([
                    'success' => true,
                    'message' => 'OTP sent successfully.',
                    'data' => $contactpersoninfo_details
                ], 200);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'An error occurred while sending the email.'
                ], 500);
            }


        } catch (\Throwable $th) {
            //throw $th;
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while processing your request.',
                'data' => $th->getMessage()
            ], 500);
        }

    }
    // logout
    public function logoutAsContactPersons(Request $request)
    {
        try {
            // Get and print the contact_person_id session variable
            $contact_person_id = $request->session()->get('contact_person_id');

            if (!$contact_person_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found in session. Please log in.'
                ], 404);
            }

            // Remove the contact_person_id session variable
            $request->session()->forget($contact_person_id);

            // Delete the access token cookie by setting its expiration time to a past date
            $accessTokenCookie = cookie('access_token', '', -1);

            // Check if the session variable and cookie are properly removed
            if ($request->session()->has('contact_person_id') || $request->hasCookie('access_token')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to logout.'
                ], 500);
            }

            // Return success response if both session variable and cookie are properly removed
            return response()->json([
                'success' => true,
                'message' => 'Logout successful.'
            ], 200)->cookie($accessTokenCookie);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to logout.',
                'error' => $th->getMessage()
            ], 500);
        }
    }


    public function GetCurrentContactPersons(Request $request)
    {
        try {
            $contact_person_id = $request->session()->get('contact_person_id');

            if (!$contact_person_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found in session. Please log in.'
                ], 404);
            }

            // Retrieve user details based on the ID stored in the session
            $cp_id = $this->britam_db->table('PortalUserLoginInfo AS p')
                ->join('contactpersoninfo AS c', 'c.id', '=', 'p.ContactPerson')
                ->where('p.Id', $contact_person_id)
                ->first()->ContactPerson;

            $user = ContactPerson::find($cp_id);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found.'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'User details retrieved successfully.',
                'data' => $user
            ], 200);

        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve user details.',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    //get current user using access_tokens
    public function GetCurrentContactPersonsUsingToken(Request $request)
    {
        try {

            $access_token = $request->accessToken;
            //if $access_token is blank then get the token from the cookie
            if (!$access_token) {

                $access_token = $request->cookie('access_token');
            }

            // if $access_token is still blank then return an error message
            if (!$access_token) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access token not provided.'
                ], 400);
            }

            // from 4|p3EnspfE9oVWfnzsJQxglEUVYNxiY43EWwSms6TQ34ad361c take only the token
            $access_token = explode('|', $access_token)[1];

            // the current token $access_token is in plain text

            //plaintext reverted back to hashed token

            $orignal_token = hash('sha256', $access_token);

            //print_r($orignal_token);
            $contact_person_id = $this->api_token_db->table('personal_access_tokens')
                ->where('token', $orignal_token)
                ->first()->tokenable_id;

            // Retrieve user details based on the ID stored in the session
            $cp_id = $this->britam_db->table('PortalUserLoginInfo AS p')
                ->join('contactpersoninfo AS c', 'c.id', '=', 'p.ContactPerson')
                ->where('c.id', $contact_person_id)
                ->first()->ContactPerson;


            $user = ContactPerson::find($cp_id);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found.'
                ], 404);
            }

            // return response()->json([
            //     'success' => true,
            //     'message' => 'User details retrieved successfully.',
            //     'data' => $user
            // ], 200);

            $client_results = $this->britam_db->table('PortalUserLoginInfo AS p')
                ->join('contactpersoninfo AS c', 'c.id', '=', 'p.ContactPerson')
                ->where('c.ContactEmail', $user->ContactEmail)
                ->select('p.*', 'c.Client', 'c.Intermediary')
                ->first();

            return response()->json([
                'success' => true,
                'message' => 'Current User fetched successfully.',
                'data' => $client_results
            ], 200);

        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve user details.',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    // function to log out a user

    public function user(Request $request)
    {
        return response()->json($request->user());
    }

}
