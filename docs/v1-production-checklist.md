# V1 readiness checklist — `dispatch.silverside.co`

This checklist is for the current single-client test installation at **`https://dispatch.silverside.co`**. The client’s own server is a later installation step. The application remains branded **Dispatch** by default; the client can use **Campaign Manager** through the configured application name.

## Current position

The operator reports that the latest previously deployed branch is running on the staging server, system email SMTP has been corrected, and a restricted SendGrid test campaign reached the four specified inboxes. Earlier staging checks also reported working Redis/Horizon and scheduler tasks, company SendGrid configuration, domain authentication, and a webhook test request returning HTTP 202. These are operator-reported observations; this workspace cannot inspect the Plesk server or independently confirm them.

This work adds clearer interface wording and persistent in-app notifications for campaign completion/failure and contact import completion/failure. **These uncommitted local changes have not been deployed to staging.** Once reviewed and released, the notification migration must be run on staging before the new bell behavior is available there.

## Work we can complete in the project

- [x] Review instructional and status text across the main navigation, contacts, lists, imports, campaigns, templates, sending setup, reports, account pages and platform administration; make wording shorter, neutral and action-oriented.
- [x] Keep the exact staging address `dispatch.silverside.co` in release notes and setup guidance.
- [x] Add persistent notifications for campaign outcomes and contact import outcomes. Notifications identify simulation, SendGrid test or SendGrid processing, and never describe server acceptance as inbox delivery.
- [x] Restrict each notification to the campaign creator/scheduler or import uploader who is still a member of that company. Opening a notification rechecks membership and company ownership before showing results.
- [x] Add idempotency so a retried queue job does not create duplicate completion notices.
- [x] Run the complete PHP, TypeScript, lint, production-build and Playwright checks on these exact changes; record the results below.
- [ ] Review the final copy and notification behavior in desktop and mobile browsers, including light and dark themes.
- [ ] Recheck security-sensitive flows: company isolation, role access, SMTP credential handling, SendGrid credential redaction, webhook signature/correlation, imports, sanitised email HTML, unsubscribe, suppression, live-send confirmation, pilot limits, pause/cancel and uncertain submissions.
- [ ] Reconcile the readiness document with current evidence after staging tests. Keep old reports clearly marked historical.
- [ ] Keep client campaign styling/content work until last, then recreate and approve the client’s actual template on mobile and desktop.
- [ ] Commit and push only after local review and checks. Deploying the changes requires a separate staging release and migration.

## Items needing Richard/operator help on staging

- [ ] Confirm the released code is deployed at **`https://dispatch.silverside.co`** (not `.co.uk`), the intended branch/commit is shown, the app loads over HTTPS and `APP_DEBUG=false`.
- [ ] Run the new database migration on staging, clear/rebuild Laravel configuration as required, rebuild frontend assets, restart Horizon and confirm the scheduler continues to run.
- [ ] Sign in as a normal company user and confirm notification behavior after a completed simulation, an authorised SendGrid test, an import, and a failed/rejected operation. Check each notice opens the correct results and another company cannot access it.
- [ ] Confirm account emails reach the intended mailbox: invitation, verification and password reset. The application’s account email SMTP is separate from each company’s campaign SendGrid credentials.
- [ ] Verify Plesk operations persist after a server restart: Horizon service, Redis, scheduler, failed-job visibility, disk space, log rotation and queue health.
- [ ] Confirm protected backups are running and complete a restore rehearsal to an isolated database/files location. Confirm rollback steps before widening campaign use.
- [ ] Review staging logs and monitoring for exposed credentials, personal data, failed webhooks, stalled queues and mail errors.
- [ ] Run SendGrid feedback checks on the public staging endpoint: a signed event is accepted and correlated to the right company/recipient; duplicate and delayed events do not corrupt results; an approved test bounce, complaint handling and unsubscribe create the intended suppressions. A `202` webhook response only confirms request acceptance, not completed event processing.
- [ ] Review sender, reply-to, postal address, sending domain and SPF/DKIM/DMARC alignment for the client account. Keep tracking disabled unless the client has approved the relevant privacy notice and settings.
- [ ] Authorise a new, small test audience and fixed daily cap before any additional live email. Confirm audience, content, sender and recipients explicitly for that test. No send is authorised by this checklist.
- [ ] Have the client complete a short, observed workflow: sign in, find/import contacts, choose an audience, create or edit a basic email, preview, review, schedule and find results. Capture only genuine friction and make targeted fixes.
- [ ] Obtain the client’s final confirmation of contact source/eligibility, sender identity, company postal address, unsubscribe wording, privacy/tracking choice and campaign content before their first campaign.

## Client-server handover after staging is accepted

- [ ] Collect the client host’s PHP/database/Redis details, domain and HTTPS setup, database credentials, SMTP account mail settings and encrypted backup plan.
- [ ] Install the reviewed release and required PHP/Node build artifacts on the client’s server; configure a unique app key, production environment and least-privilege database user.
- [ ] Set the product title in the client `.env`/configuration to **Campaign Manager**; retain **Dispatch** on `dispatch.silverside.co`.
- [ ] Configure the client’s company-specific SendGrid credentials, authenticated sending domain, sender, HTTPS event webhook and verification key. Do not place campaign API keys in shared platform mail settings.
- [ ] Configure Redis/Horizon, scheduler, log/monitoring, backups and restore procedure; apply migrations and verify queue recovery before live delivery.
- [ ] Re-run invitation, password recovery, import, simulation, webhook, unsubscribe and restricted pilot checks on the client host.
- [ ] Obtain explicit approval for the first actual campaign’s sender, audience, content and volume. Start with a small authorised pilot, check provider events and suppression behavior, and only then discuss a wider send.

## Protections that stay enabled

Do not remove tenant isolation, role checks, contact eligibility/suppression enforcement, authenticated webhooks, duplicate/replay protection, live-send confirmation or uncertain-submission handling. Pilot recipient lists and daily limits may be adjusted deliberately for an approved test; they are not a substitute for the normal eligibility and unsubscribe protections.

CSV import can set an unsuppressed contact to the workspace’s configured `csv_import` eligibility state. That records where the address came from; it does **not** verify that marketing permission exists. The client must confirm their list source and permission before sending.

## Verification record

Fill these from actual execution; do not infer staging or live results from local tests.

| Check | Status / evidence |
| --- | --- |
| PHP feature suite on local changes | **PASS** — `php artisan test --compact`: 133 passed, 725 assertions. |
| PHP formatting | **PASS** — Pint passed on the modified PHP files. |
| TypeScript | **PASS** — `npx tsc --noEmit`. |
| ESLint | **PASS** — `npx eslint resources/js`. |
| Production frontend build | **PASS** — `npm run build`. |
| Browser acceptance tests | **PASS** — `PLAYWRIGHT_BASE_URL=http://127.0.0.1:8014 npx playwright test`: 6 passed. Ran on a temporary loopback server with synchronous local simulation; no SendGrid calls or email transmission. The temporary server was stopped after the run. |
| Notification database migration | **PASS locally** — additive migration applied to `dispatch_dev`; did not reset or reseed existing data. |
| Staging release and migration | Pending operator action |
| Staging notification walkthrough | Pending operator action |
| Staging SMTP account emails | Operator reports SMTP fixed; repeat verification pending |
| SendGrid signed event correlation / unsubscribe / bounce | Pending staging evidence |
| Client workflow acceptance | Pending client session |
| Client server deployment | Not started; requires later approval and client host details |
| Additional live campaign | Not authorised by this checklist |

