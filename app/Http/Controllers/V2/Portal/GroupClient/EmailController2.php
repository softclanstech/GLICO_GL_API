<?php

namespace App\Http\Controllers\V2\Portal\GroupClient;

use no;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

class EmailController2 extends Controller
{
    //

    public function sendOTPusingPolicyNumber(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'policy_no' => 'required',
            'contact_person_email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid request data',
                'errors' => $validator->errors()
            ], 400);
        }
        try {
            // $scheme_id = $request->scheme_id;
            // $gc_id = $request->gc_id;

            $policy_no = $request->policy_no;
            $contact_person_email = $request->contact_person_email;

            //select * from polschemeinfo p where p.policy_no = 'BGLAP/2023/00004';

            $policy_visibility = $this->britam_db->table('polschemeinfo')
                ->where('policy_no', $policy_no)
                ->select('ClientNumber', 'interm_ID')
                //->first();
                ->get();

            if ($policy_visibility == null) {
                return response()->json([
                    'success' => false,
                    'message' => 'No results found.',
                    'data' => []
                ], 400);
            }

            $broker_id = null;
            $gc_id = null;
            $contact_person = null;

            // loop through the policy_visibility as there might be multiple policies with the same policy number :: should be discussed

            // should break once it gets the contact person
            foreach ($policy_visibility as $policy) {
                //group client id from contact persons email
                $gc_id = $policy->ClientNumber;
                //broker id from policy number
                $broker_id = $policy->interm_ID;
                // check contactpersoninfo table if the email exists under the client
                $contact_person = $this->britam_db->table('contactpersoninfo')
                    ->where('ContactEmail', $contact_person_email)
                    ->where(function ($query) use ($gc_id, $broker_id) {
                        $query->where('Client', $gc_id)
                            ->orWhere('Intermediary', $broker_id);
                    })
                    ->get();
                 //   print_r ($contact_person);
                // Check if $contact_person has any records, if so, break the loop
                if ($contact_person->isNotEmpty()) {
                    break;
                }
            }

            //echo $contact_person;

            if ($contact_person->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'The email does not exist under the client.'
                ], 400);
            }

            // get email from glifeclientinfo
            $data = $this->britam_db->table('PortalUserLoginInfo AS p')
                ->join('contactpersoninfo AS c', 'c.id', '=', 'p.ContactPerson')
                ->where('c.ContactEmail', $contact_person_email)
                ->select('c.ContactEmail', 'p.Otp', 'p.Password', 'p.ContactPerson')
                ->first();

            //print_r($data);

            // check if there is a password keep in mind that it might be null. so cannot read the value directly

            //check if password is not null
            if ($data->Password != null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Contact Person has a password already.'
                ], 400);
            }

            //$otp = $email->Otp;

            if ($data == null) {
                return response()->json([
                    'success' => false,
                    'message' => 'No email found for the contact person.'
                ], 400);

            } else {
                // send email
                $email = $data->ContactEmail;

                //validate email
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid email address.'
                    ], 400);
                }

                //generate OTP
                //$otp = rand(1000, 9999);
                $otp = substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 9);

                $contact_person_id = $data->ContactPerson;
                //print_r($contact_person_id);

                //update otp in glifeclientinfo under the client
                $this->britam_db->table('PortalUserLoginInfo')
                    ->where('ContactPerson', $contact_person_id)
                    ->update(['Otp' => $otp]);

                $contactpersoninfo_details = $this->britam_db->table('PortalUserLoginInfo')
                    ->join('contactpersoninfo', 'contactpersoninfo.id', '=', 'PortalUserLoginInfo.ContactPerson')
                    ->where('PortalUserLoginInfo.ContactPerson', $contact_person_id)
                    ->select('contactpersoninfo.ContactEmail')
                    ->first();

                $to = $email;
                $subject = "One Time Password";
                $message = "Your OTP is: " . $otp;

                $token = $request->bearerToken();

                $result = $this->sendEmail($to, $subject, $message, $contactpersoninfo_details);
                //print_r($result);
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

            }

        } catch (\Throwable $th) {
            //throw $th;
            return response()->json([
                'success' => false,
                'message' => 'An error occurred.',
                'error' => $th->getMessage()
            ], 500);
        }
 
    }

    public function verifyOTP(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'otp' => 'required',
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
            $otp = $request->otp;
            $email = $request->email;

            $verify_otp = $this->britam_db->table('PortalUserLoginInfo')
                ->join('contactpersoninfo', 'contactpersoninfo.id', '=', 'PortalUserLoginInfo.ContactPerson')
                ->where('contactpersoninfo.ContactEmail', $email)
                ->select('PortalUserLoginInfo.Otp')
                ->first();

            if ($verify_otp == null) {
                return response()->json([
                    'success' => false,
                    'message' => 'No results found.',
                    'data' => []
                ], 400);
            }
            // verify if otp matches
            $set_otp = $verify_otp->Otp;

            if ($set_otp == $otp) {
                return response()->json([
                    'success' => true,
                    'message' => 'OTP matches.'
                ], 200);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'OTP does not match.'
                ], 400);
            }

        } catch (\Throwable $th) {
            //throw $th;
            return response()->json([
                'success' => false,
                'message' => 'An error occurred.',
                'error' => $th->getMessage()
            ], 500);

        }
    }
}
