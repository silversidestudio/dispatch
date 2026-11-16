# Campaign Manager client cutover

Use this with [the approved checklist](client-launch-checklist.md). This branch prepares the application and local verification. It does not configure the client's host or SendGrid account.

## Before moving

- Record the target HTTPS hostname, Plesk application directory and PHP CLI, site user, client organisation ID, and owners for DNS, SendGrid, backup and alerts.
- Decide whether to migrate existing data or use a fresh installation. For a migration, preserve the database, private storage, original APP_KEY (needed to decrypt stored credentials), SendGrid attempt history and suppressions. A fresh installation uses a newly generated APP_KEY and a new database. Do not copy a developer environment file or test contacts to a fresh client workspace.
- Stop new live submissions on the source before any state transfer. Pause live campaigns, disable the live gate, refresh the config and terminate/restart the old workers as appropriate. Review in-flight and uncertain attempts against provider evidence. Do not run two live schedulers/workers against copies of the same campaign history.
- Keep the client installation invite-only; use APP_NAME="Campaign Manager". Preserve the queue and cache namespaces of a moved installation, or assign separate ones on a shared server.

## Verify the destination (sending off)

1. Install the reviewed release and frontend assets. Check the public document root, private configuration and HTTPS. Run additive migrations, build the configuration cache, start the supervised Horizon worker and enable exactly one scheduler. Use the site's PHP binary and system user.
2. Run `php artisan dispatch:launch-check`. Resolve any CHECK rows. This checks cached settings and local connectivity; it does not prove external TLS, scheduler cadence, delivery or backup recovery. Run a dummy import and a scheduled simulation with the manual SSH session closed.
3. Verify a protected off-server backup and restore it into an isolated environment with egress/sending disabled. Record the backup timestamp, restored scope, time to recover and evidence that the database, private files and encrypted fields are readable. Keep recovery keys out of task logs and wide-access storage. Confirm how post-backup submissions and suppressions would be reconciled.
4. Set alerts for HTTPS liveness, scheduler last success, Horizon worker/queue delay, failed jobs, webhook errors and backup age. Trigger at least one controlled alert and confirm who receives and responds to it. Inspect web and app logs for secret or private link leakage.
5. Have a client test user verify an invitation, sign in, and exercise recovery. The log-based local flow requires operator handling now. Configure and test the [separate SMTP mailer](system-email.md) before changeover; campaign credentials do not send account mail.

## Verify feedback and engagement

- Use the client's authorised SendGrid account, verified sending domain and sender. Review From, Reply-to, postal address and authenticated alignment in received test headers. Use the company's HTTPS webhook endpoint and verification key. Confirm a signed sample returns 202. Samples are not evidence of campaign attribution.
- In SendGrid Event Webhooks, select Processed, Delivered, Deferred, Bounced, Dropped, Spam Report and Unsubscribe. Confirm the provider's blocked bounce classification is reflected in Dispatch. Select Opened and Clicked only after the client approves the tracking policy.
- In Workspace settings → Privacy and tracking review, record that decision and allow the approved types. On a **new** pilot campaign, select the same types. Company permission alone does not turn on campaign tracking. Previously scheduled snapshots are not rewritten. Leave tracking off elsewhere.
- Complete a sandbox validation first. Then approve the precise pilot sender, audience and attempt cap. On a restricted live campaign, open a copy and click an approved test link. Compare provider events with **Campaign analytics**, recipient activity and the event CSV. Delivery means receiving-server acceptance. Tracked opens and clicks are approximate and may include automated activity. Compare total and unique counts; some opens/clicks may be absent.
- Verify a visible unsubscribe and RFC 8058 one-click POST on approved test recipients. Check the suppression and confirm the recipient stays excluded after a repeat import. Use an approved bounce test method. Do not fabricate complaints.
- Exercise Pause, Resume and Cancel on staging and verify no new submission after the worker observes a pause or cancellation. In-flight provider requests cannot be recalled. Reconcile uncertain outcomes without automatic replay.
- Log the evidence: date, installation, commit, campaign/recipient IDs or redacted references, expected and observed outcomes. No keys, raw event payloads or private unsubscribe tokens.

## Client sign-off

Finish the approved editable Relaxa template last. Use the source material supplied privately, not a checked-in copy of the client's content. Review visual rendering and the plain text in Gmail and Outlook and obtain sign-off on the final text, links, sender identity and footer. Review contact sources and carry over suppression before importing the real audience. Test the workflow with the actual authorised user.

Confirm the first live campaign, sender, audience and volume explicitly. Monitor results with the client before any expansion. Keep the pilot restriction or keep live sending disabled until that approval. Rollback starts by stopping new submissions, preserving feedback and submission history and reconciling ambiguous attempts; do not restore an older sending ledger and immediately resume.

## Local verification of this release candidate

- `php artisan test --compact` — 130 passed, 703 assertions (SQLite).
- `DB_CONNECTION=mysql DB_DATABASE=dispatch_test php artisan test --compact` — 130 passed, 703 assertions on isolated MariaDB. Do not use a development, staging or production database for this command.
- `APP_ENV=testing DB_CONNECTION=mysql DB_DATABASE=dispatch_test php tests/operations/pilot-concurrency.php` — cap and duplicate reservation cases passed with eight workers.
- `npx playwright test` — all six Chromium journeys passed, including responsive navigation, simulation and mocked analytics. Browser analytics screenshot: `test-results/mock-sendgrid-analytics.png` (test fixture only).
- `npx tsc --noEmit`, `npx eslint resources/js`, `npm run build` and `git diff --check` — passed. Changed PHP files checked with Pint.
- Read-only launch check — all checks passed against local services using a temporary production-like configuration with live sending disabled. This was CLI verification only.
- `composer audit --format=summary` — no advisories after updating Laravel 12.70.1 to 12.72.0 (resolves CVE-2026-128083).
- `npm audit --omit=dev --audit-level=low` — no vulnerabilities. The full npm audit still reports seven moderate entries stemming from [one minimatch issue](https://github.com/advisories/GHSA-g3hv-75wg-m249) in the TypeScript ESLint development chain. Keep build/lint inputs controlled and review a supported fix before release. This is not an independent penetration test.

The staged four-inbox pilot and signed sample webhook are operator-reported evidence from the current server. No live provider calls, host modifications or real messages were made for this branch. Destination validation, client-approved tracking and permission to use the client list, authenticated real event correlation, Gmail/Outlook rendering, account recovery, alerts and backup recovery are still required.
