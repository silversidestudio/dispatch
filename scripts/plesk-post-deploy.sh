#!/usr/bin/env bash
set -Eeuo pipefail

# Configure this as the Plesk Git post-deployment action. The script is safe to
# run on every release: Laravel only applies migrations that are still pending.
APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PHP_BIN="${DISPATCH_PHP_BIN:-/opt/plesk/php/8.3/bin/php}"

if [[ ! -x "$PHP_BIN" ]]; then
    echo "PHP CLI not found or not executable: $PHP_BIN" >&2
    echo "Set DISPATCH_PHP_BIN to the PHP CLI configured for this Plesk site." >&2
    exit 1
fi

if [[ ! -f "$APP_DIR/artisan" || ! -f "$APP_DIR/vendor/autoload.php" ]]; then
    echo "This does not look like an installed Laravel release: $APP_DIR" >&2
    exit 1
fi

if [[ ! -f "$APP_DIR/public/build/manifest.json" ]]; then
    echo "Frontend build is missing: public/build/manifest.json" >&2
    exit 1
fi

cd "$APP_DIR"

"$PHP_BIN" artisan migrate --force
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan route:cache
"$PHP_BIN" artisan view:cache
"$PHP_BIN" artisan horizon:terminate

echo "Post-deployment tasks completed for $APP_DIR"
