# Cairo Cool Travel

Cairo Cool Travel is a Laravel hotel booking platform planned for Egypt first, with future expansion to Saudi Arabia and the UAE.

This repository is currently in Phase 8: Manual Payments, Payment Review, Voucher, and Invoice only.

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
- Public Blade/Livewire hotel search and hotel-details foundation using the Mock Supplier only.
- Secure expiring search sessions with non-sequential public UUIDs and public result tokens.
- Public Check Rate, guest details, internal booking creation, Mock Supplier booking confirmation, and booking reconciliation foundations.
- Manual payment methods, customer payment submission, private evidence storage, admin payment review, printable vouchers, commercial invoices, receipts, and document verification tokens.

## Out of Scope

The following are intentionally not implemented yet:

- Real supplier adapters or integrations
- Supplier hotel mappings
- Room types, room inventory, or rate plans
- Real supplier booking integrations
- Real online payment gateways
- Payment webhooks
- Refunds
- Booking PDFs
- Customer cancellation workflow
- Quotations
- Customer accounts
- B2B or B2B2C features
- Real API credentials
- Deployment automation

## Admin Documentation

See [docs/admin-foundation.md](docs/admin-foundation.md) for admin setup, roles, permissions, and first super admin instructions.

See [docs/core-reference-data.md](docs/core-reference-data.md) for reference-data entities, seeders, permissions, and currency behavior.

See [docs/hotel-catalog.md](docs/hotel-catalog.md) for hotel catalog entities, publication rules, permissions, and media metadata behavior.

See [docs/supplier-integration.md](docs/supplier-integration.md), [docs/supplier-contract.md](docs/supplier-contract.md), [docs/mock-supplier.md](docs/mock-supplier.md), and [docs/supplier-security.md](docs/supplier-security.md) for the Phase 5 supplier foundation.

See [docs/hotel-search.md](docs/hotel-search.md) for the Phase 6 public search and hotel details foundation.

See [docs/booking-flow.md](docs/booking-flow.md) for the Phase 7 Check Rate, guest details, and booking creation foundation.

See [docs/manual-payments.md](docs/manual-payments.md) and [docs/documents.md](docs/documents.md) for the Phase 8 manual payment and document foundation.

## Local Documentation

See [docs/local-development.md](docs/local-development.md) for local setup notes.

See [docs/implementation-plan.md](docs/implementation-plan.md) for the phased project plan.
