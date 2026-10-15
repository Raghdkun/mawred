<?php

declare(strict_types=1);

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

    // Upload scanning (D20, ADR-0008). Unset = no scanning; set = every upload is scanned, fail closed.
    'clamav' => [
        'host' => env('CLAMAV_HOST'),
        'port' => (int) env('CLAMAV_PORT', 3310),
    ],

    // Public form proof-of-work (D10): PBKDF2 cost and the counter range the browser searches.
    'altcha' => [
        'cost' => env('ALTCHA_COST', 5000),
        'counter_min' => env('ALTCHA_COUNTER_MIN', 5000),
        'counter_max' => env('ALTCHA_COUNTER_MAX', 10000),
    ],

    /*
    | WhatsApp (ADR-0015). Credentials live in the vault (integration_connections); only the
    | pinned Graph API version and timeouts are configuration.
    */
    'whatsapp' => [
        'graph_url' => env('WHATSAPP_GRAPH_URL', 'https://graph.facebook.com'),
        'graph_version' => env('WHATSAPP_GRAPH_VERSION', 'v25.0'),
        'timeout' => (int) env('WHATSAPP_TIMEOUT', 15),
    ],

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

];
