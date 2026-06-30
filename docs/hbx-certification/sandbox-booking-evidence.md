# HBX Sandbox Booking Evidence

Phase 14 uses the already confirmed HBX Sandbox booking only:

- Local reference: `CCT-2026-422M23IT`
- Supplier reference: `138-3060762`
- Supplier: `hbx_hotels`
- Endpoint family: HBX Sandbox Booking API
- Base URL: `https://api.test.hotelbeds.com`

The evidence command is:

```bash
php artisan hbx:certification:evidence --booking=CCT-2026-422M23IT
```

The command retrieves Booking Detail with:

```text
GET /hotel-api/1.0/bookings/{bookingReference}
```

It then performs a cancellation simulation only:

```text
DELETE /hotel-api/1.0/bookings/{bookingReference}?cancellationFlag=SIMULATION
```

The command prints sanitized evidence only. It never prints credentials, signatures, headers, rate keys, raw payloads, supplier net price, card data, full guest identity, phone, email, or production URLs.

No second booking is created by this evidence process. No production request is allowed.
