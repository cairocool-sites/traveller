# Manual Payments

Phase 8 adds an internal manual payment domain for confirmed bookings. It does not add online gateways, webhooks, refunds, cancellation requests, quotations, customer accounts, agencies, B2B, real supplier integrations, or deployment.

## Lifecycle

Confirmed booking -> select manual payment method -> submit reference/evidence -> submitted -> under review -> approved/rejected -> paid.

Payment status is independent from supplier booking status. Approval updates `bookings.payment_status` only; it never calls supplier APIs, never rebooks, and never changes supplier confirmation state.

## Manual Methods

Seeded methods are:

- `bank_transfer`
- `cash_at_office`
- `mobile_wallet`
- `manual_confirmation`

Seed data uses placeholders only. No real account numbers, credentials, tokens, or bank-sensitive values are seeded.

## Evidence

Evidence is stored on Laravel's private `local` disk under the configured private directory. Raw storage paths are not public. Access goes through a signed controller route plus authorization.

Allowed extensions and MIME types are configured in `config/travel.php`. Executable files are rejected. EXIF stripping is deferred until a stable image-processing dependency is intentionally selected.

## Review Controls

Payment review is permission-protected. Approval and rejection are explicit transitions with history records. Rejection requires a reason.

Basic maker-checker control prevents an admin who submitted a payment record from approving it. A super admin may override only with an explicit reason.

## Permissions

Phase 8 adds `view_payments`, `submit_manual_payments`, `review_payments`, `approve_payments`, `reject_payments`, `view_payment_evidence`, `manage_payment_methods`, `view_financial_totals`, and `view_sensitive_payment_data`.

Auditors remain read-only. Reservation agents cannot approve payments.

## Known Limitations

- No gateway integration.
- No refunds.
- Phase 9 adds manual refund tracking only; no gateway refund is connected.
- No official e-invoicing or tax authority integration.
- No SMS or WhatsApp provider.
