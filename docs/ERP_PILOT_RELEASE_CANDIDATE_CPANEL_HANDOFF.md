# DhakaWala ERP — Pilot Release Candidate Handoff (code verification)

**Status: release-candidate code preparation, NOT live approval.**
Development source: `security/production-readiness-gates` (NOT `main`).
Freeze the exact SHA from the final all-green GitHub Actions run before packaging/deployment.
The current tenant, finance and privacy external verification blocker remains [Issue #18](https://github.com/shohan-55/erp/issues/18).

## Final code gates

- Fail closed if session revocation cannot be enforced: **database** session driver is required. The application resets passwords and turns off MFA by invalidating previous entries in the `sessions` table.
- Encrypt session payloads at rest (`SESSION_ENCRYPT=true`) because TOTP setup temporarily stores an enrollment secret in session state.
- Require HTTPS/secure session cookies, HttpOnly, SameSite Lax/Strict and host-only cookies (`SESSION_DOMAIN=null`, not a parent-domain wildcard).
- Reject Partner attendance corrections when the linked student membership belongs to another company/workspace or is inactive. Preserve the audit record and payroll freeze rules for authorized corrections.
- Run `php artisan erp:integrity:check` separately against an approved staging dataset. A clean result detects no anomalies among those checked; **do not** interpret it as verified external settlement or production approval.

## Phase A — Download *review* artifacts

1. Open the latest successful GitHub Actions run for the **reviewed development SHA**.
2. Confirm **all three jobs passed**: PHPUnit/browser/Vite, MySQL 8, cPanel release packaging.
3. In the workflow's Artifacts area, download `erp-cpanel-release-review`: `erp-cpanel-application.zip`, `erp-cpanel-split-webroot.zip`, `SHA256SUMS.txt`.
4. Independently check the SHA-256 hashes of both archives against `SHA256SUMS.txt`. Record nonsecret run ID and commit SHA. No `.env`, private data or QA DB is in either package.

**Neither GitHub CI nor packaging installs anything on cPanel.**

## Phase B — Isolated target account and backups

- Use the already intended **dedicated ERP WHM/cPanel account** serving `erp.dhakawala.com`. Verify actual authoritative DNS, SSL, PHP 8.4 CLI **and web** runtimes, hostname, disk/inode quota, private filesystem and MySQL support.
- Never overwrite the existing `dhakawala.com` hosting-billing site, its public_html, database, Git checkout or production credentials.
- Take encrypted offsite backup of existing ERP database and **private claim/payroll attachments** if any. Verify a restore into an isolated environment before handling real data. If new and blank, confirm the DB is empty and record that fact; do not assume.
- Use exactly one webroot mode:
  - **A, configurable docroot:** app private at `/home/<ERP_USER>/erp-app`; docroot `/home/<ERP_USER>/erp-app/public`; only application ZIP needed.
  - **B, fixed docroot:** app private at `/home/<ERP_USER>/erp-app`; copy only **contents** of `erp-webroot/` from split-webroot ZIP to that ERP account's dedicated `/home/<ERP_USER>/public_html`. Verify `index.php` references the sibling `erp-app`. Never unpack app source into public_html.
- Do not use `chmod 777`. Give writable permissions only to `storage` and `bootstrap/cache` as required by cPanel user.

## Phase C — Secure private configuration (no credentials in chat/GitHub)

On a new, isolated ERP instance, create `erp-app/.env` only inside the private directory, with account-unique secret APP_KEY (generate **once**, not during upgrades):

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://erp.dhakawala.com
DB_CONNECTION=mysql
SESSION_DRIVER=database
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
SESSION_DOMAIN=null
QUEUE_CONNECTION=sync
FILESYSTEM_DISK=local
MAIL_MAILER=smtp
```

Set real DB credentials, mail host/port credentials and verified sender *privately*; use `docs/CPANEL_RESELLER_DEPLOYMENT.md` for more detailed settings. Avoid setting any IP/GPS enforcement or actual salary deductions.

## Phase D — **staging** rehearsal (after approved private configuration)

From the private `erp-app` directory, with the same verified PHP 8.4 CLI binary:

```bash
php artisan erp:hosting:check
php artisan migrate:status
# Review the pending migrations first, then run on STAGING only:
php artisan migrate --force
php artisan erp:db:check
php artisan erp:release:check
php artisan erp:integrity:check
php artisan route:list --path=c
php artisan schedule:list
```

**Do not run migrations or payroll actions against live data without approval and a recoverable backup.** Separate cPanel cron/SMTP/HTTPS/private-file, browser/device/GPS permissions, company/Partner role, MFA account recovery, email delivery, attendance, leave, claim and payroll tests still need independent manual evidence. Do not mark any as passed from the CI results alone.

## Release hold / rollback

- If `erp:release:check` fails, an integrity check requires review, TLS is absent, SMTP isn't deliverable, private files are public, or rollback backups cannot be restored: **stop / NO-GO**.
- Do not point production traffic to an unverified site or invite live users, and do not enable GPS tracking by default.
- Keep the prior immutable artifact and independent backups before any later release. On rollback preserve live records and private receipts; never run blind `migrate:rollback` on real payroll data.
- Record external approval and staging/UAT evidence against [Issue #18](https://github.com/shohan-55/erp/issues/18) prior to accepting confidential staff/payroll records.

**Code complete for pilot-review is not equal to independently accepted production readiness.**
