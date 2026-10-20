# DhakaWala ERP — Multi-Tenant CA Firm / Corporate SaaS

> **Release status (20 October 2026):** 24-phase development **release candidate** on `security/production-readiness-gates` following Phase 24 merge and CI. This is **not authorization to deploy to live customers**. The default `main` branch may contain a much older UI preview. Verify the reviewed branch and exact commit before any deployment.

## What the development branch implements

- Company, independent Partner/workspace, Manager/Moderator and Student identity, roles and configurable delegated permissions.
- Attendance check-in/out, optional location consent, controlled corrections/proxy attendance, schedules, holidays and attendance reports.
- Leave requests, delegated workflows, partner-controlled approval and audit evidence.
- Conveyance claims, reviewed amounts, receipts, payroll draft/approval/reconciliation, private evidence and restricted statements.
- Engagements, timesheets, milestones, notices, smart forms and documents.
- Subscription plans, manual financial verification, invoices, trials, company/domain administration and scoped software storage allowances.
- Private Vault pilot, guarded company-exit package pilot, read-only scoped API v1 and bilingual Integration settings.
- MFA, sensitive action logging, token revocation, tenant-isolation gates, scoped performance optimization and immutable-reviewed release packaging.

**Scope limits:** Phase 17 private Vault and Phase 18 offboarding package are conservative small-volume pilots; **200GB customer transfer/restore is not certified.** Bilingual coverage does not mean every ERP module is translated. HTTP/API security hardening does not constitute ISO 27001, SOC 2 or independent penetration-test certification.

## Technology and environments

Laravel 13, PHP 8.4 development CI, MySQL 8 integration CI, Blade/Livewire, Tailwind/Vite, OAuth-independent authenticator MFA. Production must run with HTTPS, private Laravel storage outside webroot, database sessions, encrypted session payloads, secure host-only cookies, supervised queue/scheduler, real outbound SMTP, audited encrypted offsite backups and independent recovery witnesses.

- **Development source:** `security/production-readiness-gates`. Never confuse with `main`.
- **Production site:** `erp.dhakawala.com` is not modified by GitHub development merges.
- **Package:** CI builds `erp-cpanel-application.zip`, `erp-cpanel-split-webroot.zip`, `SHA256SUMS.txt` and `release-manifest.json` from an exact reviewed Git SHA. Review artifacts are **not live deployment instructions**.

## Safe read-only checks after staging is separately configured

```bash
php artisan erp:release:check --json
php artisan erp:db:check --json
php artisan erp:integrity:check --json
php artisan erp:tenant:check --json
php artisan erp:billing:check --json
php artisan erp:scale:diagnose --json
php artisan erp:security:report --days=7 --json
php artisan erp:release:final-audit --sha=REVIEWED_40_CHARACTER_SHA --json
```

These commands are **diagnostic**, not proof of physical backups or permission to deploy. The final audit intentionally outputs `production_decision=NO_GO` and exits nonzero regardless of how convincing untrusted local metadata appears; separately signed DR/UAT and exact-SHA production release authorization remain necessary.

For a source/host identity check **without updating Git worktree or customer data**, run this in the correct ERP cPanel terminal:

```bash
pwd && whoami && git rev-parse --show-toplevel && git branch --show-current && git rev-parse HEAD && git status --short
```

Do not run `git pull`, `php artisan migrate --force`, `php artisan key:generate`, `db:wipe`, `migrate:fresh`, or any database restore on a live customer ERP before the independent staging recovery/UAT gates, backup evidence, worktree/branch verification and explicit owner production approval. A successful pull can immediately replace application code and break compatibility with live schema, sessions or encrypted data.

## Current handoff documents

- [Phase 23 — Staging recovery, UAT and independent evidence](docs/PHASE_23_PRODUCTION_READINESS_DR_UAT.md)
- [Phase 24 — Final candidate audit and release decision](docs/PHASE_24_FINAL_RELEASE_CANDIDATE_HANDOFF.md)
- [Dedicated cPanel release and packaging](docs/ERP_PILOT_RELEASE_CANDIDATE_CPANEL_HANDOFF.md)
- [Production and rollback checklist](docs/PRODUCTION_READINESS_GATES.md)
- [Backup and restore runbook](docs/PHASE_01_BACKUP_RESTORE_RUNBOOK.md)

**Release decision:** Development may be marked merged/CI-verified. **Live production rollout remains NO-GO until independently signed evidence and explicit owner authorization.** No customer passwords, bank information, ERP database backups, `.env` or private files belong in issues, logs or chat.
