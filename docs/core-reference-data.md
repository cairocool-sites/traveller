# Core Reference Data

Phase 3 adds the reference data foundation for future hotel and booking modules. It does not add hotel records, supplier mappings, search, bookings, payments, quotations, agencies, B2B, or external API integrations.

## Entities

- `countries`: ISO country metadata, bilingual names, nationality labels, phone code, currency code, active flag, and sort order.
- `cities`: city records belonging to countries, bilingual names, optional code, coordinates, timezone, featured flag, active flag, and sort order.
- `areas`: optional city districts belonging to cities, bilingual names, optional coordinates, active flag, and sort order.
- `currencies`: canonical currencies with uppercase code, symbol, decimal places, optional rounding increment, active flag, and base-currency flag.
- `exchange_rates`: manual exchange rates from one currency to another with high-precision decimal rate, source, effective window, active flag, and optional creator.
- `facilities`: canonical stable facility codes with controlled categories.
- `facility_translations`: Arabic and English facility names and optional descriptions.

## Relationships

- A country has many cities.
- A city belongs to one country and has many areas.
- An area belongs to one city.
- A currency can be the base or quote currency for many exchange rates.
- A facility has Arabic and English translations.

Reference-data deletes are intentionally restricted in the database where removal could orphan future business records. Facilities cascade only to their own translations.

## Currency Rules

Currency codes are stored uppercase. USD is seeded as the single active base currency and default payable currency. EGP remains supported for approximate local display estimates when an active USD to EGP exchange rate exists. The application prevents disabling a base currency when no other active base currency exists.

Money amounts are not stored in Phase 3 booking/payment tables because those modules do not exist yet. Exchange rates use `decimal(20, 10)`. Conversion accepts and returns decimal strings to avoid binary floating point drift.

Rounding uses half-up behavior to the quote currency decimal places. Initial currencies use two decimal places except where explicitly configured later.

## Exchange Rates

Exchange rates are manual-only in this phase. No external exchange-rate APIs are integrated and no fake live rates are seeded.

The latest-rate resolver selects the newest active exchange rate where:

- base currency matches,
- quote currency matches,
- `effective_at` is at or before the requested time,
- `expires_at` is empty or after the requested time.

Missing rates throw an explicit exception. The application does not silently invent exchange rates.

## Seed Data

Countries:

- Egypt
- Saudi Arabia
- United Arab Emirates
- Turkey
- United Kingdom
- United States

Cities:

- Egypt: Cairo, Giza, Alexandria, Hurghada, Sharm El Sheikh, Luxor, Aswan
- Saudi Arabia: Makkah, Madinah, Riyadh, Jeddah
- United Arab Emirates: Dubai, Abu Dhabi, Sharjah, Ras Al Khaimah
- Turkey: Istanbul, Antalya

Currencies:

- EGP, USD, EUR, SAR, AED, GBP

Facilities:

- wifi
- parking
- swimming_pool
- restaurant
- breakfast
- airport_transfer
- air_conditioning
- family_rooms
- fitness_center
- spa
- accessible_rooms
- business_center

## Facility Categories

Controlled categories:

- general
- room
- food
- wellness
- business
- accessibility
- transport
- family

## Permissions

Phase 3 adds:

- `view_countries`, `manage_countries`
- `view_cities`, `manage_cities`
- `view_areas`, `manage_areas`
- `view_currencies`, `manage_currencies`
- `view_exchange_rates`, `manage_exchange_rates`
- `view_facilities`, `manage_facilities`

Allocation:

- `super_admin`: all permissions
- `general_manager`: all permissions
- `operations_admin`: view/manage countries, cities, and areas
- `accountant`: view currencies, view/manage exchange rates
- `content_manager`: view/manage countries, cities, areas, and facilities
- `auditor`: view-only reference-data permissions

Other roles keep admin access only unless future phases justify more.

## Filament Navigation

Reference data appears in the admin panel under the `Reference Data` navigation group:

- Countries
- Cities
- Areas
- Currencies
- Exchange Rates
- Facilities

All resources use policies and Spatie permissions. Destructive bulk actions are not enabled.

## Seeder Commands

Run all seeders:

```bash
php artisan migrate --seed
```

Run only reference data:

```bash
php artisan db:seed --class=CoreReferenceDataSeeder
```

The seeders are deterministic and safe to rerun. They use stable codes and do not delete existing production records.

## Testing

```bash
php artisan test
vendor/bin/pint --test
npm run build
php artisan route:list --path=admin
```

## Known Limitations

- No hotel, supplier, booking, payment, quotation, agency, B2B, or API implementation exists yet.
- Exchange rates are manual only.
- No exchange rates are seeded by default.
- Areas are optional and are not pre-seeded.
- Facility translations are limited to Arabic and English.
