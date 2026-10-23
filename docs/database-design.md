# Database design

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

Development migrations are additive only; no reset or development seeder was run during Phase 1B. MariaDB integration tests use dispatch_test, never dispatch_dev. Raw import files, attempts/events and audit history currently require an operational retention policy before production use. Database administrators are trusted; application-level tenant checks do not protect against direct privileged database writes.
