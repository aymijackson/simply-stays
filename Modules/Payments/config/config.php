<?php

return [
    'name' => 'Payments',

    'default_gateway' => env('PAYMENTS_DEFAULT_GATEWAY', 'stripe'),

    'platform_account_email' => env('PLATFORM_ACCOUNT_EMAIL', 'platform@shortstay.internal'),

    'stripe' => [
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    'paystack' => [
        'secret' => env('PAYSTACK_SECRET_KEY'),
        'base_url' => env('PAYSTACK_BASE_URL', 'https://api.paystack.co'),
    ],

    'flutterwave' => [
        'secret' => env('FLUTTERWAVE_SECRET_KEY'),
        'base_url' => env('FLUTTERWAVE_BASE_URL', 'https://api.flutterwave.com/v3'),
        'webhook_hash' => env('FLUTTERWAVE_WEBHOOK_HASH'),
    ],
];
