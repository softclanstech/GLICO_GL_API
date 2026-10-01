<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    protected $britam_db;
    protected $api_token_db;

    public function __construct()
    {
        $this->britam_db = DB::connection('britam_db'); //life
        $this->api_token_db = DB::connection('sqlsrv'); //life
    }

    public function generateClientCredentialsToken()
    {
        $url = config('services.passport_client.token_url');
        $client_id = config('services.passport_client.client_id');
        $client_secret = config('services.passport_client.client_secret');

        if (!$client_id || !$client_secret) {
            return response()->json([
                'success' => false,
                'message' => 'Client credentials are not configured.',
            ], 500);
        }

        $data = [
            'grant_type' => 'client_credentials',
            'client_id' => $client_id,
            'client_secret' => $client_secret,
            //'scope' => '*'
        ];

       $http = new \GuzzleHttp\Client();

        try {
            $response = $http->post($url, ['form_params' => $data]);
            /*$response = Http::asForm()->post($url, [
                'grant_type' => 'client_credentials',
                'client_id' => $client_id,
                'client_secret' => $client_secret,
                //'scope' => '*'
            ]);*/
            
            if ($response->getStatusCode() == 200) {
                $response = json_decode($response->getBody(), true);
                return response()->json([
                    'success' => true,
                    'message' => 'Token generated successfully',
                    'data' => $response
                ], 200);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => $response
                ], 500);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Exception: ' . $e->getMessage()
            ], 500);
        }
    }



    public function testDatabaseConnection()
    {
        try {
            $tables = $this->britam_db->select("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE = 'BASE TABLE'");

            $tableNames = array_column($tables, 'TABLE_NAME');
            var_dump($tableNames);
        } catch (\Exception $exception) {
            echo 'Error: ' . $exception->getMessage();
        }
    }

    public function generateRequestNumber($is_endorsement, $is_claim, $scheme_id)
    {
        $policy_number = $this->britam_db->table('polschemeinfo')
            ->select('policy_no')
            ->where('SchemeID', $scheme_id)
            ->first()->policy_no;

        $request_prefix = $is_endorsement ? 'END-REQ' : ($is_claim ? 'CLM-REQ' : 'REQ');

        $unique_identifier = substr(uniqid(), -6);

        $request_number = $request_prefix . '-' . date('Y') . '-{' . $policy_number . '}-' . $unique_identifier;

        return $request_number;
    }

    public function sendEmails($to, $subject, $message, $login_details)
    {
        try {
            Mail::raw($message, function ($mail) use ($to, $subject) {
                $mail->to($to)->subject($subject);
            });

            if (Mail::failures()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to send email to some or all recipients.',
                    'failedRecipients' => Mail::failures()
                ], 500);
            }
    
            return response()->json([
                'success' => true,
                'message' => 'Email sent successfully.',
                'data' => $login_details
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred.',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function sendEmail($to, $subject, $message, $login_details)
    {
        try {
            $tenant_id = config('services.azure_mail.tenant_id');
            $client_id = config('services.azure_mail.client_id');
            $client_secret = config('services.azure_mail.client_secret');

            if (!$tenant_id || !$client_id || !$client_secret) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mail service credentials are not configured.',
                ], 500);
            }

            $token_url = "https://login.microsoftonline.com/{$tenant_id}/oauth2/v2.0/token";
            $grant_type = 'client_credentials';
            $scope = 'https://graph.microsoft.com/.default';
            
            $token_response = Http::asForm()->post($token_url, [
                'client_id' => $client_id,
                'client_secret' => $client_secret,
                'grant_type' => $grant_type,
                'scope' => $scope
            ]);

            if (!$token_response->successful()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to obtain access token.',
                    'error' => $token_response->json()
                ], 500);
            }

            $access_token = $token_response->json()['access_token'];
            $sender_email = config('services.azure_mail.sender');
            $send_mail_url = "https://graph.microsoft.com/v1.0/users/{$sender_email}/sendMail";

            $recipients = is_array($to) ? $to : [$to];
            $toRecipients = array_map(function($email) {
                return [
                    'emailAddress' => [
                        'address' => $email
                    ]
                ];
            }, $recipients);

            $mail_data = [
                'message' => [
                    'subject' => $subject,
                    'body' => [
                        'contentType' => 'Text',
                        'content' => $message
                    ],
                    'toRecipients' => $toRecipients
                ]
            ];

            $send_response = Http::withToken($access_token)
                ->post($send_mail_url, $mail_data);

            if (!$send_response->successful()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to send email.',
                    'error' => $send_response->json()
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Email sent successfully.',
                'data' => $login_details
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred.',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function sendGeneralEmails($to, $subject, $view, $data)
    {
        try {
            Mail::send($view, $data, function ($mail) use ($to, $subject) {
                $mail->to($to)
                    ->subject($subject);
            });

            return true;

        } catch (\Throwable $th) {
            Log::error('Error sending email: ' . $th->getMessage());
            print_r($th->getMessage());
            return false;
        }
    }

    // public function getEndorsementProcessingResults($request_id)
    // {

    //     if (!$request_id) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Invalid request id'
    //         ], 400);
    //     }
    //     //post to the /api/PortalEndorsementsProcessing/automate-endorsements and get the base url from env
    //     $base_url = env('ENDORSEMENT_PROCESSING_BASE_URL', 'https://localhost:64353');
    //     $url = $base_url . '/api/PortalEndorsementsProcessing/automate-endorsements?request_id=' . $request_id;

    //     //disable ssl verification
    //     $client = new \GuzzleHttp\Client([
    //         'verify' => false
    //     ]);

    //     $response = $client->post($url);

    //     if ($response->getStatusCode() == 200) {
    //         $results = $response->getBody()->getContents();

    //         return $results;

    //     } else {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Error processing endorsement request'
    //         ], 500);
    //     }
    // }

    // public function getClaimsProcessingResults($claim_request_id)
    // {
    //     if (!$claim_request_id) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Invalid request id'
    //         ], 400);
    //     }
    //     //post to the /api/PortalEndorsementsProcessing/automate-endorsements and get the base url from env
    //     $base_url = env('ENDORSEMENT_PROCESSING_BASE_URL', 'https://localhost:64353');
    //     $url = $base_url . '/api/PortalEndorsementsProcessing/automate-endorsements?claim_request_id=' . $claim_request_id;

    //     //disable ssl verification
    //     $client = new \GuzzleHttp\Client([
    //         'verify' => false
    //     ]);

    //     $response = $client->post($url);

    //     if ($response->getStatusCode() == 200) {
    //         $results = $response->getBody()->getContents();

    //         return $results;

    //     } else {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Error processing Claim request'
    //         ], 500);
    //     }
    // }

    public function britam_email_sending($subject, $text_message, $notification_address, $token)
    {

        // POST https://brtgw.britam.com/notification/api/v1/create/email/
        // Authorization: Bearer <token>

        // Content-Type: application/json
        // Ocp-Apim-Subscription-Key: <BRITAM_GATEWAY_SUBSCRIPTION_KEY>

        // {
        //     "ref_no":"EM1003",
        //     "notification_address":[
        //         "pngetich@britam.com"

        //     ],
        //     "subject":"Trial Email",
        //     "html":"<html><body><h1>Handle this message with care</h1></body></html>",
        //     "text_message":"Handle this message with care",
        //     "callback_url":"https://brtgw.britam.com/api/auth/login/"
        // }

        $url = 'https://brtgw.britam.com/notification/api/v1/create/email/';

        $headers = [
            'Ocp-Apim-Subscription-Key' => config('services.azure_ad.subscription_key'),
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $token
        ];

        $notification_addresses = [];

        $notification_addresses[] = $notification_address;

        $data = [
            'ref_no' => 'EM1003',
            'notification_address' => $notification_addresses,
            'subject' => $subject,
            'html' => "<html><body><h1> " . $text_message . " </h1></body></html>",
            'text_message' => $text_message,
            'callback_url' => 'https://brtgw.britam.com/api/auth/login/'
        ];
        $data_string = json_encode($data);

        //use http_guzzle

        $http = new \GuzzleHttp\Client();

        $response = $http->post($url, [
            'headers' => $headers,
            'body' => $data_string
        ]);
        // {
        //     "status": "Success",
        //     "message": "Email sending initiated",
        //     "data": {
        //         "bulkId": "9f5bi6dntldpnlamm6zl",
        //         "messages": [
        //             {
        //                 "to": "flaughters10045@mailinator.com",
        //                 "messageId": "4dsq9ao6loho2bnr9nv8",
        //                 "status": {
        //                     "groupId": 1,
        //                     "groupName": "PENDING",
        //                     "id": 26,
        //                     "name": "PENDING_ACCEPTED",
        //                     "description": "Message accepted, pending for delivery."
        //                 }
        //             }
        //         ]
        //     }
        // }

        // if response contains status as Success and message is Email sending initiated then return true else return false

        if ($response->getStatusCode() == 200) {
            $response = json_decode($response->getBody(), true);

            if ($response['status'] == 'Success' && $response['message'] == 'Email sending initiated') {
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }

    }

}
