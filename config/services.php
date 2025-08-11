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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
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

    'mandrill' => [
        'secret' => env('MANDRILL_SECRET'),
        'webhook_key' => env('MANDRILL_WEBHOOK_KEY'),
    ],

    'facebook' => [
        'app_id' => env('FACEBOOK_APP_ID'),
        'app_secret' => env('FACEBOOK_APP_SECRET'),
        'verify_token' => env('FACEBOOK_VERIFY_TOKEN'),
        'redirect_uri' => env('FACEBOOK_REDIRECT_URI'),
        'graph_version' => env('FACEBOOK_GRAPH_VERSION', 'v19.0'),
    ],
    
    // Indica a nuestro Manager cuál es el proveedor por defecto
    'default_email_provider' => env('DEFAULT_EMAIL_PROVIDER', 'mandrill'),
    'mail_batch_size' => env('MAIL_BATCH_SIZE', 100),

];
