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

    'hbx' => [
        'enabled' => (bool) env('HBX_ENABLED', false),
        'api_key' => env('HBX_API_KEY'),
        'api_secret' => env('HBX_API_SECRET'),
        'base_url' => env('HBX_BASE_URL', 'https://api.test.hotelbeds.com'),
        'timeout' => (int) env('HBX_TIMEOUT', 45),
        'connect_timeout' => (int) env('HBX_CONNECT_TIMEOUT', 15),
        'integration_tests' => (bool) env('HBX_INTEGRATION_TESTS', false),
        'sandbox_booking_enabled' => (bool) env('HBX_SANDBOX_BOOKING_ENABLED', false),
        'production_enabled' => (bool) env('HBX_PRODUCTION_ENABLED', false),
        'destination_codes' => [
            'cairo' => env('HBX_DESTINATION_CAIRO', 'CAI'),
            'giza' => env('HBX_DESTINATION_GIZA', 'CAI'),
            'alexandria' => env('HBX_DESTINATION_ALEXANDRIA', 'ALY'),
            'hurghada' => env('HBX_DESTINATION_HURGHADA', 'HRG'),
            'sharm el sheikh' => env('HBX_DESTINATION_SHARM_EL_SHEIKH', 'SSH'),
            'dubai' => env('HBX_DESTINATION_DUBAI', 'DXB'),
            'makkah' => env('HBX_DESTINATION_MAKKAH', 'MAK'),
            'istanbul' => env('HBX_DESTINATION_ISTANBUL', 'IST'),
        ],
    ],

    'tbo' => [
        'enabled' => (bool) env('TBO_ENABLED', false),
        'username' => env('TBO_USERNAME'),
        'password' => env('TBO_PASSWORD'),
        'base_url' => env('TBO_BASE_URL', 'https://api.tbotechnology.in'),
        'timeout' => (int) env('TBO_TIMEOUT', 45),
        'connect_timeout' => (int) env('TBO_CONNECT_TIMEOUT', 15),
        'integration_tests' => (bool) env('TBO_INTEGRATION_TESTS', false),
        'sandbox_booking_enabled' => (bool) env('TBO_SANDBOX_BOOKING_ENABLED', false),
        'production_enabled' => (bool) env('TBO_PRODUCTION_ENABLED', false),
    ],

];
