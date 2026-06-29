# Refunds

Phase 9 adds manual refund tracking only. It does not call payment gateways, banks, card networks, supplier APIs, or accounting ledgers.

## Lifecycle

Statuses are controlled by `RefundStatus`:

- `pending`
- `under_review`
- `approved`
- `rejected`
- `processing`
- `completed`
- `failed`

Every transition writes `refund_status_histories`.

## Limits

Refunds are capped by both:

- the cancellation refundable amount
- paid payment totals minus already completed refunds

All values are stored as integer minor units.

## Maker-Checker

Refund creators cannot approve or complete their own refund unless a super admin uses an explicit override reason. Reservation agents cannot approve refunds. Financial roles such as accountant and general manager can approve/complete according to permissions.

## Payment Synchronization

Completing a refund updates payment status to `partially_refunded` or `refunded` and mirrors that summary to `bookings.payment_status`. It never edits the original paid amount, receipt, invoice, or supplier booking state.

## Known Limitations

- No automatic gateway refunds.
- No chargeback workflow.
- No accounting ledger integration.
- No official tax or e-invoice credit note integration.
