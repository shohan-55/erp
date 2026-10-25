# Phase 16 — Smart Forms, Scoped Reports, Documents and Notification Rules

This is a **development-only change** on top of merged Phase 15 (PR #72, SHA 11757093). It does not deploy to cPanel, alter existing customer records, or deliver external emails.

## Reuse existing product rather than duplicating it

- Existing private Partner Practice Reports and CSV export remain unchanged at /reports/practice.
- Existing branded official document templates, Word/Excel exports, approved leave letters, employment/salary certificates and the scoped Document Center remain unchanged.
- Configurable forms reuse authenticated tenant/workspace middleware and the **Document Center** feature gate.
- Existing tenant_notifications and tenant_audit_events remain the single in-app message and audit channels.

## Workspace Smart Form Studio

- Route: /c/{company}/app/forms. Signed-in MFA-verified workspace members only, subject to the Document Center switch.
- Only the home Partner can create or revise a form. Publishing the same form code adds an **immutable version** rather than changing historical fields.
- No-code dynamic builder: 1–12 keyed typed fields (text, number, date, select), with bounded select options. No arbitrary HTML, PHP, JavaScript, SQL or formulas are executed.
- Only the latest version accepts new responses. Every submitted form is immutable and retains its source template, actor and timestamp.
- Student and Manager see only their own responses. Partner sees the workspace and may export version-specific CSV. Formula injection prevention and cursor streaming are enforced.
- Audits log template publication, response creation and export metadata, **never the answer contents**. In-app notification carries the form title only.

## Milestone Notification Rules

- Route: /c/{company}/app/settings/reminder-rules; only home Partner can manage.
- Explicit opt-in workspace policy: enable/disable, 0–30 days lookahead and mandatory reason with immutable audit of previous settings.
- Daily scheduled command: php artisan erp:milestone-reminders. Use --dry-run for read-only eligible counts.
- Only open/rejected assigned milestones and active unsuspended members receive private inbox reminders; checks use company timezone, exclude items over 30 days overdue and never cross workspace boundaries.
- Unique delivery key (rule, milestone, due date, type) gives at-most-once upcoming and at-most-once overdue. Delivery and inbox inserts are atomic. Due-date changes intentionally create new eligibility.
- Does not alter approval, timesheets, payroll or invoice records. **No external email, WhatsApp, SMS or push delivery**.
- Actual delivery requires a working Laravel scheduler via the hosting cron. Migrations and GitHub CI do not prove cron is configured.

## Known limits / future phases

- Conditional branching, customizable multi-stage form approval, file attachments and public client forms are **not yet included**. Phase 17 brings scalable private object storage.
- No arbitrary cross-database SQL report builder; the existing Partner report and official export remain the official bounded operational summary. User-generated SQL is deliberately forbidden.
- No outgoing message provider, SMS, multi-step escalation engine or provider delivery guarantees.
- Form catalog displays up to 100 templates and 100 recent submissions; exports stream authorized template-version rows. High-volume 200GB benchmark remains Phase 21.
- No destructive alteration of old customer data or Phase 15 project milestones.

## Verification gates

- [ ] PHP/SQLite and MySQL 8 suite including negative-access and CSV safety checks
- [ ] Authenticated browser QA and Vite build
- [ ] cPanel release package validation
- [ ] Reviewed PR to security/production-readiness-gates, post-merge exact-SHA CI
- [ ] **No main-branch merge or live deployment** without explicit release approval
