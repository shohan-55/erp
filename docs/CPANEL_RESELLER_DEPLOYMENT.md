# RA&C ERP SaaS — cPanel Reseller Shared Hosting Deployment

**Destination:** \`https://erp.dhakawala.com\` **on an isolated cPanel account provisioned from the existing reseller WHM, if supported by the hosting provider.**

**Release status: NOT YET APPROVED FOR LIVE DATA.** This file is a configuration playbook, not proof that the current reseller account provides PHP 8.3+, SSH, Cron, an isolated document root, SSL, sufficient resources, working SMTP, or backups.

## 1. Check the hosting account before deployment

From the cPanel account that will host the ERP, confirm:

| Requirement | What to verify |
| --- | --- |
| Domain | \`erp.dhakawala.com\` is created in the correct cPanel account and has a matching A/AAAA record in the **authoritative** DNS zone of \`dhakawala.com\`. The registrar is NOT where this is normally configured if external nameservers are active. |
| Laravel runtime | PHP 8.3+ (PHP 8.4 preferred), for both the website in MultiPHP Manager **and** the Cron/Terminal PHP CLI. |
| PHP extensions | ctype, curl, dom, fileinfo, filter, hash, mbstring, openssl, pcre, pdo, pdo_mysql, session, tokenizer, xml; zip helpful for on-server Composer. |
| DB | MySQL 8 or supported MariaDB, with a dedicated database and least-privilege user. |
| File locations | The domain Document Root points **only** at the project's \`public/\` directory. If cPanel cannot do this, stop and contact hosting support; DO NOT move Laravel app code into public_html. |
| File permissions | cPanel user may write \`storage/\` and \`bootstrap/cache/\`. App and .env files are outside public document root. |
| Cron | The account supports cPanel Cron Jobs. A once-per-minute cron is ideal; if limited to every five minutes, confirm schedule timing and UAT latency explicitly. |
| Terminal/SSH | Preferred for Artisan and diagnostics. If disabled, request SSH/Terminal for this particular cPanel account, or plan a provider-assisted deployment. |
| Mail | A real, deliverable SMTP/API mail provider for password resets and invitations; no mail log/test transport in production. Port 587 typically uses STARTTLS; verify with the provider. The current Laravel mail config reads `MAIL_SCHEME`, not `MAIL_ENCRYPTION`. |
| SSL | AutoSSL or equivalent certificate installed; forced HTTPS on the entire ERP host. |
| Resources | Confirm allowed PHP execution/memory limits, disk inode quota, database size, concurrent request/CPU limits, and host backup policy with the reseller provider. |
| Restore | Separate encrypted offsite backup of both MySQL database and private receipt/payment evidence files, with actual restoration demonstrated. |

**Recommended architecture:** dedicate a separate WHM/cPanel account to \`erp.dhakawala.com\` to reduce cross-site exposure from other hosting customers. Provisioning \`erp.dhakawala.com\` as the primary domain of that account may require the reseller provider's approval. If there is no safe isolated account, reconsider before storing sensitive financial data.

## 2. Required folder layout

Example using **placeholders**, not a verified server path:

\`\`\`
/home/<ERP_CPANEL_USER>/
├── erp-app/                   # complete private Laravel project root
│   ├── .env                  # never public or committed to Git
│   ├── artisan
│   ├── app/
│   ├── bootstrap/
│   ├── database/
│   ├── public/
│   │   ├── index.php
│   │   └── build/            # compiled Vite assets
│   ├── resources/
│   ├── routes/
│   ├── storage/app/private/  # receipts / payroll proof
│   └── vendor/               # composer dependencies
└── [other account files]
\`\`\`

The cPanel domain's actual configured **document root must be \`/home/<ERP_CPANEL_USER>/erp-app/public\`** or an equivalent private app path. Do not copy the entire repository into \`public_html\`. The domain's document root path depends on provider controls; verify before uploads. Avoid \`777\` permissions.

## 2A. The two cPanel webroot modes — choose exactly one

A successful GitHub Actions run (after SQLite, MySQL 8 and browser tests all pass) uploads a **review-only** artifact containing:
- \`erp-cpanel-application.zip\`: complete private project with vetted production \`vendor/\` and compiled \`public/build/\` assets, but no \`.env\`, database contents, test fixtures or development tools.
- \`erp-cpanel-split-webroot.zip\`: **public assets only**, with \`index.php\` adjusted to load \`../erp-app/vendor/autoload.php\` and \`../erp-app/bootstrap/app.php\`.
- \`SHA256SUMS.txt\`: verify the files you download were not altered.

**Mode A — adjustable subdomain Document Root:** extract the private archive into \`/home/<USER>/erp-app\` and set the subdomain document root to \`/home/<USER>/erp-app/public\`. Do not deploy the split-webroot archive.

**Mode B — fixed \`public_html\` in a DEDICATED ERP cPanel account:** extract the private application to \`/home/<USER>/erp-app\`, then copy **only the contents of** the split webroot's \`erp-webroot/\` directory into \`/home/<USER>/public_html/\`. Its patched index file loads the private app from a sibling \`erp-app\` folder. This mode is correct only if \`public_html\` belongs exclusively to \`erp.dhakawala.com\` and both paths are siblings. Never overwrite the existing DhakaWala hosting-business site or any other live cPanel account's \`public_html\`.

**Do not mix modes or copy Laravel source/config/vendor into any web-accessible document root.** These packages are deployment artifacts for approval, not a live deployment or production certification.

## 3. Build/deploy strategy on constrained shared hosting

Preferred: GitHub CI builds and tests the exact reviewed commit, with Composer production vendor dependencies and the Vite manifest/assets prepared for PHP 8.4 **before** deployment. No Node server, Vite dev process, Docker, Supervisor or persistent queue daemon is required for the first small RA&C deployment. Ensure no CI \`.env\`, fake QA DB/users, or secret materials are included in any release artifact.

A direct checkout plus \`composer install --no-dev --optimize-autoloader\` is an alternative **only when SSH, Composer and sufficient shell memory are available**. Match the production PHP version and Composer platform to the CI build. Do not use \`composer update\` on production.

Do not merge competing backend PR #3/#4 indiscriminately; only deploy an independently reviewed single backend commit.

## 4. Safe environment settings

Create \`.env\` securely for the cPanel user, never in GitHub or chat. The following example deliberately omits all credentials:

\`\`\`dotenv
APP_NAME="RA&C ERP"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://erp.dhakawala.com
APP_TIMEZONE=Asia/Dhaka
DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=<CREATE_IN_CPANEL>
DB_USERNAME=<CREATE_IN_CPANEL>
DB_PASSWORD=<SET_PRIVATELY>
SESSION_DRIVER=database
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
SESSION_COOKIE=rac_erp_session
SESSION_DOMAIN=null
CACHE_STORE=database
CACHE_PREFIX=rac_erp_
QUEUE_CONNECTION=sync
FILESYSTEM_DISK=local
LOG_CHANNEL=stack
LOG_LEVEL=warning
MAIL_MAILER=smtp
MAIL_HOST=<CONFIGURE_PRIVATELY>
MAIL_PORT=587
MAIL_USERNAME=<CONFIGURE_PRIVATELY>
MAIL_PASSWORD=<CONFIGURE_PRIVATELY>
MAIL_SCHEME=smtp
MAIL_FROM_ADDRESS=<VERIFIED_SENDER>
MAIL_FROM_NAME="RA&C ERP"
\`\`\`

Generate APP_KEY once **only for a brand-new blank instance**. Preserve and back up this key securely. Rotating it later without a migration plan breaks encrypted MFA secrets and invalidates encrypted data.

We deliberately start with \`QUEUE_CONNECTION=sync\` because basic invitations and mail sends can work synchronously for a small pilot without an always-on queue worker. Evaluate request timeouts with the real SMTP provider. Switch to \`database\` queues only after a reliable queue processing mechanism is configured, tested and monitored.

## 5. cPanel Cron Jobs

Laravel already defines the prior-month payroll **draft only** for **day 1 at 03:30 Asia/Dhaka**. It NEVER automatically approves, pays or locks money.

Identify the verified PHP 8.4 CLI binary in the actual cPanel account; cPanel commonly exposes \`/usr/local/bin/ea-php84\`, but do **not** assume this path is present on your server.

The usual cPanel Cron Job is (exact paths must be replaced):

\`\`\`cron
* * * * * /usr/local/bin/ea-php84 /home/<ERP_CPANEL_USER>/erp-app/artisan schedule:run >> /home/<ERP_CPANEL_USER>/erp-scheduler.log 2>&1
\`\`\`

If the provider enforces a **five-minute minimum**, the expression may be:

\`\`\`cron
*/5 * * * * /usr/local/bin/ea-php84 /home/<ERP_CPANEL_USER>/erp-app/artisan schedule:run >> /home/<ERP_CPANEL_USER>/erp-scheduler.log 2>&1
\`\`\`

Verify the scheduler's selected minute with \`php artisan schedule:list\` and the cPanel server timezone. Laravel converts scheduled Asia/Dhaka time separately, but cron must actually invoke it at the intended moment. Every-five-minute cron may delay other scheduled tasks; never silently assume every-minute service.

Keep these log files **outside** the domain's public document root. Configure log rotation and alerting; disable customer financial operations if the cron/scheduler repeatedly fails.

Do NOT start \`php artisan queue:work\` as a permanent daemon on shared hosting. If later necessary, provider-approved \`queue:work --stop-when-empty\` with guarded cron and monitoring can be evaluated separately.

## 6. Staging install and release gate

Run against a disposable staging database and a tested private staging domain, not real student records:

\`\`\`bash
php -v
php -m
php artisan erp:hosting:check
php artisan migrate:status
php artisan migrate --force
php artisan optimize
php artisan erp:release:check
php artisan schedule:list
\`\`\`

\`erp:hosting:check\` reports missing CLI extensions, writable directories and Vite build manifest **without printing any passwords**. It cannot establish SSL or cron capabilities by itself.

\`erp:release:check\` checks a subset of production config and route guards. Passing both does not mean the host is operationally production-ready.

Do not run database migrations on your existing DhakaWala hosting business DB. The ERP must have a completely separate DB and backups.

## 7. Live deployment: mandatory final acceptance

Require dated evidence of:

1. Accurate A-record, HTTPS certificate and document-root isolation (no \`.env\`, \`artisan\`, \`vendor\` or storage exposure through HTTP).
2. Real MFA enrollment/recovery for a platform operator and RA&C Partners; password reset and onboarding email arrive and links expire.
3. Attendance, leave, conveyance receipt uploads and payroll record-paid workflow on **fictional staging data**; Partner can retrieve only own payment evidence.
4. Cron trigger and monthly draft schedule verified; SMTP timeout and PHP resource usage acceptable.
5. Independent file/database backup restoration, and safe rollback rehearsal.
6. RA&C three-Partner permission/policy signoff and financial reconciliation procedures.
7. Independent tenant/authorization/security review and no unresolved high-severity findings.

**Go/No-Go:** keep live ERP disabled until the checks are accepted. A shared hosting plan can host a small Laravel firm pilot **only if its PHP/cron/DB/security controls actually support the application**; hosting type alone cannot certify the platform for financial SaaS.

## 8. One screenshot that helps next

From **WHM/cPanel** provide redacted screenshots showing **MultiPHP Manager / Select PHP Version**, **Advanced → Cron Jobs**, **Domains (document root only)**, and **Terminal** availability. **Never include passwords, API tokens, .env contents, SMTP credentials, or cPanel login links.**
