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

    'public_search' => [
        'max_rooms' => (int) env('TRAVEL_SEARCH_MAX_ROOMS', 4),
        'max_adults_per_room' => (int) env('TRAVEL_SEARCH_MAX_ADULTS_PER_ROOM', 4),
        'max_children_per_room' => (int) env('TRAVEL_SEARCH_MAX_CHILDREN_PER_ROOM', 4),
        'max_child_age' => (int) env('TRAVEL_SEARCH_MAX_CHILD_AGE', 17),
        'max_stay_nights' => (int) env('TRAVEL_SEARCH_MAX_STAY_NIGHTS', 30),
        'session_lifetime_minutes' => (int) env('TRAVEL_SEARCH_SESSION_LIFETIME_MINUTES', 30),
        'suppliers' => ['mock_hotels'],
        'results_limit' => (int) env('TRAVEL_SEARCH_RESULTS_LIMIT', 30),
    ],

    'booking' => [
        'rate_check_lifetime_minutes' => (int) env('TRAVEL_RATE_CHECK_LIFETIME_MINUTES', 20),
        'draft_lifetime_minutes' => (int) env('TRAVEL_BOOKING_DRAFT_LIFETIME_MINUTES', 30),
    ],

];
