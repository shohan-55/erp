# RA&C ERP SaaS — Production Deployment Runbook

**Release status: BLOCKED until production checklist and user acceptance are signed off.**

**Application branch:** security/production-readiness-gates (Pull Request #5). This review branch is not automatically deployed.

## Prerequisites

- Domain: erp.dhakawala.com, with HTTPS; configure the domain document root to Laravel's `public/`, NOT the repository root.
- Server: supported PHP 8.4 with extensions, Composer 2, MySQL 8 or compatible MariaDB. Configure TLS and trusted proxies accurately.
- Separate non-root DB user, with least privileges; preserve encrypted offsite DB and private-file backups.
- Persistent private storage for claim receipts and payroll evidence; never expose `storage/app/private` or `.env` via the public web root.
- `SESSION_DRIVER=database`, `SESSION_SECURE_COOKIE=true`, production cache, queue and cron configuration. `APP_DEBUG=false` and `APP_ENV=production`.
- External mailer for invitations and password recovery; real end-to-end delivery/expiry tests are mandatory.
- RA&C Partner policy approvals and independent Security/UAT evidence; no real student payroll or financial data before signoff.

## Safe order of operations

1. Obtain explicit deployment authorization and freeze a reviewed Git commit SHA. Do not deploy all open PRs: PR #3 and PR #4 are alternative backend designs.
2. Create a time-stamped encrypted offsite DB backup and private files backup. Restore these onto **separate staging** and document successful recovery.
3. On staging, checkout the reviewed SHA and run `composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction`.
4. Install/build frontend assets using Node 22 and `npm ci && npm run build` before the deploy. Never require Node on a restricted production hosting account if build artifacts are already packaged and verified.
5. Configure environment securely outside Git. Use a unique, secret APP_KEY (never regenerate on an existing instance with encrypted data). Set HTTPS APP_URL and secure session settings.
6. Run `php artisan migrate:status`, inspect schema changes, then `php artisan migrate --force` on staging first. Rehearse an approved rollback and re-restore strategy.
7. Start a persistent queue worker and the every-minute Laravel scheduler. In managed hosting, confirm actual cron/queue operation, not just command availability.
8. Run `php artisan erp:release:check`; abort on a nonzero exit. A green result is a configuration **precheck**, not overall production certification.
9. UAT in staging: individual Partner login and mandatory MFA setup; lost device/recovery codes; restricted Student and Manager roles; tenant isolation; mail activation/reset; attendance; leave; receipts; payroll draft/review/approve/paid/lock, including rejected unauthorized cross-partner actions.
10. Verify payment evidence download is only available to the owning Partner, approved amount exactly matches external settlement, and bank/cash proof has been independently verified by an authorized person.
11. Only after human release approval, deploy the exact reviewed artifact/SHA to the live server during an approved maintenance window. Verify HTTPS, login throttling, backups, mail, queue, storage, audit logs and monitoring.
12. Record deployed SHA, approver, deployment time, smoke test output, backup restore evidence and rollback instructions.

## Rollback

- Stop new financial and attendance writes during rollback. Preserve current DB/data and new private receipts.
- Roll back the application release using the previously approved immutable artifact. Do **not** run irreversible migrations backwards on production without a tested data-safe plan.
- If rollback requires DB restore, reconcile any activity since backup and obtain explicit firm approval to avoid losing legitimate transactions.
- Validate tenant scope, authentication and all five MVP modules before reopening.

## Explicit exclusions

- GitHub CI alone does not prove payment settlement, SMTP reachability, backup recoverability or server security.
- Authenticator-based MFA and amount/proof requirements reduce risk, but do not replace independent financial reconciliation or security testing.
- No production access credentials are required in GitHub, chat or source code.
