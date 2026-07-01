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
        'timeout' => (int) env('HBX_TIMEOUT', 60),
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
        'endpoints' => [
            'hotel_search' => env('TBO_ENDPOINT_HOTEL_SEARCH', '/HotelBookingApi/HotelSearch'),
            'available_hotel_rooms' => env('TBO_ENDPOINT_AVAILABLE_HOTEL_ROOMS', '/HotelBookingApi/AvailableHotelRooms'),
            'availability_and_pricing' => env('TBO_ENDPOINT_AVAILABILITY_AND_PRICING', '/HotelBookingApi/AvailabilityandPricing'),
            'hotel_book' => env('TBO_ENDPOINT_HOTEL_BOOK', '/HotelBookingApi/HotelBook'),
            'hotel_booking_detail' => env('TBO_ENDPOINT_HOTEL_BOOKING_DETAIL', '/HotelBookingApi/HotelBookingDetail'),
            'hotel_cancel' => env('TBO_ENDPOINT_HOTEL_CANCEL', '/HotelBookingApi/HotelCancel'),
            'hotel_cancellation_policy' => env('TBO_ENDPOINT_HOTEL_CANCELLATION_POLICY', '/HotelBookingApi/HotelCancellationPolicy'),
            'hotel_details' => env('TBO_ENDPOINT_HOTEL_DETAILS', '/HotelBookingApi/HotelDetails'),
        ],
    ],

    'ratehawk' => [
        'enabled' => (bool) env('RATEHAWK_ENABLED', false),
        'key_id' => env('RATEHAWK_KEY_ID'),
        'api_key' => env('RATEHAWK_API_KEY'),
        'base_url' => env('RATEHAWK_BASE_URL', 'https://api.worldota.net'),
        'timeout' => (int) env('RATEHAWK_TIMEOUT', 45),
        'connect_timeout' => (int) env('RATEHAWK_CONNECT_TIMEOUT', 15),
        'integration_tests' => (bool) env('RATEHAWK_INTEGRATION_TESTS', false),
        'sandbox_booking_enabled' => (bool) env('RATEHAWK_SANDBOX_BOOKING_ENABLED', false),
        'production_enabled' => (bool) env('RATEHAWK_PRODUCTION_ENABLED', false),
        'endpoints' => [
            'suggest' => env('RATEHAWK_ENDPOINT_SUGGEST', '/api/b2b/v3/search/multicomplete/'),
            'search_region' => env('RATEHAWK_ENDPOINT_SEARCH_REGION', '/api/b2b/v3/search/serp/region/'),
            'search_hotels' => env('RATEHAWK_ENDPOINT_SEARCH_HOTELS', '/api/b2b/v3/search/serp/hotels/'),
            'search_geo' => env('RATEHAWK_ENDPOINT_SEARCH_GEO', '/api/b2b/v3/search/serp/geo/'),
            'hotelpage' => env('RATEHAWK_ENDPOINT_HOTELPAGE', '/api/b2b/v3/search/hp/'),
            'prebook' => env('RATEHAWK_ENDPOINT_PREBOOK', '/api/b2b/v3/hotel/prebook/'),
            'booking_form' => env('RATEHAWK_ENDPOINT_BOOKING_FORM', '/api/b2b/v3/hotel/order/booking/form/'),
            'booking_finish' => env('RATEHAWK_ENDPOINT_BOOKING_FINISH', '/api/b2b/v3/hotel/order/booking/finish/'),
            'booking_status' => env('RATEHAWK_ENDPOINT_BOOKING_STATUS', '/api/b2b/v3/hotel/order/booking/finish/status/'),
        ],
    ],

];
