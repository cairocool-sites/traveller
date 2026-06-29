# HBX Content Sync and Mapping

Phase 14 adds a bounded HBX Content API layer so public search and the manual sandbox verification command use official HBX destination and hotel identifiers instead of free-text city names.

## Official Endpoints Used

The Content API is kept separate from the Booking API adapter and uses the same HBX sandbox host, signed headers, JSON responses, timeout settings, and sanitized logging.

- `GET /hotel-content-api/1.0/locations/countries`
- `GET /hotel-content-api/1.0/locations/destinations`
- `GET /hotel-content-api/1.0/hotels`
- `GET /hotel-content-api/1.0/hotels/{code}/details`

Availability, CheckRate, booking lookup, booking, and cancellation remain in the Booking API path under `/hotel-api/1.0/*`.

## Authentication

Content API requests use:

- `Api-key`
- `X-Signature`
- `Accept: application/json`
- `Content-Type: application/json`
- `X-Correlation-ID`

Credentials, signatures, headers, and raw sensitive payloads are never printed. Supplier operation logs are sanitized by the existing payload sanitizer.

## Stored Reference Data

`hbx_destinations` stores supplier code, HBX destination code, destination name, country code, parent destination code, active state, and sync timestamp.

`hbx_hotels` stores supplier code, HBX hotel code, destination code, hotel name, category/star signal, coordinates, address, active state, and sync timestamp.

Upserts are idempotent. Records missing from later bounded syncs are deactivated rather than deleted.

## Mapping Workflow

`supplier_destination_mappings` links local cities or areas to confirmed HBX destination codes. Public search requires an active, manually confirmed mapping before HBX availability is called.

`supplier_hotel_mappings` links canonical local hotels to HBX hotel codes when content teams are ready to confirm hotel-level matching. Similar names are not enough to merge or overwrite canonical hotel content.

## Cairo-First Procedure

1. Validate countries:
   `php artisan hbx:sync-content --countries --dry-run`
2. Sync Egypt destinations only:
   `php artisan hbx:sync-content --destinations --country=EG --page-limit=1`
3. Confirm the Cairo destination mapping in admin after reviewing suggested mappings.
4. Sync a bounded Cairo hotel set:
   `php artisan hbx:sync-content --hotels --destination=CAI --page-limit=1`
5. Confirm active Cairo hotel count in the HBX Hotels admin resource.
6. Run:
   `php artisan hbx:verify-sandbox-booking --dry-run`

Do not sync the entire global catalog during this phase.

## Public Search Flow

The browser submits a local destination token such as `city:{id}`. The server resolves:

1. Local destination token
2. Confirmed HBX destination mapping
3. Active synchronized HBX hotel codes
4. HBX availability payload using `destination.code` and `hotels.hotel`

The free-text name `Cairo` is never sent as the HBX availability identifier. If no mapping or no hotel codes exist, the search fails safely with a customer-safe message. Real HBX searches do not silently fall back to Mock.

## Quota and Pagination

Sync commands are bounded by `--page-limit`, support `--dry-run`, and require explicit options. There is no uncontrolled full-world sync command.

## Manual Verification

The dry-run command requires synchronized and confirmed HBX content before it can search. It prints only supplier code, local destination, HBX destination code, number of hotel codes searched, dates, currency, availability count, CheckRate source, and dry-run completion.

No booking or cancellation request is sent by dry-run.
