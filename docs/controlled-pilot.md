# Controlled live pilot — requires separate explicit authorisation

Nothing in this document authorises deployment, credentials, DNS or real email transmission. Do not automatically progress to unrestricted sending. Record the operator, approval, exact release commit, company, approved recipient addresses, volume ceiling, time window and enabled tracking types before execution.

## Entry gates

Resolve mandatory production-readiness gaps, provision approved HTTPS staging/production infrastructure, finish backup/restore and queue recovery exercises, client privacy/legal review and supported email-client rendering checks. Obtain SendGrid account approval for the intended marketing use. Configure restricted credentials through the secret store, authenticated domain and signed public webhook. Use a dedicated pilot company/audience and explicitly documented marketing permission for each controlled recipient. Do not import the client's full audience as the pilot.

## Procedure

1. Keep all live and transactional gates off. Verify the reviewed commit, migrations, secret permissions, TLS, debug off, proxy trust, backups, alert routing, Horizon/scheduler and empty unexpected queues. Record baseline attempts and recipient counts.
2. Following explicit approval to contact SendGrid in sandbox mode, configure provider/key/enabled, leave live disabled and schedule a fresh sandbox campaign with one approved recipient. Verify the actual sandbox validation response and no external delivery. Existing simulations must remain simulations after provider changes.
3. Verify the SendGrid authenticated-domain ID from the workspace Sending setup screen. A workspace Owner/Administrator can run this check using that workspace's credentials. Inspect SPF/DKIM/domain alignment and DMARC separately. Provider verification is not proof of inbox placement. Ensure no stale identity evidence.
4. Configure signed Event Webhook to the stable public HTTPS endpoint. Test invalid signature rejection and provider-signed ingestion, inspect correlation and quarantine. Verify receiver clocks, proxy body limits and no request-body logging. Do not disable verification to make testing pass.
5. Obtain a specific approval for one real message. Enable live gates for the agreed window, keep account notifications disabled unless separately approved. Create a new one-recipient live campaign with sender, postal address, visible unsubscribe and opaque one-click headers. Leave tracking off unless reviewed and approved. Review counts and warnings, type SEND and schedule. Record attempt UUID, HTTP202/provider ID and observed signed processed/delivered events.
6. Inspect received source in the controlled mailbox: rendering, personalisation, plain text, postal identity, visible unsubscribe, both List-Unsubscribe headers and DKIM signature coverage required by RFC8058. Confirm GET confirmation has no side effect, visible POST suppresses, and sessionless one-click POST suppresses idempotently. Verify a new campaign excludes that contact.
7. Use only a provider-approved bounce testing method, with separate explicit permission; never send to arbitrary invalid real addresses. Observe signed bounce/diagnostics, hard-bounce suppression and safe subsequent exclusion. Verify complaint handling through an approved provider fixture or permitted test mechanism; do not manufacture harmful spam complaints.
8. If authorised, use separate approved recipients to observe tracked opens/clicks. Confirm snapshot flags, provider request settings, unique versus total counts, per-link safe labels, excluded unsubscribe links and the documented limitations of scanners/privacy proxies. No tracked event proves a human action.
9. Reconcile every attempt/UUID against provider evidence, recipient timeline, CSV export and campaign summary. Confirm no duplicate submissions, no cross-tenant access and no unexplained uncertain/quarantined records. Compare actual received events to UI counters. Record late events and observation window.
10. Disable live gates after the agreed pilot window, stop new scheduling and review queue health. Preserve auditable evidence without keys, tokens, full URL queries or raw PII. Client reviews results and signs acceptance. Wider sending requires separate written authorisation and volume/monitoring plan.

## Stop and incident conditions

Stop initiation for unexpected recipients/duplicates, wrong sender/content, authentication errors, missed webhook feedback, unexplained uncertain attempts, suppression bypass, abnormal bounce/complaint rates, queue failures or infrastructure instability. Disable live gates, pause campaigns/workers after identifying in-flight operations, preserve evidence and investigate. Do not blindly retry uncertain submissions. Do not roll the database back over accepted messages or suppression events.

## Evidence record

For every 18.9 criterion record status, approval reference, UTC times, release/backup IDs, campaign/attempt/event identifiers, provider evidence and client sign-off. Store sensitive recipient/raw-source evidence in restricted operational storage, not this repository. Update production-readiness.md only after verification; never convert mocked tests into real-integration PASS results.
