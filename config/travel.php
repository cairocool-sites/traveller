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

    'payments' => [
        'evidence_max_kilobytes' => (int) env('TRAVEL_PAYMENT_EVIDENCE_MAX_KB', 4096),
        'evidence_mimes' => ['jpg', 'jpeg', 'png', 'webp', 'pdf'],
        'evidence_mime_types' => ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'],
        'submission_expiry_hours' => (int) env('TRAVEL_PAYMENT_SUBMISSION_EXPIRY_HOURS', 24),
        'private_directory' => env('TRAVEL_PAYMENT_EVIDENCE_DIRECTORY', 'payment-evidence'),
    ],

    'documents' => [
        'voucher_prefix' => env('TRAVEL_VOUCHER_PREFIX', 'VCH'),
        'invoice_prefix' => env('TRAVEL_INVOICE_PREFIX', 'INV'),
        'receipt_prefix' => env('TRAVEL_RECEIPT_PREFIX', 'RCT'),
        'company_name' => env('TRAVEL_DOCUMENT_COMPANY_NAME', 'Cairo Cool Travel'),
        'company_contact' => env('TRAVEL_DOCUMENT_COMPANY_CONTACT', 'Contact details will be confirmed before launch.'),
        'show_supplier_confirmation_reference' => (bool) env('TRAVEL_DOCUMENT_SHOW_SUPPLIER_CONFIRMATION', false),
    ],

    'cancellations' => [
        'request_lifetime_hours' => (int) env('TRAVEL_CANCELLATION_REQUEST_LIFETIME_HOURS', 24),
        'submission_rate_limit' => env('TRAVEL_CANCELLATION_RATE_LIMIT', '6,1'),
        'auto_supplier_cancel_mock' => (bool) env('TRAVEL_AUTO_SUPPLIER_CANCEL_MOCK', true),
        'manual_review_threshold_minor' => (int) env('TRAVEL_CANCELLATION_MANUAL_REVIEW_THRESHOLD_MINOR', 1000000),
    ],

    'refunds' => [
        'reference_prefix' => env('TRAVEL_REFUND_REFERENCE_PREFIX', 'RFD'),
        'max_age_days' => (int) env('TRAVEL_REFUND_MAX_AGE_DAYS', 180),
        'customer_processing_message' => env('TRAVEL_REFUND_PROCESSING_MESSAGE', 'Refunds are tracked manually and may take time to process.'),
    ],

];
