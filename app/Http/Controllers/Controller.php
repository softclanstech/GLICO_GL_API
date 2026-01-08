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
        //$url = env('APP_URL') . '/oauth/token';
        $url = 'http://127.0.0.1:8000/oauth/token';

        // $client_id = env('CLIENT_ID', '1');
        // $client_secret = env('CLIENT_SECRET', 'M6eUvovO28Cn2ZbqT3RBLZY8uP7hpHnPtgTY0ASS');
        $client_id = '99b1beaf-1fb8-4077-b533-06992472852b';//'15';
        $client_secret = 'lUeviYKNH9XblnJDYSnFQwPFJy0NV2WKlcqYGM2E';//'QritycYY3L9RMfh8dANWM7PJGPz3BxzOGOWqamNq';

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

    public function sendEmail($to, $subject, $message, $login_details)
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
        // Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsIng1dCI6InEtMjNmYWxldlpoaEQzaG05Q1Fia1A1TVF5VSIsImtpZCI6InEtMjNmYWxldlpoaEQzaG05Q1Fia1A1TVF5VSJ9.eyJhdWQiOiJhcGk6Ly8yNTUxYTZhZS0wMzlkLTQzYmItYjA1ZS04ZDk3ZDA3ZjE1ZTIiLCJpc3MiOiJodHRwczovL3N0cy53aW5kb3dzLm5ldC9lMzAzZjIxOS03NWVmLTQ3OWEtYjIzYy0zNWFjOTQ3OWE4Y2UvIiwiaWF0IjoxNzEyODM0NDg1LCJuYmYiOjE3MTI4MzQ0ODUsImV4cCI6MTcxMjgzODM4NSwiYWlvIjoiRTJOZ1lIajNLL2VmY25LWUNjZXNucDJpUjZ5dkF3QT0iLCJhcHBpZCI6IjgxNmFhNDU3LWQyNTgtNDUyNi05ZTVjLTdiNjhlOTU3ODU2OSIsImFwcGlkYWNyIjoiMSIsImlkcCI6Imh0dHBzOi8vc3RzLndpbmRvd3MubmV0L2UzMDNmMjE5LTc1ZWYtNDc5YS1iMjNjLTM1YWM5NDc5YThjZS8iLCJvaWQiOiJkODA4OTZmZS0zYjBlLTQwZDYtOWNkYy0xY2JlMjNhZDhiYzYiLCJyaCI6IjAuQVI4QUdmSUQ0LTkxbWtleVBEV3NsSG1venE2bVVTV2RBN3REc0Y2Tmw5Ql9GZUlmQUFBLiIsInJvbGVzIjpbIlJlZ2lzdGVyQ2xpZW50IiwiVmVyaWZ5QWdlbnQiXSwic3ViIjoiZDgwODk2ZmUtM2IwZS00MGQ2LTljZGMtMWNiZTIzYWQ4YmM2IiwidGlkIjoiZTMwM2YyMTktNzVlZi00NzlhLWIyM2MtMzVhYzk0NzlhOGNlIiwidXRpIjoiVmZOVHlsQ1NYRXVLOUFfV2ZlNlRBQSIsInZlciI6IjEuMCJ9.PDGQ5v9rt8Ma0HTPb80Ql1lk9UkKaZjn54AzCwpWaKjfNhwKvxNWnr6lyB_k0pIb4lucVt2fUdvyvNt76uu8vC9Khng8O3XzF98HErl2AGleJaZl5sA6nBoYOHO0VZiwjSsixl2yJO6P6eajjj5ZC_TcNqB9saoba7LFt0KkDeS176wlI31jyKUiOoBlDtRoCeKs3EldcBkDGP1FS_1Oo-tuMzSG0uZ7qCVKNBYqz1xGq2QY7dYH_BKmVveay8F8V4u6bSYEPcL-zAK1IjtTGqQA8Fw6lkvRJT0x7v6T162p7C1pUR4LRC_DwcJxtG4mQa8y7oBftYTMxFOmKZQUlA

        // Content-Type: application/json
        // Ocp-Apim-Subscription-Key: 12eab6d7b2b248a3b7f5fa0256884b2b

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
            'Ocp-Apim-Subscription-Key' => '12eab6d7b2b248a3b7f5fa0256884b2b',
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
