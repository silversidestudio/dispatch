# Dispatch Phase 1C handover

26 October 2026. **Locally implemented and tested; not production ready and not yet cleared for an external pilot.** No live credentials, DNS modification, push, deployment, real SendGrid sandbox request or email transmission occurred. Existing development accounts, organisations and passwords were preserved; the database was not reset or reseeded.

## Implementation and Git

Branch: `feature/phase-1c-sendgrid-production`, based on clean Phase 1B `9f1cc07` (following `810d67d`, `0218fea`, `b1b231d`).

- `f63d50f` — gated SendGrid delivery and durable uncertain submission handling.
- `215b2d5` — signed feedback, analytics, controlled campaign workflows, privacy and production safeguards.
- Final documentation commit — this handover, readiness matrix and operational runbooks; resolve its hash with `git log -1 --oneline` after checkout. Documentation cannot embed its own final commit hash.

Code and documentation are committed locally; no remote push. Final working-tree state is checked after the documentation commit. Ignored runtime data, `.env`, logs and browser screenshots are intentionally not committed.

C.1–C.4 are implemented for local review. C.5 supplies local hardening/tests and deployment preparation; external infrastructure/security/client acceptance and live verification remain incomplete.

## Delivery and feedback

SendGridDeliveryProvider implements the typed v3 Mail Send adapter, with explicit simulation/sandbox/live separation, secure environment-only credentials, sender/readiness checks, durable UUID reservations, provider ID/HTTP response persistence, shared throttling, bounded deferred retries and conservative uncertainty handling. A worker restart does not replay accepted/reserved/uncertain attempts. There is no general exactly-once delivery guarantee after provider acknowledgement loss; safe evidenced reconciliation is essential. Pause/resume/cancel retain evidence and cannot recall an in-flight HTTP request.

SendGridWebhookController verifies ECDSA signatures over exact raw body/timestamp, bounds payloads, encrypts normalised envelopes and queues ProcessSendGridWebhook. WebhookService deduplicates, correlates attempts tenant-safely, quarantines unmatched feedback, prevents terminal-status regressions and suppresses hard bounces, complaints and unsubscribes. Full original URLs, IPs, user agents and arbitrary diagnostic bodies are not stored. Default retention is 14 days for completed envelopes and 90 days for engagement; client approval is outstanding.

Sending identity controls expose authenticated-domain status/readiness and explicit platform authorisation without private campaign/contact access. Actual SendGrid verification is not performed; SPF/DKIM provider evidence and DMARC not-checked are distinguished. Authentication verification/reset notifications have a separate gated API channel, mocked only; normal local notifications remain log transport. Invitation emails are not automated; invitation links are shared securely by an authorised user.

See [SendGrid setup](sendgrid-setup.md) for every environment variable, API/domain/webhook setup, Cloudflare instructions, rate/retry limits and acknowledgement ambiguity. All live/auth/provider-enabled gates remain false in local configuration; default provider is simulation.

## Database changes

One additive migration: `2026_10_26_000001_add_provider_delivery.php`, applied as dev batch 4. Adds organisation tracking review controls; identity provider authorisation; campaign mode/tracking/live confirmation/pause origin; recipient delivery status; attempt correlation/lifecycle/HTTP diagnostics; event provider/time/attempt/URL metadata. New tables webhook_batches (encrypted payloads, unique digest, status) and webhook_receipts (unique event digest, attempt FK, quarantine status/reason). Indexes support recovery and campaign/recipient event timelines. Existing recipient uniqueness and tenant foreign keys remain. Detailed schema: database-design.md. Up migrations were exercised by SQLite and MariaDB test suites; destructive down/restore/production rollout has not been verified.

## Analytics and usability

Campaign analytics show original/eligible/excluded/suppressed audience, pending recipients, initiated submission attempts, unique accepted/processed/delivered/deferred/bounced/blocked/dropped recipients, complaints/unsubscribes and separate unique/total tracked opens/clicks. Uncertain outcomes are visible. Recipient history shows ordered events, safe SMTP/HTTP diagnostics, attempt IDs and first/last engagement times. Search, event/date filters, CSV export and basic campaign comparison are available. Reporting and dashboards distinguish delivery modes; no synthetic open/click/revenue estimates are shown as real activity.

Definitions: delivery and bounce rates divide unique event recipients by unique provider-accepted recipients; opens/clicks/unsubscribes divide unique corresponding recipients by delivered recipients; click-to-open divides unique clicking recipients by unique tracked opening recipients. N/A means zero denominator, disabled tracking or no real mode. Signed downstream evidence can establish acceptance without a saved HTTP acknowledgement. Local unsubscribe alone is not provider acceptance. Submission count is initiated attempt reservations excluding cancelled/confirmed-not-submitted guards, not proof of receipt; unique accepted count is separate. Date ranges filter provider event time (UTC), attempts by started time; audience counts remain snapshot totals. Per-category outcomes can overlap historically. Missing/late feedback can produce incomplete rates; retained tracking data expires and historical engagement can decrease after pruning. No metric proves inbox placement or human engagement.

The five original starters are business announcement, newsletter, product/service promotion, event invitation and simple professional email. Existing Tiptap gains alignment while preserving text/link/image/button/divider editing, sanitisation, personalisation and plain text. Desktop/mobile browser previews work. Gmail/Outlook and non-technical client usability have not been externally tested.

Campaign review exposes sender, postal address, audience/exclusions, tracking and warnings; live scheduling requires SEND confirmation. A single-recipient test form creates a separate simulation/sandbox draft for an existing eligible contact with tracking off, without submitting anything. Review/pre-flight/explicit scheduling remain mandatory. No arbitrary-address or live-mode shortcut exists in this test preparation endpoint.

## Unsubscribe, privacy and security

Visible GET is confirmation only; secure POST applies suppression. RFC8058 one-click uses separate opaque signed/encrypted tokens and sessionless CSRF-exempt POST; GET does nothing. Headers and tracking exclusion are generated. Actual public endpoint compatibility and DKIM coverage of BOTH headers remain unverified. SendGrid subscription tracking is disabled to avoid competing systems; real provider suppression reconciliation requires pilot evidence.

Owner/admin privacy export and explicit ERASE redact personal contact/evidence/completed recipient data, remove matching raw uploads and retain minimal suppression. Active campaign/import guards apply. This is an audited privacy exception to ordinary recipient personal-data immutability; downstream provider/backup deletion is an operational responsibility.

ProductionSafety enforces HTTPS/debug/secure-cookie configuration, HSTS/nosniff, same-origin referrers and authenticated private no-store responses. Secrets never enter frontend props. Published dependency audits are clean. See phase-1c-security-review.md for limitations: no independent penetration test, multi-worker kill/restart stress test, public abuse load test or target-host log/proxy audit. Analytics are appropriate for small pilot volumes; current per-campaign in-memory event aggregation/N+1 recipient summaries need scale profiling before large sends.

## Verification executed

All commands run from the repository root. No real email/provider HTTP was used by tests. The MariaDB command targets dispatch_test only.

| Exact command | Result |
|---|---|
| `php artisan test --compact` | 106 passed,451 assertions, 3.46s final run |
| `DB_CONNECTION=mysql DB_DATABASE=dispatch_test php artisan test --compact` | 106 passed,451 assertions, 8.01s final run |
| `npx playwright test` | 3 Chromium journeys passed, 48.9s final run |
| `npx tsc --noEmit` | exit 0 |
| `npx eslint resources/js` | exit 0 |
| `npm run build` | exit 0; Vite 6.4.5, 2095 modules, 2.29s final run |
| `vendor/bin/pint` | applied formatting |
| `vendor/bin/pint --test` | passed |
| `npm audit --audit-level=low` | 0 vulnerabilities |
| `composer audit --format=summary` | No vulnerability advisories |
| `git diff --check` | exit 0 |
| `php artisan migrate:status` | all 7 migrations ran,Phase 1C batch 4 |
| `php artisan schedule:list` | tick/recovery every minute, pruning daily registered |
| `php artisan horizon:status` | running locally |
| `php artisan queue:failed` | no failed jobs at inspection |
| `php artisan route:list --path=one-click` | GET and POST registered separately |
| `curl --silent --output /dev/null --write-out '%{http_code}\n' http://127.0.0.1:8012/login` | HTTP 200 |
| `curl --silent --output /dev/null --write-out '%{http_code}\n' http://127.0.0.1:8013/login` | HTTP 200 |

Browser journeys cover foundation registration/onboarding/switching/themes/mobile navigation/platform, company-owner-team/import/list/template/campaign/simulation/report flow including platform content denial and test-draft preparation, and MOCK provider analytics/timeline/comparison/export/sessionless one-click flow. Screenshots are in ignored test-results/. The analytics screenshot was visually inspected. Intermediate failures: initial no-referrer header broke back redirects, fixed with same-origin policy plus explicit import redirect; new analytics test needed to await login/dashboard. Final rerun passed all three. Earlier implementation syntax issues were corrected before passing the full suites. No outstanding FAIL result is hidden as PASS.

Not run: actual SendGrid sandbox/live, domain validation, provider-origin webhook, real account notification, Gmail/Outlook rendering, client unaided usability, sustained concurrency/fault/load/security assessment, production deploy, backup restoration or rollback drill. Automated fixtures are not real-integration proof.

## Local access and first client

Local UI: http://127.0.0.1:8012. Network-preview listener: http://127.0.0.1:8013 on this Mac, bound 0.0.0.0; another device must use this Mac's current LAN address and port 8013. Processes are foreground development sessions and not production supervision. MariaDB 13307, Redis 16380 and Horizon are local. Scheduler registration was inspected; sustained scheduler operation remains unverified.

The existing admin@example.test account remains a Platform Administrator; password was not changed or documented. Use the login password-reset workflow (local link in restricted logs) if needed. A fresh administrator registers/gets verified and is promoted by `php artisan platform:promote their-address@example.test`; local-only `development:verify` is available during development. Never put real passwords into Git or this handover.

First company setup:

1. Platform Administrator creates company and assigns an existing verified owner (or secure invitation flow). Confirm account/company active.
2. Owner logs into the same application, switches workspace, enters postal address and company details, invites team and assigns roles. Platform role alone does not confer private workspace access.
3. Create/import contacts; imported records remain unknown/ineligible. Record explicit supporting eligibility evidence separately, then create list/segment. Never infer permission from a CSV.
4. Create an identity. In local simulation it is clearly unverified. Actual provider authorisation requires the separately approved external setup in sendgrid-setup.md.
5. Choose a starter, edit subject/content/plain text/footer, preview desktop/mobile and review audience. Prepare a one-contact simulation test and schedule explicitly. Inspect resulting events/report.
6. Leave provider/live/auth/tracking gates off until the corresponding external/client approvals and acceptance checks are completed.

## Deployment, launch readiness and pilot

Required infrastructure and release, supervision, scheduler, HTTPS/proxy, logging, monitoring, backup/restore and rollback procedures are in production-deployment.md. None has been executed against a production host.

Production-readiness.md assesses every one of 132 criteria: **87 PASS (local evidence only), 0 FAIL, 35 BLOCKED, 9 NOT TESTED, 1 NOT IMPLEMENTED**. The missing implementation is configured external monitoring/alerting. Critical blockers include production-like HTTPS infrastructure, approved SendGrid credentials/domain/public callbacks, actual provider tests, protected backups and restore drill, concurrency/security/operational validation, email-client rendering and client legal/privacy/usability acceptance. All 19 controlled live pilot criteria remain BLOCKED.

Next: resolve these mandatory gaps, then request explicit authorisation for the small pilot procedure in controlled-pilot.md. It specifies sandbox first, one approved real recipient, signed feedback reconciliation, rendering/DKIM/unsubscribe/bounce/tracking checks, duplicate/queue review, stop conditions and client sign-off. Wider sending requires a separate approval after pilot results. Do not infer approval from this handover or the passing mocked tests.
