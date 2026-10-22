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
