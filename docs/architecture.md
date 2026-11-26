# Architecture

Current state: Phase 1C extends the earlier foundation and simulation. Historical Phase 1A/1B descriptions below explain decisions; the Phase 1C section and sendgrid-setup.md describe current delivery capabilities. Live sending is disabled and externally unverified.

## Scope and design decisions

Phase 1A is a conventional Laravel monolith with Inertia/React pages. Dispatch is a working development name. No existing application was modified. The local MyBookable foundation at `website-design-test/booking-saas` was inspected read-only for neutral surfaces, component conventions, spacing and navigation. No business logic, credentials or assets were copied.

Laravel 12 was selected to match PHP 8.3 and the requested stack. Composer resolves maintained compatible releases. The official React starter kit supplies authentication, accessible Radix/shadcn UI and theme primitives; the application shell and product screens were replaced with workspace-specific interfaces. Fixed roles and Laravel policies avoid a permissions-package dependency for a four-role membership matrix.

## Tenant boundary

A user belongs to many organisations through a unique membership. The active organisation ID is session state, never authority. Every organisation view and mutation authorises the bound organisation with a policy. Dashboard resolution starts from the authenticated user's active memberships and rejects stale/suspended context. Explicit settings URLs address the authorised bound organisation, independent of the dashboard selection.

Platform administration has a separate gate. A platform super administrator does not automatically acquire organisation membership. Platform functions expose summary information and status controls through a distinct controller. Horizon has an explicit authentication callback that applies even in local environments; its default local bypass is overridden.

| Role | View workspace | Edit organisation | Manage membership | Future campaign permission |
| --- | --- | --- | --- | --- |
| Owner | Yes | Yes | Yes | Yes |
| Administrator | Yes | Yes | No | Yes |
| Campaign manager | Yes | No | No | Yes |
| Viewer | Yes | No | No | No |

Only owners invite and change non-owner roles. Ownership transfer and member removal require future work. Owner account deletion is blocked to prevent orphaning organisations. Platform elevation is a trusted CLI operation, never a registration field.

## Authentication and sessions

Registration, login, email verification, signed verification URLs, password reset, password confirmation, profile updates and account settings use Laravel's established authentication components. User passwords are hashed; session IDs rotate at login and logout. Database sessions are encrypted locally. CSRF middleware protects mutations. Login and selected account operations are rate limited. Verified email is required for organisation and platform functionality. Local HTTP uses HTTP-only/Lax cookies; HTTPS and secure cookies are required before any future production use.

Invitations use random 64-character tokens with SHA-256 hashes at rest, seven-day expiry, verified exact-email matching and transactional one-time acceptance. Reissuing invalidates the previous token. Links are shown once to the owner for secure local sharing; no invitation email is transmitted. Avoid sharing application logs or invitation URLs publicly.

## Email safety — original foundation

In Phase 1A no SMTP/provider implementation or send endpoint existed. AppServiceProvider replaces Laravel's merged mailer configuration with log and array transports only. Even MAIL_MAILER changes cannot enable SMTP. Verification/reset links remain in ignored local logs. Horizon runs Redis workers but Phase 1A enqueues no campaign delivery jobs.

## Audit and metrics

Organisation creation/settings/status changes, invitations, member role changes and CLI administrator elevation are audited. Audit data uses identifiers and bounded metadata, without invitation tokens/passwords. Audit lists are scoped for ordinary users; platform administrators have a separate platform-wide view. Comprehensive authentication auditing, retention and deletion controls remain future work.

The dashboard reports database membership counts, actual role and tenant-scoped audit entries. Campaign and acceptance values display unavailable markers with explanatory empty states. No fabricated events or delivery statistics are presented. Future SMTP acceptance must never be presented as inbox placement.

## Original planned delivery contract (implemented in Phase 1B/1C)

Phase 1D will introduce provider-independent submission/result contracts and a deterministic simulator, recipient uniqueness constraints, transactional queue dispatch, durable idempotency keys and structured attempts/events. Acknowledgement loss after provider acceptance creates an unavoidable uncertain outcome: a restart must reconcile that outcome using provider evidence/idempotency rather than blindly resubmitting. Webhook signatures, redaction, duplicate event keys and ordering rules are deferred to that milestone. No partial live integration is included.


## Phase 1B extensions

The foundation now includes platform-managed company creation, invitations, safe membership removal/ownership transfer and account suspension. Platform content access still requires actual organisation membership; support impersonation is not implemented. Last-owner and last-active-platform-admin mutations are protected by transaction locks. Active-account middleware blocks suspended sessions, while unsubscribe remains available.

Contacts and audience operations use explicit tenant policy checks and validated linked IDs. EligibilityService centralises eligibility/evidence/suppression. AudienceService accepts structured rules only. CSV files remain private, size/row/column bounded, with profile-only mapping and per-row replay keys. Templates/campaigns use server HTML Purifier and sandboxed previews. The simulator and queue jobs run independently of browser sessions and enforce company/recipient state themselves.

Campaign scheduling creates immutable content and recipient snapshots. Jobs and a recovery scheduler reconcile pending work, with campaign/recipient locks and unique attempt/event keys. At the Phase 1B handover no external delivery adapter existed; Phase 1C adds the gated SendGrid adapter below. See contact-eligibility.md, campaign-lifecycle.md, simulated-delivery.md and email-editor.md for definitions and limits.

## Phase 1C provider feedback and analytics

SendGridDeliveryProvider implements the existing typed DeliveryProvider contract using Mail Send v3. SimulatedDeliveryProvider remains independent. Immutable snapshots select simulation/sendgrid_sandbox/sendgrid_live; application defaults cannot upgrade existing campaigns. ProviderReadiness enforces configuration/identity/tracking gates; SendGridSubmissionService commits durable attempt reservations before network work and holds uncertain outcomes for evidenced reconciliation. See sendgrid-setup.md for limits of exactly-once submission and cancellation.

Signed raw-body webhooks enter SendGridWebhookController, encrypted normalised WebhookBatch envelopes and ProcessSendGridWebhook. WebhookService verifies signatures, correlates only server-generated UUIDs and exact tenant/recipient relationships, deduplicates receipts/events, quarantines unmatched records, preserves terminal delivery evidence and applies suppressions. No raw unredacted provider payload is retained. Analytics services query stored provider evidence, isolate simulation and distinguish attempts/events/unique recipients. No new external analytics service or infrastructure was introduced.

ContactPrivacyController provides restricted export and audited erasure with suppression retention and active-work guards. ProductionSafety fails production HTTP/debug/insecure session configurations closed. Account verification/reset use a separately gated SendGridAuthChannel only when explicitly configured; local log notifications remain the default. Platform staff can authorise operational identities without gaining private content permission. See phase-1c-security-review.md and production-readiness.md for unverified external controls and scale limits.

## Email presentation refinement

EmailContentService remains the shared contract. EmailMarkup uses a DOM tree to validate/normalise safe fragments, apply inline presentation and render a CTA table-cell fallback. A separate Blade email document supplies fluid widths/media CSS and an MSO wrapper. EmailStarterTemplates supplies original versioned designs; optional organisation branding is applied on creation. Tiptap retains table/section/inline styles and editable atomic CTA/image nodes; app controls remain shadcn/Lucide. Saved preview iframes stay sandboxed with script/form restrictions. Existing campaign snapshots are never rewritten. See email-editor-refinement-handover.md for tests, visual evidence and outstanding actual-client checks.

## Restricted pilot and basic editor — 11 November 2026

SendGridPilotGuard adds exact server-side company/recipient scope and a durable row-locked global UTC daily attempt budget before live API submission. Readiness/pre-flight and submission checks fail closed; the adapter rechecks and reserves before HTTP. Simulation/sandbox do not spend live capacity. SendGridAuthChannel is blocked while restricted pilot mode is enabled. Existing provider contracts, snapshots and webhooks remain. Basic typography uses inline styles through existing sanitisation/rendering; plain text is generated through an authorised server route. See basic-email-pilot-handover.md.

## CSV eligibility policy — 12 November 2026

At the operator’s explicit request, newly created CSV contacts are automatically eligible using the distinct csv_import state and an automatic provenance record (import/user/source/row/time). No manual evidence form is needed. This does not fabricate consent or a lawful-basis assessment. Central eligibility continues to require an active tenant-scoped contact, a matching record and no organisation suppression. Duplicates preserve existing status; historical records are not backfilled. No schema migration or live-send setting change is required.

## In-app completion notifications — 16 November 2026

Laravel database notifications are created after campaign processing reaches a terminal state and after contact imports complete or exhaust retries. Campaign messages distinguish local simulation, SendGrid sandbox validation and SendGrid submission results; SendGrid acceptance is not described as inbox delivery. Notices go to the campaign creator/scheduler or import uploader and are deduplicated by a unique event key. The bell shows unread status and the latest six notices. Opening a notice rechecks company membership and target ownership before switching to the relevant results page. This workspace’s tests cover the local notification flow only; staging delivery and migration remain operator verification steps. See `v1-production-checklist.md`.

Contact CSV imports require an active mailing list and a final confirmation before queueing. Contact and suppression imports support up to 25,000 data rows per file by default (5 MB and 30 columns) and process in 250-row queue chunks with a durable file cursor, so worker retries resume without repeating completed rows. Change their shared row limit with `DISPATCH_IMPORT_MAX_ROWS`; larger settings should be sized against worker memory and job duration.

SendGrid's domain authentication status does not check DMARC. An organisation owner or administrator can record a manual DMARC DNS review on a sending identity; Dispatch stores the reviewer and timestamp and resets that record if the sender domain changes. This is an audit acknowledgement, not an automated DNS validation.

Sender identities can be archived and restored by workspace administrators. Archived identities are excluded from new campaign forms and cannot be selected for new campaigns. Existing campaigns retain their immutable sender snapshot; each workspace must keep at least one active sender identity.
