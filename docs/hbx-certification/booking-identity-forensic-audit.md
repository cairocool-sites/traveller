# HBX Booking Identity Forensic Audit

This Phase 14 audit is used when the local supplier reference does not match HBX Booking Detail identity fields.

Official HBX Booking API reference points used:

- Booking confirmation returns the booking object.
- The HBX booking reference is `booking.reference`.
- Booking Detail reads `GET /hotel-api/1.0/bookings/{bookingId}`.
- `bookingId` is the booking reference taken from the confirmation response or Booking List.
- The documented format is `XXX-YYYYYY`, so hyphenated references are expected and must be URL-safe encoded by the HTTP client if needed.
- Booking List reads `GET /hotel-api/1.0/bookings` with bounded filters including `from`, `to`, `start`, `end`, `filterType`, `status`, and optional `clientReference`.

The forensic command is:

```bash
php artisan hbx:booking-identity:audit --booking=CCT-2026-422M23IT
```

It is read-only and sends only:

- Booking Detail lookup for the stored supplier reference.
- A bounded Booking List lookup using the locally generated `clientReference`.

It must not send Booking, CheckRate, cancellation, modification, confirmation, or production requests.

Identity fingerprint fields:

- clientReference
- hotel code
- check-in/check-out
- room count
- board
- occupancy count
- currency
- creation-date window

Classifications:

- `exact_match`
- `reference_mismatch`
- `local_mapping_error`
- `supplier_reference_reused_or_unexpected`
- `insufficient_evidence`
- `manual_review`

If identity is unresolved, customer voucher, payment, cancellation, and modification actions remain blocked.
