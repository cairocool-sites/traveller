# Cairo Cool Travel

Cairo Cool Travel is a Laravel hotel booking platform planned for Egypt first, with future expansion to Saudi Arabia and the UAE.

This repository is currently in Phase 2: Admin Foundation only.

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

## Out of Scope

The following are intentionally not implemented yet:

- Hotel search, content, or inventory features
- Supplier adapters or integrations
- Booking flow
- Payments
- Quotations
- B2B or B2B2C features
- Real API credentials
- Deployment automation

## Admin Documentation

See [docs/admin-foundation.md](docs/admin-foundation.md) for admin setup, roles, permissions, and first super admin instructions.

## Local Documentation

See [docs/local-development.md](docs/local-development.md) for local setup notes.

See [docs/implementation-plan.md](docs/implementation-plan.md) for the phased project plan.
