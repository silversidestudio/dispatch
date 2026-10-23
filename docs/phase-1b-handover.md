# Dispatch Phase 1B handover

## Outcome

Phase 1B extends the verified Phase 1A foundation into a working local multi-company marketing demonstration. The complete browser acceptance workflow passes using real MariaDB, Redis and Horizon, with a deterministic simulation provider. No marketing email is transmitted externally.

Base commit: `810d67d`. Branch: `feature/phase-1b-core-features`.

Implementation commits:

- `0218fea` — Extend platform company administration and protect account ownership.
- `b1b231d` — Implement tenant contacts audiences campaigns and safe simulated delivery.

Final documentation/verification follows in a separate local commit. No push, deployment or DNS change was performed.

## Completed functionality

### Platform administration

Platform administrators can create companies and assign an existing active user as owner. For a new email address, company creation produces a secure owner invitation; the invitee registers/verifies and accepts it. Platform company screens expose profile editing, membership, pending invitations, existing-user addition, role changes, safe removal, explicit audited ownership transfer, contact/campaign aggregate counts and simulated delivery activity. Company directory supports search, status filtering, activation/suspension and pagination.

Platform user directory includes name/email search, status filter, memberships, platform role indicators, invitation status and recent account audit history. Account suspension deletes existing database sessions and blocks future authentication and requests. The final active platform administrator cannot be suspended or deleted. The final company owner cannot be removed/demoted without replacement. Ownership transfer promotes an existing active member and changes former owners to administrators under a company lock.

Platform administration does not grant implicit company membership or access to private contacts, email bodies, previews or campaign recipient screens. There is no unrestricted impersonation/support bypass.

### Company workspaces and permissions

Existing registration, verification, reset, profile, organisation creation/switching and theme/navigation functionality is preserved. Owners/administrators configure company details and sender identities. Owners administer membership and ownership transfer. Owners, administrators and campaign managers manage contacts/audiences/templates/campaigns; viewers can read authorised company information. Every route checks server-side policies and tenant-owned linked records.

Company navigation now includes Contacts, Lists & segments, Imports, Campaigns, Templates, Sending identities, Reporting and workspace settings. Postal address is required by campaign pre-flight. Dashboards use real contact/eligibility/suppression/list/campaign/event queries.

### Contacts, eligibility and audiences

Contacts support tenant-local normalised email uniqueness, profile editing, phone/source/status, scalar custom attributes, search/filter/sort/pagination, bulk selection/archive/suppression and contact history. Identical addresses in different companies remain independent.

New/imported contacts default to unknown eligibility. Evidence records include basis, explanation, method, timestamp, optional policy version, actor and source. Central eligibility excludes archived, unknown and suppressed records. Suppressions survive list changes, imports and normal profile edits. Changing an address resets eligibility; suppression is retained for the old address and carried to the new one when applicable.

Mailing lists support creation/editing/description, counts, contact search, paginated membership selection, bulk add/remove and membership history. Dynamic segments support up to eight AND rules over status, source, company name, list membership, creation date and validated scalar custom attributes, with preview and eligible counts. No arbitrary SQL is accepted.

### CSV imports

Uploads are private and limited to 5 MB, 10,000 rows and 30 unique header columns. Workflow includes preview, mapping, duplicate/format warnings, queued execution, progress, successful/rejected counts, recent row results and downloadable formula-safe errors. Full server validation runs in the worker. Replaying/retrying skips previously committed row numbers. Existing records are preserved rather than overwritten. Only profile fields are mapped; no imported permission is assumed.

### Templates and sender identities

Company sender identities store name, address, reply-to, domain association/default flag and explicitly unverified/not-checked verification fields. No DNS verification or real-provider readiness is claimed.

Tiptap supplies the free rich-text editor: headings, paragraphs, bold/italic, lists, HTTPS links, styled link-buttons, images, section dividers, personalisation and sender/unsubscribe footer insertion. HTML Purifier sanitises on the server. Three original starter templates can be saved, edited, previewed and duplicated. Campaigns may start from saved templates. Plain-text alternative is separately editable. Saved desktop/mobile previews use sample personalisation in a sandboxed iframe; they send no email.

### Campaigns and simulation

Campaign workflow: draft → ready → scheduled → processing → completed, with cancellation and failure handling. Drafts can be edited; duplicate makes a fresh draft. Readiness/scheduling pre-flight checks sender/company configuration, content/footer requirements, audience ownership and eligible recipients. Scheduling creates an immutable content/envelope/audience/recipient snapshot. Later content/audience edits are rejected.

A typed provider contract uses DeliveryMessage and SubmissionResult. Only SimulatedDeliveryProvider exists. It never opens SMTP, calls an external API or uses Laravel Mail. Configuring any other provider fails closed. Recipient jobs check company state, exact email and current eligibility/suppression before each attempt. Database locks and unique recipient/attempt/event keys make replay safe. Delayed jobs plus the scheduled recovery tick handle due/retry work and the commit-to-queue gap.

Deterministic fake address outcomes:

- `success@example.test` or ordinary fake address: simulated acceptance.
- `temporary@example.test`: first attempt defers, second accepts after 30 seconds.
- `permanent@example.test`: permanent simulated rejection and company-level bounce suppression.

Prefixes may have suffixes, as used by generated browser-test addresses. A completed campaign means its recipients reached terminal outcomes, not that every recipient was accepted.

Reporting uses stored recipient, attempt and event data: original audience, eligible/excluded/suppressed counts, attempts, simulated acceptances/failures, later suppression and retries. It includes outcome bars, campaign history, recent events and date filters. No opens/clicks/revenue or inbox delivery is invented.

### Unsubscribe

Encrypted opaque recipient tokens plus relative URL signatures protect unsubscribe links. GET displays confirmation without changing eligibility. Signed POST with CSRF protection creates idempotent company-level suppression. Links reveal no plaintext recipient address, and remain usable on localhost or LAN origins. App-key/address changes invalidate old tokens. Suppression applies even while list membership persists.

## Verification actually executed

| Check | Result |
| --- | --- |
| Baseline Phase 1A PHP suite | 39 tests, 156 assertions passed |
| Final PHP suite, SQLite | 69 tests, 317 assertions passed |
| Final PHP suite, isolated MariaDB | 69 tests, 317 assertions passed |
| Playwright Chromium | 2 journeys passed; final run 46.8 seconds |
| TypeScript | Passed |
| ESLint | Passed |
| Vite production build | Passed |
| Pint | Passed (`vendor/bin/pint --test`) |
| npm audit | 0 reported vulnerabilities |
| Composer audit | No reported advisories |
| Migration status | All six migrations applied |
| Horizon | Running |
| Recovery tick | Executed successfully |
| LAN login | HTTP 200 |

Commands:

```sh
php artisan test --compact
DB_CONNECTION=mysql DB_DATABASE=dispatch_test php artisan test --compact
npx playwright test
npx tsc --noEmit
npx eslint resources/js
npm run build
vendor/bin/pint
vendor/bin/pint --test
npm audit --audit-level=low
composer audit --format=summary
php artisan migrate:status
php artisan horizon:status
php artisan dispatch:tick
```

The expanded browser journey exercises platform company creation, owner login, team invitation/acceptance, Redis-backed contact import, eligibility evidence, mailing-list membership, sender identity, template editing/preview, campaign creation/audience selection/mobile preview, scheduling, queue retries/completion, reporting and denial of private campaign access to the platform admin. The original journey checks registration, verification gating, organisation switching, persistent dark mode, responsive navigation and Horizon access. Browser fixture accounts have generated undisclosed passwords and `.test` addresses. Existing development records are never reset.

Screenshots are ignored local artifacts in test-results: company-campaign-preview.png, company-campaign-results.png, dashboard-light.png, dashboard-dark.png, dashboard-mobile.png and mobile-navigation.png. Company results and mobile/desktop layout were visually inspected during development.

## Local operation and review

LAN preview: http://192.168.0.22:8013
Local/test server: http://127.0.0.1:8012
Main platform account: `admin@example.test`, using the previously established password. It remains active; no password was changed during Phase 1B. Passwords, application key and database credentials remain outside Git and documentation.

Running service ports: MariaDB 13307, Redis 16380, application 8012/8013. Horizon and `php artisan schedule:work` run separately. Stop only project-owned foreground processes with Ctrl-C. The preview is available while the Mac and processes are running. Browser tests need the 8012 server and live queue workers; they create additional labelled demonstration records.

### Review the workflow yourself

1. Log in and open `/platform`.
2. Create a company with an existing registered owner email, or share the generated owner invitation securely. Assigning yourself explicitly is also possible; platform role alone does not make you a member.
3. As owner, open the company workspace and enter its sender postal address in settings.
4. Invite a colleague and configure an unverified sender identity.
5. Create/import fake contacts. Record consent/eligibility evidence for the demonstration contacts you want to simulate; unknown records remain excluded.
6. Create a mailing list and add those contacts, or save a structured segment.
7. Create a template from an original starter, edit and save it.
8. Create a campaign, select the company identity and audience, save a draft and review its preview/pre-flight.
9. Mark ready; schedule in browser-local time or leave blank to initiate now. Scheduling freezes content/recipients.
10. Review actual simulated attempts, recipient outcomes and reports. Use a generated local unsubscribe link to confirm suppression.

## Major implementation locations

- Controllers: PlatformCompanyController, ContactController, AudienceController, ImportController, ContentController, CampaignController and UnsubscribeController.
- Services: MembershipService, EligibilityService, AudienceService, CsvImportService, EmailContentService, CampaignService and UnsubscribeService.
- Jobs: ProcessContactImport, PumpCampaigns and DeliverRecipient.
- Delivery: typed provider/message/result contract and SimulatedDeliveryProvider.
- Frontend: contacts/detail, audiences/list detail, imports/detail, identities, templates/editor, campaigns/editor/detail, reports, unsubscribe and expanded platform/settings/dashboard screens.
- Tests: PlatformAdministrationTest, ContactsTest, CampaignDeliveryTest, CoreSecurityRegressionTest and two browser journeys.
- Additive migrations: 2026_10_23_000001_extend_company_administration and 2026_10_23_000002_create_marketing_core.

See database-design.md for the table/index list and architecture.md for policy/queue boundaries. Existing development users, organisations, memberships, credentials and demonstration data were preserved; the development database was neither reset nor reseeded.

## Known limitations and next work

- This is a local demonstration, not a production-ready sending service. No external delivery, webhooks, tracking, production credentials or DNS verification exists.
- Email layout uses a practical rich-text editor and basic table wrapper, not a full drag-and-drop builder. Cross-client visual compatibility needs dedicated testing before real sends.
- Imports map profile data only; eligibility is recorded separately. A future bulk-evidence workflow must preserve row-level evidence and suppression safeguards.
- Reports currently show the latest 30 snapshotted campaigns with client-side date filtering. User/invitation/audit summaries also use bounded recent lists; large-scale reporting needs server-side ranges and deeper drilldowns.
- Scheduler/worker throughput has not been load-tested. Raw import files, personalisation snapshots, attempts/events and audit history need production retention/deletion policies and operational monitoring.
- The database is MariaDB through Laravel's mysql driver; Oracle MySQL itself has not been exercised on this workstation.
- There is no support impersonation or automatic platform bypass. Any future support access must be explicit, time-limited and audited.
- No legal-compliance certification is claimed. Recorded evidence, suppression and unsubscribe are controls; a current UK GDPR/PECR assessment is still required for any proposed real sending.

Recommended next phase: usability review with realistic fake company workflows, bulk eligibility-evidence design, server-side reporting/filtering, retention/export/deletion workflows, concurrency/load tests and email-client preview QA. A real-provider integration remains a separately approved future project; it must address acknowledgement-loss ambiguity, provider idempotency and authenticated feedback before any real transmission.
