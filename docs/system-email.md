# System email (platform SMTP)

Password resets, email verification and company invitations use the same platform Laravel mailer. Marketing campaigns still use the organisation's existing SendGrid integration and live-send safeguards. Invite-only registration and manual invitation links remain.

## Default staging mode

Keep `MAIL_MAILER=log`. No email leaves the application. Messages, including usable private links, are written to `storage/logs/account-mail-YYYY-MM-DD.log` by default, independently of the app log level. The default channel creates private (0600) daily files and rotates them with a two-file retention setting. Keep access restricted; never export these logs or share links outside the intended account flow. If an installation explicitly selects `MAIL_LOG_CHANNEL`, check its access, verbosity and retention separately.

## Opt in to authenticated SMTP

Set these on the client host only once the mailbox and controlled test recipients are approved. Use the SMTP provider's actual settings.

```dotenv
MAIL_MAILER=smtp
MAIL_SCHEME=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=notifications@example.com
MAIL_PASSWORD="replace-with-the-SMTP-password"
MAIL_FROM_ADDRESS=notifications@example.com
MAIL_FROM_NAME="${APP_NAME}"
```

For implicit TLS on port 465 set `MAIL_SCHEME=smtps`. For port 587 use `smtp` with STARTTLS; TLS is required by the transport. Use a sender your SMTP service permits. Keep credentials server-side. Legacy `SENDGRID_AUTH_*` and the environment-level `SENDGRID_API_KEY` are no longer used. Company SendGrid keys are unchanged.

Ensure `APP_URL` is the correct public HTTPS hostname. Rebuild the config cache with the site PHP binary and reload long-lived processes after configuration changes. No database migration is needed. Invitations are attempted synchronously once the database transaction commits, including owner invitations for newly provisioned companies. If invitation mail fails, the saved invitation and manual link remain usable; inspect the restricted application log for the warning. Do not blindly retry an uncertain SMTP send. Generating another link for the same invitation replaces the old link.

Before relying on account email, use authorised test accounts to verify receiving an invitation and creating the correct account, verification, and password reset. Check the destination and From display, the HTTPS links and expiry. To revert to log-only, change `MAIL_MAILER=log` and refresh the config cache. No external SMTP provider has been tested as part of the repository changes.
