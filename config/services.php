<?php

return [
    'amadeus' => [
        'base_url' => env('AMADEUS_BASE_URL', 'https://test.api.amadeus.com'),
        'client_id' => env('AMADEUS_CLIENT_ID'),
        'client_secret' => env('AMADEUS_CLIENT_SECRET'),
    ],

    'liteapi' => [
        'base_url' => env('LITEAPI_BASE_URL', 'https://api.liteapi.travel/v3.0'),
        'api_key' => env('LITEAPI_API_KEY'),
    ],

    'opentripmap' => [
        'base_url' => env('OPENTRIPMAP_BASE_URL', 'https://api.opentripmap.com/0.1/en'),
        'api_key' => env('OPENTRIPMAP_API_KEY'),
    ],

    'openweather' => [
        'key' => env('OPENWEATHER_API_KEY'),
    ],

    'stripe' => [
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'currency' => env('STRIPE_CURRENCY', 'gbp'),
    ],
];