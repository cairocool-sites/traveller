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
        'default' => env('TRAVEL_DEFAULT_CURRENCY', 'USD'),
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
        'suppliers' => ['hbx_hotels', 'mock_hotels'],
        'results_limit' => (int) env('TRAVEL_SEARCH_RESULTS_LIMIT', 30),
        'markup_basis_points' => (int) env('TRAVEL_PUBLIC_SEARCH_MARKUP_BASIS_POINTS', 0),
    ],

    'booking' => [
        'rate_check_lifetime_minutes' => (int) env('TRAVEL_RATE_CHECK_LIFETIME_MINUTES', 20),
        'draft_lifetime_minutes' => (int) env('TRAVEL_BOOKING_DRAFT_LIFETIME_MINUTES', 30),
    ],

    'payments' => [
        'live_enabled' => (bool) env('TRAVEL_PAYMENT_LIVE_ENABLED', false),
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
        'payment_notice' => env('TRAVEL_DOCUMENT_PAYMENT_NOTICE', 'Payable through Cairo Cool Travel.'),
        'vat_notice' => env('TRAVEL_DOCUMENT_VAT_NOTICE', 'VAT: N/A for sandbox verification.'),
    ],

    'cancellations' => [
        'actual_supplier_cancellation_enabled' => (bool) env('TRAVEL_ACTUAL_SUPPLIER_CANCELLATION_ENABLED', false),
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

    'security' => [
        'csp_report_only' => (bool) env('TRAVEL_CSP_REPORT_ONLY', true),
    ],

    'rate_limits' => [
        'health' => ['per_minute' => (int) env('TRAVEL_RATE_LIMIT_HEALTH_PER_MINUTE', 30)],
        'public-search' => ['per_minute' => (int) env('TRAVEL_RATE_LIMIT_SEARCH_PER_MINUTE', 30)],
        'booking-submission' => ['per_minute' => (int) env('TRAVEL_RATE_LIMIT_BOOKING_PER_MINUTE', 10)],
        'payment-submission' => ['per_minute' => (int) env('TRAVEL_RATE_LIMIT_PAYMENT_PER_MINUTE', 6)],
        'evidence-downloads' => ['per_minute' => (int) env('TRAVEL_RATE_LIMIT_EVIDENCE_PER_MINUTE', 20)],
        'document-verification' => ['per_minute' => (int) env('TRAVEL_RATE_LIMIT_DOCUMENT_VERIFY_PER_MINUTE', 30)],
        'cancellation-requests' => ['per_minute' => (int) env('TRAVEL_RATE_LIMIT_CANCELLATION_PER_MINUTE', 6)],
        'status-pages' => ['per_minute' => (int) env('TRAVEL_RATE_LIMIT_STATUS_PER_MINUTE', 30)],
    ],

    'operations' => [
        'scheduler_stale_after_minutes' => (int) env('TRAVEL_SCHEDULER_STALE_AFTER_MINUTES', 5),
        'retention' => [
            'supplier_logs_days' => (int) env('TRAVEL_RETENTION_SUPPLIER_LOGS_DAYS', 90),
            'search_sessions_days' => (int) env('TRAVEL_RETENTION_SEARCH_SESSIONS_DAYS', 2),
            'rate_checks_days' => (int) env('TRAVEL_RETENTION_RATE_CHECKS_DAYS', 2),
            'booking_drafts_days' => (int) env('TRAVEL_RETENTION_BOOKING_DRAFTS_DAYS', 7),
            'notifications_days' => (int) env('TRAVEL_RETENTION_NOTIFICATIONS_DAYS', 180),
            'temporary_uploads_days' => (int) env('TRAVEL_RETENTION_TEMP_UPLOADS_DAYS', 2),
        ],
    ],

    'suppliers' => [
        'soap_enabled' => (bool) env('TRAVEL_SUPPLIER_SOAP_ENABLED', false),
    ],

    'hbx' => [
        'public_country' => env('TRAVEL_HBX_PUBLIC_COUNTRY', 'EG'),
        'autocomplete_limit' => (int) env('TRAVEL_HBX_AUTOCOMPLETE_LIMIT', 8),
    ],

];
