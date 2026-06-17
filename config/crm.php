<?php

return [
    'website_api_key' => env('WEBSITE_API_KEY'),

    'allowed_origins' => array_filter(array_map('trim', explode(',', env('CORS_ALLOWED_ORIGINS', 'https://truckbill.in,https://www.truckbill.in')))),

    'onboarding_frontend_url' => env('ONBOARDING_FRONTEND_URL', 'https://truckbill.in'),

    'products' => [
        'ownfleet' => [
            'api_url' => env('OWNFLEET_API_URL', 'https://fleet.truckbill.in'),
            'login_url' => env('OWNFLEET_LOGIN_URL', 'https://fleet.truckbill.in/app/login'),
            'signing_secret' => env('OWNFLEET_SIGNING_SECRET'),
        ],
        'parchun' => [
            'api_url' => env('PARCHUN_API_URL', 'https://parchun.truckbill.in'),
            'login_url' => env('PARCHUN_LOGIN_URL', 'https://parchun.truckbill.in/login'),
            'signing_secret' => env('PARCHUN_SIGNING_SECRET'),
        ],
        'truckbill' => [
            'api_url' => env('TRUCKBILL_API_URL', 'https://live.truckbill.in'),
            'login_url' => env('TRUCKBILL_LOGIN_URL', 'https://live.truckbill.in/login'),
            'signing_secret' => env('TRUCKBILL_SIGNING_SECRET'),
        ],
    ],
];
