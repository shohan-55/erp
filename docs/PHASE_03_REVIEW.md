# Phase 03 — Platform Super Admin Dashboard Design Review

## Implemented and review location

- **Preview route:** /platform/dashboard-preview
- **Entry point:** /platform/login contains a direct Phase 03 design-preview link.
- **Stack:** Laravel Livewire, Blade, Tailwind CSS, existing Vite pipeline.
- **Brand:** DhakaWala platform identity using the yellow, amber, charcoal, and white design system.
- **GitHub branch:** feature/phase-03-platform-dashboard
- **Review PR:** https://github.com/shohan-55/erp/pull/1

The Phase 03 route is **public by design only because the page contains exclusively illustrative sample data**. Do not expose real platform administrator features through this route.

## Screens

1. **Overview** — illustrative organization, workspace and seat metric cards; organization summaries; privacy principles; unconnected platform-health card.
2. **Organizations** — example firm metadata directory, live search, status filter, mobile cards and read-only summary dialog.
3. **Support Access** — design for a scoped and time-limited read-only support request requiring firm approval and audit logging. No access requests are actually made or approved.
4. **Platform Status** — placeholders for application services, queues, database/backups and provider integrations. All say not connected.
5. **Dialogs** — creation and support-request previews are non-destructive and explicitly state no data is written.

## Privacy / scope

- Demo-only counts and records. RA&C firm name is used as a design reference; its illustrated seats/workspaces are **not** asserted as actual figures.
- No company payroll, employee records, real student attendance or tenant documents are read or displayed.
- No real firm creation, account provisioning, authentication, approvals, audit trail, server monitoring or subscription billing.
- Demo support flow is **not** an implemented authorization control. Platform/infrastructure operators may technically control backing infrastructure; do not claim cryptographic isolation.
- Application routes for existing Phase 01 and Phase 02 are retained.

## Automated verification

GitHub Actions workflow: .github/workflows/laravel-ui.yml

The CI pipeline runs:
1. Composer installation and Laravel setup with PHP 8.4.
2. npm ci and a production Vite build.
3. The complete PHPUnit / Livewire test suite, including tests/Feature/Phase03PlatformDashboardTest.php.
4. A real local Laravel server plus Playwright Chromium checks for overview, navigation, search filters, preview dialogs and 390px mobile layout.
5. Uploads 7 preview screenshots to the run's temporary **phase-03-platform-dashboard-screenshots** artifact.

See GitHub Actions run and PR status for **actual** pass/fail results. Screenshots may be downloaded under the successful run's Artifacts section.

## Review before approval

- Examine the desktop and mobile screenshots.
- Confirm the platform navigation, labels and fictitious metrics presentation.
- Verify no real admin permissions are inferred from the demo role preview.
- Accept or request visual revisions in the PR.

## Deferred until specifically authorized

Authentication/authorization, RBAC, company onboarding backend, real tenants and partner mapping, actual support consent/expiry/audit implementation, billing, health observability, production deployment.

**No merge or production deployment should happen automatically as a side effect of reviewing this document.**
