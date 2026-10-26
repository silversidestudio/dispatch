# Dispatch — email marketing platform

Standalone Phase 1C implementation, locally tested with mocked SendGrid responses. Laravel 12, PHP 8.3+, React 19, Inertia 2, TypeScript, Tailwind 4, Radix/shadcn components, MySQL-compatible database, Redis and Horizon.

**Not production ready. Live sending remains disabled.** SendGrid v3 integration, signed feedback, analytics and controlled delivery modes are implemented, but no external provider verification, deployment or live pilot has occurred. Simulation is the default. See [launch readiness](docs/production-readiness.md) before any production action.

Start with [development setup](docs/development-setup.md). See [architecture](docs/architecture.md), [database design](docs/database-design.md), [implementation plan](docs/implementation-plan.md) and [Phase 1A handover](docs/phase-1a-handover.md).

Phase 1C: [handover](docs/phase-1c-handover.md), [SendGrid setup](docs/sendgrid-setup.md), [deployment runbook](docs/production-deployment.md), [pilot plan](docs/controlled-pilot.md), [security review](docs/phase-1c-security-review.md).

## Quick start

```sh
composer install
npm ci
cp .env.example .env # fresh clones only; never replace an existing .env
php artisan key:generate
# Configure your dedicated MySQL database/user and Redis connection in .env.
php artisan migrate # existing installations: never reset or reseed development data
npm run build
php artisan serve --host=127.0.0.1 --port=8012
```

Run `php artisan horizon` and `php artisan schedule:work` in separate terminals. Register your own account in the browser. Verification and reset messages are written to `storage/logs/laravel.log`; these logs are sensitive and ignored by Git. Alternatively verify locally with `php artisan development:verify your-address@example.test`. Promote an existing account with `php artisan platform:promote your-address@example.test`. Passwords are chosen in the registration form, never seeded or committed.

See [Phase 1B handover](docs/phase-1b-handover.md), [contact eligibility](docs/contact-eligibility.md), [campaign lifecycle](docs/campaign-lifecycle.md), [simulation](docs/simulated-delivery.md) and [editor](docs/email-editor.md).

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

Email Studio refinement: [handover and screenshots](docs/email-editor-refinement-handover.md). Includes responsive icon tools, editable CTA/images, five versioned starter designs, organisation branding and shared desktop/mobile/plain-text previews. Existing templates/snapshots and closed live-delivery gates are preserved.
