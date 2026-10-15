# Phase 6A — observed attendance report + scheduled payroll draft integrity

**Status:** authenticated report route implemented; final code/CI review required. This is a code milestone only, NOT production-ready or a staging sign-off.

## Implemented on the isolated feature branch
- Read-only report controller/view for recorded monthly attendance sessions. Distinct days and session/late/incomplete/holiday counts are scoped by company, Partner workspace, assigned Manager and student.
- Private no-store response headers; no GPS, salary, absence deductions or unapproved financial figures exposed.
- Attendance page links to the protected report route (`auth`, `tenant.member`, `mfa.verified`).
- Scheduled *draft-only* payroll calculation now uses the Partner-approved conveyance amount when present, matching the already implemented manual payroll workflow. Old historical approvals with no approved amount retain the original-amount fallback; review of that policy is outstanding.
- Synthetic regression tests for report boundaries, validation, no-write behavior and approved claim draft calculations.

## Merge verification requirements

- Protected `GET /c/{firmSlug}/app/attendance/report` is registered in `routes/erp.php` with the existing tenant and MFA middleware group.
- Report uses server-recorded attendance only, monthly grouped per student; distinct attended days are **not** absence or payable-day counts.
- No GPS coordinates or payroll amounts are accessed by this report.
- All PHPUnit/SQLite, MySQL 8, browser and cPanel review-packaging jobs must pass on the final PR head.
- Other previously proposed automatic draft calculation work was independently tested and merged as PR #16; this report must not introduce new payroll behavior.
- Do not merge before successful CI, review of the report's role boundaries and confirmed no sensitive data leakage.

## Phase 6 further work
- Partner-reviewed payroll breakdown UI and export formats with independent sign-off; keep all amounts draft-only.
- User acceptance on metrics terminology: sessions, late *sessions*, completed vs open check-outs, missing days not treated as automatic absence.
- Historical former-team access and retained report export privacy to be approved separately.

## Phases 7/8 release gates (not code milestones)
- Staging-only production-shaped integration test and domain routing validation with non-confidential data.
- Off-site encrypted backup and actual restore drill, followed by documented recovery acceptance.
- Real-device location UAT *without* activating GPS for tenants, privacy/notice/legal approval.
- Proxy attendance threat model and mitigation decision; role/tenancy/MFA security review.
- Approved policy for late, half-day, holidays and leave; no financial deductions without written rules.
- Independent security approval, protected release commit and final owner go/no-go.

**Explicitly excluded:** production changes, live credentials, DNS, cron, MySQL and cPanel updates, automatic late/half-day penalties, live payroll disbursement, GPS tenant activation. All regression fixtures are synthetic.
