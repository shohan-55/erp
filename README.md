# RA&C ERP SaaS — Complete UI/UX Design Prototype (Phases 01–10)

A Laravel + Livewire enterprise SaaS **interface prototype** for Chartered Accountants firms.

**Status:** All ten *design phases* are implemented and cloud-tested. This is **not a production-ready ERP**. All example users, salaries, attendance and approvals are fictional. Real authentication, role authorization, persistent tenancy, attendance processing, approval voting, payroll accounting and notifications remain future backend work.

## Technology

Laravel 13 · PHP 8.4 (cloud CI) · Livewire 4 · Blade · Tailwind CSS 4 · Vite 8 · Lucide icons.

## What is implemented

| Phase | Deliverable |
|---|---|
| 01 | RA&C brand-inspired visual system, responsive application shell, reusable components |
| 02 | Firm and Platform login, password recovery and invitation screens (UI only) |
| 03 | Platform Super Admin dashboard, organization directory, privacy-first support access design |
| 04 | Home Partner / Principal Dashboard |
| 05 | Manager Dashboard showing only assigned fictional team |
| 06 | Mobile-friendly personal Student Dashboard |
| 07 | Office / Client Attendance, history, check-in/out simulation and correction design |
| 08 | Student Leave Application and Manager/Partner review design |
| 09 | Conveyance Claims, evidence and approval-preview workflow |
| 10 | Monthly Allowance & Payroll, Notifications, Policy / Governance and Settings UI |

## Design routes (local server)

| Experience | Path |
|---|---|
| Phase 01 component library | / |
| Platform login | /platform/login |
| Platform Super Admin preview | /platform/dashboard-preview |
| RA&C firm login | /c/rac/login |
| Partner dashboard | /c/rac/preview/partner |
| Manager dashboard | /c/rac/preview/manager |
| Student dashboard | /c/rac/preview/student |
| Student attendance | /c/rac/preview/student/attendance |
| Student leave application | /c/rac/preview/student/leave |
| Student conveyance claims | /c/rac/preview/student/conveyance |
| Partner payroll | /c/rac/preview/partner/payroll |
| Partner notifications | /c/rac/preview/partner/notifications |
| Partner policy governance | /c/rac/preview/partner/policies |
| Partner workspace settings | /c/rac/preview/partner/settings |

All design routes are intentionally **public examples containing fictional records only**. The role switcher is a visual preview, **not real authentication or authorization**. Do not attach real student, firm, attendance, financial or personal information to them.

## Development setup

~~~sh
composer install
cp .env.example .env
php artisan key:generate
npm ci
npm run build
php artisan serve
~~~

Use npm run dev in a second terminal when working on frontend changes.

## Automated verification

- GitHub Actions workflow: [.github/workflows/laravel-ui.yml](.github/workflows/laravel-ui.yml).
- PHP regression: php artisan test.
- Production asset validation: npm run build.
- Chromium browser workflows: tests/browser/phase03-preview.mjs and tests/browser/practice-phases.mjs.
- Desktop and mobile screenshots: open **GitHub Actions → Successful workflow → Artifacts**.
- Design and security boundary report: [docs/PHASES_04_10_REVIEW.md](docs/PHASES_04_10_REVIEW.md).

## Approved UI policy baseline

- Asia/Dhaka; office start 10:00, end 18:00; 10-minute grace; Friday/Saturday holiday.
- On time through 10:10:59, late from 10:11.
- No automatic lateness or absence financial penalty until explicit approved policy.
- Home Partner owns student records, even when the student works at another Partner's client.
- RA&C company-wide defaults require unanimous approval from all three Partners. Individual permitted workspace overrides require their authorized Partner.
- Monthly payroll eventually follows Draft → Review → Approve → Paid → Lock, with effective policy snapshots.

## Must be implemented before any production or live-data use

1. Tenant-aware users, memberships, strict company/Partner row isolation and corporate governance variants.
2. Authentication, secure sessions/2FA, invitations, authorization and password reset delivery.
3. Server-timestamp attendance, sessions, consolidated daily status, corrections and audit history.
4. Versioned policy overrides, unanimous voting, approvals and effective date rules.
5. Leave entitlement and approvals, conveyance receipts, duplicate reimbursement controls.
6. Real allowance/payroll calculations, immutable approvals and paid/locked snapshots.
7. Notifications, consented expiring support access, immutable logs, billing, backup recovery, security testing and production hardening.

**Privacy:** A platform role should not grant ambient access to a tenant's private payroll or attendance. Infrastructure administrators may technically still access infrastructure; do not promise cryptographic isolation from a role-based UI.

**Repository safety:** Never push .env secrets, passwords, real salary details, bank accounts, client documents or real student records. No actual tenant changes or live payments exist in this design.

The application project is separate from the MIT-licensed Laravel framework. Its own reuse and redistribution rights are not established merely by the underlying framework license.
