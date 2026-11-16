# Campaign Manager client launch — proposed checklist

Review before implementation. Single managed client, invite-only, company-specific SendGrid credentials. Keep Dispatch as the default app name and Campaign Manager on the client installation. Defer billing, public signup and other SaaS work.

## Evidence so far

Operator reports that Plesk scheduled simulations, Horizon CSV processing, SendGrid sandbox validation, domain authentication, a signed sample webhook (HTTP 202), and a four-address live inbox pilot succeeded. These observations are from the current staging server. Repeat deployment checks on the client's destination. Inbox receipt does not establish that Dispatch correlated all delivery events. No evidence yet of a client acceptance run, opt-out round trip, or recovery drill.

## 1. SendGrid feedback and analytics

- [ ] Verify the client's own account, sending domain, sender, reply-to and postal address. Configure the company-specific HTTPS signed webhook and verify SPF/DKIM/DMARC alignment.
- [ ] Confirm processed, delivered, deferred, bounced, blocked/dropped, complaint and unsubscribe events map to the right recipient. Verify automatic suppression and that later imports do not undo it. Test visible and one-click unsubscribe and a provider-approved bounce method.
- [ ] Agree tracking and privacy settings with the client before enabling opens/clicks. Enable those events in SendGrid, enable approved company controls in the app, and explicitly select tracking on a new pilot draft. Keep both off elsewhere. Verify totals, unique counts, link breakdown, recipient history and CSV export. Explain that privacy proxies and scanners affect tracking, and delivery does not prove inbox placement.
- [ ] Check analytics with duplicate, delayed, unmatched and cross-company events. Confirm no false delivery or engagement in simulation/sandbox.

## 2. Usability

- [ ] Review the sidebar, contacts/import, audiences, campaign editor/review and analytics with a client user. Add meaningful icons alongside labels, clearer hierarchy and status badges, and surface the next action. Keep keyboard controls, mobile layouts and light/dark readability.
- [ ] Run an invite-to-campaign walkthrough and address points where the client needs help. Clarify queued, submitted, delivered, suppressed and uncertain states and the limits of Pause/Cancel.

## 3. Security and operations

- [ ] Review dependency advisories, tenant and role boundaries, credential storage/redaction, signed webhooks, public route throttles, invitation and reset flows, CSV uploads and HTML sanitisation. Resolve critical/high findings before launch and document any remaining findings.
- [ ] Provide and test a secure invitation, verification and password recovery process for the client; staging currently logs verification/reset links. Decide whether to implement separate transactional account mail and verify it before relying on it.
- [ ] Verify HTTPS, debug off, correct proxy handling, private Redis/database access, least privilege, scheduled jobs and Horizon restart. Test Pause/Cancel before submission, duplicate prevention and recovery from an uncertain submission.
- [ ] Configure protected off-host backups and demonstrate an isolated restore. Set up alerts for site outages, stalled queue/scheduler, failed jobs and provider/webhook failures. Rehearse rollback with live sending disabled and submission history preserved.
- [ ] Review client list provenance, eligibility and suppression carryover before import. CSV imports mark new unsuppressed contacts eligible in the application; import alone does not establish permission.

## 4. Client template — last

- [ ] Build the approved editable template after the workflow and safety checks. Confirm its content, links, mobile layout, plain text, sender/footer and unsubscribe in actual Gmail and Outlook, and obtain client sign-off.

## Release gate

Document tests and evidence against the production-readiness report. Repeat the restricted test on the destination with approved recipients and a fixed cap. Obtain explicit approval of the first live audience, sender, campaign and volume before scheduling; monitor that send before expansion. Do not treat staging success as production sign-off.
