# HBX Booking Reconciliation

Booking reconciliation compares local booking state against HBX Booking Detail without overwriting local records automatically.

Compared fields:

- local reference
- supplier reference
- supplier status
- hotel code and name
- check-in and check-out
- room count, room type, and board
- occupancy/passenger count
- currency
- total amount
- cancellation policy presence

Each field is classified as:

- `matched`
- `mismatched`
- `missing_local`
- `missing_supplier`
- `not_comparable`
- `manual_review`

Results are stored in `booking_certification_evidences` with operation type `booking_detail_reconciliation`.

Admins with `reconcile_bookings` can open the protected reconciliation view from the booking resource. The view shows classifications and presence flags only, not supplier payloads or sensitive customer data.
