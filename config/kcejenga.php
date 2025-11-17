<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Environment
    |--------------------------------------------------------------------------
    |
    | Set to 'sandbox' for testing or 'production' for live payments
    |
    */
    'environment' => env('JENGA_ENVIRONMENT', 'sandbox'),

    /*
    |--------------------------------------------------------------------------
    | Merchant Code
    |--------------------------------------------------------------------------
    |
    | Your Jenga merchant code obtained from Jenga HQ
    |
    */
    'merchant_code' => env('JENGA_MERCHANT_CODE', ''),

    /*
    |--------------------------------------------------------------------------
    | Consumer Secret
    |--------------------------------------------------------------------------
    |
    | Your Jenga consumer secret obtained from Jenga HQ
    |
    */
    'consumer_secret' => env('JENGA_CONSUMER_SECRET', ''),

    /*
    |--------------------------------------------------------------------------
    | API Key
    |--------------------------------------------------------------------------
    |
    | Your Jenga API key obtained from Jenga HQ
    |
    */
    'api_key' => env('JENGA_API_KEY', ''),

    /*
    |--------------------------------------------------------------------------
    | Private Key
    |--------------------------------------------------------------------------
    |
    | Your private key for signature generation (required if secure mode is enabled)
    | This should be your RSA private key in PEM format
    |
    */
    'private_key' => env('JENGA_PRIVATE_KEY', ''),

    /*
    |--------------------------------------------------------------------------
    | Callback URL
    |--------------------------------------------------------------------------
    |
    | The URL where Jenga will send payment callbacks
    | This will be used as the default callback URL if not specified in payment data
    |
    */
    'callback_url' => env('JENGA_CALLBACK_URL', '/kcejenga/callback'),

    /*
    |--------------------------------------------------------------------------
    | Success URL
    |--------------------------------------------------------------------------
    |
    | The URL to redirect users after successful payment
    |
    */
    'success_url' => env('JENGA_SUCCESS_URL', '/payment/success'),

    /*
    |--------------------------------------------------------------------------
    | Failure URL
    |--------------------------------------------------------------------------
    |
    | The URL to redirect users after failed payment
    |
    */
    'failure_url' => env('JENGA_FAILURE_URL', '/payment/failed'),

    /*
    |--------------------------------------------------------------------------
    | Verify Hash
    |--------------------------------------------------------------------------
    |
    | Whether to verify the hash in payment callbacks for security
    | Set to true if you want to verify callback authenticity
    |
    */
    'verify_hash' => env('JENGA_VERIFY_HASH', false),

    /*
    |--------------------------------------------------------------------------
    | API Endpoints
    |--------------------------------------------------------------------------
    |
    | These endpoints are automatically set based on the environment
    | You can override them here if needed
    |
    */
    'endpoints' => [
        'sandbox' => [
            'token' => 'https://uat.finserve.africa/authentication/api/v3/authenticate/merchant',
            'payment' => 'https://v3-uat.jengapgw.io/processPayment',
        ],
        'production' => [
            'token' => 'https://api.finserve.africa/authentication/api/v3/authenticate/merchant',
            'payment' => 'https://v3.jengapgw.io/processPayment',
        ],
    ],
];

