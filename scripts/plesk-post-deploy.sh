#!/usr/bin/env bash
set -Eeuo pipefail

# Configure this as the Plesk Git post-deployment action. The script installs
# locked dependencies, builds frontend assets, applies pending migrations,
# refreshes Laravel caches, and signals Horizon to restart.
APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PHP_BIN="${DISPATCH_PHP_BIN:-/opt/plesk/php/8.3/bin/php}"
COMPOSER_BIN="${DISPATCH_COMPOSER_BIN:-composer}"

if [[ ! -x "$PHP_BIN" ]]; then
    echo "PHP CLI not found or not executable: $PHP_BIN" >&2
    echo "Set DISPATCH_PHP_BIN to the PHP CLI configured for this Plesk site." >&2
    exit 1
fi

if [[ ! -f "$APP_DIR/artisan" || ! -f "$APP_DIR/composer.json" || ! -f "$APP_DIR/composer.lock" ]]; then
    echo "This does not look like a Laravel release with a Composer lockfile: $APP_DIR" >&2
    exit 1
fi

if ! command -v "$COMPOSER_BIN" >/dev/null 2>&1; then
    echo "Composer was not found: $COMPOSER_BIN" >&2
    echo "Install Composer for the subscription user or set DISPATCH_COMPOSER_BIN." >&2
    exit 1
fi

cd "$APP_DIR"

# Show the branded maintenance page while dependencies/assets and database
# migrations are updated. If an update fails, keep maintenance mode enabled so
# an operator can inspect the release before reopening the site.
MAINTENANCE_ACTIVE=0
restore_application() {
    local deploy_status=$?
    trap - EXIT
    if [[ "$MAINTENANCE_ACTIVE" == "1" ]]; then
        if [[ "$deploy_status" -ne 0 ]]; then
            echo "Deployment failed; the site remains in maintenance mode. Review the release, then run artisan up when it is safe." >&2
        fi
    fi
    exit "$deploy_status"
}
trap restore_application EXIT
"$PHP_BIN" artisan down --render=errors::503 --retry=30
MAINTENANCE_ACTIVE=1

"$COMPOSER_BIN" install --no-dev --prefer-dist --no-interaction --optimize-autoloader

# Plesk may run deploy actions in a non-interactive shell. Initialise nodenv
# explicitly and select the version pinned in the repository.
if [[ -n "${NODENV_ROOT:-}" ]]; then
    export PATH="$NODENV_ROOT/bin:$NODENV_ROOT/shims:$PATH"
elif [[ -x "$HOME/.nodenv/bin/nodenv" ]]; then
    export NODENV_ROOT="$HOME/.nodenv"
    export PATH="$NODENV_ROOT/bin:$NODENV_ROOT/shims:$PATH"
fi

if command -v nodenv >/dev/null 2>&1; then
    eval "$(nodenv init - bash)"
fi

if [[ ! -f "$APP_DIR/.node-version" ]]; then
    echo "Node version file is missing: $APP_DIR/.node-version" >&2
    exit 1
fi

NODE_VERSION="$(tr -d '[:space:]' < "$APP_DIR/.node-version")"
if [[ -z "$NODE_VERSION" ]]; then
    echo "Node version file is empty: $APP_DIR/.node-version" >&2
    exit 1
fi
export NODENV_VERSION="$NODE_VERSION"

if ! command -v node >/dev/null 2>&1 || ! command -v npm >/dev/null 2>&1; then
    echo "Node.js or npm was not found for the Plesk subscription user." >&2
    echo "Ensure Node $NODE_VERSION is installed and available to the Git deployment action." >&2
    exit 1
fi

NODE_ACTUAL="$(node --version)"
if [[ "$NODE_ACTUAL" != "v$NODE_VERSION" && "$NODE_ACTUAL" != "v$NODE_VERSION."* ]]; then
    echo "Expected Node $NODE_VERSION from .node-version, but found $NODE_ACTUAL." >&2
    exit 1
fi

npm ci --no-audit --no-fund
npm run build

if [[ ! -f "$APP_DIR/public/build/manifest.json" ]]; then
    echo "Frontend build did not produce public/build/manifest.json" >&2
    exit 1
fi

if [[ ! -f "$APP_DIR/vendor/autoload.php" ]]; then
    echo "Composer install did not produce vendor/autoload.php" >&2
    exit 1
fi

"$PHP_BIN" artisan migrate --force
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan route:cache
"$PHP_BIN" artisan view:cache
"$PHP_BIN" artisan horizon:terminate

"$PHP_BIN" artisan up
MAINTENANCE_ACTIVE=0
trap - EXIT

echo "Post-deployment tasks completed for $APP_DIR"
