# HBX Certification Request Package

Use this file as the working checklist before asking HBX to start certification.

Official reference:

https://developer.hotelbeds.com/documentation/hotels/knowledge-base/certification-process/

## Current Decision

Status: not ready to submit.

Reason:

- HBX Content API Sandbox hotel/content requests remain blocked by `HTTP 500` / `SYSTEM_ERROR`.
- One previous Sandbox booking identity/reference remains disputed and must be excluded from evidence.
- A clean, traceable Sandbox booking and voucher evidence package must be produced after HBX support responds.

## Certification URL

Planned staging URL:

```text
https://travel.cairocool.com
```

Deployment requirements for staging:

- `APP_ENV=staging`
- `APP_DEBUG=false`
- `APP_URL=https://travel.cairocool.com`
- `HBX_SANDBOX_BOOKING_ENABLED=false` by default
- No production HBX endpoint
- No payment gateway credentials
- No real card capture

## Workflow Explanation for HBX Reviewers

Prepare a short tester guide that covers:

1. Open the staging URL.
2. Switch between Arabic and English.
3. Search for a destination or locally stored HBX hotel.
4. Review hotel results.
5. Open hotel details.
6. Run CheckRate only from a valid offer.
7. Enter fake Sandbox guest details only.
8. Create exactly one controlled Sandbox booking only after explicit approval.
9. Review the local booking confirmation.
10. Open the protected voucher preview/download.
11. Do not test cancellation until HBX support confirms the correct evidence path.

## Commercial Information to Confirm

- Default customer currency: EGP.
- Supported currencies: EGP, USD, EUR, SAR, AED, GBP.
- Online payment gateway: out of scope.
- Manual payment review: supported.
- Markup/commission rules: must be reviewed before certification if customer selling prices are final.
- Taxes and fees: shown only when supplied or safely normalized.
- Supplier net prices: never shown to customers or in vouchers.

## HBX-Only Testing Notes

The reviewer must be able to identify HBX-only behavior:

- Public search suppliers should be configured for `hbx_hotels` only when collecting certification evidence.
- Mock supplier fallback must not be used for HBX certification evidence.
- Browser hotel-name search must submit an opaque local hotel ID and the server must resolve the integer HBX hotel code.
- Availability hotel-code searches must send HBX hotel codes server-side only.

## Payment Notes

Online payment is not implemented in this phase.

The current platform supports manual payment review only:

- customer submits a manual reference/evidence file;
- admin reviews it;
- document generation depends on booking/payment state;
- no card data is collected.

## Voucher Evidence Requirements

Voucher evidence should be generated only from a clean confirmed Sandbox booking.

Voucher must be checked for:

- Cairo Cool Travel branding;
- local booking reference;
- HBX confirmation reference;
- booking status;
- hotel name;
- room;
- board;
- check-in/check-out;
- guest summary;
- selling total and currency;
- cancellation summary;
- issue date;
- clear Sandbox/Test notice;
- no API key, secret, signature, raw payload, supplier net price, or full sensitive contact data.

## Known Deviations and Blockers

| Item | Status | Action |
| --- | --- | --- |
| Content API hotels/details returns `HTTP 500` / `SYSTEM_ERROR` | Blocked externally | Wait for HBX support response. |
| Previous Sandbox booking identity/reference mismatch | Manual review | Exclude from certification evidence. |
| Clean Sandbox booking evidence | Missing | Create one new controlled booking only after blockers are resolved. |
| Voucher evidence | Missing | Generate from clean confirmed booking only. |
| Cancellation evidence | Paused | Do not send cancellation or simulation until HBX confirms the expected evidence flow. |
| Legal text | Needs review | Replace readiness placeholder text before public launch. |
| Support channels | Needs review | Add approved contact email/phone/hours before public launch. |

## Evidence Not Allowed

Do not use:

- disputed booking `CCT-2026-422M23IT`;
- any cancellation artifact related to the disputed booking;
- Mock Supplier responses;
- production requests;
- raw HBX payloads containing sensitive data;
- screenshots that reveal credentials, signatures, or private headers.

## Pre-Submission Command

Run locally before sending certification materials:

```bash
php artisan hbx:certification:readiness
```

The command is read-only and must report no supplier requests, no booking, no modification, no cancellation, and no production request.
