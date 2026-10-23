# Simulated delivery

## Absolute boundary

Dispatch has one adapter: SimulatedDeliveryProvider. It has no HTTP client, SMTP connection or Laravel Mail call. Laravel authentication mail remains restricted to log/array transports. No provider credential, DNS integration or real-sending switch has been introduced. Changing dispatch.provider away from simulation fails closed; it never selects another adapter.

DeliveryProvider accepts a typed DeliveryMessage (envelope, subject, HTML, plain text and durable idempotency key) and returns a SubmissionResult with classified outcome and deterministic provider message ID. This interface is for future extension, not authorisation to implement live delivery.

## Deterministic outcomes

Email local parts beginning with `permanent` are rejected; those beginning with `temporary` defer once and are accepted on their next attempt; other addresses are accepted. Use fake `.test` addresses. These results are labelled simulated throughout; no inbox placement, opens or clicks are generated.

Temporary failures wait 30 seconds. Jobs use bounded framework retries for infrastructure errors. Permanent simulated failures retain evidence and suppress that company/address. Recipient and campaign locks plus unique recipient/attempt/event keys prevent duplicate durable submissions when queue jobs are replayed. Adapter invocation and outcome persistence share a DB transaction because the adapter has no external side effect.

## Workers and recovery

```sh
php artisan horizon
php artisan schedule:work
php artisan dispatch:tick
php artisan horizon:status
```

Redis must be running (this workstation: 127.0.0.1:16380). Horizon consumes the default queue. The scheduler runs the recovery tick every minute; the final command runs one tick manually. Delayed jobs handle normal scheduled campaigns/retries. The recovery tick handles the commit-to-queue dispatch gap and pending/deferred recipients after worker restart. Jobs independently check company state and contact eligibility, not browser session state. Company suspension prevents further submissions; recovery resumes due work after reactivation.

CSV imports also use queued jobs. Rows have unique import/row keys and are committed individually; retry resumes without duplicating successful rows. A failed import can be retried from its detail page after the cause is corrected.

## Unsubscribe

Recipient links carry authenticated encrypted payloads and relative URL signatures, containing company, contact and exact snapshotted email. Payloads are opaque, URL-safe and do not disclose an address in plaintext. GET shows a confirmation page; signed POST applies a company-level suppression with CSRF protection. Repeated confirmations are idempotent. Host-independent signatures allow localhost and LAN previews. Links remain valid while the application key and contact address remain valid. They are sensitive bearer links and must not be logged publicly.

## Future real-provider ambiguity

A real provider may accept a message before the application records its acknowledgement. A worker restart cannot prove whether submission occurred. Future implementation must use provider-supported idempotency keys/reconciliation and explicitly represent uncertain outcomes; it must not blindly resubmit. This simulator does not establish production duplicate-prevention guarantees for external providers. Signature-verified webhooks, provider reconciliation, delivery feedback, tracking and production retention remain future work.
