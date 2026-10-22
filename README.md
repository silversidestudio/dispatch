# Dispatch — email marketing platform

Standalone Phase 1A development foundation. Laravel 12, PHP 8.3+, React 19, Inertia 2, TypeScript, Tailwind 4, Radix/shadcn components, MySQL-compatible database, Redis and Horizon.

**Local development only. No external email delivery is implemented or enabled.** Mail transports are restricted to local logging and in-memory testing. Contacts, campaigns and simulated delivery belong to later milestones.

Start with [development setup](docs/development-setup.md). See [architecture](docs/architecture.md), [database design](docs/database-design.md), [implementation plan](docs/implementation-plan.md) and [Phase 1A handover](docs/phase-1a-handover.md).

## Quick start

```sh
composer install
npm ci
cp .env.example .env # fresh clones only; never replace an existing .env
php artisan key:generate
# Configure your dedicated MySQL database/user and Redis connection in .env.
php artisan migrate --seed
npm run build
php artisan serve --host=127.0.0.1 --port=8012
```

Run `php artisan horizon` in another terminal. Register your own account in the browser. Verification and reset messages are written to `storage/logs/laravel.log`; these logs are sensitive and ignored by Git. Alternatively verify locally with `php artisan development:verify your-address@example.test`. Promote an existing account with `php artisan platform:promote your-address@example.test`. Passwords are chosen in the registration form, never seeded or committed.

## Verification

```sh
php artisan test
npx tsc --noEmit
npm run build
vendor/bin/pint --test
npx eslint resources/js
npm audit --audit-level=low
composer audit --format=summary
npx playwright test
```

Browser tests require the development server, database, Redis and Chromium (`npx playwright install chromium`). They create clearly named browser demonstration accounts and workspaces in the local database. Do not run them against shared or production data. The test passwords are generated at runtime and never printed.
