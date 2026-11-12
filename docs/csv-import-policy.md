# CSV import eligibility policy — 12 November 2026

At the operator’s request, each newly created, valid, unsuppressed CSV contact becomes eligible automatically. No extra CSV column, checkbox or individual evidence entry is required. This is an application sending policy, not a recorded claim of explicit consent or an automatic legal assessment.

The distinct `csv_import` state uses the central eligibility query, so dashboard/audience counts, scheduling and submission agree. An automatic eligibility record stores the importing user, source, import ID, row and processing time. Queued jobs attribute the record to the import user rather than relying on a worker login. Existing contacts (including duplicates, archived and unknown records) retain their status. Prior imports are not retroactively changed. Address edits continue to reset eligibility. Suppressions, unsubscribe, hard bounce, complaints and administrative blocks remain effective, including when a suppressed email is imported after its old profile was erased.

No database migration, data reset or real email operation is needed. The queue workers must be restarted when deploying this code. Local Horizon was refreshed. Existing live/sandbox/pilot gates remain unchanged.

Verification commands:

```sh
php artisan test --compact
DB_CONNECTION=mysql DB_DATABASE=dispatch_test php artisan test --compact
npx tsc --noEmit
npx eslint resources/js
npm run build
npx playwright test tests/browser/core-features.spec.ts
vendor/bin/pint --test app/Services/CsvImportService.php app/Services/EligibilityService.php app/Http/Controllers/ImportController.php tests/Feature/ContactsTest.php
git diff --check
```

PHP:123 tests/618 assertions pass on SQLite and isolated MariaDB. Frontend type/lint/build and changed-file Pint checks pass. Regression tests cover auto eligibility/provenance, duplicate preservation, retries without duplicate records, organisation-scoped suppression and subsequent unsubscribe. The updated browser journey passes (44.1 seconds): it imports contacts and proceeds to audience/campaign simulation without filling the evidence form. A transient test run overlapped asset rebuilding and could not find the Vite manifest; the completed-build rerun passes. The initial browser import used an old Horizon process; workers were refreshed before the final run. No development database was reset or reseeded.
