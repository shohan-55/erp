# Phases 7–8 — staging UAT, security review and go/no-go evidence matrix

**Decision today: NO-GO.** This document is an execution checklist, **not** evidence that staging, production or real-user testing was performed. The reviewed code baseline is `security/production-readiness-gates`; `main` contains a separate design-preview history. Do not deploy, merge competing PRs, configure DNS or turn on GPS as part of this checklist.

## A. Scope and evidence rules

- Use an isolated, access-controlled staging tenant and synthetic records only. Do not copy confidential staff, payslips, identity documents, private receipts or client files.
- Capture evidence in an approved **restricted** system, not the public repository. Reference only nonsecret ticket IDs, redacted screenshots and hashed artifact manifests here.
- For each gate, capture: reviewer, reviewed commit SHA, UTC timestamp, tenant-independent outcome PASS/FAIL/NOT RUN, ticket/reference, remedial action and retest date.
- No PASS from source review or CI alone is equivalent to a real-server or real-device PASS.
- Never store passwords, MFA recovery codes, APP_KEY, location coordinates or production URLs with secret tokens in the evidence record.

## B. Phase 7 — isolation and UAT scenarios

| ID | Gate | Safe staging procedure and expected result | Required reviewer |
| --- | --- | --- | --- |
| S-01 | Release artifact integrity | Verify reviewed Git SHA, CI all-green for PHPUnit/SQLite, MySQL 8, browser, Vite and cPanel packaging; validate SHA256 checksums of unpacked private/public artifacts | Engineering |
| S-02 | Tenant isolation | Partner A cannot query Partner B or Company B sessions, leave, claims, notices, payroll statements or private documents; expect 403/404 without side effects | Security + Partner |
| S-03 | Manager scope | Manager sees only assigned students' attendance and leave/claim workflow; Manager cannot see salary amounts or Partner-only workflow | Partner + Security |
| S-04 | MFA and recovery | Test privileged MFA enrollment/replay/recovery/reset on actual supported phones. Test lost-device path without bypassing authentication | Security |
| S-05 | Attendance | In/out on real phone + desktop, no overlapping sessions; server time displayed correctly for Asia/Dhaka; verify missing checkout and authorized correction with audit | Operations |
| S-06 | Calendar | Friday/Saturday weekly holiday; authorized future exceptional working days; locked payroll period refuses retroactive changes | All impacted Partners |
| S-07 | Leave and conveyance | Manager review followed by Partner approval, revised amount displayed distinctly from original, reject over-approval, preserve audit and notifications | Operations + Finance |
| S-08 | Draft payroll | Use synthetic allowance and approved conveyance amounts; assert adjustment = 0 with no late/half-day deduction, no payout; review idempotency, cross-workspace/period separation | Finance + Security |
| S-09 | Payroll confidentiality | Manager denied draft salary; student sees only own locked statement; file download checks and response cache behavior | Security |
| S-10 | Proxy attendance | Assess device-sharing, buddy check-in, session hijacking, IP anomalies and consented alternatives. Document residual risk and mitigation decision; **do not** silently deploy fingerprinting or GPS enforcement | Security + HR |
| S-11 | GPS optional pilot | Opt-in/consent, denial, offline, accuracy limits, location retention/deletion and access to encrypted data; no tenant location capture enabled without signed policy/legal notice | Privacy + Partner |
| S-12 | Mail and notifications | Test invitations, recovery, notices and scheduled reminders against synthetic/staging mailboxes; no accidental external recipients | Operations |
| S-13 | Mobile/browser/a11y | Test supported Android/Windows devices, small screens, keyboard navigation, screen-reader labels and reasonable accessibility | QA |
| S-14 | Concurrency | Two concurrent submissions for check-in, Partner approval, claim reservation and payroll review; only one valid transition and no duplicate payable rows | Engineering |
| S-15 | Backup and restore | Complete off-site encrypted DB **and private attachments** recovery drill in a separate staging account; document measured RPO/RTO, checksum and inaccessible private files over public web | Operator + Independent reviewer |
| S-16 | Rollback rehearsal | Simulate rejected migration/application release on isolated staging; prove reversible compatible application and data recovery without losing audit history | Operator |
| S-17 | Custom domain and infrastructure | Verify intended vhost/domain, TLS, public webroot, private Laravel path, least-privilege MySQL, SMTP, cron/worker, APP_URL and secure cookies on staging | Operator |

## C. Phase 8 — mandatory signoffs

| Gate | Criterion | Present status |
| --- | --- | --- |
| G-01 | All test suites green on the *final reviewed release commit* | NOT RUN for Phase 6+ |
| G-02 | External independent authorization, tenant isolation, MFA and file-privacy review | NOT RUN |
| G-03 | Legally and contractually reviewed written attendance/late/leave/half-day policy | NOT APPROVED |
| G-04 | Signed financial approval and segregation of duties, no speculative deductions | NOT APPROVED |
| G-05 | Real-device UAT and three-Partner acceptance with documented exceptions | NOT RUN |
| G-06 | Privacy notice and GPS consent/retention policy; location features default off | NOT APPROVED |
| G-07 | Restoration drill with independently verified encrypted backup and attachments | NOT RUN |
| G-08 | Custom domain, SSL, SMTP, queue/cron, deployment architecture confirmed on staged hosting | NOT RUN |
| G-09 | Owner accepts residual proxy-attendance risk, incident and rollback procedure | NOT APPROVED |
| G-10 | Designated owner signs release decision with dated evidence IDs | NO-GO |

The application-only `php artisan erp:release:check` command checks configuration and routes, **not** business-policy, off-site backups, external security approval or successful hosting rollout.

## D. Explicit stop conditions

Any unresolved high/critical security issue, cross-tenant disclosure, payroll amount discrepancy, unverified data restore, missing privacy/legal approval, or any red required CI job is an automatic **NO-GO**. Do not auto-merge or deploy to live cPanel, manipulate live DB records, keys, cron or DNS. Do not infer any late/half-day deduction formula or activate GPS.

## E. Signoff form (fill only after real execution in restricted evidence register)

- Reviewed commit: **NOT ASSIGNED**
- Staging environment evidence ticket: **NOT RUN**
- Gate IDs verified: **NONE**
- External security reviewer/date/evidence: **PENDING**
- Finance and policy owner/date/evidence: **PENDING**
- Privacy reviewer/date/evidence: **PENDING**
- Backup/restore operator and independent verifier/date/evidence: **PENDING**
- Three RA&C Partner acceptance references: **PENDING**
- Final release manager decision: **NO-GO**

See also `docs/PHASE_01_BACKUP_RESTORE_RUNBOOK.md` and `docs/PRODUCTION_READINESS_GATES.md`. This checklist does not supersede them.
