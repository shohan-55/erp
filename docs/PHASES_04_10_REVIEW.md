# RA&C ERP SaaS — Phases 04–10 Design Audit & Completion Report

## Outcome

The planned **ten-phase frontend/UI prototype** is complete in Laravel + Livewire, including the existing Phase 01–03 work. Phase 04–10 work is proposed via [Pull Request #2](https://github.com/shohan-55/erp/pull/2), keeping main intact until validated.

This document certifies only **UI design and preview testing**, not authentication, financial processing or tenant privacy enforcement.

## Delivered screens and behavior

| Phase | Surface | Non-destructive features |
|---|---|---|
| 04 | Partner / Principal Dashboard | Partner Unit 01 sample metrics; own Partner sample team; policy and quick actions |
| 05 | Manager Dashboard | Example assigned team-only list; review entry points; scope-limited preview |
| 06 | Student Dashboard | Touch-friendly overview, private sample rows, quick links |
| 07 | Attendance | Office/Client mode, check-in/out appearance states, status search/filter, correction review |
| 08 | Leave | Student sample validation, Manager/Partner review dialog, entitlement disclaimer |
| 09 | Conveyance | Sample expense validation, proof requirement, scope-limited claim review |
| 10 | Payroll | Illustrative allowance/claims, no payslip or payment, Draft→Review→Approve→Paid→Lock description |
| 10 | Notifications | Fictional notification inbox and local-only read indicators |
| 10 | Governance | Three-Partner unanimous company-default change preview; Home Partner override concept |
| 10 | Settings | Organization metadata, roles, attendance policy and privacy concepts |

## Known important design rules retained

- Client and Office work; only one demo session active at a time.
- RA&C default: 10:00–18:00, 10-minute grace, Friday/Saturday weekly holidays, Asia/Dhaka.
- On time through 10:10:59, late beginning 10:11. Financial penalty rule not configured.
- Home Partner owns all student records, even during client engagements at another Partner's firm.
- Manager A's example list excludes Manager B's example rows; student preview shows only own sample record.
- Company-wide default policy change requires all 3 Partners to independently approve; rejection or roster changes must be validated in a future backend.
- Policy effective dates, approval audit trail, locked payroll snapshots and avoiding duplicate expense payments are backend requirements, not implemented claims.
- Platform privacy-first design shows firm counts/metadata but no ambient private tenant record access.
- Infrastructure operators may have low-level system access. Application-level support consent is not cryptographic isolation.

## Automated tests

GitHub Actions executes, for each pull request:

1. PHP 8.4 setup, Composer install, application key and a local test environment.
2. npm ci and Vite production asset build.
3. php artisan test (all prior and current features, Laravel/Livewire test assertions).
4. Headless Chromium Playwright Phase 03 browser test.
5. Headless Chromium practice UI suite testing Phase 04–10, forms, dialogs, role scope, navigation and narrow screen widths.
6. Screenshot artifacts (desktop and mobile).

**Verified successful benchmark:** 48 tests passed / 245 assertions. Vite build, prior Phase 03 browser smoke tests and practice suite browser smoke tests all passed in [GitHub Actions run 38457405940](https://github.com/shohan-55/erp/actions/runs/38457405940).

Additional UI polishing is verified by later successful runs and may be retested when this PR merges.

## Issues found and corrected during audit

1. Browser test ambiguity: "Attendance" sidebar button and "View Attendance" hero button share a substring. Browser selectors now use an exact label.
2. Notification sidebar badge affected accessible name; fixed with an explicit navigation aria-label.
3. Preview role/section switching previously retained search/status filters; corrected.
4. Student could invoke some reviewer modals using direct public Livewire methods; UI preview permissions narrowed.
5. Direct acknowledgement of unopened dialogs gave meaningless status messages; validation now requires an open permitted preview dialog.
6. Partner/Manager Dashboard's attendance card unnecessarily stretched to the height of adjacent cards; aligned cards to content.
7. Role-routing simulation previously pointed to Phase 01 preview rather than the chosen persona's UI; now opens correct partner/manager/student preview route.
8. Entire project retained the Laravel default README; now replaced with project-specific documentation, routes and explicit production limitations.

## Automated quality scope & limitations

- Tests show the preview flows rendered and interacted correctly in tested desktop/mobile browsers.
- Tests **do not** prove access controls, tenancy isolation or actual finance/attendance correctness because those systems have not been implemented.
- No actual user credentials, private student data, pay rates, client audit documents, bank accounts or policies are used.
- Screenshots are visual evidence; individual UI states outside the tests still require standard user acceptance review before any production release.
- Public sample preview routes must be disabled or protected before connecting real data.
- No production deployment was attempted.

## Functional backend backlog (not included in the 10 design phases)

- Secure central-cloud tenant onboarding, memberships, company and Partner ownership with tests.
- Real auth, 2FA, password recovery, role and permission policy enforcement.
- Attendance timestamps and consolidated days, client/office assignments, correction logging.
- Leave entitlement rules and decisions; conveyance attachments and duplicate/reimbursement reconciliation.
- Partner-unanimous policy voting with proposal versions, separate votes and effective dates.
- Automatic monthly allowance/payroll with approval snapshots, locking, idempotence, concurrency, payout status and immutable audit logs.
- Real notification delivery, platform support approval/scope/expiry, infrastructure monitoring and backups.
- Integration, penetration, resilience, device and load testing; security audit and deployment.

## Source reference

- GitHub source repository: https://github.com/shohan-55/erp
- Review PR: https://github.com/shohan-55/erp/pull/2
- CI suite: .github/workflows/laravel-ui.yml

**Sign-off boundary: Design phases complete after green final CI and merge. The commercial SaaS backend is not delivered in this design milestone.**
