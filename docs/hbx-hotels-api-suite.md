# HBX Hotels API Suite

Phase 14 now treats HBX as a full Hotels API Suite integration with feature gating. Technical capability can be implemented before it is authorized by the HBX account, enabled for administrators, or enabled for public customers.

No production request, live booking, live modification, or live cancellation may be sent by automated tests or development commands.

## Official Documentation Reviewed

The implementation is based on the attached official HBX Group Hotels OpenAPI specification for the Hotel Booking API and the previously integrated official Content API endpoint set:

- `POST /hotel-api/1.0/hotels`
- `POST /hotel-api/1.0/checkrates`
- `POST /hotel-api/1.0/bookings`
- `GET /hotel-api/1.0/bookings`
- `GET /hotel-api/1.0/bookings/{bookingId}`
- `PUT /hotel-api/1.0/bookings/{bookingId}`
- `DELETE /hotel-api/1.0/bookings/{bookingId}?cancellationFlag=SIMULATION`
- `DELETE /hotel-api/1.0/bookings/{bookingId}?cancellationFlag=CANCELLATION`
- `GET /hotel-api/1.0/bookings/reconfirmations`
- `GET /hotel-content-api/1.0/locations/countries`
- `GET /hotel-content-api/1.0/locations/destinations`
- `GET /hotel-content-api/1.0/hotels`
- `GET /hotel-content-api/1.0/hotels/{hotelCodes}/details`

Cache API and Change Discovery Service endpoints are capability-gated until official account authorization and endpoint details are confirmed.

## Capability States

Every HBX capability is tracked separately:

- `implemented`: code support exists in the platform.
- `configured`: local configuration and credentials are present.
- `credential_access_confirmed`: a sanitized successful call exists for the exact capability where applicable.
- `sandbox_tested`: a sanitized successful sandbox call exists.
- `production_enabled`: production endpoint use is explicitly enabled.
- `admin_enabled`: authorized administrators can access or operate it.
- `public_enabled`: public customers can access it.

The table `hbx_api_capabilities` stores this status. The admin resource `HBX API capabilities` displays it without credentials, signatures, rate keys, card data, or raw supplier payloads.

Refresh/display locally:

```bash
php artisan hbx:api-suite:status
php artisan hbx:certification:readiness
```

These commands do not call HBX.

## Current Implementation Matrix

Implemented now:

- Availability
- CheckRate
- Booking confirmation behind sandbox guard
- Booking detail/reconciliation
- Cancellation simulation endpoint support through explicit internal metadata
- Cancellation endpoint support through protected cancellation service
- Internal voucher support for confirmed sandbox bookings
- Content countries endpoint validation
- Content destinations sync
- Content hotels sync
- Content hotel details client method

Tracked but not yet implemented/enabled:

- Booking list
- Booking modification/update
- Reconfirmation retrieval
- Payment-data and 3DS orchestration
- Full Content API master/descriptive resource catalogue
- Hotel Cache API FULL/update import
- Change Discovery Service
- Production access
- Certification evidence automation

## Ordered Phase 14 Sub-Plan

1. Capability registry, status command, and admin read-only matrix. Completed in commit `c735144`.
2. Typed HBX destination/hotel catalogue, local autocomplete, and HBX search resolution without mandatory city mapping. Completed in the typed catalogue foundation.
3. Full Content API resource catalogue, generic resource storage, sync checkpoints, queued batches, and differential-sync command options. Foundation completed; large live imports remain manual and bounded.
4. Booking API operation coverage for list/detail/change/cancel simulation/cancel/reconfirmation.
5. Payment-data and 3DS schema support behind explicit security feature flags.
6. Cache API adapter, safe ZIP extraction, file validation, import status, and rollback.
7. CDS adapter, checkpoints, deduplication, and prioritized refresh workflow.
8. Egypt-first public exposure using local content only for static data and Booking API only for availability.
9. Certification-readiness checklist and safe local status command.

## Hybrid Public Search Architecture

The selected architecture is:

```text
HBX Content API
-> scheduled local content synchronization
-> local fast search catalogue
-> live HBX Booking API Availability only after the user submits a search
```

Public autocomplete and static destination/hotel pages must use local database records only. The Content API must not be called while rendering public pages or autocomplete responses.

The public search form submits local opaque tokens:

- `hbx_destination:{id}` for destination searches.
- `hbx_hotel:{id}` for hotel-code searches.

The server resolves those records to official HBX identifiers internally. Raw HBX destination codes, hotel codes, and rate keys must not be trusted from browser input.

The local crawlable catalogue uses:

- `/destinations/{slug}`
- `/hotels/{destination-slug}/{hotel-slug}`
- `/sitemap.xml`

These pages are powered by synchronized local content, public visibility flags, SEO fields, and minimal schema.org data for visible content only. They do not display fabricated prices, ratings, reviews, offers, or availability.

Content synchronization records safe batches in `hbx_content_sync_batches`. Admin users can inspect resource, status, counts, checkpoints, and sanitized errors from Filament. The batch table is an operational audit trail and must not contain credentials, signed headers, or raw supplier payloads.

## Safety Gates

- Production endpoints remain blocked unless explicitly configured in a later approved phase.
- Booking transmission remains disabled by default through `HBX_SANDBOX_BOOKING_ENABLED=false`.
- Public card collection remains disabled.
- `paymentDataRequired=true` rates must not be discarded permanently; they must be routed to a disabled payment-data capability until the account and PCI design are approved.
- No automatic retry is allowed for booking, modification, or cancellation after an ambiguous timeout.
- Cancellation and modification require explicit admin authorization and confirmation.

## Known Account-Authorization Unknowns

The following may be implemented locally while still marked not authorized until HBX confirms access:

- Cache API
- CDS
- Push reconfirmation service
- Email reconfirmation service
- Payment-data model
- Production booking
- Resident/source-market-specific rates

No fake success state may be recorded for unavailable capabilities.
