# Voucher Evidence

The internal voucher is available only for authorized admin users and only for confirmed bookings or manual-review provisional bookings.

The printable HTML fallback includes:

- Cairo Cool Travel branding
- Sandbox / Test Booking notice
- local booking reference
- HBX supplier reference
- booking status
- hotel name
- hotel address when available from local content
- destination when available from local content
- category when available
- check-in and check-out as separate labelled fields
- room and board
- guest summary and lead passenger
- selling total and currency
- cancellation summary
- booking remarks when supplied
- customer support wording
- issue date

The voucher excludes supplier net price, rate keys, API credentials, signatures, raw supplier payloads, payment receipt data, card data, and customer contact details.

Voucher field completeness is recorded as `present`, `unavailable_from_supplier`, `blocked_by_content_api`, or `manual_review`.
