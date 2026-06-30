# Local Development

## Requirements

- PHP 8.4.1 or newer. PHP 8.5 is recommended.
- Composer 2
- Node.js and npm
- MySQL 8
- Redis recommended

This workspace was bootstrapped with portable local tools because PHP, Composer, Node.js, and npm were not available in PATH. Git was installed at `C:\Program Files\Git`, but also was not available in PATH.

## Environment

Copy `.env.example` to `.env` for local development and fill in only local, non-shared values.

Do not commit `.env`, passwords, API keys, supplier credentials, payment credentials, or production secrets.

Important defaults:

- `APP_LOCALE=ar`
- `APP_FALLBACK_LOCALE=en`
- `APP_TIMEZONE=Africa/Cairo`
- `DB_CONNECTION=mysql`
- `TRAVEL_DEFAULT_CURRENCY=USD`

## Database

Create a MySQL 8 database before running migrations:

```bash
mysql -u root -p
CREATE DATABASE cairo_cool_travel CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Then set `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` in `.env`.

## Redis And Local Fallbacks

The application is configured for Redis-first cache and queue usage:

- `CACHE_STORE=redis_failover`
- `QUEUE_CONNECTION=redis_failover`

The cache fallback is file-based. The queue fallback is database, then sync.

Sessions default to `file` for safe local development. In production, use Redis sessions after Redis is available:

```dotenv
SESSION_DRIVER=redis
SESSION_STORE=redis
```

## Common Commands

Use the PHP, Composer, Node.js, and npm binaries available in your environment:

```bash
composer install
npm install
php artisan key:generate
php artisan migrate
php artisan db:seed --class=AdminFoundationSeeder
php artisan db:seed --class=CoreReferenceDataSeeder
npm run dev
php artisan serve
```

To create the first local super admin, set `ADMIN_NAME`, `ADMIN_EMAIL`, and a strong `ADMIN_PASSWORD` in `.env`, then run:

```bash
php artisan migrate --seed
```

The admin panel is available at `/admin`.

Hotel catalog management is available under `/admin/hotels` for users with hotel permissions.

Supplier management is available under `/admin/suppliers` for users with supplier permissions. Supplier operation logs are available under the Supplier Management navigation group.

Public hotel search is available at `/` and `/hotels`. Search results prefer the active `hbx_hotels` sandbox supplier when configured, then fall back to `mock_hotels`; normalized offers are stored in short-lived `search_sessions`.

Phase 7 booking flow is available from a hotel details page by choosing Check rate, entering guest details, and submitting the deterministic Mock Supplier booking. Phase 13 allows HBX Sandbox booking only when `HBX_SANDBOX_BOOKING_ENABLED=true`, the supplier is active, credentials are configured locally, and the base URL is exactly `https://api.test.hotelbeds.com`. HBX production booking and cancellation submission remain blocked. No online payment gateway, customer account, quotation, or B2B feature is connected.

Phase 8 manual payment flow is available from confirmed booking pages. Evidence is stored privately on the local disk. Seeded payment methods use safe placeholder account details only.

Phase 9 cancellation flow is available from confirmed booking pages. Refunds are manual tracking records only and do not call banks, cards, gateways, or real suppliers.

Phase 10 operational checks can be run locally with:

```bash
php artisan app:check-environment
php artisan ops:scheduler-heartbeat
php artisan ops:cleanup --dry-run
```

Local health endpoints are available at `/health/live` and `/health/ready`. They are intentionally minimal and do not expose environment values, paths, credentials, or versions.

Reference data can be seeded with:

```bash
php artisan db:seed --class=CoreReferenceDataSeeder
```

No fake hotels are seeded by default.

The supplier seeder creates the deterministic `mock_hotels` sandbox supplier and an `hbx_hotels` sandbox supplier shell. It does not seed usernames, passwords, tokens, API keys, or production connections.

Search limits can be adjusted with safe local values such as `TRAVEL_SEARCH_MAX_ROOMS`, `TRAVEL_SEARCH_MAX_ADULTS_PER_ROOM`, `TRAVEL_SEARCH_MAX_CHILDREN_PER_ROOM`, `TRAVEL_SEARCH_MAX_CHILD_AGE`, `TRAVEL_SEARCH_MAX_STAY_NIGHTS`, and `TRAVEL_SEARCH_SESSION_LIFETIME_MINUTES`.

Stale draft/rate-confirmed booking records can be expired locally with:

```bash
php artisan bookings:expire-drafts
```

The scheduler heartbeat is registered for Laravel's scheduler. A production server must run `php artisan schedule:run` every minute through cron; local development can run `php artisan ops:scheduler-heartbeat` manually.

PHP XML extensions required for future production integrations include `dom`, `libxml`, `SimpleXML`, `xmlreader`, and `xmlwriter`. PHP SOAP is not installed in the current local toolchain and is only scaffolded in Phase 5.

Run tests:

```bash
php artisan test
```

Run formatting:

```bash
vendor/bin/pint
```
