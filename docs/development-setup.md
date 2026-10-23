# Development setup

## Dependencies

PHP 8.3+ with PDO MySQL/SQLite, mbstring, XML, ctype, curl, fileinfo, OpenSSL and PCNTL/POSIX for Horizon; Composer 2; Node 22+; MySQL 8+ or compatible MariaDB; Redis. This session used PHP 8.3.9, Node 24.22, MariaDB 13.0.2 and Redis 8.10.2. Laravel 12 is locked by composer.lock; frontend dependencies are locked by package-lock.json.

Use `composer install` and `npm ci`. Never copy credentials from another project. For a fresh clone only, copy `.env.example` to `.env`, set database credentials, and run `php artisan key:generate`. Changing an established key invalidates encrypted session data; do not regenerate it routinely.

## Local configuration

- APP_NAME=Dispatch; APP_ENV=local; APP_URL=http://127.0.0.1:8012.
- DB_CONNECTION=mysql; DB_HOST=127.0.0.1; DB_DATABASE=dispatch_dev; use a dedicated least-privilege account/password.
- QUEUE_CONNECTION=redis; CACHE_STORE=redis; REDIS_CLIENT=predis.
- SESSION_DRIVER=database; SESSION_ENCRYPT=true.
- MAIL_MAILER is intentionally ineffective for external delivery: only log/array transports exist.
- APP_KEY and DB_PASSWORD are secrets. Keep `.env`, storage logs, `.local`, database files and test artifacts out of Git.

The current workstation uses a project-owned database at port 13307 and Redis at 16380, avoiding existing services. A random database password exists only in ignored `.env`. The dedicated application account has access only to dispatch_dev and dispatch_test. Database administration uses the local Unix socket account.

Start existing project-local services in separate terminals, from the repository root:

```sh
/opt/homebrew/opt/mariadb/bin/mariadbd --no-defaults --datadir="$PWD/.local/mysql" --socket="$PWD/.local/mysql.sock" --port=13307 --bind-address=127.0.0.1 --pid-file="$PWD/.local/mysql.pid"
redis-server --bind 127.0.0.1 --port 16380 --dir "$PWD/.local/redis" --dbfilename dispatch.rdb
php artisan serve --host=127.0.0.1 --port=8012
php artisan horizon
```

These data directories already exist on this workstation. A fresh clone should provision its own dedicated database/service and edit `.env`; the above commands do not initialise a fresh data directory. Stop each foreground process with Ctrl-C. Do not stop services belonging to other projects. The Redis instance accepts local connections only and has no password; this is development configuration.

## Application and first administrator

```sh
php artisan migrate --seed
npm run build
php artisan serve --host=127.0.0.1 --port=8012
```

Register at http://127.0.0.1:8012/register and choose your own password. Follow the signed verification link from storage/logs/laravel.log, or use the local-only shortcut:

```sh
php artisan development:verify your-address@example.test
php artisan platform:promote your-address@example.test
```

The promotion command requires an existing account and does not bypass email verification. Open `/platform` after verification. Create your organisation via `/organisations`; ownership belongs to its creator. Switch using the workspace selector. Owners can issue local invitation links from workspace settings; the invitee registers/verifies using the exact invited email and accepts the link. Password reset is available from login; its link is logged locally.

## Frontend development

Use `npm run dev` for Vite HMR, or `npm run build` for static assets. `npx tsc --noEmit` checks types. The appearance menu selects light/dark/system and persists the preference locally. Mobile navigation uses the Radix sidebar sheet. No external fonts or product assets are needed.

## Tests

```sh
php artisan test
DB_CONNECTION=mysql DB_DATABASE=dispatch_test php artisan test
npx tsc --noEmit
npm run build
vendor/bin/pint --test
npx eslint resources/js
npm audit --audit-level=low
composer audit --format=summary
npx playwright install chromium
npx playwright test
php artisan horizon:status
```

The default PHP suite uses SQLite :memory: and array sessions/cache. The MySQL command uses `.env` credentials with an explicit dispatch_test database. RefreshDatabase destroys test tables: never use the development or production database as the test database. Browser tests run against the local server, create labelled demonstration data and save ignored screenshots to test-results. They invoke local-only verification and CLI promotion for their generated account.

## Operational limits

No deployment, real SMTP connection, domain verification, contacts, campaigns or live sending is configured. HTTPS, production secret management, backups, retention, monitoring and deployment controls require a future operational review. Logs contain verification/reset links and must remain private.

## Phase 1B operation

Existing installations run `php artisan migrate` only: do not reset or reseed development data. Phase 1B introduces real Redis jobs for CSV imports and deterministic local delivery simulation. Keep `php artisan horizon` and `php artisan schedule:work` running. `php artisan dispatch:tick` performs one recovery tick manually. Redis retry_after defaults to 180 seconds; Horizon timeout is 150 seconds, with import-job timeout 120 seconds and per-recipient timeout 60 seconds. Keep worker timeouts below retry_after when adapting infrastructure.

The LAN preview uses port 8013; browser tests use port 8012. Both can run against the same preserved local database. Authentication email remains in local logs; invitation links are shared securely from the UI. See phase-1b-handover.md for the review walkthrough, contact-eligibility.md for permission evidence, campaign-lifecycle.md for snapshots, and simulated-delivery.md for retry/recovery definitions.
