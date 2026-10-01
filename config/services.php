<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'azure_ad' => [
        'tenant_id' => env('AZURE_TENANT_ID'),
        'client_ref' => env('AZURE_CLIENT_REF'),
        'client_secret' => env('AZURE_CLIENT_SECRET'),
        'gateway_url' => env('BRITAM_GATEWAY_URL', 'https://brtgw.britam.com/api/auth/login/'),
        'subscription_key' => env('BRITAM_GATEWAY_SUBSCRIPTION_KEY'),
    ],

    'passport_client' => [
        'client_id' => env('PASSPORT_CLIENT_ID'),
        'client_secret' => env('PASSPORT_CLIENT_SECRET'),
        'token_url' => env('PASSPORT_TOKEN_URL', 'http://127.0.0.1:8000/oauth/token'),
    ],

    'azure_mail' => [
        'tenant_id' => env('AZURE_MAIL_TENANT_ID'),
        'client_id' => env('AZURE_MAIL_CLIENT_ID'),
        'client_secret' => env('AZURE_MAIL_CLIENT_SECRET'),
        'sender' => env('AZURE_MAIL_SENDER', 'noreply@glicolife.com'),
    ],

];
