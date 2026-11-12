# Contact eligibility and suppression

Stored contact data is separate from marketing permission. Dispatch does not infer permission from an email address, company name, list membership or segment rule. From 12 November 2026, the operator-requested CSV policy automatically assigns application eligibility to newly imported contacts; this is not a claim that consent was collected.

## Model

Manually created contacts start as `unknown`. New CSV contacts receive `csv_import` eligibility without a separate evidence form. The import automatically appends a record containing the importing user, source, import ID, row and processing timestamp; it does not label them as consented. Suppressed addresses remain blocked. A company owner, administrator or campaign manager can append evidence using the contact detail screen: basis (`consent`, `documented_basis` or `unknown`), explanation, method, evidence timestamp and optional notice/policy version. Records retain the actor and source. Evidence is append-only through the application.

`documented_basis` means the operator has recorded an assessment; it does not mean Dispatch has determined that a legal basis is sufficient. Do not use it as a blanket “B2B permitted” flag.

Central EligibilityService requires an active contact, a consent/documented-basis/CSV-import state, a corresponding eligibility record, and no suppression for that organisation/email. The same query supplies eligible audience counts and scheduling; every recipient is checked again under a contact lock before simulated submission. Email changes reset eligibility to unknown; existing suppression is also carried to the new address. The former address remains suppressed.

Suppression reasons include unsubscribed, suppressed, hard_bounce, complaint and administratively_blocked. Suppression persists across lists, archive/reactivation, imports and ordinary eligibility edits. There is deliberately no casual “unsuppress” endpoint. Any future restoration workflow must require independent evidence and preserve history. Simulated permanent rejections create clearly evidenced simulation-based hard-bounce suppressions in this development environment.

## Lists, segments and imports

Lists store membership only. Segment rules are a bounded, structured AND predicate: status, source, company name, list ID, creation date or a validated custom-attribute key. No frontend SQL is accepted. CSV processing imports profile data and automatically assigns `csv_import` eligibility to new, unsuppressed contacts. No additional evidence entry is required. Existing contacts are not updated, even when they are unknown, archived or have withdrawn eligibility; historical imports are not retroactively promoted. Duplicate rows never overwrite existing contact or suppression information.

## UK context

Implementation follows a conservative evidence-first design, not a legal certification. The ICO distinguishes individual and corporate subscribers; individual subscribers generally require consent or all applicable soft-opt-in conditions, while corporate subscribers have different PECR rules. Data protection duties and opt-out requirements still matter. A business-looking address is not evidence of subscriber type or permission.

Sources reviewed for this phase:

- [ICO: direct marketing using electronic mail](https://ico.org.uk/for-organisations/direct-marketing-and-privacy-and-electronic-communications/guidance-on-direct-marketing-using-electronic-mail/)
- [ICO: complying with the electronic mail marketing rules](https://ico.org.uk/for-organisations/direct-marketing-and-privacy-and-electronic-communications/guidance-on-direct-marketing-using-electronic-mail/how-do-we-comply-with-the-pecr-electronic-mail-marketing-rules/)

These rules require a fresh operational/legal review before implementing or approving real sending. An unsubscribe link alone does not establish compliance.

## Phase 1C privacy and feedback

Signed provider hard bounces, complaints and unsubscribes apply the same tenant suppression service used by visible and one-click unsubscribe. Suppression remains effective across imports/lists/copies/address changes. Tracking is off by default; owner/admin review records evidence and permits explicit campaign flags, not automatic legal permission.

Owner/admin contact JSON export and explicit ERASE action provide a restricted privacy workflow. Active campaigns/imports block erasure; completed recipient personalisation and profile/evidence fields are redacted, matching raw uploads removed, minimal original-address suppression retained. Audit IDs/counts remain. Retained suppression is personal data requiring approved access/retention controls. Provider-held data, backups and legal exceptions still need an operator process.

UK policy decisions remain the client's responsibility: subscriber category, consent or other applicable eligibility, documented source/evidence, privacy notice, tracking technologies, retention and data subject requests. Email permission does not by itself settle tracking-pixel storage/access requirements. See [ICO electronic mail guidance](https://ico.org.uk/for-organisations/direct-marketing-and-privacy-and-electronic-communications/guidance-on-direct-marketing-using-electronic-mail/what-else-do-we-need-to-consider/) and [ICO storage/access technology guidance](https://ico.org.uk/for-organisations/direct-marketing-and-privacy-and-electronic-communications/guidance-on-the-use-of-storage-and-access-technologies/what-are-storage-and-access-technologies/). No legal compliance certification or client approval has been inferred.
