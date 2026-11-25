<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

    /*
     * Personal information is detected by llm-inspect-server on the loopback address of this host
     * (docs/personal-info-detection.md). While it cannot be reached, consultations are refused.
     */
    'llm_inspect' => [
        'url' => env('LLM_INSPECT_URL', 'http://127.0.0.1:8787'),
        'token' => env('LLM_INSPECT_TOKEN'),
        'connect_timeout' => (float) env('LLM_INSPECT_CONNECT_TIMEOUT', 1),
        'timeout' => (float) env('LLM_INSPECT_TIMEOUT', 3),
    ],

    'contact' => [
        'email' => env('CONTACT_EMAIL', 'artfullstack@gmail.com'),
    ],

    'stripe' => [
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'ca_bundle' => env('STRIPE_CA_BUNDLE', ini_get('curl.cainfo') ?: null),
        'prices' => [
            'credits_10' => env('STRIPE_PRICE_CREDITS_10'),
            'credits_50' => env('STRIPE_PRICE_CREDITS_50'),
            'credits_100' => env('STRIPE_PRICE_CREDITS_100'),
            'lite' => env('STRIPE_PRICE_LITE'),
            'lite_yearly' => env('STRIPE_PRICE_LITE_YEARLY'),
            'premium' => env('STRIPE_PRICE_PREMIUM'),
            'premium_yearly' => env('STRIPE_PRICE_PREMIUM_YEARLY'),
        ],
    ],

];
