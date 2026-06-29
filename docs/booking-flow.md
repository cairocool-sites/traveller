# Phase 7 Booking Flow

Phase 7 adds customer Check Rate, guest details, booking creation, Mock Supplier confirmation, and booking reconciliation foundations.

No real supplier integration, payment gateway, manual payment approval, voucher/PDF, cancellation workflow, quotations, customer accounts, agency/B2B, or deployment work is implemented.

## Public Flow

1. A customer searches hotels through the Phase 6 public search flow.
2. Hotel details expose only opaque hotel and rate tokens.
3. `RateCheckService` resolves the trusted stored supplier rate key from the search session and calls the Mock Supplier `checkRate` operation.
4. The guest details page captures contact details, guests, special requests, and price-change acceptance when needed.
5. `BookingService` creates the internal booking source of truth before calling the supplier booking operation.
6. The confirmation page shows the internal booking reference and customer-safe status only.

Supplier hotel IDs, supplier rate keys, supplier booking references, and net prices are not rendered in public pages.

## Tables

- `rate_checks`: trusted pre-book snapshot for a selected supplier rate, encrypted supplier hotel/rate references, normalized cancellation policy snapshot, original and checked amounts, correlation ID, and expiry.
- `bookings`: internal booking source of truth with public UUID, internal `CCT-YYYY-*` reference, supplier status snapshot, idempotency key/hash, contact details, totals, status, and payment placeholder.
- `booking_rooms`: immutable booked room snapshot.
- `booking_guests`: immutable guest snapshot with lead guest flag.
- `booking_status_histories`: append-only booking status transitions.

## Statuses

Booking statuses are controlled by `App\Enums\BookingStatus`:

- `draft`
- `pending_rate_check`
- `rate_confirmed`
- `guest_details_completed`
- `pending_supplier_confirmation`
- `confirmed`
- `supplier_failed`
- `manual_review`
- `expired`

Payment is intentionally minimal: `not_required` or `pending`. No online payment, refund, capture, or manual payment approval is implemented.

## Services

- `RateCheckService`: validates search-session expiry, resolves opaque public tokens to trusted supplier references, calls Check Rate, and persists a rate-check snapshot.
- `BookingService`: enforces rate validity, price-change acceptance, guest occupancy, idempotency, status history, supplier booking submission, and customer-safe notifications.
- `BookingStateMachine`: records status transitions.
- `BookingReconciliationService`: calls supplier `getBooking` for existing supplier references and reconciles uncertain Mock bookings.
- `BookingReferenceGenerator`: creates non-sequential internal booking references.

## Admin And Permissions

Filament adds read-oriented resources under `Reservations`:

- Bookings
- Rate checks
- Booking status histories

Permissions added:

- `view_rate_checks`
- `view_bookings`
- `manage_booking_status`
- `reconcile_bookings`
- `view_booking_sensitive_data`

Auditors can view bookings and rate checks but cannot mutate or reconcile records.

## Known Limitations

- Mock Supplier only.
- No real supplier booking integration.
- No payment gateway or manual payment approval.
- No vouchers, PDFs, customer accounts, quotations, agency/B2B, refunds, or deployment.
- No customer cancellation flow.
- No internal room inventory or stock management.

## Phase 8 Boundary

Phase 8 may add payment or manual payment workflow depending on product priority. It must not overwrite the internal booking source of truth or expose supplier identifiers publicly.
