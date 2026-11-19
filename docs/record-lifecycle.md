# Record tidy-up and recovery

Dispatch provides Active, Archived and Recently deleted views for company contacts, mailing lists and campaigns.

## Contacts

Archiving changes the contact status to archived, which excludes the contact from campaign audiences. Recently deleted uses a reversible database soft delete. Restoring a deleted contact leaves its prior active or archived status, eligibility and suppression records unchanged. Contacts involved in a scheduled, processing or paused campaign cannot be moved to Recently deleted until that campaign is cancelled or completed. A deleted address remains reserved by the tenant-local unique constraint, so restore it rather than importing the same address again.

Contact data erasure remains a separate privacy action. It redacts the contact’s personal fields and historical snapshots as permitted by that workflow, and retains minimal suppression and audit records. Ordinary deletion does not erase this evidence.

## Mailing lists

Archived lists remain available for review and can be restored. They are excluded from new campaign audience selection and dynamic segment membership. Lists selected by a ready or active campaign cannot be archived. A list that appears in campaign history or a saved dynamic segment cannot be deleted; archive it instead. Deleting an unused list moves it to Recently deleted and preserves its membership rows for recovery.

## Campaigns

Scheduled, processing and paused campaigns must be cancelled or completed before archiving. Only unsent drafts without a recipient snapshot can be moved to Recently deleted. Campaigns with a snapshot or delivery activity can be archived, preserving recipients, provider events, attempts and reports. Restoring a deleted campaign returns it as a draft; restoring an archived campaign retains its existing terminal status. Archived campaigns cannot be edited, prepared or scheduled until restored.

## Users

Platform administrators can edit account name and email, activate or suspend accounts, and manage company memberships and roles. Changing an email address clears verification, sends a verification message to the new address and revokes existing sessions. Accounts are suspended rather than deleted so company membership, audit attribution and ownership history remain intact. The final active Platform Administrator and final company Owner remain protected by existing safeguards.

## Retention

Recently deleted items are reversible and are not automatically purged by this feature. Permanent deletion is intentionally not exposed for contacts, campaigns or lists with dependencies or compliance history. A later retention policy must account for recipient foreign keys, provider feedback, audit history and suppression records before adding automated purging.
