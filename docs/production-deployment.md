# Production deployment runbook — preparation only

No host has been provisioned or deployed. Run only after explicit deployment approval. Pilot and broader sending require separate approval. See production-readiness.md for mandatory outstanding controls.

## Infrastructure and secrets

Use a supported Linux distribution, PHP 8.3+ with the extensions in development-setup.md (including OpenSSL, PDO MySQL, PCNTL/POSIX), Composer, Node for asset builds, Nginx/PHP-FPM, supported MariaDB, Redis, HTTPS certificates, process supervision and cron. Pin installed dependencies to lockfiles. Serve only public/, deny dotfiles, disable PHP execution in uploads, limit request bodies and avoid public storage exposure for imports. Use a dedicated non-root application account and separate least-privilege database credentials. Bind database/Redis to private interfaces, firewall access, require appropriate authentication/TLS for remote services. Restrict SSH and administration access. Set reliable UTC/NTP.

Provision APP_KEY once through a secret manager. Back it up separately: encrypted webhook batches/sessions require the key. Never rotate it casually. Secrets include DB_PASSWORD, REDIS_PASSWORD, APP_KEY and SENDGRID_API_KEY. Keep environment files outside web root with restrictive permissions; redact them from diagnostics. Do not put keys in shell history. Production uses APP_ENV=production, APP_DEBUG=false, HTTPS APP_URL, SESSION_SECURE_COOKIE=true, SESSION_ENCRYPT=true, database sessions and Redis queue/cache. Trust only explicit proxy IP/CIDR values through DISPATCH_TRUSTED_PROXIES. ProductionSafety rejects debug/insecure cookie/HTTP configurations and HTTP requests; configure TLS/proxies before opening the site. HTTPS responses set HSTS, nosniff and same-origin referrer policy; authenticated pages use private no-store. Validate these on the actual reverse proxy.

Start with simulation and all SendGrid/auth/live gates false. Configure a single stable public APP_URL before snapshots: unsubscribe URLs require that host to remain available. Do not reuse local accounts, cookies, fixtures or the development database in production.

## Release procedure

Build a versioned release directory from a reviewed commit; link persistent private storage and provisioned environment. Establish an encrypted database and storage backup before changes. Install dependencies and build frontend assets before the post-deployment action runs. In Plesk, open **Websites & Domains → Git → Repository Settings**, enable post-deployment actions, and set the action to:

```sh
/bin/bash scripts/plesk-post-deploy.sh
```

The script uses `/opt/plesk/php/8.3/bin/php` by default (override with `DISPATCH_PHP_BIN` when this site uses a different Plesk PHP version). On every deployment it verifies the Laravel install and frontend manifest, runs `php artisan migrate --force`, refreshes config/route/view caches, then runs `php artisan horizon:terminate` so the configured supervisor restarts Horizon on the new code. It does not reset or seed data, enable sending, or create a backup. Configure a protected backup before deployment separately; Plesk should report deployment failure if a migration or cache step fails.

These instructions do not deploy or run against a host from this workspace. Coordinate maintenance with running workers: stop initiation and let in-flight requests settle before changing code/schema. Point the release symlink only after smoke checks and compatible migration review. Writable paths are storage/ and bootstrap/cache/ only, owned/grouped for PHP-FPM and worker users; avoid chmod777. Do not reseed/reset established data. Keep source maps/private logs out of public access. Record migration/commit/backup IDs.

## Horizon and scheduler

Supervise `php /srv/dispatch/current/artisan horizon` with systemd/Supervisor, non-root app user, working directory current, automatic restart and stop timeout at least 180 seconds. Capture stdout/stderr to managed private logs. Production Horizon maxProcesses defaults to10; review account rate limit before increasing concurrency. Worker timeout150s must remain below Redis retry_after180s; per-recipient timeout60s and import timeout120s. SendGrid HTTP timeout15s. Use dedicated Redis namespace/service per environment and a shared lock-capable cache across web/worker nodes.

Cron as the app user:

```cron
* * * * * cd /srv/dispatch/current && php artisan schedule:run >> /var/log/dispatch/scheduler.log 2>&1
```

Schedule runs dispatch:tick and dispatch:recover-submissions every minute and dispatch:prune-feedback daily. Ensure one scheduler authority or distributed overlap locks. `php artisan schedule:list`, `php artisan horizon:status`, `/horizon` (Platform Administrator only), and `php artisan queue:failed` provide checks. Never blindly retry failed submission jobs with uncertain attempts. Recovery/reconciliation is described in sendgrid-setup.md. Test an import, simulated campaign, paused campaign and worker restart on staging before production.

## Monitoring, retention and incidents

Configure external HTTPS checks, scheduler heartbeat, Horizon queue delay/failed jobs, Redis memory/persistence, database disk/connections, error-rate alerts, webhook batch backlog/quarantine, uncertain submissions, provider429/auth errors, bounce/complaint thresholds, certificate expiry and backup age. Alerts need an assigned owner and escalation route. This repository documents these requirements; an external monitoring service has not been configured.

Use private log directories, logrotate with restricted ownership, compression and approved retention. Avoid request-body/access logging on webhook and unsubscribe endpoints; URL tokens must be redacted from proxy logs. Framework auth log transport includes reset/verification links: never use it as production delivery or ship those logs to broad analytics. Set appropriate LOG_LEVEL, exclude secrets/PII, and test redaction. Set environment-specific feedback retention; support deletion and suppression preservation per contact-eligibility.md. Do not automatically retry all failed jobs or release quarantined events.

## Backup, restore and rollback

Schedule encrypted MariaDB consistent backups (mariadb-dump --single-transaction where compatible), private storage backups and versioned release/config metadata. Protect encryption keys separately and store encrypted backups off-host with restricted access and tested retention. Redis persistence supports queue recovery but is not a substitute for database backups. Choose and approve RPO/RTO; suggested pilot target daily backup plus pre-release snapshot, refine to actual risk.

Restore drill on an isolated host: disable live/auth gates and egress, restore DB/private storage using separately retrieved keys, verify row counts/tenant boundaries/encrypted batch decryption, start isolated Redis, run migrations appropriate to restored release, authenticate through test accounts, exercise imports/simulation/unsubscribe, and record elapsed recovery/evidence. Do not replay restored external-submission jobs: first reconcile accepted/uncertain attempts with provider evidence. No successful restoration drill has occurred in this phase.

Rollback: disable live gates and new scheduling, pause workers, preserve attempt/webhook evidence, switch to the prior compatible release and rebuild config/routes/views; restart workers after verification. Prefer forward fixes for additive schema. Do not blindly migrate:rollback or restore an old DB over accepted submissions: that can lose suppression/evidence and create duplicate delivery. If restore is necessary, reconcile all post-backup provider submissions and suppressions before any sending. Test this procedure on staging and record results before launch.
