# Architecture

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

## Email safety

No SMTP/provider implementation or send endpoint exists. AppServiceProvider replaces Laravel's merged mailer configuration with log and array transports only. Even MAIL_MAILER changes cannot enable SMTP. Verification/reset links remain in ignored local logs. Horizon runs Redis workers but Phase 1A enqueues no campaign delivery jobs.

## Audit and metrics

Organisation creation/settings/status changes, invitations, member role changes and CLI administrator elevation are audited. Audit data uses identifiers and bounded metadata, without invitation tokens/passwords. Audit lists are scoped for ordinary users; platform administrators have a separate platform-wide view. Comprehensive authentication auditing, retention and deletion controls remain future work.

The dashboard reports database membership counts, actual role and tenant-scoped audit entries. Campaign and acceptance values display unavailable markers with explanatory empty states. No fabricated events or delivery statistics are presented. Future SMTP acceptance must never be presented as inbox placement.

## Later delivery contract

Phase 1D will introduce provider-independent submission/result contracts and a deterministic simulator, recipient uniqueness constraints, transactional queue dispatch, durable idempotency keys and structured attempts/events. Acknowledgement loss after provider acceptance creates an unavoidable uncertain outcome: a restart must reconcile that outcome using provider evidence/idempotency rather than blindly resubmitting. Webhook signatures, redaction, duplicate event keys and ordering rules are deferred to that milestone. No partial live integration is included.


## Phase 1B extensions

The foundation now includes platform-managed company creation, invitations, safe membership removal/ownership transfer and account suspension. Platform content access still requires actual organisation membership; support impersonation is not implemented. Last-owner and last-active-platform-admin mutations are protected by transaction locks. Active-account middleware blocks suspended sessions, while unsubscribe remains available.

Contacts and audience operations use explicit tenant policy checks and validated linked IDs. EligibilityService centralises eligibility/evidence/suppression. AudienceService accepts structured rules only. CSV files remain private, size/row/column bounded, with profile-only mapping and per-row replay keys. Templates/campaigns use server HTML Purifier and sandboxed previews. The simulator and queue jobs run independently of browser sessions and enforce company/recipient state themselves.

Campaign scheduling creates immutable content and recipient snapshots. Jobs and a recovery scheduler reconcile pending work, with campaign/recipient locks and unique attempt/event keys. No external delivery adapter exists. See contact-eligibility.md, campaign-lifecycle.md, simulated-delivery.md and email-editor.md for definitions and limits.
