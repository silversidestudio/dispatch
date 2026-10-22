# Phase 1A handover

## Delivered

Standalone Laravel 12 / React / Inertia application named Dispatch. Authentication includes verification/reset/profile/password flows. Organisation creation/switching, memberships with four roles, owner-created expiring invitations, member role changes and server policies are implemented. Platform super administrators have a separate organisation overview/status area, recent users, configuration summary, audit entries and protected Horizon queue monitor. The responsive shell includes account controls, workspace selection, breadcrumbs, search entry point, notification empty state and persistent light/dark/system themes.

Dashboard membership/role/audit information comes from the database. Campaign/delivery areas are explicit empty states for later phases. Authentication emails remain local logs; all external mail transports are disabled in configuration and at application boot.

## Major files

- app/Models/{User,Organisation,AuditLog}.php and app/Policies/OrganisationPolicy.php.
- app/Http/Controllers/{DashboardController,OrganisationController,PlatformController}.php.
- app/Providers/{AppServiceProvider,HorizonServiceProvider}.php and config/{mail,horizon}.php.
- routes/{web,auth,console}.php; local verification and administrator promotion commands.
- resources/js/pages/{dashboard,organisations,organisation-settings,platform,accept-invitation}.tsx.
- resources/js/components/{workspace,app-sidebar,app-sidebar-header,app-logo}.tsx; shared shadcn/Radix primitives.
- tests/Feature/FoundationTest.php and tests/browser/foundation.spec.ts; inherited authentication/settings tests.
- README and docs architecture/database/setup/implementation/handover files.

## Migrations

Standard Laravel users/reset/session, cache and job migrations plus `2026_10_21_000001_create_organisation_foundation` (organisations, membership, invitations, audit and platform flag). Migrations and repeatable local seeders run against the isolated project database.

## Verification

Final validation passed: 39 PHP tests / 156 assertions on SQLite and again on the isolated MySQL-compatible test database; one Chromium end-to-end journey; TypeScript, ESLint, Pint and Vite build; npm and Composer audits with no reported vulnerabilities. All four migrations are applied, the seeder reruns successfully, and Horizon reports running. The complete command list is in development-setup.md. The local URL is http://127.0.0.1:8012 while the server is running. Services use separate ports (database 13307, Redis 16380).

## First administrator

Register your own account, choose a password, then use `php artisan development:verify your-address@example.test` and `php artisan platform:promote your-address@example.test`. There is no committed administrator password. The seeded viewer and browser-generated accounts have undisclosed random passwords and are demonstration data only.

## Known limitations

- Local development only. No live provider or real email transmission, and no implementation beyond Phase 1A.
- Local database server is MariaDB via Laravel's mysql driver; Oracle MySQL validation remains outstanding.
- Campaign/contacts charts intentionally have no fabricated data; future milestones introduce those database entities.
- Platform users are a recent-account overview with CLI elevation; full user lifecycle tools, sensitive setting editors and comprehensive authentication audit logging remain future work.
- Ownership transfer/member removal are not implemented; owners cannot delete their accounts through the profile screen.
- Invitations use local link sharing, not external email. No notification persistence/global search index yet; workspace search is functional.
- No production security, compliance or infrastructure certification is claimed. Future contact eligibility requires current UK legal guidance and recorded evidence.

## Next step

Review Phase 1A locally. Phase 1B requires explicit approval and should begin with tenant-scoped contacts, eligibility evidence and suppression enforcement before CSV imports.

## Exact final verification commands

```sh
php artisan migrate:status
php artisan horizon:status
php artisan db:seed --no-interaction
php artisan test
DB_CONNECTION=mysql DB_DATABASE=dispatch_test php artisan test
npx tsc --noEmit
npm run build
npx playwright test
npx eslint resources/js
vendor/bin/pint --test
npm audit --audit-level=low
composer audit --format=summary
```

Screenshots from browser validation are local ignored files: test-results/dashboard-light.png, dashboard-dark.png, dashboard-mobile.png and mobile-navigation.png. Desktop/mobile output was visually reviewed. The browser journey covers registration, verification gating, two-workspace creation/switching, persistent dark theme, mobile navigation and the platform/Horizon route.

## Version control

Local branch: `feature/phase-1a-foundation`. The implementation commit includes source, migrations, tests, documentation and both lockfiles. A workstation-wide ignore rule excludes composer.lock by default; it is explicitly tracked for this standalone application's reproducible installs. No remote push or deployment was performed.
