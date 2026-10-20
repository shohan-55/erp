# Phase 06 — Time-limited Full/Partial Workspace Delegation

**Status:** Development PR pending CI and review; no production release.
**Baseline:** `security/production-readiness-gates` at `9dbea9a6844be937daf1b6a8c03b301fcb4edd7c`.

## Delivered

- **Scoped manager/moderator grants:** a Partner may delegate only within the current company and Partner workspace. Base membership, reporting lines, salary privacy and audit provenance are unchanged.
- **Modes:** `limited` grants only explicitly selected actions; `full` grants all *currently supported* workspace operations: `attendance.propose`, `organization.view`, `organization.manage`. Full is **not** impersonation, platform ownership, financial control, payroll/final approval or cross-workspace access. New feature/module actions are intentionally not automatically available until reviewed server-side authorization exists.
- **Timing:** optional future `starts_at` and `expires_at` per Partner grant. Every relevant HTTP request reads the current grant and rejects it before start or at/after expiry; no cron is needed to cut off authority. Existing stored grants default to `limited` with null times, preserving their stored operation rights pending admin review.
- **Delegate controls:** a partner-issued `company_admin` only gains authority to issue *limited Moderator grants* when they are **explicitly** granted `access.delegate`; it can never issue Company Admin/full grants, grant unheld capabilities, grant itself, grant cross-workspace rights or grant for longer than 30 days/the parent's remaining period.
- **Lineage:** a child grant stores `parent_grant_id`; its authority depends on the parent's current active state, member status, scope, time, `access.delegate` and each operational right. Revoking a parent writes revocation timestamps for all direct children, and all nested escalation is disallowed.
- **Access integrity:** Partner controls custom roles independently. `WorkspaceAccess` evaluates legacy direct grants, new timed grants and Phase 05 custom role permissions through real attendance/organization route checks; permissions are **not** inferred from a job title or sidebar visibility.
- **Forensic trail:** reason, grantor, target, changed role/mode/permissions, start/expiry, parent grant and revocations are recorded using existing tenant-scoped audit events and recipient notification.
- **Admin UI:** Company Access Control displays form, duration, Full/Partial descriptions, direct-vs-parent dependence, live status and revoke.

## Deliberate security hardening versus legacy

Previously a Manager labeled `company_admin` could issue further grants without an explicit delegation-management permission. This has been **tightened**: now `access.delegate` is required. Old positive regression fixtures are updated to request an explicit Partner grant. This is a deliberate privilege-reduction, not an accidental backward compatibility change.

## Strict boundaries and pending modules

- **No proxy attendance check-in/out** or forged location; the existing manager capability is to propose verified corrections for students actually assigned to them. Another authorized actor must make final decisions.
- **No automatic leave/claim/financial/payroll final approval** from the Full switch. Modules join the permission system only after dedicated workflow and regression coverage in the scheduled later phases.
- A disabled/revoked delegated grant cannot cancel a separately authorized custom-role right. Effective access is a union of independently justified grants and base Partner rights; superusers must review those channels separately.
- Member identities, payroll records, old invoices, customer documents, database rows and live cPanel/server are untouched by coding in this feature branch.

## Verification gates

- [x] Backwards-compatible additive migration and parent grant index
- [x] Server-side dynamic expiration, scope, privilege-ceiling and chain checks
- [x] Partner/delegate management UI and reasoned audit
- [x] Automated negative and positive tests for expiry, scheduled start, full/limited, cross-company, forbidden payroll, self escalation and parent recall
- [ ] Exact PR head PHPUnit/Vite + MySQL8 tests and browser QA
- [ ] Independent review and merge only to development
- [ ] Merge commit CI re-verification
- [ ] Staging restore, UAT and owner-approved deployment (separate release)

Related existing delegated attendance proposal regression: `tests/Feature/CompanyAccessAndManagerAttendanceRecoveryTest.php`.
