<?php

return [
    // iyzico (sandbox) or fake. iyzico without keys falls back to fake.
    'driver' => env('PAYMENT_DRIVER', 'iyzico'),

    'iyzico' => [
        'api_key' => env('IYZICO_API_KEY'),
        'secret_key' => env('IYZICO_SECRET_KEY'),
        'base_url' => env('IYZICO_BASE_URL', 'https://sandbox-api.iyzipay.com'),
    ],

    // Brand storefront origin; {tenant} is replaced with the brand slug.
    'frontend_url' => env('FRONTEND_TENANT_URL', 'http://{tenant}.localhost:3300'),

    // Public URL providers call back to (the API itself).
    'callback_url' => env('APP_URL', 'http://localhost:8000').'/api/payments/iyzico/callback',

    'renewal' => [
        'max_attempts' => 3,
        'retry_after_days' => 1,
    ],
];
