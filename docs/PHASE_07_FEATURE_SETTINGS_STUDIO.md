# Phase 07 — No-Code Feature Settings & Tenant Modules

**Development scope only**, based on `security/production-readiness-gates` at `c9d9a5b86b9f2097e258003a48754dc64fd30d08`.

## Implemented
- Registry of **13 operational modules**: attendance, attendance reports, leave, conveyance, payroll/payslips, engagements, timesheets, notices/notifications, private documents, staff records, organization units, calendar, practice reports.
- Partner-controlled Feature Settings Studio with scopes **whole company, selected Partner workspace, base role within that workspace, and individual active membership**.
- *Deny takes precedence*. Existing tenants start with existing access, because no rows means enabled. A disabled company/workspace/role/user setting blocks a module for that person. Lower-level `enable` cannot bypass an upper `disable`. Reset removes only that precise override, not history.
- **Existing student Partner/Manager feature controls remain enforced**. Company/role controls add restrictions; no old consent or membership is rewritten.
- **Actual backend protection**: same access resolver used by the tenant middleware to block URLs, POST writes, downloads and reports, not merely sidebar links; dashboard Quick Actions and navigation hide disabled modules. Billing, security, feature settings, company governance and auth routes remain deliberately recoverable.
- Dependencies: reporting unavailable when attendance is disabled; timesheets unavailable when engagements are disabled. No deletions of attendance, payroll, claims, files or employee histories when a module is disabled.
- Every edit requires an authenticated active Partner in the selected workspace, validated scope/target and reason, serialized DB transaction, and tenant-scoped audit event.
- **Multi-Partner firm-wide governance**: a company with more than one active Partner or more than one active Workspace CANNOT undergo a unilateral company-wide override. Partner can control their own workspace, role and users. Phase 08 will provide approved company-wide policy voting.
- No arbitrary PHP/SQL/eval customer policies, no direct changes to tenant-private data or platform administrator scope.

## Deliberately future scope
- **SaaS plan/module entitlements** (Phase 19): existing plan employee/workspace capacities remain enforced; no new plan-feature billing promise or fee is made by Phase 07.
- Company-wide multi-partner proposal/vote/future effective date requires Phase 08.
- Custom role-specific toggles beyond base Partner/Manager/Student role are part of the Policy/Permission convergence review. Existing Phase 05 custom-role authorization remains intact.
- The Studio lists a bounded number of members/overrides; large-tenant pagination and 200GB performance acceptance are Phase 21.
- Only operational modules in the typed registry are configurable. Critical audit, legal hold, payroll financial integrity, access controls and platform security are non-toggleable.

## Acceptance scenarios
1. Partner disables Attendance for company: existing leave/history intact, student/manager/partner route and POST access blocked, attendance reporting also blocked.
2. Workspace/role/user overrides cannot elevate access above a parent restriction; reset returns to inherited status.
3. Student's historical Partner restriction remains restrictive even after workspace `enable`.
4. Multi-partner company-level policy edit refused, while partner-local workspace controls work without affecting other partners.
5. Member IDs or company IDs from another tenant cannot be assigned toggles; managers/students cannot edit settings.
6. Navigation, dashboard Quick Actions, direct route, write and download enforcement reference same effective module registry.
7. Security/MFA and Settings Studio never toggled; logged-in Partner can recover from mistaken module disable.

## Gates
- [x] Additive schema, typed module registry and no-code UI
- [x] Real middleware + navigation + dashboard enforcement
- [x] Tests for inheritance, cross-company scoping, student legacy and multi-partner governance
- [ ] Exact-head CI: full PHPUnit/Vite, MySQL 8, browser and cPanel package
- [ ] Reviewed development merge and merge-SHA CI
- [ ] Production readiness/staging verification is independent and NOT automatic
