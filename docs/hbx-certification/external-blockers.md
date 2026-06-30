# HBX External Blockers

Phase 14 is paused for additional live HBX diagnosis until HBX support replies.

## Open Support Items

1. Content API bulk hotel and hotel-detail retrieval in Sandbox can return `HTTP 500` / `SYSTEM_ERROR` even when the request is sanitized and bounded.
2. One previous Sandbox booking identity/reference remains unresolved and must not be used as certification evidence.

## Freeze Rules

- Do not send additional live HBX Availability, CheckRate, Booking, Booking Detail, Booking List, Cancellation, Cancellation Simulation, or Modification requests for these blockers until support replies.
- Do not send production HBX requests.
- Do not send payment data.
- Do not retry the disputed booking.
- Keep the disputed local booking under manual review.
- Exclude the disputed booking and all related cancellation artifacts from certification evidence.

## Allowed Work While Waiting

- Supplier-independent public UI and content readiness.
- Local catalogue display from already-stored content.
- HTTP-fake tests for supplier behavior.
- Admin and customer workflow hardening that does not make live supplier calls.
- Documentation, security review, deployment planning, SEO readiness, and accessibility improvements.

## Evidence Policy

Certification evidence must be created only from clean, traceable, non-disputed Sandbox operations after HBX support confirms the correct path forward.

The current disputed booking identity audit remains useful as internal forensic documentation, but it is not launch or certification evidence.
