# Phase 24 — Final System Audit, Security Guard Inventory & Release-Candidate Handoff

**Baseline:** Phase 23 merged and verified on development SHA `40f4d324bd52e5a52463b49487c5a238ca273fad`.

## Definition of completion

**Code-complete and development CI-verified** means Phase 24 merged to `security/production-readiness-gates`, MySQL8 / SQLite PHPUnit, browser QA, Vite build, cPanel offline ZIP packaging, and exact post-merge SHA CI pass.

**Production-ready / safe to pull** is a **different milestone** requiring independent staging backup-and-private-files restore, security acceptance, tenant UAT, physical storage evaluation, tested rollback, supervised queue/mail, and explicit owner authorization for the exact artifact and deployment window. These are not proven by CI. Phase 24 deliberately cannot authorize live deployment.

## Implementation
1. The `ReleaseRouteAudit` scans **all currently registered** company paths, platform paths and API-v1 paths; it does not rely on a stale list of 2016/early-phase endpoints. It requires tenant host, session login/MFA/company membership/feature policy for sensitive tenant routes; verified host, session, platform-admin and MFA for platform routes; and verified tenant host, hashed bearer auth, rate-limiting and HTTP GET/HEAD-only for exactly four integration routes. Sensitive new routes failing guards invalidate this check.
2. The read-only command `php artisan erp:release:final-audit --sha=REVIEWED_EXACT_SHA --json` combines the route-surface result and the strict Phase 23 evidence-index result. **It always outputs `production_decision=NO_GO` and `deployment_authorized=false`**, even for an apparently complete evidence index, because neither JSON nor local tool output is an independently signed management approval.
3. Regression tests prove the route inventory passes for current source, and **fails** if a test registers an unguarded tenant route or a write-capable API route. The final CLI fails closed without evidence, for incorrect SHA, and never alters company records.
4. Replaced the obsolete design-only README with honest source, branch and release warnings; documented the non-mutating cPanel identity check and corrected the distinction between code review package and human signoff.
5. CI checks the final audit's **expected negative (NO_GO)** result, not a fictional production approval. CI source test and production release safety remain intentionally separate.

## Release-candidate audit matrix

| Gate | Automated development scope | Independent production obligation |
|---|---|---|
| Git source | Exact SHA from feature -> squash merge -> exact-SHA CI | Compare against installed staging/prod SHA and release checksum |
| Build & dependencies | Composer/npm audits, Laravel tests, Vite, webroot ZIP isolation | Verify installed web/FPM PHP extensions and OS patches |
| Identity & permissions | Tenant/Partner/MFA/API route guards + regression tests | Red-team penetration test, real MFA recovery and role-based UAT |
| Attendance/leave/claims/payroll | Existing feature, integrity and authenticated browser suites | Three Partner policy approvals, segregation, live-safe sandbox verification |
| Billing/domains | Invoice/evidence consistency tests, no forged settlement | Verify real payment records and HTTPS/DNS approval via owner |
| Storage / exit | Small, bounded encrypted package tests and private vault checks | Large-tenant 200GB export/restore & retained legacy files coverage NOT proven |
| Recovery | Synthetic AES-GCM SQLite staging-only rehearsal | Full MySQL + private files encrypted offsite restore and measured RPO/RTO |
| Monitoring | Log metadata and CLI reporting tests | Real SMTP, supervised queue, every-minute scheduler, alert response |
| cPanel | Offline two-ZIP, manifest/checksum and private-file exclusion | Account-specific staging installation, verified backups/rollback, user approval |
| Legal/privacy | Policy controls implemented | Independent records retention/legal hold and compliance approval |

## Next terminal action — observation only

**Do not run a pull yet.** In the actual ERP cPanel terminal, first capture ONLY these non-secret identity/status items and return the output:

```bash
pwd && whoami && git rev-parse --show-toplevel && git branch --show-current && git rev-parse HEAD && git status --short
```

If the terminal is not already inside the ERP Git worktree, the Git commands may fail. Do not guess an alternate account, home path or application root. Tell the operator what terminal they opened; verify correct ERP cPanel account and exact application path before proceeding.

Do not print `.env`, passwords, DB hostnames, API secrets, logs or customer data. No `git pull`, migration, config clearing, queue restart, data delete or domain change until explicit production authorization and technical reconciliation. Note `git status --short` reveals filenames, not file contents; redact private filenames if any appear.

When release conditions are actually met, a separate plan must specify a freeze SHA, tested immutable artifact, independently verified backup snapshot, schema compatibility, command order, rollback criteria, smoke tests and named owner approval. **Never deploy by pulling the development branch merely because CI passed.**

## Commands for authorized isolated staging

These are **read-only** diagnostics after isolated staging is provisioned:

```bash
php artisan erp:hosting:check --json
php artisan erp:release:check --json
php artisan erp:db:check --json
php artisan erp:integrity:check --json
php artisan erp:tenant:check --json
php artisan erp:billing:check --json
php artisan erp:security:report --days=7 --json
php artisan erp:scale:diagnose --json
php artisan erp:release:final-audit --sha=EXACT_REVIEWED_SHA --json
```

`erp:release:check` assesses basic config, not real SMTP, backups or UAT. `erp:release:final-audit` will **exit 1 by design**; its NO_GO means human approval cannot be inferred. Neither command performs installation or production rollback.

**Phase 24 human production gates (still pending)**:
- [ ] Permission + inventory for isolated full-volume production-style staging
- [ ] Independently witnessed encrypted offsite MySQL and all private-file restore
- [ ] Exact SHA and artifact signing/verification, supported web PHP and schema migration plan
- [ ] Partner/Manager/Student/Billing full acceptance, sensitive authorization negative testing
- [ ] 200GB/large-data contractual capacity and restore tests where sold
- [ ] Independent security, legal/privacy, backup retention review
- [ ] No data loss under tested staging rollback, correct APP_KEY handling
- [ ] Authorized owner signs the exact-SHA production release and window

**Status:** Development candidate only. Live release remains **NO-GO** until the above are independently completed and authorized.
