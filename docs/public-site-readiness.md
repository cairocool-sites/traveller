# Public Site Readiness

This document tracks supplier-independent launch readiness for the public Cairo Cool Travel website.

## Current Public Surface

- Bilingual Arabic/English public layout with RTL/LTR support.
- Homepage hotel search entry point.
- Hotel search results and hotel details views.
- Local HBX catalogue destination and hotel pages from stored content.
- CheckRate review page and guarded booking flow.
- Manual payment, cancellation, refund, document, and verification pages.
- Trust and policy pages:
  - About
  - Contact
  - Terms of use
  - Privacy notice
  - Payment policy
  - Cancellation policy
  - Support

## SEO Readiness

- Public layout includes title, meta description, canonical URL, and Open Graph title/description.
- XML sitemap includes home, hotel search, trust/policy pages, public destinations, and public hotel catalogue records.
- Catalogue pages expose structured data for destinations and hotels when local content is available.

## Supplier Boundary

Public pages must not call suppliers merely to render static content.

Live supplier calls remain limited to the guarded search, CheckRate, booking, lookup, and cancellation flows that already enforce sandbox/production controls.

While HBX support blockers are open, additional live HBX diagnosis is frozen. Supplier-related automated tests must use HTTP fakes.

## Customer Safety

- Public copy avoids unsupported launch claims.
- Payment-card collection is out of scope.
- Booking, payment, voucher, cancellation, and modification actions are blocked when supplier identity is unresolved.
- Support guidance asks customers to use local booking references and avoid sending sensitive payment or supplier data.

## Remaining Non-Supplier Readiness Work

- Replace placeholder support contacts with approved launch contact details.
- Legal review of terms, privacy, payment, and cancellation text.
- Final accessibility pass with browser screenshots across Arabic and English.
- Final performance pass on the production-like Nginx/Vite deployment.
- Operational review of support SLAs and escalation paths.
