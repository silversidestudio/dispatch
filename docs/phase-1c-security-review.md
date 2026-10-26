# Phase 1C security review

This is a developer review with regression tests, not a penetration test or production certification. No live infrastructure was inspected. See production-readiness.md for outstanding mandatory controls.

## Implemented controls and evidence

- Tenant policies and explicit organisation/record relationship checks cover analytics, exports, privacy controls, identities and reconciliation. Platform administrators have no private content bypass. Tests cover cross-tenant/provider correlation and platform-only boundaries.
- Campaign mode/content/tracking snapshots remain frozen. Company, current identity, address and central eligibility/suppression checks precede submission. Existing simulation snapshots do not become live after configuration changes. Live gates default false.
- Durable reservations commit before HTTP; unique correlation and recipient/attempt locks prevent sequential replay. Ambiguous results are held, not blindly retried. Signed feedback before acknowledgement and evidence-based reconciliation are tested. Concurrent multi-worker fault injection remains outstanding.
- Webhook raw-body ECDSA verification, timestamp bounds, body/event limits, route throttling, encrypted normalised batches, idempotent receipts, quarantine and state rank safeguards are implemented. Raw payloads/IP/user-agent/full URL queries are not retained. Diagnostic fields use bounded codes rather than arbitrary provider response bodies.
- Tracking off by default and subject to recorded review. Safe URL labels retain origin plus opaque HMAC path label. Unsubscribe/administrative paths excluded; clicktracking=off added to visible unsubscribe links. External link behaviour and provider-specific rewritten-link exclusions need pilot verification.
- Opaque signed/encrypted unsubscribe tokens are tenant/recipient bound. GET harmless; one-click POST session/CSRF-free only on the dedicated signed route. Invalid signatures rejected; suppression is idempotent. RFC8058 DKIM header coverage is outstanding.
- HTMLPurifier sanitises HTML; previews are isolated. Existing CSV size/row limits, validation, tenant scoping, formula-safe error/event exports and private upload storage remain. Tiptap alignment is the only added frontend dependency.
- Owner/admin contact export and explicit ERASE action retain minimal suppression, redact profile/evidence/completed snapshots and remove matching raw imports. Active campaign/import guards prevent unsafe erasure. This is a deliberate audited privacy exception to recipient personal-data immutability; campaign content and counts remain historical. Backups, legal retention and downstream provider data still require an operational deletion policy.
- ProductionSafety fails closed for debug/insecure URL/cookies, requires secure requests, adds HSTS/nosniff and authenticated no-store. Same-origin referrer policy preserves internal Laravel redirect behaviour while withholding cross-origin URLs. Browser testing caught the initial no-referrer redirect regression; corrected before final validation.
- Credentials environment-only; no key frontend props. Account notifications remain local log transport unless separately explicitly configured. Local logs contain authentication links and require restricted access; production proxy/token/body logging must be reviewed.

## Remaining risks and required work

1. Production security, TLS/proxy trust, key management, firewall/database permissions, log redaction, backup encryption/restore and alert configuration are unverified external controls. Do not launch on the basis of this code review.
2. No sustained concurrency/race/worker-kill stress run, penetration test, adversarial public-endpoint load test or volume soak test. Execute before wider sending.
3. CampaignAnalytics currently loads a campaign's selected events into memory and computes recipient summaries individually. Adequate for a controlled small pilot; aggregate SQL/materialised summaries and query/load profiling are required before large campaigns. Event indexes are present but do not replace load testing.
4. Signature timestamps assume synchronised clocks. Late or unmatched events are quarantined, not guessed; operators need an investigation process. Quarantined receipts and suppression retention require client-approved periods.
5. APP_KEY loss prevents encrypted batch/token recovery. Rotation invalidates links unless a reviewed key migration exists. Keep the public URL and keys stable and recoverable.
6. Ordinary suppression controls are not a legal assessment. Client must approve lawful basis/source, privacy notices, tracking and retention. Tracking remains off; counters represent provider evidence, not human engagement or inbox placement.
7. Single-recipient simulation/sandbox preparation is implemented with separate review/scheduling. Actual SendGrid sandbox and Outlook/Gmail/client usability verification remain launch gaps. Sandbox validation and preview are not substitutes for real rendering acceptance.

Dependency audits on 26 October 2026: npm audit --audit-level=low found 0 vulnerabilities; composer audit --format=summary found no advisories. This is a point-in-time dependency check, not proof that no application vulnerabilities exist.
