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

Working package template:

[docs/hbx-certification/certification-request-package.md](hbx-certification/certification-request-package.md)

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
- Certification request package items.
- Open blocker and review items.

## Current Status

Technical:

- Booking API requests use signed JSON requests.
- Content API requests use `Accept: application/json` and `Accept-Encoding: gzip`.
- Sandbox base URL remains `https://api.test.hotelbeds.com`.
- Production is blocked for this phase.

Workflow:

- Public and manual flows call Availability first.
- RECHECK rates require CheckRate.
- Booking uses trusted RateCheck snapshots and does not repeat Availability during booking submission.
- Booking transmission remains guarded by `HBX_SANDBOX_BOOKING_ENABLED`.

Voucher:

- A protected internal voucher route exists for confirmed bookings.
- The final certification voucher evidence must still be manually reviewed against HBX requirements.

Content:

- Countries, destinations, hotels, hotel details, diagnostics, and code-based fallback are implemented.
- The current HBX Sandbox account returns HTTP 500 for official hotels and hotel-details content requests, so real hotel content is not yet stored locally.
- Content API failures are sanitized and tracked.

Live:

- Live booking and live cancellation are blocked.
- The official live test must wait for HBX live keys and explicit owner approval.
- Do not select NRF or penalty-bearing rates for any future live test unless HBX and the project owner explicitly approve the risk.

Staging:

- Planned staging URL: `https://travel.cairocool.com`.
- Staging should run with `APP_DEBUG=false`.
- Keep `HBX_SANDBOX_BOOKING_ENABLED=false` until a single controlled Sandbox booking is explicitly approved.
- Do not use the production HBX endpoint.

## Not Certification Complete

This project is not certified yet. The command and this document are readiness tools only. Certification must be requested from HBX after the remaining manual evidence and account-side access issues are resolved.
