<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;

class Azure_AD_AuthController extends Controller
{
    //
    // get token from https://login.microsoftonline.com/169053ef-20fe-4b73-acf5-e2a0002f002f/oauth2/v2.0/token
    public function azure_ad_token_request(Request $request)
    {

        $client_ref = config('services.azure_ad.client_ref');
        $client_secret = config('services.azure_ad.client_secret');
        $tenant_id = config('services.azure_ad.tenant_id');

        //error handling
        if (!$client_ref || !$client_secret || !$tenant_id) {
            return response()->json([
                'success' => 'false',
                'message' => 'Please provide the required credentials',
            ], 400);
        }


        $http = new \GuzzleHttp\Client;

        $headers = [
            'Ocp-Apim-Subscription-Key' => config('services.azure_ad.subscription_key'),
            'Cache-Control' => 'no-cache',
        ];

        $response = $http->post(config('services.azure_ad.gateway_url'), [
            'headers' => $headers,
            'form_params' => [
                'tenant_id' => $tenant_id,
                'client_ref' => $client_ref,
                'client_sec' => $client_secret,
            ],
        ]);

        return json_decode((string) $response->getBody(), true);
    }
}
