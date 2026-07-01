# HBX Hotel API Sandbox Integration

Phase 11 adds the HBX Hotels Sandbox supplier behind the existing `HotelSupplierInterface`. The Mock Supplier remains fully functional.

No production HBX endpoint, production credential, payment gateway, customer account, quotation, B2B, mobile, or deployment work is included.

## Environment Variables

Use local `.env` only:

```dotenv
HBX_ENABLED=false
HBX_API_KEY=
HBX_API_SECRET=
HBX_BASE_URL=https://api.test.hotelbeds.com
HBX_TIMEOUT=60
HBX_CONNECT_TIMEOUT=15
HBX_INTEGRATION_TESTS=false
```

Never commit `.env`, API keys, API secrets, signatures, or raw supplier responses containing sensitive data.

## Authentication

HBX requests use the official Hotelbeds/HBX authentication pattern:

- `Api-key` header.
- `X-Signature` header.
- Signature = SHA-256 hash of API key + API secret + current UTC Unix timestamp.
- `Accept: application/json`.
- `Content-Type: application/json`.

`App\Services\Supplier\Hbx\HbxSignatureService` is deterministic when a fixed timestamp is supplied, and is covered by tests.

## Supplier Configuration

The idempotent supplier seeder creates:

- Name: `HBX Hotels Sandbox`
- Code: `hbx_hotels`
- Integration type: JSON
- Environment: sandbox
- Base URL: `HBX_BASE_URL`
- Credentials: not seeded
- Status: active only when `HBX_ENABLED=true`; otherwise inactive

Credentials are loaded from environment/config for Phase 11. If credentials are later persisted through `supplier_credentials`, encrypted casts and masked Filament fields must be used.

## Operations

The adapter implements:

- Availability search: `POST /hotel-api/1.0/hotels`
- Hotel details: sandbox-compatible hotel lookup through the hotels endpoint where supported
- CheckRate: `POST /hotel-api/1.0/checkrates`
- Booking: `POST /hotel-api/1.0/bookings`
- Booking list: `GET /hotel-api/1.0/bookings`
- Booking lookup: `GET /hotel-api/1.0/bookings/{reference}`
- Booking change: `PUT /hotel-api/1.0/bookings/{reference}`
- Cancellation simulation: `DELETE /hotel-api/1.0/bookings/{reference}?cancellationFlag=SIMULATION`
- Cancellation: `DELETE /hotel-api/1.0/bookings/{reference}?cancellationFlag=CANCELLATION`
- Booking reconfirmations: `GET /hotel-api/1.0/bookings/reconfirmations`
- Health/status: `GET /hotel-api/1.0/status`

Automated tests use Laravel HTTP fakes and never call live HBX.

## Availability Mapping

HBX destination, dates, occupancy, nationality, currency, hotel code, hotel name, category/star signal, coordinates, room codes, board basis, prices, cancellation policies, rate type, allotment, and rate keys are normalized into the existing DTOs. When the content-mapping layer supplies explicit HBX hotel codes, availability requests send `hotels.hotel` and do not also send a destination filter.

Public pages use public hotel/rate tokens. HBX hotel codes and rate keys are retained only in server-side snapshots needed for CheckRate and booking.

## BOOKABLE and RECHECK

- `BOOKABLE` rates are treated as directly checkable/bookable according to HBX semantics.
- `RECHECK` rates are marked with `requires_check_rate` metadata and must pass CheckRate before booking.
- Expired or unchecked RECHECK rates must not be booked.

## Booking and Reconciliation

Booking uses existing supplier idempotency records. The adapter never retries booking blindly. If a booking request times out after submission, the result is `uncertain`, requires manual review, and should be reconciled through booking lookup.

## Booking List, Change, and Reconfirmation

Booking list uses official bounded pagination (`from`, `to`) plus documented filters for `start`, `end`, `filterType`, `status`, `clientReference`, `creationUser`, `country`, `destination`, and `hotel`.

Booking change is implemented only as protected low-level adapter support for official `SIMULATION` and `UPDATE` payloads. No public customer modification workflow is enabled.

Reconfirmation retrieval uses official filters for pagination, optional check-in/reconfirmation date windows, `clientReferences`, and HBX `references`.

## Cancellation

Cancellation uses existing supplier idempotency records. The adapter never retries cancellation blindly. HBX cancellations run the official `cancellationFlag=SIMULATION` request first and persist a sanitized simulation snapshot. The live `cancellationFlag=CANCELLATION` request is sent only after a safe simulation result. Penalties are normalized when HBX returns them. Timeout, unavailable simulation, rejected simulation, or unknown cancellation results require manual review.

## Logging and Redaction

Supplier operation logs are sanitized. The sanitizer redacts:

- `Api-key`
- `X-Signature`
- `Authorization`
- tokens
- passwords
- secrets
- payment and identity indicators
- customer email/phone fields

Logs should be used for diagnostics only and must not expose raw credentials or signatures.

## CLI Commands

Safe health check:

```bash
php artisan hbx:test-connection
```

Safe diagnostic health check:

```bash
php artisan hbx:test-connection --diagnostic
```

The command:

- Runs only when `HBX_ENABLED=true`.
- Requires local credentials.
- Calls only the HBX sandbox status endpoint.
- Prints sanitized success/failure and correlation ID.
- In diagnostic mode, prints only target host, path, method, timeout values, proxy presence, and whether an HTTP response was received.
- Never prints credentials, signatures, or raw sensitive responses.

No command is provided for creating a real sandbox booking without code-level safeguards.

## Tests

Automated:

```bash
php artisan test tests/Feature/HbxSandboxSupplierTest.php
```

Full suite:

```bash
php artisan test
```

Live sandbox checks are manual and opt-in only:

```bash
HBX_ENABLED=true
HBX_INTEGRATION_TESTS=true php artisan hbx:test-connection
```

Do not run live sandbox checks in CI unless explicit sandbox credentials and policy approval exist.

## Certification Considerations

Before production certification, HBX documentation and account-specific requirements must be reviewed for:

- Required request fields.
- Market and nationality restrictions.
- Booking holder and pax validation.
- Cancellation edge cases.
- Error code mapping.
- Rate comments and mandatory fees.
- Certification test cases.

## Known Limitations

- No production endpoint is configured.
- No credentials are committed.
- Hotel content mapping to canonical hotels remains a future supplier-mapping phase.
- Public search includes HBX only if `travel.public_search.suppliers` is configured to include `hbx_hotels`.
- The adapter provides Phase 11 normalization foundations and must be expanded during certification.

## Hotels API Suite Capability Registry

Phase 14 adds a persisted HBX capability matrix in `hbx_api_capabilities`. It separates implementation status from HBX account authorization, admin enablement, public enablement, sandbox testing, and production activation.

Refresh/display the matrix without making HBX calls:

```bash
php artisan hbx:api-suite:status
```

The admin resource `HBX API capabilities` is read-only for standard users and never displays credentials, signatures, raw payloads, rate keys, or card data.
