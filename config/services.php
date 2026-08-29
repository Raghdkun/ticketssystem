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

    /*
     * Firebase Cloud Messaging. Leave these blank to keep push disabled: the
     * sender reports itself unconfigured and the UI never asks for permission.
     */
    'fcm' => [
        'project_id' => env('FCM_PROJECT_ID'),
        /*
         * Path to the Firebase service-account JSON. Absolute is honoured as
         * given; anything else is resolved against the project root, because
         * a relative path would otherwise resolve against the working
         * directory -- which is the project root under artisan and something
         * else entirely under php-fpm.
         */
        'credentials' => ($fcmKey = env('FCM_CREDENTIALS')) === null || $fcmKey === ''
            ? null
            : (str_starts_with((string) $fcmKey, '/') ? $fcmKey : base_path((string) $fcmKey)),
        'access_token' => env('FCM_ACCESS_TOKEN'),
        'vapid_key' => env('VITE_FCM_VAPID_KEY'),
    ],

];
