# Local Development

## Requirements

- PHP 8.3 or newer
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
- `TRAVEL_DEFAULT_CURRENCY=EGP`

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

Reference data can be seeded with:

```bash
php artisan db:seed --class=CoreReferenceDataSeeder
```

No fake hotels are seeded by default.

The supplier seeder creates only the deterministic `mock_hotels` sandbox supplier. It does not seed real endpoints, usernames, passwords, tokens, API keys, or production connections.

PHP XML extensions required for future production integrations include `dom`, `libxml`, `SimpleXML`, `xmlreader`, and `xmlwriter`. PHP SOAP is not installed in the current local toolchain and is only scaffolded in Phase 5.

Run tests:

```bash
php artisan test
```

Run formatting:

```bash
vendor/bin/pint
```
