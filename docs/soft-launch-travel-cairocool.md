# Soft Launch Runbook: travel.cairocool.com

Use this runbook to launch the public site safely while HBX certification blockers remain open.

## Recommended Environment

```env
APP_ENV=staging
APP_DEBUG=false
APP_URL=https://travel.cairocool.com

HBX_ENABLED=true
HBX_BASE_URL=https://api.test.hotelbeds.com
HBX_SANDBOX_BOOKING_ENABLED=false

TRAVEL_PUBLIC_SEARCH_SUPPLIERS=hbx_hotels
TRAVEL_BOOKING_SUBMISSION_MODE=manual_review
TRAVEL_PUBLIC_SEARCH_MARKUP_BASIS_POINTS=0
```

Do not use the production HBX endpoint on the staging subdomain.

## Customer Flow Enabled

The public customer can:

1. Open the homepage.
2. Search by destination or locally stored hotel name.
3. Select dates, rooms, adults, children, currency, nationality, and residence country where available.
4. View HBX Availability results when the supplier responds.
5. Open hotel details.
6. Run CheckRate for a selected offer.
7. Enter guest details.
8. Submit a local manual-review booking request.

In `manual_review` mode, no HBX booking request is sent.

## Customer Flow Disabled

The public customer cannot:

- pay online;
- submit payment-card data;
- send a production HBX booking;
- send a production HBX cancellation;
- automatically receive a final supplier voucher from a disputed or unconfirmed booking;
- trigger Mock Supplier fallback when `TRAVEL_PUBLIC_SEARCH_SUPPLIERS=hbx_hotels`.

## HBX Autocomplete Strategy

Autocomplete must read from local tables:

- `hbx_destinations`
- `hbx_hotels`
- local city/area/country records

The browser submits opaque local tokens such as `hbx_hotel:{id}`. The server resolves the stored HBX destination or hotel code. Raw HBX hotel codes must not be trusted from browser input.

## Payment Strategy

Online payment gateway integration is out of scope for this launch.

Manual payment review is available only after a booking is confirmed by an authorized workflow. While `TRAVEL_BOOKING_SUBMISSION_MODE=manual_review`, customer submissions remain under review and should not be treated as final confirmed bookings.

## HBX Payment Types

Display and internal handling should respect HBX `paymentType` values from normalized rates:

- `AT_WEB`: Cairo Cool Travel must decide the collection/review process before final supplier confirmation.
- `AT_HOTEL`: customer payment may be collected at the hotel according to the supplier/hotel terms.

Do not assume HBX acts as a customer-facing payment gateway.

## Pre-Launch Checks

Run:

```bash
php artisan optimize:clear
php artisan migrate --force
php artisan db:seed --force
php artisan hbx:certification:readiness
npm run build
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Then test:

- homepage loads in Arabic and English;
- destination autocomplete returns local records;
- search sends HBX Availability only when submitted;
- CheckRate works for a selected offer;
- guest details submission creates a manual-review booking;
- no `/hotel-api/1.0/bookings` request is sent in manual-review mode;
- payment and cancellation remain guarded for under-review bookings.

## Current Blockers

See:

- [HBX external blockers](hbx-certification/external-blockers.md)
- [HBX certification request package](hbx-certification/certification-request-package.md)
