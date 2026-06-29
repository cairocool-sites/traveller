# HBX Sandbox Verification and Internal Voucher

Phase 14 adds a controlled manual verification workflow for one HBX Sandbox booking and a protected internal printable voucher for locally confirmed bookings.

## Scope

- Sandbox only: `https://api.test.hotelbeds.com`
- No production endpoint usage.
- No payment gateway, payment receipt, tax invoice, cancellation, B2B, or Phase 15 work.
- Automated tests keep `HBX_SANDBOX_BOOKING_ENABLED=false` by default and use HTTP fakes only.

## Dry Run

Run:

```bash
php artisan hbx:verify-sandbox-booking --dry-run
```

The command refuses to continue unless:

- `HBX_SANDBOX_BOOKING_ENABLED=true`
- the configured HBX base URL resolves to `https://api.test.hotelbeds.com`
- the `hbx_hotels` supplier exists and is active
- local sandbox credentials are configured
- the operator confirms the manual safety prompt

Dry run performs search and CheckRate, displays only sanitized hotel, dates, currency, and selling total, then stops before any booking request.

Before running dry-run, HBX destination and hotel content must be synchronized and the local destination must have a confirmed HBX destination mapping. The command will not fall back to Mock Supplier when HBX content mapping is missing or unavailable.

## One-Booking Procedure

Run:

```bash
php artisan hbx:verify-sandbox-booking
```

The command searches a small Cairo test stay, selects one available rate, runs CheckRate, displays a sanitized summary, then asks for final confirmation. If confirmed, it sends exactly one booking request and never retries booking. The command reports only the local booking reference, HBX reference, and safe local status.

## Test Guest Data

The command uses clearly fake sandbox guest/contact data:

- no real customer name
- no real phone number
- no real customer email
- no card data

Sensitive values such as API keys, API secrets, signatures, request headers, raw payloads, phone, email, and full guest identity must not be printed.

## Voucher Generation

Admins with booking-view authorization can preview or download a printable internal voucher from the Booking resource. The route is authenticated and policy-protected:

```text
/admin/bookings/{booking}/voucher
```

Confirmed bookings show a final internal voucher. Manual-review bookings show a provisional notice only. Other unconfirmed bookings cannot generate vouchers.

The printable HTML fallback includes:

- Cairo Cool Travel branding
- local booking reference
- HBX confirmation reference
- booking status
- hotel, room, board, dates
- guest count summary
- selling total and currency
- cancellation summary
- issue date
- prominent Sandbox / Test Booking notice

It excludes credentials, supplier net prices, raw supplier payloads, signatures, headers, full contact details, payment receipt data, and tax invoice data.

## Safety Checks

- The command blocks production endpoints.
- The command blocks when sandbox booking is disabled.
- Booking and cancellation are never retried automatically by this manual workflow.
- No automatic cancellation is attempted.
- Voucher filenames are generated from safe booking-reference characters.
- No external fonts or remote assets are required.

## Quota Precautions

Use dry-run first, then run the live command only once when the operator is ready. Do not repeat live booking verification unless HBX quota and sandbox operational needs justify another explicit manual run.
