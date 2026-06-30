#!/usr/bin/env bash
set -Eeuo pipefail

PHP_BIN="${PHP_BIN:-/usr/local/php85/bin/php}"
COMPOSER_BIN="${COMPOSER_BIN:-/usr/local/bin/composer}"
NODE_BIN="${NODE_BIN:-npm}"
APP_DIR="${APP_DIR:-$(pwd)}"
REQUIRED_PHP="8.4.1"
REQUIRED_EXTENSIONS=(bcmath ctype curl dom fileinfo intl json mbstring openssl pdo pdo_mysql tokenizer xml zip)

cd "$APP_DIR"

if [ ! -x "$PHP_BIN" ]; then
    echo "PHP binary not found or not executable: $PHP_BIN" >&2
    exit 1
fi

PHP_VERSION="$("$PHP_BIN" -r 'echo PHP_VERSION;')"
"$PHP_BIN" -r "exit(version_compare(PHP_VERSION, '$REQUIRED_PHP', '>=') ? 0 : 1);" || {
    echo "PHP $REQUIRED_PHP or newer is required. Current: $PHP_VERSION" >&2
    exit 1
}

for extension in "${REQUIRED_EXTENSIONS[@]}"; do
    "$PHP_BIN" -m | grep -qi "^${extension}$" || {
        echo "Missing required PHP extension: $extension" >&2
        exit 1
    }
done

if [ ! -f .env ]; then
    echo ".env is missing. Create it manually on the server; this script will not generate or overwrite it." >&2
    exit 1
fi

if grep -q '^HBX_SANDBOX_BOOKING_ENABLED=true' .env || grep -q '^HBX_PRODUCTION_ENABLED=true' .env; then
    echo "Unsafe HBX write flag is enabled. Disable supplier writes before staging deployment." >&2
    exit 1
fi

"$PHP_BIN" "$COMPOSER_BIN" install --no-dev --optimize-autoloader --no-interaction --prefer-dist

if [ -f package-lock.json ]; then
    "$NODE_BIN" ci
    "$NODE_BIN" run build
fi

"$PHP_BIN" artisan down --render="errors::503" || true
"$PHP_BIN" artisan optimize:clear
"$PHP_BIN" artisan migrate --force
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan route:cache
"$PHP_BIN" artisan view:cache
"$PHP_BIN" artisan queue:restart || true
"$PHP_BIN" artisan up
"$PHP_BIN" artisan app:check-environment

echo "Staging deployment completed safely."
