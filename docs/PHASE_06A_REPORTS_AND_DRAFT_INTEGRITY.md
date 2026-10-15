# Phase 6A — observed attendance report + scheduled payroll draft integrity

**Status:** code proposed for review; NOT production-ready. A required report route registration is blocked by the GitHub content write safety gate, therefore the feature tests are expected to fail until reviewed. Do not merge this PR while red.

## Implemented on the isolated feature branch
- Read-only report controller/view for recorded monthly attendance sessions. Distinct days and session/late/incomplete/holiday counts are scoped by company, Partner workspace, assigned Manager and student.
- Private no-store response headers; no GPS, salary, absence deductions or unapproved financial figures exposed.
- Attendance page links to the report (link will remain inoperative until the route is registered).
- Scheduled *draft-only* payroll calculation now uses the Partner-approved conveyance amount when present, matching the already implemented manual payroll workflow. Old historical approvals with no approved amount retain the original-amount fallback; review of that policy is outstanding.
- Synthetic regression tests for report boundaries, validation, no-write behavior and approved claim draft calculations.

## Required code blocker (before merge)
The attempt to update `routes/erp.php` was blocked by the connected GitHub file-write safety gate. A reviewer must explicitly add the following two non-sensitive route lines in the existing authenticated `auth`, `tenant.member`, `mfa.verified` middleware group:

1. Import `App\\Http\\Controllers\\Erp\\AttendanceReportController`.
2. Put `Route::get('/attendance/report', [AttendanceReportController::class, 'index'])->name('attendance.report');` **before** the dynamic `/attendance/{sessionId}/location` route.

Re-run full PHPUnit, MySQL 8 and browser/cPanel CI. Do not merge this draft feature while the new test suite is red.

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
