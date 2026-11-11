# Basic email and restricted pilot handover

11 November 2026. Branch: `feature/basic-email-pilot-preparation`, based on clean editor-refinement commit `5577c53`. This work extends the existing application; it does not rebuild it. Existing users, organisations, templates, credentials and campaign snapshots are preserved. The development database was not reset or reseeded. Browser journeys add clearly labelled fake-data accounts/workspaces.

## Delivered

- Font family (Arial, Verdana, Tahoma, Georgia, Times New Roman), text size, colour and underline alongside bold, italic and links. Inline styling survives save, sanitisation, rendering and reload; existing table/image content remains supported. Mobile uses two compact rows and a More menu for secondary actions, headings and variables.
- A separate **Basic email** starter: familiar text-led layout, personal greeting, optional reusable organisation signature, editable blue heading, body and required sender/address/unsubscribe footer. Five existing Studio designs remain. Create basic email always creates a new template; it never overwrites a user's saved content. New campaign drafts use the basic starter, while existing drafts/snapshots remain unchanged.
- Explicit Update from email action generates the plain-text alternative through the server's sanitised renderer, with confirmation before replacing manually edited text. The user saves the template/campaign normally afterwards.
- Clearer preview/review/schedule guidance, actual sender verification label, and explicit SEND confirmation shown only for live mode. Live pre-flight rejects unchanged basic demonstration text, alongside the existing eligibility, sender, footer, company and provider checks.
- Restricted live pilot guard: exact approved company IDs and recipient addresses, durable global UTC daily submission budget (default 25, permitted 1–100), replay protection, audited campaign pause on pilot scope/cap failure. The restriction is enabled by default, with blank scope that blocks live submission. Simulation and API sandbox remain distinct and do not consume the live budget. No key or live setting was enabled.
- Account notification SendGrid channel cannot bypass the restricted campaign pilot. Account notification delivery is deliberately blocked in this mode and must be separately approved/tested for launch; current local verification/reset messages use protected logs.
- Plesk Linux staging instructions, test evidence and clean screenshots. Network preview restored using the existing local MariaDB/Redis data and local Horizon/scheduler.

## Main code

UI: email-typography.tsx, email-editor.tsx, email-nodes.ts, plain-text-alternative.tsx, email-branding.tsx, email-preview.tsx, templates.tsx, template-editor.tsx, campaign-editor.tsx and campaign-detail.tsx under resources/js.

Backend: EmailStarterTemplates, CampaignService, ProviderReadiness, SendGridSubmissionService, SendGridAuthChannel, ContentController and CampaignController; new SendGridPilotGuard and PilotSubmissionBlocked; DeliveryMessage and SendGridDeliveryProvider retain the existing provider interface. Routes add tenant-authorised basic creation and rate-limited plain-text generation. No SendGrid webhook/provider architecture replacement occurred.

Migration: `2026_11_11_000001_create_sendgrid_pilot_budgets.php` adds `sendgrid_pilot_budgets` (unique calendar day, reserved count) and `sendgrid_pilot_reservations` (budget FK, unique SHA-256 correlation hash). No recipient address, API secret or message content is stored in these tables. Migration ran successfully against the existing development database; all other changes are additive application code.

## Pilot safety semantics

Configuration is server-only in config/dispatch.php and .env.example:

```dotenv
DISPATCH_PILOT_ENABLED=true
DISPATCH_PILOT_DAILY_LIMIT=25
DISPATCH_PILOT_RECIPIENTS=
DISPATCH_PILOT_ORGANISATION_IDS=
```

Lists are comma-separated exact addresses/positive numeric company IDs. No domain wildcards. All existing provider/live-enable flags, verified identity and explicit campaign authorisation remain mandatory. Merely configuring pilot scope never activates delivery. The adapter also enforces the guard immediately before external HTTP.

Budget reservations commit before HTTP and count even if SendGrid rejects the request or its outcome is uncertain. Retries get a new correlation and consume a new slot; replaying the same correlation is blocked, even on a later day. All companies share the application's daily cap. Cache clearing or worker restart cannot reset it. Unused capacity does not carry forward. SendGrid traffic from another application/account tool is outside this local cap. Do not delete/refund reservation rows to force sending. Budgets/hashed reservations are intentionally retained for replay protection; define long-term archival with delivery evidence retention before removing them.

An eight-process MariaDB exercise exposed a lock-upgrade race when budget creation and reservation shared a transaction. Budget creation now completes before the row-lock transaction. Both cap and duplicate-correlation scenarios pass. This exercises the budget mechanism, not a full production-scale queue load/restart drill.

## Verification

All tests use fake recipients; SendGrid HTTP tests use mocked responses. No real sandbox/live API request, message or DNS change occurred.

Commands executed:

```sh
php artisan test --compact
DB_CONNECTION=mysql DB_DATABASE=dispatch_test php artisan test --compact
APP_ENV=testing DB_CONNECTION=mysql DB_DATABASE=dispatch_test php tests/operations/pilot-concurrency.php
npx tsc --noEmit
npx eslint resources/js
npm run build
npx playwright test
vendor/bin/pint --test app/Delivery/DeliveryMessage.php app/Delivery/SendGridDeliveryProvider.php app/Http/Controllers/CampaignController.php app/Http/Controllers/ContentController.php app/Services/CampaignService.php app/Services/EmailStarterTemplates.php app/Services/ProviderReadiness.php app/Services/SendGridAuthChannel.php app/Services/SendGridSubmissionService.php config/dispatch.php routes/marketing.php tests/Feature/ProductionControlsTest.php tests/Feature/SendGridTest.php app/Exceptions/PilotSubmissionBlocked.php app/Services/SendGridPilotGuard.php database/migrations/2026_11_11_000001_create_sendgrid_pilot_budgets.php tests/Feature/BasicEmailPilotTest.php tests/operations/pilot-concurrency.php
git diff --check
composer audit --format=summary
npm audit --audit-level=low
npm audit --omit=dev --audit-level=low
```

PHP: **122 tests  / 603 assertions pass** on SQLite and isolated MariaDB. No development database reset: dispatch_test is the dedicated disposable integration database. Concurrency: 8 workers, cap 3 gives 3 reservations / 5 blocks; duplicate correlation gives 1 reservation / 7 blocks. Harness refuses databases other than dispatch_test in testing environment and cleans only its own fixtures. New BasicEmailPilotTest covers basic template preservation/permissions, typography, plain text, exact pilot scope, quota persistence, uncertain responses, pre-flight demo rejection, account-channel blocking and UTC rollover/replay.

Final full browser suite: **6 passed (1.0 minute)**. Two additional consecutive basic-editor journeys also pass. Browser review covers all six journeys: authentication/workspaces/themes/mobile navigation; platform company→owner→contacts/import→campaign simulation→report; advanced email editing/mobile preview; existing table/inline-style preservation; mocked SendGrid analytics/recipient timeline/one-click unsubscribe; and basic formatting/paste/save/reload/plain-text/mobile 320px/390px/dark mode. A flaky test initially pasted over its selected paragraph; the harness now waits for the visible font-size control to reflect the new caret before typing and asserts preservation. Two consecutive focused runs pass. This was not a change to pasted content policy. Screenshots are in docs/screenshots/basic-email-*.png. Browser evidence cannot certify Gmail/Outlook rendering or real SendGrid delivery.

Pint is scoped to changed PHP files; unrelated pre-existing formatting is preserved. Frontend checks/build pass. Full dependency audit findings and final results are recorded below; an initial audit failure was investigated rather than reported as passing.

## Security maintenance

Compatible Symfony patches resolve eight reported advisories: http-foundation/http-kernel/routing/yaml7.4.21→7.4.23 and mime7.4.19→7.4.22. Laravel is unchanged. npm compatible fixes update follow-redirects1.16.1→1.16.3 and resolve deepmerge's advisory using the resolver's supported4.1.2 version; TypeScript ESLint/tooling patch dependencies also update without a major upgrade. See lockfiles for exact versions.

After fixes Composer audit reports no advisories. Full npm audit still reports **7 moderate entries**, all propagated from the same minimatch glob amplification advisory through the TypeScript ESLint development-tool chain (minimatch10.2.6, typescript-eslint8.72.2). Compatible npm audit fix does not eliminate it. No force/major override was introduced. Do not lint untrusted repositories or accept user-supplied glob patterns into build tooling; use controlled build input and revisit the upstream fix before launch. The production-only npm audit reports zero vulnerabilities. Production dependency audit is recorded separately; this unresolved tooling finding does not constitute a clean full audit. [Upstream advisory](https://github.com/advisories/GHSA-g3hv-75wg-m249).

## Review limits and next steps

Local functionality is ready for your review and a production-like Plesk staging exercise. It is **not declared production-ready**. Read [plesk-staging-setup.md](plesk-staging-setup.md), sendgrid-setup.md, controlled-pilot.md and production-readiness.md. Outstanding: staging provisioning/HTTPS/security, worker/scheduler supervision, monitoring, protected backups and restore drill, actual SendGrid sandbox/API/domain/webhook evidence, authorised small live pilot, real Gmail/Outlook rendering, client usability sign-off, contact lawful-basis/privacy and tracking approval, and separately approved account email delivery. Tracking stays off by default.

Preview: http://192.168.0.22:8013/login on the same network, while this Mac and its local processes remain running. Existing administrator password is unchanged. This local PHP server is not staging hosting and may stop on sleep/reboot. Plesk will provide the persistent staging service once separately authorised/configured.

No deployment, remote push, DNS change, real credentials or real email transmission was performed. Implementation commit: `40a99f2`; dependency patches: `589a1d2`. Documentation/review commit follows these; `git log --oneline 5577c53..HEAD` lists the complete local sequence.
