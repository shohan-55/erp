# DhakaWala ERP — Phase 14 Final Development Handoff and Production Go/No-Go

**Development:** All earlier phase implementations have been merged to the designated development branch. Phase 14 changes are reviewed through PR #41. Completion of code/tests/ZIPs is NOT authority to deploy.

**Production decision on 2026-10-18: NO-GO, pending independent signoff.** No production database migration, live cPanel deployment, genuine backup restore or customer-data processing is claimed.

## Scope across fourteen phases

| Phase | Development module | Remaining operator acceptance |
|---|---|---|
| 01 | SaaS tenant foundation | Tenant identity and invitation acceptance |
| 02 | Independent Partner workspaces | Revocation, cross-unit switching, privacy |
| 03 | Platform roles | Super-admin / Partner MFA and least privilege |
| 04 | Role dashboards | Mobile/device and accessibility |
| 05 | Calendar, holiday and policy | Written firm/Partner approval |
| 06 | Attendance and duty | Real dates, shifts and location privacy |
| 07 | Leave and approval | HR policy and balances |
| 08 | Conveyance | Approved amounts, private receipts |
| 09 | Staff/articleship | HR history and transfer consent |
| 10 | Partner payroll | Independent settlement and deduction policy |
| 11 | Audit clients and engagements | Client confidentiality and time approval |
| 12 | Reports, notices and documents | Document retention and private ACL |
| 13 | SaaS billing and custom domain | External collection verification and DNS/TLS |
| 14 | Security, CI gates, release handoff | Every human/infrastructure gate below |

DhakaWala owns the software. CA firms (such as RA&C if onboarded) are customers. Independent Partner workspaces are private; Super Admin has no implicit access to payroll or audit-client records.

## Automated CI gates (necessary, not sufficient)

The precise reviewed Git commit must pass:
1. PHPUnit, dependency audits, production Vite build and authenticated browser QA.
2. MySQL 8 fresh schema migration, full PHPUnit, the read-only commands erp:db:check, erp:integrity:check, erp:billing:check.
3. cPanel private Laravel ZIP and separate public-webroot ZIP; SHA256SUMS.txt; release-manifest.json; verify package checksums and reviewed GITHUB_SHA.

The release manifest explicitly records deployment_authorized=false. It is a checksum/integrity report, not a signature or a go-live approval. Freeze an actual all-green reviewed merge SHA and build matching release artifacts when final human gates have been met.

## Mandatory manual Go/No-Go gates

- [ ] Verified separate ERP staging server and database, isolated from live ERP and DhakaWala hosting billing.
- [ ] PHP 8.4 CLI and web runtime, MySQL support, HTTPS, TLS, disk quota and private storage checked on the target hosting account.
- [ ] APP_ENV=production, APP_DEBUG=false, correct HTTPS APP_URL, encrypted database sessions, host-only secure HttpOnly cookies; existing APP_KEY preserved and escrowed privately.
- [ ] Consistent encrypted, timestamped offsite database AND private receipt/document backup; independent reviewer verifies checksums.
- [ ] Matched backup restored into separate staging. Actual data and private-file recovery verified; observed RTO/RPO and operator evidence recorded.
- [ ] Email invitation/reset actually delivered and tested to authorized test inboxes; never email real account tokens from staging.
- [ ] Partner role/MFA/recovery, manager team restriction, student locked-payslip privacy and cross-workspace boundary tested.
- [ ] Partner consent and documented attendance/leave/payroll/deduction policies; independent real-world external payment settlement checked.
- [ ] Audit engagement, time review, notice inbox, document ACL, subscription renewal, payment approvals and exact-host custom domain tested on staging.
- [ ] Scheduler/cron, queue draining, alerts, private-file permissions, disk/inode monitoring and incident response tested.
- [ ] Independent security review, concurrent finance tests, accessibility and final tenant business-owner UAT accepted.
- [ ] Business owner explicitly approves exact deployment window, source SHA, artifact hashes, backup, rollback and responsible reviewers.

ANY missing evidence means Production NO-GO. Green CI, staging CLI checks and artifact generation are insufficient on their own.

## Read-only target account preflight

In the private /home/erpdhakawala/erp-app directory, before deployment or migration, inspect the commands and environment:

    php artisan erp:hosting:check && php artisan erp:release:check && php artisan erp:db:check && php artisan erp:integrity:check && php artisan erp:billing:check && php artisan migrate:status

If migrations are pending, the schema check correctly reports not ready. Never migrate live merely to silence a check.

On a separate, authorized staging restore ONLY, review migrations and then use:

    php artisan migrate --force && php artisan erp:db:check && php artisan erp:integrity:check && php artisan erp:billing:check && php artisan erp:release:check

Never paste passwords, APP_KEY, authentication codes, payment references or private audit records in chat/GitHub.

## Production sequence ONLY after explicit approval

1. Freeze all-green reviewed SHA and cryptographic archive hashes; retain previous working release.
2. Capture matched offsite encrypted DB/files backups and restore-test them in isolated staging.
3. Use ONLY dedicated ERP cPanel account, never modify /home/dhakawal/dhakawala; keep private Laravel outside public_html and publish only split-webroot files.
4. Keep existing APP_KEY. Review pending migrations before controlled release; avoid destructive down-migrations or blind data restore.
5. Smoke test live TLS, tenant MFA/login, finance role separation, private files, subscription screens and logs using approved test identities.
6. Record deployed SHA, release approver, timestamp, checksum evidence, backup/restore reviewer and rollback results.

**Rollback:** freeze new attendance/financial writes, preserve any new private attachments, use prior approved app artifact when schema compatible; reconcile new data before any database restore.

## Residual risks after CI

CI does not prove real SMTP, TLS ingress behavior, restored offsite backups, bank settlement, production device compatibility, all-role 14-phase UAT, accessibility, live queue/scheduler, incident readiness or independent security signoff. SaaS overdue/grace does not auto-suspend tenants; manual payment approval never executes a transfer. These are deliberate safety rules, not evidence of a deployed or externally validated service.
