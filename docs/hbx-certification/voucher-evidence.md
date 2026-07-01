# Voucher Evidence

The voucher is available to authorized admin users and to the customer through the opaque public booking UUID. Confirmed bookings show a final voucher. Manual-review bookings show a provisional notice only. Bookings with unresolved supplier identity are blocked from voucher access.

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
- HBX rate comments when supplied
- safe payment wording, VAT notice, and local payment reference
- customer support wording
- issue date

The voucher excludes supplier net price, rate keys, API credentials, signatures, raw supplier payloads, payment receipt data, card data, and customer contact details.

Voucher field completeness is recorded as `present`, `unavailable_from_supplier`, `blocked_by_content_api`, or `manual_review`.
