# RA&C ERP SaaS — Production Go / No-Go Release Gate

**Status: NO-GO** until every mandatory gate below is satisfied with dated evidence and independent review.

The main branch contains the ten-phase **design prototype**; production ERP implementations currently live on separate review branches. Do not deploy the UI preview as though it provided real authentication, access control or payroll operations.

## Code/CI gates

- [x] Laravel PHP feature/unit tests on SQLite (verify latest CI status)
- [x] Laravel PHP feature/unit tests on MySQL 8 (separate CI job; verify latest CI status)
- [x] Build production Vite assets (CI)
- [x] Browser smoke tests on representative desktop/mobile viewports (CI)
- [x] Private payroll visibility: students only see own LOCKED results, managers do not have implicit salary access
- [x] Attendance corrections serialized with payroll freeze and denied when time overlaps
- [x] Duplicate/overlapping student leave requests rejected while pending/reviewed/approved
- [ ] Independent security review: authorization, session/token handling, tenant isolation, race conditions and private files
- [ ] Full simultaneous multi-user MySQL race tests (payroll, claims, attendance updates)
- [ ] Accessibility and non-functional/load testing with representative RA&C traffic
- [ ] Dependency vulnerability remediation and update cadence
- [ ] Single backend consolidation: PR #3 and PR #4 are competing implementations and cannot be blindly merged

## Functional gates

- [x] TOTP Authenticator MFA mandatory for Platform Admin; optional/recommended for tenant members including Partners, and enforced after individual enrollment, with encrypted secrets, replay protection and single-use recovery codes; verified by unit/feature/browser CI
- [ ] Validate opt-in MFA enrollment, protected opt-out and recovery with real devices in staging, including documented lost-device recovery and a decision on whether payroll/Partner roles need company-mandated MFA before handling real financial data
- [ ] Actual SMTP password recovery/invitation delivery and offboarding tests
- [ ] Company/Workspace role permission matrix accepted by all three RA&C Partners
- [ ] Attendance, holidays, late, leave, conveyance and payroll policies approved (no invented salary deductions)
- [x] Record Paid now requires private external payment evidence, payment method/date/reference, exact line-total reconciliation, and role-scoped downloads; tested on SQLite and MySQL 8
- [ ] Independent external settlement verification and payroll approval segregation of duties with signed RA&C policy
- [ ] Company policy approvals by all 3 distinct Partners with immutable effective-date versions
- [ ] Tenant-approved support-access scope, expiry and durable audit evidence
- [ ] Account onboarding and deactivation, export/deletion, retention and legal review

## Infrastructure gates

Run `php artisan erp:release:check` on each staging/production release. A passing configuration check is necessary, but not sufficient for final sign-off.


- [ ] HTTPS/TLS with trusted ingress, APP_URL HTTPS and secure session cookies
- [ ] APP_ENV=production, APP_DEBUG=false and a rotated secret APP_KEY outside GitHub
- [ ] Supported PHP/MySQL runtime, least-privilege database credentials and verified migrations
- [ ] Private file storage, receipt access checks, malware scan policy
- [ ] Encrypted off-site backups AND documented successful restore drills
- [ ] Production SMTP provider, no bearer tokens in logs, queue worker supervision
- [ ] Laravel Scheduler, failure alerts, error monitoring and incident response
- [ ] Staging UAT with all Partners, data migration rehearsal, rollback plan and after-release verification

## Release rule

**NO-GO until all mandatory items are independently verified.** Passing GitHub Actions does not prove server security, mail delivery, financial correctness with real data, disaster recovery or production deployment health.

Never put production credentials in GitHub or in this document. No live data, real payroll disbursement or production deploy has been performed as part of this hardening PR.

## Review links

- Security hardening PR: https://github.com/shohan-55/erp/pull/5
- Operational pilot alternative: https://github.com/shohan-55/erp/pull/4
- Real ERP foundation: https://github.com/shohan-55/erp/pull/3
