# Campaign lifecycle

## States

`draft → ready → scheduled → processing → completed`

Draft/ready/scheduled/processing campaigns may be cancelled. Infrastructure/provider-authorisation failures can result in failed processing. Individual simulated recipient rejections do not make the whole campaign fail: a completed campaign means every snapshotted recipient has a terminal result, not that every recipient was accepted.

Only drafts without a snapshot can be edited. Duplicate creates a new draft with no recipient snapshot or delivery history. A ready campaign can be duplicated to revise it. Illegal transitions are rejected on the server under transactional campaign locks.

## Pre-flight

Required: active company, company postal address, tenant-owned sender identity, non-empty subject/HTML/plain text, sender-address footer placeholder, unsubscribe placeholder in both bodies, a valid tenant-owned list/segment and at least one eligible contact. A 10,000-recipient simulation cap applies. Unknown eligibility, suppression and archived contacts are excluded. Simulation remains the default; SendGrid sandbox/live use separate explicit modes and the Phase 1C gates below. A successful simulation never verifies a sender.

## Snapshot and scheduling

Scheduling snapshots content, subject, sender identity, reply-to, sender postal address and eligible recipients with personalisation. Recipients are unique per campaign/contact. The campaign and recipients are written in one transaction; queue dispatch occurs after commit. Content and audience criteria cannot be edited after snapshot creation. Later list changes cannot add recipients to that send. Later suppression, archive, changed email or company suspension prevents submission.

The UI converts a scheduled browser-local time to an ISO timestamp; storage and jobs use UTC. Leave the date empty to initiate simulation immediately. A delayed queue pump starts due campaigns. `dispatch:tick`, scheduled every minute, reconciles due scheduled/processing campaigns after dispatch loss or worker restart. Cancellation leaves prior attempts/events in history and stops remaining work.

## Reporting definitions

Audience at snapshot: contacts matching the selected list/segment at scheduling.
Eligible at snapshot: unique recipient rows actually snapshotted.
Excluded at snapshot: audience minus eligible; includes unknown, archived and suppressed contacts.
Attempts: durable simulated adapter outcomes, including temporary deferrals.
Acceptances: recipient terminal accepted state, not confirmed inbox delivery.
Failures: recipient terminal failed state.
Suppressed before submission: snapshotted recipients subsequently blocked by the eligibility recheck.
Retries: attempts numbered greater than one; routine job replay with no new attempt is not a retry metric.

Historical eligibility counts are snapshot values; current pre-flight counts may differ. Reports expose actual stored data and no invented opens/clicks/revenue.

## Phase 1C

Draft → ready → scheduled → processing → completed remain the primary states. Scheduled/processing campaigns may pause, retain paused_from and resume; cancellation preserves in-flight evidence. SendGrid attempts reserve submitting before HTTP; accepted/sandbox_validated/deferred/rejected/uncertain outcomes persist separately from delivery feedback. Uncertain recipients hold completion pending explicit evidence-based reconciliation. Provider feedback can arrive before acknowledgement and must not trigger replay. Delivery completion is submission work completion, not proof that all receiving servers have accepted mail; late events remain ingestible.

Mode, sender, content, recipient address, privacy flags and unsubscribe base freeze at scheduling. Later configuration cannot transmit prior simulations. Live scheduling requires SEND confirmation plus all pre-flight gates; imports/list changes never restore eligibility. See sendgrid-setup.md for retries, cancellation race limits, throttling and reconciliation. Test preparation creates a distinct one-contact draft and never submits automatically.
