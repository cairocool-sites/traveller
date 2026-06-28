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

Reference data can be seeded with:

```bash
php artisan db:seed --class=CoreReferenceDataSeeder
```

Run tests:

```bash
php artisan test
```

Run formatting:

```bash
vendor/bin/pint
```
