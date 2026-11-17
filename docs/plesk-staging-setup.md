# Plesk Linux staging preparation

Prepared 11 November 2026. These are instructions, not evidence of deployment. No staging host has been accessed. Dispatch is ready for a staging exercise, not certified for production sending.

## Host information needed next

Provide the Plesk version, Linux distribution, staging hostname, server IP, SSH/subscription access method, selected PHP version and whether this is a dedicated VPS or shared hosting. Confirm availability of MariaDB, Redis, long-running Horizon processes and permission to install a process supervisor. Credentials must be installed through a secure server-side mechanism, not committed or pasted into documentation. SendGrid account/domain details can follow after staging is operational.

## Prerequisites

Use PHP 8.3 or a version supported by composer.lock, Composer 2, MariaDB, Redis, HTTPS and Node compatible with package-lock.json for the asset build. PHP web, CLI and workers need matching extensions: PDO MySQL, mbstring, XML/DOM, fileinfo, cURL, OpenSSL and the Redis client required by the existing configuration. Horizon CLI also requires pcntl and posix. Shared hosting without persistent Redis/Horizon support is unsuitable.

Plesk PHP CLI commonly lives at /opt/plesk/php/8.3/bin/php; verify this on the actual server. Use that binary consistently for Composer, Artisan, cron and Horizon. Never assume the system php matches the site's PHP-FPM version.

Set the site's document root to the release's **public** directory. Only public assets are web accessible. Block access to .env, storage, vendor, repository files and backups. Run PHP-FPM and workers as the subscription's unprivileged user. Grant write access only where needed (storage and bootstrap/cache), never world-write permissions. Protect staging using access controls that can later exempt the public signed webhook and unsubscribe routes; verify this explicitly during the authorised pilot.

## Initial safe environment

Create a separate staging database and database account, isolated Redis namespace/database and new APP_KEY. Do not copy local credentials or real client contact data. Use fake data or an explicitly approved anonymised dataset. Keep a single stable HTTPS APP_URL, APP_ENV=production, APP_DEBUG=false and secure session cookies; review trusted proxies against the actual Plesk/reverse-proxy topology. Production environment on staging exercises Horizon's existing production supervisor configuration and application security gates.

Start with:

```dotenv
DISPATCH_DELIVERY_PROVIDER=simulation
SENDGRID_ENABLED=false
SENDGRID_LIVE_SENDING_ENABLED=false
DISPATCH_PILOT_ENABLED=true
DISPATCH_PILOT_DAILY_LIMIT=25
DISPATCH_PILOT_RECIPIENTS=
DISPATCH_PILOT_ORGANISATION_IDS=
```

The actual simulation value is `simulation`, not `simulated`. Empty pilot scope deliberately blocks live campaigns. Open/click tracking remains off pending approval. Account verification/reset messages remain in protected application logs during this stage; this is not production account-email readiness. Do not expose logs or verification/reset links. Account email delivery must be separately authorised and tested before launch. See [system email setup](system-email.md).

## Release procedure — execute later on approved staging only

Back up database and release configuration first. Install the reviewed Git release without credentials in source control. Typical commands (using the verified PHP binary) are:

```sh
/opt/plesk/php/8.3/bin/php artisan storage:link
```

The Git post-deployment script installs Composer and npm dependencies and builds frontend assets as part of each release. It uses Node 26 from the repository's `.node-version`; Node 26 and nodenv must be available to the Plesk subscription user. For a manual first installation without the Git post-deployment action, install the locked Composer and npm dependencies and build the assets as the same subscription user before opening the site.

Generate APP_KEY only for a brand-new installation; never regenerate an existing application's encryption key. Migrate additively; never use migrate:fresh or reset/reseed an existing database. In **Websites & Domains → Git → Repository Settings**, enable post-deployment actions and configure `/bin/bash scripts/plesk-post-deploy.sh`. It automatically builds assets, applies pending migrations with `migrate --force`, refreshes Laravel caches and signals Horizon to restart on every release. Take a protected database/storage backup before deployment; the hook does not make backups. Set `DISPATCH_PHP_BIN` if Plesk uses a different PHP CLI than `/opt/plesk/php/8.3/bin/php`. Provision accounts through registration and promote an explicitly chosen existing user with `php artisan platform:promote address@example.test`. Use the normal password workflow; no shared hardcoded admin password. Confirm configuration and permissions before opening access.

## Workers and scheduler

Use Supervisor or systemd to run `php artisan horizon` continuously under the subscription user with the correct working directory, APP_ENV and PHP binary. Configure automatic restart and sufficient stop timeout for in-progress jobs. Plesk Laravel Toolkit also offers queue management, but do not run duplicate Toolkit queue workers alongside Horizon. Verify failed jobs and Horizon authorisation in the application.

Configure exactly one scheduler: Plesk Laravel Toolkit scheduler or a once-per-minute Plesk scheduled command running `php artisan schedule:run` in the release directory. Do not also start schedule:work on the server. Confirm timezone, due campaign dispatch and retention jobs. After a release, use `php artisan horizon:terminate` and let the supervisor restart workers with new code/configuration.

## Required staging review

Check HTTPS, debug off, cookies, registration, verification/reset handling, company switching, permissions and tenant boundaries. Complete a fake-data import, eligibility/list/segment selection, basic template edit on phone and desktop, plain-text generation, campaign preview/review/schedule and simulated completion/report. Verify upload limits and writable directories. Execute concurrency/failure-recovery tests against a dedicated test database only, never the staging client database.

Configure restricted log access, log rotation, queue failure alerts, webhook quarantine alerts and health checks. Establish encrypted/off-server backups including database, application key and required files. Perform and record a restore drill on an isolated database/host. Set retention and remove fake acceptance-test accounts before client use. Existing local tests do not verify these server operations.

## Later SendGrid connection and authorised pilot

1. Confirm trial/account approval, sender/domain ownership, contact source/eligibility, postal address and privacy/tracking decisions. Install scoped SendGrid API secret securely. No credentials are required to complete the local work.
2. Configure provider sandbox first, retaining live=false. Validate an actual sandbox API request; sandbox sends no message and produces no delivery events. Do not treat this as live integration verification.
3. Manually configure the authenticated sending domain using the exact records supplied by that SendGrid account. Review Cloudflare proxy status and DKIM/SPF/DMARC with the domain owner. No DNS changes have been made by this work.
4. Configure a publicly accessible HTTPS signed Event Webhook and verification key. Verify signature rejection, accepted events and tenant-safe correlation using the real endpoint. Confirm public unsubscribe pages and sessionless one-click POST.
5. After explicit approval only, allowlist one company ID and a small exact list of controlled test addresses; retain pilot=true and daily limit=25. Explicitly enable live delivery and authorise a newly reviewed live campaign. Existing simulated campaigns never switch mode automatically. The application's cap is global per UTC calendar day and counts reserved attempts, including uncertain/failed requests; it cannot govern SendGrid traffic from other applications.
6. Send one approved message, verify rendered font/link/footer on actual Gmail and Outlook clients, receiving-server event, recipient timeline and unsubscribe. Then use approved bounce testing. Authorise tracking separately before testing it. Record evidence, duplicate checks and worker health. Pause on uncertain submissions and reconcile against provider evidence; never retry blindly or clear reservations to force capacity.
7. Obtain client pilot sign-off before increasing scope. Do not disable the pilot restriction or enable account notifications merely to make a test pass. Wider sending requires a separate reviewed decision and launch checklist completion.

## Backup and rollback

Follow production-deployment.md for backup, restore and rollback detail. A rollback starts by disabling live sending and pausing affected campaigns, then stopping submission workers safely and preserving attempt/webhook evidence. Restore code and compatible configuration from a known release. Do not erase accepted or uncertain attempts, roll back the pilot ledger, or restore an old database over current delivery history to resume sending. Test migration compatibility and restore procedures on isolated infrastructure before launch.

## Official references

- [Plesk Laravel Toolkit](https://docs.plesk.com/en-US/obsidian/administrator-guide/website-management/laravel-toolkit.80010/)
- [Plesk scheduled tasks](https://support.plesk.com/hc/en-us/articles/12377989953303-How-to-enable-scheduled-tasks-for-Laravel-project-in-Laravel-Toolkit)
- [Laravel Horizon](https://laravel.com/docs/12.x/horizon)
- [SendGrid sandbox mode](https://www.twilio.com/docs/sendgrid/for-developers/sending-email/sandbox-mode)

Consult these against the supplied server version before applying commands. Plesk access, DNS, real sandbox/live calls, external webhook verification, email-client checks, monitoring and restore evidence remain outstanding.

Public registration is closed. For a fresh installation, run `php artisan platform:bootstrap` interactively. Create the company and invite approved staff from Platform administration. For the pilot, enter company keys on the approved HTTPS site under **Sending identities**; use the company-specific webhook endpoint shown there. No campaign falls back to an environment API key.
