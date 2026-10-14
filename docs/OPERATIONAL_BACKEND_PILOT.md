# RA&C ERP SaaS — Operational Backend Pilot: Security, Setup, and Deployment

## Status and safety boundary

This branch implements a **real, database-backed pilot** of firm login, partner membership, attendance, leave requests, conveyance claims, notifications and monthly allowance/payroll snapshots. The original 10-phase design previews remain available **only outside production**.

**NOT cleared for live confidential or salary processing without deployment QA, account policy review and security sign-off.** No production deployment has been performed.

### Features implemented

- Company > Partner Workspace > Partner / Manager / Student memberships, with Home Partner ownership.
- Real Laravel email/password sign-in with tenant membership verification, session regeneration, failed-login rate limit and POST logout.
- Server-sourced UTC attendance events with Asia/Dhaka work-date conversion. Office or Client sessions, one active session per student, multiple sessions in a day, date uniqueness and late/incomplete/holiday statuses.
- Student leave and conveyance submissions; scoped manager/partner decisions, state-transition validation and personal database notifications.
- Explicit payroll allowance rates with effective months (no guesswork); partner-scoped draft snapshots and claim allocation unique per conveyance claim; reviewed/approved/externally-paid/locked states.
- Students can view only their own **locked** payroll items; managers cannot browse payroll. No automatic wage penalties and **no actual bank transfer**.

### What is NOT yet delivered

- Production staging or cPanel/VPS setup; email domain/SMTP configuration; multi-factor authentication; password reset flow and account invitations.
- In-browser creation and management of users and partner workspaces (currently trusted operator Artisan commands).
- Attendance admin corrections, leave balance accrual, custom late deductions, payroll exports, receipt upload, external disbursement verification, bank integrations.
- Unanimous three-Partner approval logic for changes to company-wide **policy**, permitted per-Partner overrides, policy audit/voting engine.
- Full financial/HR policy sign-off, penetration/load testing, support-access permission grants, backup restore testing.
- Company subscription billing and full corporate governance mode.

## Local/staging provisioning

Use a private, secured PHP 8.4 / Laravel 13 compatible host, MySQL database, proper queue/cron availability and HTTPS.

1. Set correct document root to Laravel **public** (never repository root). Set APP_ENV=production, APP_DEBUG=false, APP_URL with HTTPS, DB credentials and a unique APP_KEY in a private server-side .env file.
2. Run Composer install, npm ci, npm run build, php artisan optimize:clear, php artisan migrate --force. Back up the database before migrations.
3. Create the first company and Partner in an interactive trusted terminal:

    php artisan erp:provision-firm rac "Rahman Anis & Co." partner@example.com

   The command prompts for the password securely. Do not enter passwords in command-line arguments, GitHub, screenshots or public chat.

4. Create RA&C Partner Units 02/03 if required:

    php artisan erp:add-workspace rac "Partner Unit 02"
    php artisan erp:add-workspace rac "Partner Unit 03"

5. Find workspace IDs via the previous command output. Add students/managers/other Partners interactively:

    php artisan erp:add-member rac member@example.com manager --workspace=1
    php artisan erp:add-member rac student@example.com student --workspace=1 --manager=2

   The **manager** option must be a membership ID, not a user ID.

6. Record a documented allowance rate for each active student before generating payroll:

    php artisan erp:set-allowance rac 3 2026-10 8000.00

   Effective-month rates cannot silently overwrite a previously recorded value.

7. Live sign-in link: /app/c/rac/login (the production /c/rac/login URL redirects here). Attendance: /app/c/rac/attendance; Leave/Claims: /app/c/rac/requests; Payroll: /app/c/rac/payroll.

**Never seed real user details or passwords into publicly accessible GitHub.**

## Operational safety

- Never publish public sample routes for production users: production routing turns off demo/dashboard-preview routes.
- Firm member IDs and Partner Workspace IDs must be validated against company ID in all workflows.
- Partner/Manager Approvals do not imply bank payments.
- The "mark paid" action is an operator-recorded assertion requiring an external payment reference; **it does not independently verify a bank payment**.
- Leave entitlements, conveyance receipt evidence and salary/withholding policy require RA&C approval before real-money use.
- Approved claims allocated to payroll snapshots cannot be attached to another payroll run; paid claims are marked only after manual disbursement confirmation.
- Test backups and restoration, enable HTTPS + secure cookies, apply login rate limits and test device/browser security before use.

## Test verification

Github Actions uses PHP 8.4 and SQLite in-memory to run automated tests, Vite build and browser smoke tests. Consult each branch CI for actual pass/fail, rather than claiming success based on static code.

Tests cover tenant isolation, multiple attendance sessions, late cutoff, student-only check-in, leave/claim approvals, notifications, payroll allocation, locked statements and prior UI regression.

**Production authorization should be completed only after a dedicated security audit and live staging acceptance test.**