# HBX Sandbox Booking Completion

Phase 13 completes the public sandbox booking path for HBX test traffic only:

Search -> offer selection -> CheckRate -> guest details -> final review -> HBX sandbox booking -> confirmation.

No production HBX endpoint, payment gateway, voucher issuance, invoice issuance, B2B workflow, quotation flow, customer account system, or deployment change is included.

## Sandbox Guard

HBX booking is blocked unless all conditions are true:

- `HBX_SANDBOX_BOOKING_ENABLED=true` is set server-side.
- The supplier is `hbx_hotels` and active.
- The resolved base URL is exactly `https://api.test.hotelbeds.com`.
- HBX sandbox credentials are configured locally.

The flag is never exposed to the browser. `.env.example` keeps it disabled by default.

## Trust Boundaries

The browser never controls supplier price, currency, rate key, hotel code, room data, cancellation terms, or markup. Public pages use opaque offer and rate references. Booking loads trusted values from the stored search session and CheckRate record.

RECHECK rates must have a successful CheckRate before booking. BOOKABLE rates still use the existing freshness and expiry rules.

## Guest Validation

The guest form collects lead guest name, contact email, phone, nationality, guest names per occupancy, child ages from the selected occupancy, optional sanitized special requests, and an explicit confirmation checkbox.

Validation enforces:

- exactly one adult lead guest;
- adult and child counts match the checked occupancy;
- child ages match the searched ages;
- email and phone are valid;
- names are normalized and length-limited;
- no payment card fields are collected.

## Orchestration

`BookingService` is the booking orchestration boundary. It validates the rate, applies the HBX sandbox guard, locks the idempotency key, creates a pending local booking, submits exactly one supplier booking request, stores the sanitized normalized supplier response, then transitions to confirmed, manual review, or supplier failed.

For staging or soft launch, set:

```env
TRAVEL_BOOKING_SUBMISSION_MODE=manual_review
```

In this mode, the same CheckRate and guest validation still run, but `BookingService` stops before supplier booking submission, stores a local booking under manual review, and sends no HBX booking request. Use this mode while certification blockers remain open or while manual operations need to review each request.

## Idempotency

A server-generated idempotency key is stored on the local booking and sent to HBX as the client reference. Duplicate submissions with the same payload return the existing booking and do not call HBX again. Reusing the key with different guest/contact data fails.

## Timeout And Manual Review

If HBX times out after submission, the supplier adapter returns an uncertain result. The booking is marked `manual_review`, the client reference is preserved, no automatic retry is attempted, and the customer sees a safe pending-review confirmation.

## Reconciliation

Use:

```bash
php artisan hbx:reconcile-booking {bookingReference}
```

The command only looks up an existing local HBX sandbox booking. It never creates, books, cancels, or prints secrets/raw payloads.

## Privacy And Redaction

Operation logs retain correlation IDs, safe operation metadata, durations, status categories, and sanitized payloads. API key, API secret, signatures, headers with credentials, and raw sensitive payloads must not be displayed publicly or stored in plain text.

## Manual Verification

Optional live sandbox verification requires explicit local opt-in:

```env
HBX_SANDBOX_BOOKING_ENABLED=true
```

Use one low-risk sandbox offer, perform CheckRate first, send one booking request only, do not retry, and report only sanitized status/reference data. Do not cancel in Phase 13 unless separately approved.
