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

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'internal_api' => [
        'token' => env('INTERNAL_API_TOKEN'),
    ],

    'apify' => [
        'token' => env('APIFY_TOKEN'),
        'base_url' => env('APIFY_BASE_URL', 'https://api.apify.com/v2'),
        'webhook_secret' => env('APIFY_WEBHOOK_SECRET'),
        'webhook_url' => env('APIFY_WEBHOOK_URL', env('APP_URL').'/api/v1/internal/apify/webhook'),
        'timeout_seconds' => (int) env('APIFY_HTTP_TIMEOUT_SECONDS', 30),
        'max_concurrent_launches' => (int) env('APIFY_MAX_CONCURRENT_LAUNCHES', 20),
        'watchdog_after_minutes' => (int) env('APIFY_WATCHDOG_AFTER_MINUTES', 5),
        'stale_after_minutes' => (int) env('APIFY_STALE_AFTER_MINUTES', 90),
    ],

    'supabase' => [
        'url' => env('SUPABASE_URL'),
        'publishable_key' => env('SUPABASE_PUBLISHABLE_KEY'),
        'demo_auth_user_id' => env('SML_DEMO_AUTH_USER_ID'),
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

];
