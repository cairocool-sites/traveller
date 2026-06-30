# DirectAdmin Staging Deployment

Target domain:

```text
travel.cairocool.com
```

Recommended runtime:

- PHP CLI and web runtime: PHP 8.5
- Minimum supported PHP: PHP 8.4.1
- Database: MySQL 8 strict mode
- Web server: DirectAdmin with Nginx/Apache proxy support

Do not use `--ignore-platform-reqs`. The Composer lock includes Symfony packages that require PHP 8.4.1 or newer.

## Required PHP Extensions

Verify the same PHP binary used by web, Composer, cron, and queues has:

```text
bcmath
ctype
curl
dom
fileinfo
intl
json
mbstring
openssl
pdo
pdo_mysql
tokenizer
xml
zip
```

Example:

```bash
/usr/local/php85/bin/php -m
/usr/local/php85/bin/php -r 'echo PHP_VERSION, PHP_EOL;'
```

## Safe Document Root

Preferred project path:

```text
/home/admin/domains/travel.cairocool.com/public_html
```

Preferred document root:

```text
/home/admin/domains/travel.cairocool.com/public_html/public
```

The public web root must be Laravel's `public/` directory. Do not expose the project root because it contains `.env`, `vendor`, `storage`, `config`, `database`, `routes`, `composer.json`, and `artisan`.

If DirectAdmin cannot point the domain to `public/`, place the Laravel project outside the web root and deploy only a safe public entrypoint/symlink strategy. Do not rely on `.htaccess` alone when Nginx is involved.

## Git Ownership

If Git reports:

```text
fatal: detected dubious ownership in repository
```

Fix ownership and mark the intended path safe for the deploy user:

```bash
chown -R admin:admin /home/admin/domains/travel.cairocool.com/public_html
git config --global --add safe.directory /home/admin/domains/travel.cairocool.com/public_html
```

Do not run deployments permanently as `root`.

## Permissions

Use normal file permissions:

```bash
find . -type d -exec chmod 755 {} \;
find . -type f -exec chmod 644 {} \;
chmod -R 775 storage bootstrap/cache
chown -R admin:admin storage bootstrap/cache
```

Do not use global `777`.

## Staging Environment Defaults

Use staging-safe values:

```env
APP_ENV=staging
APP_DEBUG=false
APP_URL=https://travel.cairocool.com

SESSION_DRIVER=file
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
CACHE_STORE=file
QUEUE_CONNECTION=database

HBX_ENABLED=false
HBX_INTEGRATION_TESTS=false
HBX_SANDBOX_BOOKING_ENABLED=false
HBX_PRODUCTION_ENABLED=false
TRAVEL_PAYMENT_LIVE_ENABLED=false
TRAVEL_ACTUAL_SUPPLIER_CANCELLATION_ENABLED=false

TRAVEL_PUBLIC_SEARCH_SUPPLIERS=hbx_hotels
TRAVEL_BOOKING_SUBMISSION_MODE=manual_review
```

Redis variables may remain present but should stay inactive until Redis is configured and verified.

## Admin User Creation

Do not store permanent admin passwords in `.env`.

Create the first admin interactively:

```bash
/usr/local/php85/bin/php artisan make:filament-user
```

Then assign roles from the admin panel or seeders as appropriate.

## Deployment Commands

Use one PHP binary consistently:

```bash
/usr/local/php85/bin/php /usr/local/bin/composer install --no-dev --optimize-autoloader
/usr/local/php85/bin/php artisan optimize:clear
/usr/local/php85/bin/php artisan migrate --force
/usr/local/php85/bin/php artisan db:seed --force
/usr/local/php85/bin/php artisan config:cache
/usr/local/php85/bin/php artisan route:cache
/usr/local/php85/bin/php artisan view:cache
```

Scheduler example:

```cron
* * * * * cd /home/admin/domains/travel.cairocool.com/public_html && /usr/local/php85/bin/php artisan schedule:run >> /dev/null 2>&1
```

## Safe Deploy Script

The repository includes:

```text
scripts/deploy-staging.sh
```

Run it from the project root after reviewing `.env`:

```bash
PHP_BIN=/usr/local/php85/bin/php COMPOSER_BIN=/usr/local/bin/composer ./scripts/deploy-staging.sh
```

The script does not run `migrate:fresh`, does not overwrite `.env`, and refuses obvious HBX write flags.
