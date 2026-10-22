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
