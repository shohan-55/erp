# Real ERP — Staging Deployment and Security Gate

## Status
This is a functional Laravel ERP backend intended for an isolated staging pilot, not yet a hardened public commercial SaaS. It has real company-specific logins, Partner-scoped teams, live attendance, leave requests, conveyance with private receipts, payroll run stages and notifications. Platform Operator sees aggregated company metadata, not private payroll/attendance. RA&C company defaults require the three individual Partner votes; future-effective Partner overrides are supported.

No hosting deployment was performed during GitHub development. Never enter real employee financial data on a staging server without a security review.

## Before production use
1. Verify all test suites and run multi-user concurrent operations against MySQL 8, not SQLite alone.
2. Configure HTTPS, a trusted APP_URL, APP_ENV=production, APP_DEBUG=false, new secure APP_KEY, and SESSION_SECURE_COOKIE=true.
3. Set least privilege MySQL credentials, encrypted backups, tested restoration, malware scanning and private attachment storage.
4. Configure a real SMTP/API mailer and verify invitation and password-reset email delivery. The application blocks log/array/null mailers in production for token emails.
5. Implement and require MFA for Platform Admin and Partner logins before commercial launch. MFA is **not implemented** here.
6. Configure company offboarding, consent-based temporary support access and immutable support auditing; these are not yet implemented.
7. Confirm actual leave entitlements, allowed Partner policy overrides, stipend amounts, payout instructions, rounding and audit standards.
8. Verify no sample data or public prototype routes are exposed in production (production environment disables public demo routes).
9. Perform penetration testing, accessibility testing, load testing and user acceptance with all three RA&C Partners.
10. Set up monitoring, incident response, mail/queue policies, worker supervision and backup recovery.

## Server requirements
PHP 8.4 with required extensions, Composer 2, Node 22 to compile frontend, MySQL 8+, HTTPS ingress. Serve Laravel's public directory; never expose application root.

Suggested staging install commands (on a disposable fresh server only):

    git clone https://github.com/shohan-55/erp.git
    cd erp
    # Checkout reviewed version/branch.
    composer install --no-interaction --prefer-dist --optimize-autoloader
    cp .env.example .env
    # Securely configure .env outside of GitHub.
    php artisan key:generate
    npm ci
    npm run build
    php artisan migrate --force

Configure private server variables: APP_URL=https://erp.dhakawala.com, APP_ENV=production, APP_DEBUG=false, SESSION_SECURE_COOKIE=true, DB_CONNECTION=mysql and secure DB credentials, MAIL_MAILER=smtp with verified provider credentials, MAIL_FROM_ADDRESS with a verified sender.

First platform operator can be created on a private, trusted terminal using:

    php artisan erp:platform:bootstrap

Platform operator login: /platform/app/login
First RA&C Partner invites by platform operator UI; then each Partner invites their Managers and Students. Firm login: /c/rac/app/login.

Alternative interactive CLI (passwords never passed in arguments):

    php artisan erp:bootstrap rac
    php artisan erp:member:add rac --role=partner --workspace=partner-02
    php artisan erp:member:add rac --role=manager --workspace=partner-01
    php artisan erp:member:add rac --role=student --workspace=partner-01

Important: QA seeder ErpBrowserQaSeeder is restricted to local/testing environments and must never be used with production data.

## Automated monthly payroll drafts

Laravel Scheduler is configured to run erp:payroll:auto-draft at 03:30 Asia/Dhaka on the 1st of each month, preparing a draft for the **previous** month. It does not pay or approve salary. Idempotency is guarded by a workspace row lock and a unique company/workspace/month database constraint.

For automatic operation, the server must execute the Laravel Scheduler every minute using the standard cron entry:

    * * * * * cd /absolute/path/to/erp && php artisan schedule:run >> /dev/null 2>&1

Make sure the cron user has access to the application and the relevant database; the scheduler does not run merely because the web page is open. A Partner must review and approve the draft and record verified external settlement before locking.

## Business behavior
- Asia/Dhaka local policy; 10:00–18:00, 10-minute grace and Fri/Sat holidays by default.
- Home Partner owns attendance, leave, conveyance and payroll records. Manager sees assigned student scope. Student accesses self only.
- Leave: student → Manager preliminary review (if assigned) → Partner final approval. Partner may finalize directly for students without Manager.
- Claims: private receipt, Manager preliminary review, Partner final approval. Once payroll period reviewed, changes are blocked.
- Payroll: Draft → Review & freeze snapshots → Approve → Record externally paid → Lock. No automatic bank/mobile money movement; marking paid means an external payment was actually verified.
- No automatic attendance late penalty until a formally approved compensation policy exists.
- Company-wide default policy proposals are reviewed by all 3 RA&C Partners with distinct votes, rejection ends proposal and roster changes suspend pending proposals. Permitted workspace overrides take effect on scheduled future dates.

## Verification
Workflow: .github/workflows/laravel-ui.yml
Real backend tests: tests/Feature/RealErpOperationsTest.php, RealErpTeamInvitationsTest.php, RealErpGovernanceTest.php, RealErpPlatformTest.php
Browser journey: tests/browser/erp-live-pilot.mjs

## Not included
MFA, corporate centralized governance, automated legal leave entitlement balance, payroll tax calculations, payment gateway/bank integration, audited support access, cryptographic tenant isolation, disaster recovery documentation, automated backups, security penetration signoff, and production deployment.
