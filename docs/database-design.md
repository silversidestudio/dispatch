# Database design

## Record lifecycle — 19 November 2026

Migration `2026_11_19_000001_add_archive_and_trash_support.php` adds soft-delete timestamps to contacts, mailing lists and campaigns, plus `archived_at` timestamps to lists and campaigns. Archive and trash views remain tenant-scoped. Deleted contacts retain their unique email reservation, eligibility evidence, memberships, suppression record and delivery history; restore does not grant eligibility. Mailing lists and campaigns with dependent audience or delivery history are protected from deletion. No automatic purge is scheduled.

The application uses Laravel's `mysql` connection. This workstation provides MariaDB 13; the foundation migration is exercised against that MySQL-compatible server. Native Oracle MySQL has not been exercised here. Fast default tests use isolated in-memory SQLite; the same suite can be run against the separate MySQL-compatible test database.

## Phase 1A tables

- `users`: identity, verified-email timestamp, password hash and non-mass-assignable platform administrator flag.
- `organisations`: name, indexed active/suspended status and timestamps.
- `organisation_user`: foreign keys, fixed role enum, timestamps, unique `(organisation_id,user_id)`.
- `organisation_invitations`: organisation foreign key, normalised invited email, non-owner role, unique token hash, expiry; unique `(organisation_id,email)`.
- `audit_logs`: nullable actor and organisation foreign keys, action, JSON metadata and creation timestamp; `(organisation_id,created_at)` index. Deleting a referenced actor/organisation nulls its reference rather than erasing the action.
- Laravel infrastructure: password reset tokens, database sessions, cache/locks, jobs, batches and failed jobs.

Four migrations are present: standard users/authentication infrastructure, cache, jobs, and `2026_10_21_000001_create_organisation_foundation`. Membership and invitation foreign keys cascade on organisation deletion. No organisation deletion route exists. Membership uniqueness is enforced in the database. Status checks are enforced by policies; only platform status controls can write status. Organisation names are not unique identifiers.

## Seeder

`DatabaseSeeder` is local/testing-only and repeatable. It creates `Acorn Studio · Demo` and a viewer at `viewer@example.test` with a random undisclosed password. It creates no known login credential or administrator. Browser tests create separately labelled workspaces and ephemeral-password users.

## Later milestones

Contacts, eligibility history, lists, imports and suppressions are Phase 1B; templates/campaigns Phase 1C; recipients, attempts and events Phase 1D. Every future tenant-owned table requires an indexed organisation foreign key; linked records must be checked for matching organisation, ideally with composite constraints where practical. Contact email normalisation and uniqueness, append-only eligibility evidence, minimal suppression retention, event partitioning/retention and idempotency constraints must be designed with those features, not inferred from stored contact presence.


## Phase 1B additive schema

2026_10_23_000001 adds account status, company website/postal address and owner invitations. 2026_10_23_000002 creates contacts, eligibility_records, suppressions, mailing_lists, contact_mailing_list, segments, contact_imports, import_rows, sending_identities, email_templates, campaigns, campaign_recipients, delivery_attempts and delivery_events.

Contacts have company/email uniqueness (including archived records), status/eligibility indexes and JSON custom attributes. Suppressions have independent company/email uniqueness and survive list/contact editing. Evidence references actor/company/contact and is append-only via application routes. Lists use unique contact/list membership. Imports use unique import/row-number results. Campaign audience references are structured list/segment type+ID, with same-company checks before access and snapshot. Recipient uniqueness is campaign/contact; attempts are unique per recipient/attempt-number; events use a unique deterministic event key. Delivery events have company/time indexes. Identity verification remains unverified with separate not-checked DNS fields. All personal data queries are tenant-scoped in controllers/services, including queue context.

Migration `2026_11_26_000001_add_mailing_list_to_contact_imports.php` records the selected company mailing list and resumable byte/row cursors for contact imports. New imports require an active list and an explicit final confirmation; each queued job processes up to 250 rows so larger files do not occupy one worker for the entire import. Both contact and suppression imports default to 25,000 data rows per file, configurable with `DISPATCH_IMPORT_MAX_ROWS` (the previous `DISPATCH_CONTACT_IMPORT_MAX_ROWS` remains a fallback). Uploads remain capped at 5 MB and 30 columns. Rejected and previously committed rows remain individually recorded; retry resumes from the saved cursor.

Development migrations are additive only; no reset or development seeder was run during Phase 1B. MariaDB integration tests use dispatch_test, never dispatch_dev. Raw import files, attempts/events and audit history currently require an operational retention policy before production use. Database administrators are trusted; application-level tenant checks do not protect against direct privileged database writes.

## Phase 1C additive migration

`2026_10_26_000001_add_provider_delivery.php` adds organisation tracking controls/review evidence; identity provider-domain ID, authorisation and verification time; campaign immutable-mode inputs, tracking flags, live confirmation time and paused origin; recipient delivery status/time; attempt provider, unique nullable UUID correlation, started/finished time, HTTP and safe diagnostic code; event provider, nullable attempt foreign key, provider timestamp/message ID and sanitised URL label.

New `webhook_batches`: unique SHA256 digest, encrypted normalised events longText, indexed status and timestamps. New `webhook_receipts`: unique event-key digest, nullable attempt foreign key, status/reason and timestamps. No tenant attribution is accepted from a payload; the matched attempt/recipient/campaign relationships determine scope. Unmatched receipts intentionally have no tenant assignment.

Indexes cover attempt outcome/start recovery, event campaign/provider/type/time and recipient/type/time, recipient delivery status, batch status and unique receipt/correlation keys. Existing recipient/contact/campaign constraints and foreign keys remain. No data reset or reseed occurred; the dev migration is additive. Only isolated test databases use RefreshDatabase. At larger volumes analytics need aggregate SQL/materialised summaries and event retention/partition planning; current indexed schema is a foundation, not a throughput claim.

Encrypted webhook envelopes require the retained APP_KEY. Engagement pruning intentionally changes retained-history metrics; receipts preserve deduplication. Audited contact erasure redacts completed recipient personal data as an explicit privacy exception to normal snapshot immutability, while keeping minimal tenant suppression and audit identifiers. Operational backup retention/deletion and key escrow must be approved before launch.

## Email editor refinement

Additive migration `2026_10_26_000002_add_email_branding_to_organisations.php` adds nullable JSON email_branding with validated logo URL, primary/secondary/button colours and footer information. Company name/postal address reuse existing fields. Owner/admin policy protects updates. Values are applied only when creating a new versioned starter; no existing template or campaign snapshot is updated. SQLite/MariaDB migration and snapshot-preservation tests are in EmailEditorTest.php.

## Restricted pilot ledger — 11 November 2026

Migration 2026_11_11_000001 adds sendgrid_pilot_budgets (unique UTC day, reserved count) and sendgrid_pilot_reservations (budget foreign key, globally unique correlation hash). All companies share the conservative live-attempt budget; reservations contain no recipient PII and persist across cache flush/restart. Replay reservations are retained; do not reset them to obtain quota. Existing business data and snapshots are unchanged.

## In-app notifications — 16 November 2026

Migration `2026_11_16_000001_create_in_app_notifications.php` adds Laravel database notifications with a per-user unique event key and inbox lookup index. Current notices cover campaign completion/failure and contact import completion/failure. Their payload stores a title, short outcome, organisation ID and target record ID; it contains no message body, recipient address or provider secret. Opening checks that the signed-in user is still a member of the company and that the target record belongs to that company. The unique event key prevents duplicate notices on job retries. Notices go to the campaign creator/scheduler or import uploader while that user remains a member.
