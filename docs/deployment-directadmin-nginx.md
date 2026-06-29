# DirectAdmin and Nginx Deployment Readiness

Phase 10 does not deploy the application. This document defines the expected production setup for a future VPS with DirectAdmin, Nginx, PHP-FPM, MySQL 8, Redis, queues, and the Laravel scheduler.

## Server Requirements

- PHP 8.3 or newer. Current local validation used PHP 8.5.
- Required PHP extensions: `ctype`, `curl`, `dom`, `fileinfo`, `filter`, `hash`, `intl`, `json`, `mbstring`, `openssl`, `pcre`, `PDO`, `pdo_mysql`, `session`, `tokenizer`, `xml`, `zip`.
- Optional extension: `soap` only when a real SOAP supplier is enabled.
- MySQL 8 with `utf8mb4`.
- Redis for production cache, sessions, queues, and locks.
- Nginx with HTTPS enabled.

## Document Root

The web root must point to Laravel `public/`. Never expose the repository root, `.env`, `storage/`, `vendor/`, or source files through Nginx.

Example Nginx location rules:

```nginx
root /home/account/domains/example.com/private_html/traveller/public;
index index.php;

location / {
    try_files $uri $uri/ /index.php?$query_string;
}

location ~ \.php$ {
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    fastcgi_pass unix:/run/php/php8.3-fpm.sock;
}

location ~ /\.(?!well-known).* {
    deny all;
}
```

## Security Headers

The application adds safe default headers. Nginx may also set:

```nginx
add_header X-Content-Type-Options nosniff always;
add_header Referrer-Policy strict-origin-when-cross-origin always;
add_header Permissions-Policy "camera=(), microphone=(), geolocation=()" always;
add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
```

Keep CSP report-only until Filament, Livewire, generated documents, and production assets are verified.

## Production Environment

Set `APP_ENV=production`, `APP_DEBUG=false`, HTTPS `APP_URL`, strong `APP_KEY`, MySQL credentials, Redis settings, and secure session cookies. Do not commit `.env`.

Use:

```bash
php artisan app:check-environment
```

## Cron and Queues

DirectAdmin cron must run every minute:

```cron
* * * * * php /path/to/artisan schedule:run
```

Run queue workers under Supervisor or DirectAdmin-compatible process monitoring. Restart workers after every deployment:

```bash
php artisan queue:restart
```

## Storage and Permissions

Run `php artisan storage:link` only after verifying public uploads are intended. Private payment evidence and generated document snapshots must remain private. Ensure `storage/` and `bootstrap/cache/` are writable by the PHP-FPM user.

## Uploads and Timeouts

Configure PHP-FPM and Nginx upload limits for payment evidence and future supplier payloads. Supplier APIs may need longer outbound timeouts, but customer-facing requests should remain bounded.

## Deployment Flow

1. Confirm a fresh backup exists.
2. Enable maintenance mode if the release needs it.
3. Pull the exact release.
4. Run `composer install --no-dev --prefer-dist --optimize-autoloader`.
5. Run `npm ci` and `npm run build`.
6. Run `php artisan migrate --force`.
7. Run config, route, event, and view caches.
8. Run `php artisan queue:restart`.
9. Run `php artisan app:check-environment`.
10. Check `/health/live` and `/health/ready`.
11. Disable maintenance mode.
12. Smoke test public search, booking status pages, admin login, and document verification.

No production server details, passwords, or API credentials are stored in this repository.
