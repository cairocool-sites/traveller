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
- customer currency as local pricing context
- supplier currency and supplier total amount as supplier pricing context
- cancellation policy presence

Each field is classified as:

- `matched`
- `mismatched`
- `missing_local`
- `missing_supplier`
- `not_comparable`
- `customer_pricing`
- `supplier_pricing`
- `manual_review`

Customer pricing and supplier pricing fields are intentionally separated. A customer can pay or
view a USD amount while HBX returns supplier-side totals in EUR or another supplier currency.
Those pricing-context differences must be reviewed operationally, but they do not by themselves
prove that the supplier booking identity is wrong.

Results are stored in `booking_certification_evidences` with operation type `booking_detail_reconciliation`.

Admins with `reconcile_bookings` can open the protected reconciliation view from the booking resource. The view shows classifications and presence flags only, not supplier payloads or sensitive customer data.
