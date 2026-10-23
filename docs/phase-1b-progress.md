# Phase 1B progress

Expanded scope authorised: company/user administration, contacts and audiences, campaigns/editor and simulation. Base: 810d67d; branch feature/phase-1b-core-features. Baseline: 39 tests / 156 assertions pass. Development data must not be reset or reseeded.

Milestones: (1) company administration, (2) contacts/lists/segments/imports and eligibility, (3) identities/templates/campaigns, (4) simulated delivery/unsubscribe/reporting, (5) integrated verification and handover.

## Company administration

Implemented account suspension enforcement, company creation/profile, ownership invitations, membership controls and audited explicit ownership transfer. Final owner and final active platform admin safeguards use transactional locks. Platform does not bypass tenant content policies. Verification pending below.

## Contact and audience milestone

Tenant-scoped contact CRUD/history/search/filter/pagination and bulk archive/suppression, evidence records, central eligibility, lists/membership history and structured dynamic segments are implemented. CSV upload/preview/mapping, Redis job processing, per-row idempotency/results and formula-safe error exports are implemented. Contact regression tests pass (5 scenarios / 28 assertions).

## Campaign and simulation milestone

Implemented sender identities (unverified), original templates and Tiptap editor, server HTML sanitisation, campaign draft/readiness/scheduling/snapshot/cancellation/duplication, deterministic simulation adapter, queue pump, recipient jobs, durable attempt/event keys, unsubscribe and stored reporting. Seven campaign regression scenarios / 40 assertions pass. Initial browser testing caught unsupported sanitizer configuration and an account-deletion session-ordering regression; both were corrected. Full final acceptance results are recorded in the handover once completed.

## Final integrated verification

Implementation commits: 0218fea (company administration), b1b231d (functional contact/audience/campaign/simulation core). Final PHP suite passes on SQLite and MariaDB: 69 tests / 317 assertions each. Both Chromium journeys pass; final run 46.8 seconds includes actual Redis/Horizon CSV import and delayed retry completion. TypeScript, ESLint and production build pass. npm reports 0 vulnerabilities; Composer reports no advisories. All six migrations applied; Horizon and recovery scheduler are running. LAN preview at 192.168.0.22:8013 responds HTTP 200. Original admin account remains active. No development database reset/reseed, external mail, deployment, push or DNS change occurred. See phase-1b-handover.md for details and limits.
