# Implementation plan

## Initial inspection

The target directory was empty and had no Git repository. PHP, Composer, Node, MariaDB and Redis were available. Other sites were preserved. The MyBookable frontend was inspected read-only for styling conventions. This environment requires execution approval for filesystem writes; approved operations initialise only this standalone workspace.

## Phase 1A — Foundation

1. Establish official Laravel 12 React/Inertia stack, lockfiles and local-only mail configuration.
2. Add organisation, membership and audit migrations; policies enforce tenant isolation and fixed roles.
3. Add verified-account onboarding, organisation switching, invitations and a separate super administrator gate.
4. Build responsive workspace navigation, appearance controls, dashboard empty states, settings and platform overview.
5. Configure isolated MySQL-compatible and Redis services; install and authorise Horizon.
6. Verify migrations/seeders, authentication, role matrix, cross-tenant access, invitations, platform/Horizon access, frontend build and browser interactions.
7. Document architecture, schema, setup, limitations and handover; commit locally.

## Approval boundaries

Only Phase 1A is authorised in this session. Stop after its handover. Do not push, deploy, configure live providers or proceed into Phase 1B.

## Later milestones (not implemented)

- Phase 1B: contacts, lists, mapped background CSV imports, recorded marketing eligibility, suppressions, exports/deletion foundations. Research current ICO UK GDPR/PECR guidance before implementing eligibility rules; no assumption of blanket B2B permission.
- Phase 1C: campaign states, audiences, safe HTML editor/sanitisation, templates, previews and scheduling preparation.
- Phase 1D: deterministic simulated provider, pre-flight checks, queues, durable idempotency, recipient attempts/events and precise reporting.
- Phase 1E: integrated review, security/retention review, polish and operational handover.


## Phase 1B expanded authorisation

The subsequent Phase 1B instruction explicitly authorised company/user administration, contacts/audiences/imports, templates/campaigns and simulated delivery. This supersedes the earlier stop-at-1A boundary for those local features. Branch feature/phase-1b-core-features extends 810d67d; existing development records remain preserved. No deployment, DNS or real email transmission is authorised. See phase-1b-progress.md and phase-1b-handover.md for actual results.

## Phase 1C — implementation and launch gates

1. C.1 complete locally: gated SendGrid v3 adapter, separate modes, immutable snapshots, durable reservations, conservative uncertain outcomes, throttling and pause/resume.
2. C.2 complete locally: signed raw-body webhook, encrypted/redacted queued batches, tenant-safe correlation, deduplication/quarantine, ordering and suppression. Public provider verification remains blocked.
3. C.3 complete locally: unique/total tracking, precise rate denominators, safe per-link labels, recipient timelines, date/event/search filters, CSV and comparison. No real provider activity has been observed.
4. C.4 complete for developer review: five original templates, Tiptap alignment, review gates, controlled one-contact simulation/sandbox preparation and dashboard/provider controls. Actual email-client rendering and non-technical client acceptance remain unverified.
5. C.5 partial: local security controls, regression tests, deployment/restore/rollback/pilot runbooks and all 132 acceptance assessments. Production-like infrastructure, monitoring, restore drills, stress/security review, legal approval and the authorised real pilot remain mandatory launch gates. Do not declare production readiness.

See phase-1c-handover.md for exact validation results and production-readiness.md for per-item evidence/status. No push, deployment, DNS modification, credential provisioning or real email transmission was performed.
