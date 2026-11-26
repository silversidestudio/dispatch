# Global unsubscribe list import

Campaign Manager stores suppressions at the organisation level. An address on this list is excluded from every campaign in that organisation, regardless of mailing-list membership. The record is separate from contacts, so an unsubscribe can be imported even when the address is not otherwise stored as a contact.

## Importing a legacy list

1. Export the client's historic global unsubscribe list as CSV with a header row and an email-address column.
2. Open **Contacts → Global suppressions** in the correct company workspace.
3. Upload the unsubscribe CSV, identify the email column in the preview, and start the queued import.
4. Review added, already-suppressed and rejected row counts. Download the row error file if any addresses need correction.
5. Only after the suppression import is complete, import the active contacts and create or populate their mailing lists.

Do not upload an unsubscribe export through the ordinary contact-import page. Contact imports and suppression imports have different effects.

## Import behavior

- Email addresses are trimmed and lowercased before matching.
- New rows create `unsubscribed` suppression records directly; no contacts or list memberships are created.
- Existing suppression records and their original reason/evidence are preserved.
- If a matching contact exists, its eligibility is updated to match the existing suppression reason.
- Repeating a job does not duplicate suppressions or completed row results.
- Suppression imports are company-scoped. The same address can have a distinct record in another organisation.
- Each CSV is limited to 5 MB and 25,000 data rows by default. Contact and suppression imports share the `DISPATCH_IMPORT_MAX_ROWS` setting; split larger exports into separate files.
- The upload is stored on the application's private local storage, and import history plus aggregate results are retained for audit. Rejected-row exports contain row numbers and error descriptions, not email values.

Dispatch checks suppressions during audience eligibility calculation and again before provider submission. A later contact import or list membership cannot override a suppression.
