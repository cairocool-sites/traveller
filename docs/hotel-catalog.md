# Hotel Catalog Foundation

Phase 4 adds the canonical internal hotel catalog and content-management foundation. It does not add supplier APIs, supplier mappings, room types, rate plans, live availability, search, bookings, quotations, payments, agencies, B2B portals, or deployment.

## Database Design

Tables:

- `hotels`: canonical hotel identity, geography, contact basics, property type, status, active/featured flags, publication timestamp, audit users, and soft deletes.
- `hotel_translations`: Arabic and English translated content with a unique `hotel_id` and `locale` pair.
- `hotel_facility`: many-to-many relationship between hotels and canonical Phase 3 facilities.
- `hotel_contacts`: multiple operational contacts per hotel.
- `hotel_images`: metadata for stored hotel images.
- `hotel_policies`: canonical hotel-level policies. These are not bookable/rate-level cancellation rules.

## Relationships

A hotel belongs to:

- country
- city
- optional area
- optional default currency
- creator user
- updater user

A hotel has many:

- translations
- contacts
- images

A hotel belongs to many:

- facilities

A hotel has one:

- hotel policy record

Supplier hotel records must later map to canonical internal hotels. Supplier data must not overwrite canonical hotel content.

## Enums

Backed PHP enums are used for:

- `HotelStatus`: `draft`, `published`, `inactive`, `archived`
- `PropertyType`: `hotel`, `resort`, `apartment`, `aparthotel`, `villa`, `hostel`
- `HotelContactType`: `general`, `reservation`, `sales`, `finance`, `operations`, `emergency`
- `HotelImageType`: `exterior`, `lobby`, `room`, `restaurant`, `pool`, `facility`, `other`

Database columns are strings for portability and future expansion.

## Service Layer

`HotelCatalogService` owns transactional hotel operations:

- create and update canonical hotels
- sync Arabic and English translations
- sync hotel facilities
- validate country/city/area consistency
- maintain `created_by` and `updated_by`
- publish and unpublish hotels
- add image metadata
- enforce one primary image per hotel
- validate image metadata
- roll back the transaction when a later step fails

Filament resources call the service instead of holding core hotel rules directly.

## Publication Workflow

Statuses:

- `draft`
- `published`
- `inactive`
- `archived`

Publishing requires the `publish_hotels` permission.

Minimum publication requirements:

- country is selected
- city is selected
- selected city belongs to the selected country
- selected area, if any, belongs to the selected city
- canonical hotel name is present
- at least one Arabic or English translation has a translated name

An active image is not required in Phase 4 because image support is metadata and upload foundation only. This can be tightened in a later editorial workflow.

## Media Storage

Hotel images are stored through Laravel storage abstractions. The Filament relation manager uses the `public` disk and the `hotels` directory.

The repository must not commit uploaded images or generated media. Image metadata validates:

- MIME type: JPEG, PNG, or WebP
- max file size: 5 MB
- path must not contain traversal
- path must not be absolute

The client-provided filename is not trusted as a stable identifier.

## Permissions

Phase 4 adds:

- `view_hotels`
- `manage_hotels`
- `publish_hotels`
- `manage_hotel_media`
- `manage_hotel_facilities`
- `manage_hotel_policies`

Allocation:

- `super_admin`: all permissions
- `general_manager`: all permissions
- `operations_admin`: view/manage hotels, hotel facilities, and hotel policies
- `content_manager`: view/manage/publish hotels, media, facilities, and policies
- `auditor`: view hotels only

Accountants do not receive hotel-content permissions by default.

## Filament

Hotel catalog appears under `Hotel Management`.

The Hotel resource provides:

- searchable hotel list
- filters for geography, status, star rating, property type, active, featured, and publication state
- bilingual content fields
- facility multi-select
- contact relation manager
- image relation manager
- policy relation manager
- publish/unpublish actions guarded by permissions
- active/inactive action guarded by hotel management permission

Destructive bulk actions are not enabled.

## Local Seeding

No fake hotels are seeded by default. Hotel examples should only be added later as explicit local/test fixtures and must remain clearly fictional.

## Known Limitations

- No room types, room content, occupancy rules, or bed configuration yet.
- No supplier hotel mappings.
- No live availability, search, rates, bookings, payments, quotations, agencies, or B2B features.
- No editorial approval chains or scheduled publishing.
- No generated image variants or CDN integration.

## Phase 5

Phase 5 is expected to cover room types, room content, occupancy rules, bed configurations, and hotel-room relationships. It should still avoid live supplier inventory and bookings unless a later approved phase says otherwise.
