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

    'brevo' => [
        'key' => env('BREVO_API_KEY'),
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

    'xendit' => [
        'sandbox_enabled' => env('XENDIT_SANDBOX_ENABLED', false),
        'sandbox_secret_key' => env('XENDIT_SANDBOX_SECRET_KEY'),
        'sandbox_webhook_token' => env('XENDIT_SANDBOX_WEBHOOK_TOKEN'),
        'sandbox_business_id' => env('XENDIT_SANDBOX_BUSINESS_ID'),
        'api_url' => 'https://api.xendit.co',
        'sandbox_checkout_hosts' => ['checkout-staging.xendit.co', 'dev.xen.to'],
    ],

];
