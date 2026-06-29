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

`hbx_destinations` stores supplier code, HBX destination code, destination name, country code, parent destination code, content language, destination type, coordinates where supplied, supplier/public active flags, Arabic/English display names, slug, SEO fields, display order, supplier update time, sync time, and payload checksum.

`hbx_destination_zones` stores typed destination zones where HBX supplies them.

`hbx_hotels` stores supplier code, HBX hotel code, destination code, zone/country codes, hotel name, category/star signal, accommodation type, chain, coordinates, address, postal code, contact hints where supplied, supplier/public active flags, Arabic/English display names, slug, SEO fields, display order, supplier update time, sync time, and payload checksum.

`hbx_hotel_translations`, `hbx_hotel_images`, `hbx_hotel_facilities`, and `hbx_hotel_rooms` preserve typed hotel content used for rendering and filtering. Generic content-resource storage remains available for lower-use master resources but does not replace typed destination and hotel records.

`hbx_content_resources` stores generic official Content API master/descriptive resources such as boards, rooms, accommodations, categories, category groups, chains, facilities, facility groups, issues, languages, promotions, segments, image types, currencies, terminals, rate comments, and zones. The table preserves supplier codes, language, relationship hints, payload hash, sanitized JSON payload, last update time, active state, and sync timestamp.

`hbx_content_sync_batches` records each manual, scheduled, or queued content sync. It stores the resource, mode, country/destination filters, language, page limit, differential timestamp, checkpoint summary, processed/stored counts, dry-run flag, queue flag, safe status, and sanitized error message. It never stores API keys, signatures, supplier credentials, raw headers, or raw response bodies.

Upserts are idempotent. Records missing from later bounded syncs are deactivated rather than deleted.

## Mapping Workflow

`supplier_destination_mappings` remains available for future multi-supplier aggregation and legacy local city/area flows. Public HBX search no longer requires a mapping when the user selects a synchronized public HBX destination or hotel.

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

## Expanded Content Command

The newer command supports the Phase 14 API-suite sync shape:

```bash
php artisan hbx:content:sync --resource=countries --dry-run
php artisan hbx:content:sync --resource=destinations --country=EG --page-limit=1
php artisan hbx:content:sync --resource=destinations --country=EG --limit=100
php artisan hbx:content:sync --resource=hotels --country=EG --page-limit=1
php artisan hbx:content:sync --resource=hotels --from=1 --to=100
php artisan hbx:content:sync --resource=boards --country=EG --last-update-time=2026-06-01
php artisan hbx:content:sync --resource=all --country=EG --page-limit=1
php artisan hbx:content:sync --resource=all --full-authorized-portfolio --confirm --page-limit=1
php artisan hbx:content:sync --resource=hotels --country=EG --last-update-time=2026-06-01 --queue
php artisan hbx:content:status
php artisan hbx:content:enable-public --country=EG --dry-run
```

Full authorized portfolio mode is blocked unless both `--full-authorized-portfolio` and `--confirm` are provided. The command prints sanitized progress and never sends booking, modification, cancellation, or production requests.

Each run writes a content sync batch. `--queue` creates a pending batch and dispatches `HbxContentSyncJob`; the job reruns the same guarded command with the existing batch id. Admin users with supplier-view permission can inspect the batch list in Filament without seeing sensitive payloads.

Scheduled jobs are intentionally bounded:

- Daily hotel differential update for the configured public country.
- Weekly destination refresh for the configured public country.

The scheduler does not execute unless the normal Laravel scheduler cron is configured on the server.

## Public Search Flow

The browser autocomplete reads only from local synchronized content. It does not call HBX.

For HBX destinations, the browser submits an opaque local token such as `hbx_destination:{id}`. The server resolves:

1. Local destination token
2. Active public HBX destination row
3. Original HBX destination code
4. HBX availability payload using `destination.code`

For HBX hotels, the browser submits `hbx_hotel:{id}`. The server resolves the protected HBX hotel code internally and sends `hotels.hotel`.

The free-text name `Cairo` is never sent as the HBX availability identifier. Local IDs are never sent as supplier codes. Content API is never called during public autocomplete or search-page rendering. Real HBX searches do not silently fall back to Mock.

## Public Catalogue and SEO

Synchronized public records produce crawlable local pages:

- `/destinations/{slug}`
- `/hotels/{destination-slug}/{hotel-slug}`
- `/sitemap.xml`

These pages render from the local database only. They do not call the Content API, Booking API, CheckRate, booking, modification, or cancellation endpoints. Destination and hotel pages include canonical tags from the shared public layout and minimal structured data only for visible local content. Search-result parameter combinations remain separate from the crawlable static catalogue.

Static hotel pages do not advertise unavailable prices. Live prices are requested only after the user submits dates, guests, rooms, and currency through the public search flow.

## Read-Only Sandbox Verification

The first real read-only flow is:

```text
Content API destinations
-> typed local destination records
-> local autocomplete
-> Booking API Availability
-> normalized public search results
```

`php artisan hbx:verify-sandbox-booking --dry-run --destination={LOCAL_HBX_DESTINATION_ID}` verifies Availability only and works while `HBX_SANDBOX_BOOKING_ENABLED=false`. It does not send booking, modification, or cancellation requests. It sends CheckRate only when the selected rate explicitly requires recheck.

Supported selectors:

```bash
php artisan hbx:verify-sandbox-booking --dry-run --destination=7
php artisan hbx:verify-sandbox-booking --dry-run --hotel=123
```

The selector is a local database id. The command resolves the protected HBX destination or hotel code server-side and never trusts a raw browser-supplied HBX code.

## Error Categories

Sanitized logs and command messages distinguish:

- authentication or forbidden access
- rate limit
- supplier timeout or connection failure
- supplier server unavailable
- invalid request schema, endpoint, or API version
- empty catalogue page
- no Availability results
- unresolved local catalogue relationship

When the Content API returns no authorized data or a server error, the platform records the safe failure and does not fabricate destinations, hotels, rooms, images, or rates.

## Quota and Pagination

Sync commands are bounded by `--page-limit`, support `--dry-run`, and require explicit options. There is no uncontrolled full-world sync command.

## Manual Verification

The dry-run command requires synchronized and confirmed HBX content before it can search. It prints only supplier code, local destination, HBX destination code, number of hotel codes searched, dates, currency, availability count, CheckRate source, and dry-run completion.

No booking or cancellation request is sent by dry-run.
