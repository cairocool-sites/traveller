# HBX Certification Readiness

This note maps the current Phase 14 implementation to the official HBX Hotels Knowledge Base certification process.

Official reference:

https://developer.hotelbeds.com/documentation/hotels/knowledge-base/certification-process/

## Certification Areas

HBX reviews these areas before approving go-live:

- Technical request behavior.
- Booking workflow.
- Availability, CheckRate, and Confirmation.
- Voucher.
- Content.
- Live environment.

Before requesting certification, the project owner must also prepare the workflow explanation, commercial decisions, certification URL and access details, payment notes if needed, an HBX-only testing guide if other suppliers are present, and any known deviations.

## Local Readiness Command

Run:

```bash
php artisan hbx:certification:readiness
```

The command is read-only. It does not call HBX, does not book, does not modify, does not cancel, and does not use production.

It reports:

- Technical request readiness, including signed JSON requests and gzip support.
- Workflow readiness.
- Availability, CheckRate, and guarded Booking readiness.
- Voucher readiness.
- Content API readiness and known live Sandbox limitations.
- Live-environment status.
- Manual certification evidence still required.

## Current Status

Technical:

- Booking API requests use signed JSON requests.
- Content API requests use `Accept: application/json` and `Accept-Encoding: gzip`.
- Sandbox base URL remains `https://api.test.hotelbeds.com`.
- Production is blocked for this phase.

Workflow:

- Public and manual flows call Availability first.
- RECHECK rates require CheckRate.
- Booking Confirmation uses a 60-second minimum timeout.
- Booking uses trusted RateCheck snapshots and does not repeat Availability during booking submission.
- Booking transmission remains guarded by `HBX_SANDBOX_BOOKING_ENABLED`.
- HBX `rateComments` are stored in the RateCheck snapshot and shown before guest details when supplied.

Voucher:

- A protected internal voucher route exists for confirmed bookings.
- A public customer voucher route exists at `/bookings/{booking_uuid}/voucher`, using the opaque booking UUID and the same unresolved-identity block.
- Voucher wording includes safe payment information, VAT notice, and local booking reference.
- The final certification voucher evidence must still be manually reviewed against HBX requirements.

Content:

- Countries, destinations, hotels, hotel details, diagnostics, and code-based fallback are implemented.
- Real HBX Egypt hotel content, descriptions, and image paths can be stored locally when the Content API credentials are valid and quota is available.
- Content API failures are sanitized and tracked.

Live:

- Live booking and live cancellation are blocked.
- The official live test must wait for HBX live keys and explicit owner approval.
- Do not select NRF or penalty-bearing rates for any future live test unless HBX and the project owner explicitly approve the risk.

## Not Certification Complete

This project is not certified yet. The command and this document are readiness tools only. Certification must be requested from HBX after the remaining manual evidence and account-side access issues are resolved.
