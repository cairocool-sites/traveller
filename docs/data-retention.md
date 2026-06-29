# Data Retention and Cleanup

Phase 10 introduces safe cleanup commands for transient data. It does not delete confirmed commercial records.

## Cleanup Command

Dry run:

```bash
php artisan ops:cleanup --dry-run
```

Actual cleanup:

```bash
php artisan ops:cleanup
```

## Eligible Data

- Expired search sessions.
- Expired rate checks.
- Expired booking drafts that are not supplier-confirmed.
- Old sanitized supplier operation logs after `TRAVEL_RETENTION_SUPPLIER_LOGS_DAYS`.

## Protected Data

Never delete through automated cleanup:

- Confirmed bookings.
- Issued invoices.
- Receipts.
- Vouchers.
- Payment evidence.
- Cancellations.
- Refunds.
- Status histories.
- Audit histories.

## Retention Settings

Configured placeholders:

- `TRAVEL_RETENTION_SUPPLIER_LOGS_DAYS`
- `TRAVEL_RETENTION_SEARCH_SESSIONS_DAYS`
- `TRAVEL_RETENTION_RATE_CHECKS_DAYS`
- `TRAVEL_RETENTION_BOOKING_DRAFTS_DAYS`
- `TRAVEL_RETENTION_NOTIFICATIONS_DAYS`
- `TRAVEL_RETENTION_TEMP_UPLOADS_DAYS`

Some settings are documentation placeholders until corresponding cleanup targets exist.

## Decisions Still Required

- Legal retention period for booking and financial documents.
- Egyptian tax record requirements.
- Customer data deletion policy.
- Backup retention and deletion policy.
- Supplier log retention once real suppliers are connected.
