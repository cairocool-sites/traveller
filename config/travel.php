<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cairo Cool Travel Business Defaults
    |--------------------------------------------------------------------------
    |
    | Phase 1 keeps business configuration centralized without introducing
    | hotel, supplier, booking, payment, quotation, B2B, or API features.
    |
    */

    'brand_name' => env('TRAVEL_BRAND_NAME', 'Cairo Cool Travel'),

    'market' => [
        'primary_country' => env('TRAVEL_PRIMARY_COUNTRY', 'EG'),
        'future_countries' => ['SA', 'AE'],
    ],

    'locales' => [
        'default' => env('TRAVEL_DEFAULT_LOCALE', 'ar'),
        'fallback' => env('TRAVEL_FALLBACK_LOCALE', 'en'),
        'supported' => ['ar', 'en'],
    ],

    'currency' => [
        'default' => env('TRAVEL_DEFAULT_CURRENCY', 'EGP'),
        'supported' => ['EGP', 'USD', 'EUR', 'SAR', 'AED', 'GBP'],
    ],

    'timezone' => env('TRAVEL_TIMEZONE', 'Africa/Cairo'),

];
