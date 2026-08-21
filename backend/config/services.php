<?php

return [
    'paystack' => [
        'public_key' => env('PAYSTACK_PUBLIC_KEY'),
        'secret_key' => env('PAYSTACK_SECRET_KEY'),
        'payment_url' => env('PAYSTACK_PAYMENT_URL', 'https://api.paystack.co'),
        'callback_url' => env('PAYSTACK_CALLBACK_URL', rtrim(env('FRONTEND_URL', 'http://localhost:5173'), '/').'/plans-billing'),
        'cancel_url' => env('PAYSTACK_CANCEL_URL', rtrim(env('FRONTEND_URL', 'http://localhost:5173'), '/').'/plans-billing?payment=cancelled'),
    ],
];
