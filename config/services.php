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

    'whatsapp' => [
        'webhook_url' => env('WHATSAPP_WEBHOOK_URL'),
        'api_token' => env('WHATSAPP_API_TOKEN'),
        'from' => env('WHATSAPP_FROM'),
        'timeout' => (int) env('WHATSAPP_TIMEOUT', 10),
    ],

    'integrations' => [
        'webhook_timeout' => (int) env('INTEGRATIONS_WEBHOOK_TIMEOUT', 10),
    ],

    'ops_alerting' => [
        'webhook_url' => env('OPS_ALERT_WEBHOOK_URL'),
        'timeout' => (int) env('OPS_ALERT_TIMEOUT', 10),
        'minimum_level' => env('OPS_ALERT_MINIMUM_LEVEL', 'warning'),
    ],

    'nema_control_center' => [
        'url' => env('NEMA_CONTROL_CENTER_URL', 'https://temtxnblsaadftwbycpc.supabase.co/functions/v1/platform-ingest'),
        'connector_token' => env('NEMA_CONTROL_CENTER_CONNECTOR_TOKEN'),
        'connector_token_file' => env('NEMA_CONTROL_CENTER_TOKEN_FILE', base_path('.nema-control-center-token')),
        'timeout' => (int) env('NEMA_CONTROL_CENTER_TIMEOUT', 10),
    ],

];
