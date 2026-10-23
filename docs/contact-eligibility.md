# Contact eligibility and suppression

Stored contact data is separate from marketing permission. Dispatch never infers permission from an email address, company name, list membership, segment rule or CSV upload.

## Model

New and imported contacts start as `unknown`. A company owner, administrator or campaign manager can append evidence using the contact detail screen: basis (`consent`, `documented_basis` or `unknown`), explanation, method, evidence timestamp and optional notice/policy version. Records retain the actor and source. Evidence is append-only through the application.

`documented_basis` means the operator has recorded an assessment; it does not mean Dispatch has determined that a legal basis is sufficient. Do not use it as a blanket “B2B permitted” flag.

Central EligibilityService requires an active contact, a consent/documented-basis state, a corresponding evidence record, and no suppression for that organisation/email. The same query supplies eligible audience counts and scheduling; every recipient is checked again under a contact lock before simulated submission. Email changes reset eligibility to unknown; existing suppression is also carried to the new address. The former address remains suppressed.

Suppression reasons include unsubscribed, suppressed, hard_bounce, complaint and administratively_blocked. Suppression persists across lists, archive/reactivation, imports and ordinary eligibility edits. There is deliberately no casual “unsuppress” endpoint. Any future restoration workflow must require independent evidence and preserve history. Simulated permanent rejections create clearly evidenced simulation-based hard-bounce suppressions in this development environment.

## Lists, segments and imports

Lists store membership only. Segment rules are a bounded, structured AND predicate: status, source, company name, list ID, creation date or a validated custom-attribute key. No frontend SQL is accepted. CSV processing imports profile data only; any eligibility evidence must be recorded separately after review. Duplicate rows never overwrite existing contact or suppression information.

## UK context

Implementation follows a conservative evidence-first design, not a legal certification. The ICO distinguishes individual and corporate subscribers; individual subscribers generally require consent or all applicable soft-opt-in conditions, while corporate subscribers have different PECR rules. Data protection duties and opt-out requirements still matter. A business-looking address is not evidence of subscriber type or permission.

Sources reviewed for this phase:

- [ICO: direct marketing using electronic mail](https://ico.org.uk/for-organisations/direct-marketing-and-privacy-and-electronic-communications/guidance-on-direct-marketing-using-electronic-mail/)
- [ICO: complying with the electronic mail marketing rules](https://ico.org.uk/for-organisations/direct-marketing-and-privacy-and-electronic-communications/guidance-on-direct-marketing-using-electronic-mail/how-do-we-comply-with-the-pecr-electronic-mail-marketing-rules/)

These rules require a fresh operational/legal review before implementing or approving real sending. An unsubscribe link alone does not establish compliance.
