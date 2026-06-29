# Cancellations

Phase 9 adds customer cancellation requests, immutable policy evaluation, Mock Supplier cancellation, cancellation histories, and customer-safe status pages.

No real supplier integration, real gateway refund, quotation, customer account, agency/B2B, chargeback, accounting ledger, or deployment work is implemented.

## Eligibility

Cancellation eligibility is evaluated from the internal booking source of truth:

- booking status must be confirmed
- one active cancellation request per booking
- check-in date is considered
- immutable `bookings.cancellation_policy_snapshot` is preferred
- unknown policy goes to manual review
- non-refundable policy requires explicit customer acknowledgement

The system never promises free cancellation unless the stored policy proves a zero-penalty active cancellation window.

## Lifecycle

Statuses are controlled by `CancellationStatus`:

- `requested`
- `under_review`
- `pending_supplier`
- `cancelled`
- `rejected`
- `failed`
- `manual_review`
- `expired`

Every transition writes `cancellation_status_histories`.

## Supplier Outcomes

The existing Mock Supplier `cancel` operation is used. A successful supplier cancellation marks the booking as `cancelled`, records penalty/refundable amounts, and revokes vouchers. Supplier rejection leaves the booking confirmed. Uncertain supplier exceptions move cancellation to manual review and do not trigger a blind second cancellation.

## Idempotency

Cancellation requests require an idempotency key. Reusing the same key with the same payload returns the original cancellation. Reusing the key with different data fails.

## Public Pages

Public pages use UUIDs, noindex metadata, throttling, and minimal data. Supplier IDs, raw payloads, endpoints, internal notes, and net prices are hidden.

## Document Behavior

Voucher records may be revoked after a successful cancellation. Invoice and receipt records remain immutable historical records.
