# Cairo Cool Travel

Cairo Cool Travel is a Laravel hotel booking platform planned for Egypt first, with future expansion to Saudi Arabia and the UAE.

This repository is currently in Phase 5: Supplier Integration Foundation and Mock Supplier only.

## Current Scope

- Laravel foundation installed in the repository root.
- MySQL 8 configuration baseline.
- Redis configuration baseline for cache, queues, and locks, with local fallbacks.
- Arabic as the default locale.
- English as the fallback locale.
- `Africa/Cairo` as the application timezone.
- EGP as the default business currency.
- Supported currencies configured: EGP, USD, EUR, SAR, AED, GBP.
- Pest test foundation.
- Laravel Pint formatting.
- Standard Laravel Vite frontend foundation.
- Filament admin panel at `/admin`.
- Spatie Laravel Permission roles and permissions foundation.
- Admin user management with active/inactive status and preferred locale.
- Read-only roles and permissions matrix.
- Core reference data for countries, cities, areas, currencies, exchange rates, and facilities.
- Manual exchange-rate management foundation and decimal-safe conversion service.
- Bilingual Arabic/English facility translations.
- Canonical internal hotel catalog foundation.
- Hotel translations, facilities, contacts, image metadata, and hotel policies.
- Protected Filament hotel content management.
- Supplier integration foundation for mock, REST, JSON, XML, SOAP, and OTA XML provider types.
- Secure supplier credentials, sanitized operation logs, correlation IDs, idempotency records, transport abstractions, and deterministic Mock Supplier.

## Out of Scope

The following are intentionally not implemented yet:

- Customer-facing hotel search website
- Real supplier adapters or integrations
- Supplier hotel mappings
- Room types, room inventory, or rate plans
- Booking flow
- Payments
- Quotations
- B2B or B2B2C features
- Real API credentials
- Deployment automation

## Admin Documentation

See [docs/admin-foundation.md](docs/admin-foundation.md) for admin setup, roles, permissions, and first super admin instructions.

See [docs/core-reference-data.md](docs/core-reference-data.md) for reference-data entities, seeders, permissions, and currency behavior.

See [docs/hotel-catalog.md](docs/hotel-catalog.md) for hotel catalog entities, publication rules, permissions, and media metadata behavior.

See [docs/supplier-integration.md](docs/supplier-integration.md), [docs/supplier-contract.md](docs/supplier-contract.md), [docs/mock-supplier.md](docs/mock-supplier.md), and [docs/supplier-security.md](docs/supplier-security.md) for the Phase 5 supplier foundation.

## Local Documentation

See [docs/local-development.md](docs/local-development.md) for local setup notes.

See [docs/implementation-plan.md](docs/implementation-plan.md) for the phased project plan.
