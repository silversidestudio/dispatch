# SendGrid setup and delivery safety

Status: implemented and locally mocked; no account, API request, domain, public webhook or real email has been externally verified. Do not enable the gates below without explicit authorisation.

## Configuration

`config/dispatch.php` is authoritative. Campaign API keys and webhook verification keys are configured by each company owner or administrator in **Sending identities → Company SendGrid credentials** over HTTPS. They are encrypted in the database and never returned in page props. Platform account notifications, if separately enabled, use a host secret. Keep all secrets out of Git, support screenshots and logs. Defaults:

```dotenv
DISPATCH_DELIVERY_PROVIDER=simulation
SENDGRID_ENABLED=false
SENDGRID_LIVE_SENDING_ENABLED=false
# Optional platform account notifications only.
SENDGRID_API_KEY=
SENDGRID_REQUESTS_PER_MINUTE=60
SENDGRID_AUTH_ENABLED=false
SENDGRID_AUTH_FROM=
DISPATCH_WEBHOOK_RETENTION_DAYS=14
DISPATCH_ENGAGEMENT_RETENTION_DAYS=90
DISPATCH_TRUSTED_PROXIES=
```

The campaign chooses simulation, sendgrid_sandbox or sendgrid_live. Scheduling freezes this mode, tracking, sender, audience and content. Changing the default provider cannot convert existing simulations into live sends. Sandbox calls require the provider/key/enabled gates; they still contact SendGrid and therefore were only mocked here. Live calls also require the live gate, HTTPS application URL, webhook verification key, active company, current platform-authorised identity and explicit campaign SEND confirmation. Tracking remains false until an owner/admin records a documented privacy review and separately permits each tracking type.

Use a scoped API key with Mail Send permission and read access to the required authenticated-domain resource. Prefer dedicated account/subuser isolation for the initial client and verify the chosen permissions in the account. Enter the company’s scoped key only in the company credential form on the approved HTTPS host. A company without a key cannot fall back to the platform notification key. Replacing credentials clears sender authorisation; campaigns scheduled under another key must be recreated. The operational domain check is an explicit read-only API request initiated by a Platform Administrator; no DNS writes are implemented. Platform company administration exposes identity configuration without granting private contact/campaign access.

## Domain authentication and Cloudflare

1. After authorisation, use SendGrid Settings > Sender Authentication > Domain Authentication for the exact sending domain/subdomain.
2. Copy the exact DNS names, record types and values generated for that account. Do not reuse example CNAME, SPF or DKIM values.
3. In the authoritative Cloudflare zone add the specified records. Email-related CNAME records must be DNS only, not proxied. Avoid creating duplicate SPF records; reconcile existing policy with the domain administrator.
4. Complete SendGrid validation. Copy the authenticated domain ID into Dispatch's identity verification control and explicitly authorise the verified identity.
5. Dispatch checks domain equality and SendGrid valid status, records the time and marks SPF/DKIM as provider_authenticated. This is provider evidence, not independent DNS inspection. DMARC remains not checked until separately assessed. Identity evidence expires after seven days by default and must be refreshed before further live submissions.
6. Assess DMARC alignment, existing mail services, reply-to handling and return-path behaviour with the domain owner. No DNS changes have been made by this development session.

References: [SendGrid domain authentication](https://www.twilio.com/docs/sendgrid/ui/account-and-settings/how-to-set-up-domain-authentication), [Cloudflare proxy limitations](https://developers.cloudflare.com/dns/proxy-status/limitations/).

## Submission and reconciliation

SendGridDeliveryProvider uses v3 Mail Send, not Marketing Campaigns. A database reservation commits a stable server-generated attempt UUID before HTTP submission. The UUID is sent in custom_args; responses record HTTP status and X-Message-Id. Per-company Redis throttling bounds initiation; 429 respects Retry-After, other temporary retries use exponential backoff with jitter and a maximum attempt count. Invalid credentials pause the campaign. Content, identity, active company, immutable address, current eligibility, suppression and cancellation are rechecked before submission.

A provider can accept a request before a timeout or worker crash. SendGrid does not provide a general exactly-once Mail Send guarantee. Connection errors, 5xx, 408, unexpected success and missing acknowledgements are conservatively held as uncertain; jobs do not automatically resubmit them. Recovery marks stale reservations uncertain after five minutes. Owner/admin reconciliation requires evidence and explicit confirmation: close without retry, or confirm not submitted before permitting a new attempt. Never select the latter merely because no webhook has arrived. Signed feedback can resolve an uncertain attempt and blocks unsafe replay. An already in-flight request cannot be recalled by pause/cancel. Stop both new campaign work and provider gates during an incident; reconcile durable attempts before recovery.

## Webhook

Configure POST `/webhooks/sendgrid/{organisation_id}` on public HTTPS, enable signed Event Webhook in SendGrid and set the corresponding public ECDSA verification key. Validate the actual provider's signing fixture before pilot. Timestamp and signature cover timestamp + exact raw body; altered/stale/unsigned requests are rejected. Payloads are bounded to 1 MiB/1,000 events. Verified batches are normalised, encrypted at rest and queued. Receipt keys and event constraints deduplicate. The endpoint selects its own company verification key, and only server attempt correlation within that same company, tenant relationships and immutable recipient matches allow attribution. Unmatched events are quarantined; never assign them by an untrusted organisation ID.

Keep clocks synchronised: signature tolerance is five minutes. Recovery requeues failed/pending batches. Use Horizon, `webhook_batches` status and `webhook_receipts` quarantine reason for investigation. Do not expose encrypted envelopes in general support UI. Completed envelopes default to 14 days retention; quarantined receipts need an operator review/deletion policy. Tracking events expire after 90 days; historical metrics can decline after pruning. Both periods require client policy approval.

[Signed webhook specification](https://www.twilio.com/docs/sendgrid/for-developers/tracking-events/getting-started-event-webhook-security-features), [Mail Send API](https://www.twilio.com/docs/sendgrid/api-reference/mail-send/mail-send).

## Unsubscribe and account notifications

Dispatch owns suppression. SendGrid subscription tracking is explicitly disabled to avoid competing footers; provider unsubscribe and complaint feedback still suppress Dispatch contacts. Visible links show a GET confirmation and require POST. RFC 8058 uses a separate opaque, signed, encrypted recipient token; sessionless POST requires List-Unsubscribe=One-Click. GET never suppresses. Headers include List-Unsubscribe and List-Unsubscribe-Post; visible links carry clicktracking=off. Actual SendGrid DKIM coverage of BOTH headers, client handling and public HTTPS accessibility remain pilot requirements, not verified claims. See [RFC 8058](https://www.rfc-editor.org/info/rfc8058/).

Authentication verification/reset notifications use log transport by default. A separate explicitly enabled SendGridAuthChannel can send framework notifications through a separate platform-only environment key with tracking off; mock-tested only. Do not enable SENDGRID_AUTH_ENABLED before approving real transactional notification tests and a verified SENDGRID_AUTH_FROM address. Invitations currently use secure UI acceptance links shared by an authorised operator; automatic invitation email delivery is not implemented.

## Restricted pilot — 11 November 2026

New default DISPATCH_PILOT_ENABLED=true requires explicit comma-separated DISPATCH_PILOT_ORGANISATION_IDS and DISPATCH_PILOT_RECIPIENTS before live submission. DISPATCH_PILOT_DAILY_LIMIT defaults25 (allowed1–100) and counts global UTC daily reserved live attempts including failures/uncertain outcomes. Account notifications through SendGrid must stay disabled during this restriction. Existing live flags and campaign approval still apply; no actual provider settings were enabled. See [pilot handover](basic-email-pilot-handover.md) and [Plesk staging](plesk-staging-setup.md).
