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

    // Jasapedia platform services
    'search' => [
        'driver' => env('SEARCH_DRIVER', 'sql'),
    ],

    'geo' => [
        'driver' => env('GEO_DRIVER', 'haversine'),
        'map' => env('MAP_PROVIDER', 'openstreetmap'), // openstreetmap | google | mapbox — frontend concern only
    ],

    'payments' => [
        'driver' => env('PAYMENT_DRIVER', 'sandbox'),
        'sandbox_secret' => env('PAYMENT_SANDBOX_SECRET', 'sandbox-secret'),
        'xendit' => [
            'secret_key' => env('XENDIT_SECRET_KEY'),
            'callback_token' => env('XENDIT_WEBHOOK_TOKEN'),
        ],
        'midtrans' => [
            'server_key' => env('MIDTRANS_SERVER_KEY'),
            'client_key' => env('MIDTRANS_CLIENT_KEY'),
            'production' => env('MIDTRANS_IS_PRODUCTION', false),
        ],
    ],

    'meilisearch' => [
        'host' => env('MEILISEARCH_HOST'),
        'key' => env('MEILISEARCH_KEY'),
    ],

    'media' => [
        'disk' => env('MEDIA_DISK', 'public'), // public | s3 | r2 — never couple business logic to one disk
        'max_size_kb' => (int) env('MEDIA_MAX_SIZE_KB', 4096),
    ],

];
