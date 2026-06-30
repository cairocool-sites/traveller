# MySQL Fresh Installation Verification

SQLite tests are useful but do not detect every MySQL deployment problem. MySQL must be used before deploying to `travel.cairocool.com`.

## Local Or Server Verification

Use PHP 8.5 where possible:

```bash
/usr/local/php85/bin/php artisan optimize:clear
/usr/local/php85/bin/php artisan migrate:fresh --force
/usr/local/php85/bin/php artisan db:seed --force
/usr/local/php85/bin/php artisan migrate:status
```

Expected result:

- every migration completes;
- every migration in `migrate:status` shows `Ran`;
- seeders finish without duplicate keys;
- no supplier network requests are sent;
- no real credentials are required;
- `HBX_SANDBOX_BOOKING_ENABLED=false`;
- `HBX_PRODUCTION_ENABLED=false`.

## Docker Example

If the server database should not be reset, verify with a temporary MySQL 8 container:

```bash
docker run --name traveller-mysql-verify -e MYSQL_ROOT_PASSWORD=secret -e MYSQL_DATABASE=traveller_verify -p 3307:3306 -d mysql:8
```

Set a temporary `.env` or shell variables:

```bash
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3307
DB_DATABASE=traveller_verify
DB_USERNAME=root
DB_PASSWORD=secret
```

Then run:

```bash
php artisan migrate:fresh --force
php artisan db:seed --force
php artisan migrate:status
```

Remove the temporary container when done:

```bash
docker rm -f traveller-mysql-verify
```

## MySQL Compatibility Checks

The test suite includes static checks for:

- PHP syntax of all migration files;
- generated index and unique names over MySQL's 64-character identifier limit;
- unsafe non-null timestamp columns without defaults;
- duplicate `.env.example` keys;
- staging-safe `.env.example` defaults;
- deploy script safety.
