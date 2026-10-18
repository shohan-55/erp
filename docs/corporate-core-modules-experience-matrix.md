# Corporate ERP Experience Matrix — Core Modules

Status: current repository feature inventory. Passing CI does NOT imply cPanel deployment.

## Experience and governance
- Student sees own self-service records only, following the Partner-enabled feature map.
- Manager sees assigned students only; financial approval and disbursement are Partner-controlled.
- Partner controls their own workspace, retaining Company-wide governance where already configured.
- Approved policy and server timestamps decide attendance and leave; calendar color never causes an automatic absence or wage penalty.
- Dangerous mutations need explicit confirmation and still require server authorization, CSRF and an audit record.
- Government holiday imports require the firm's verification; there are no invented dates or pretend payments.

## Core module inventory

| Module | Student | Manager | Partner | Corporate experience pass |
|---|---|---|---|---|
| Attendance | Check-in/out, history, correction requests | Own attendance, assigned team review | Reports, corrections, reconciliation | Live Work Pulse, searchable history, contextual guide |
| Calendar | Own schedule, approved leave and holiday status | Team attendance counts, annual holiday proposals | Annual approvals and branding | Previous/next month, date details, scoped CSV export |
| Leave | Balance, applications, withdrawal, approvals status | Assigned student initial review | Final approval, future cancellation, credits | Status tracker, filters and confirmation |
| Conveyance | Private receipt-backed claims | Assigned claims review | Final amounts and payroll reimbursement | Filterable claims, live queue and approval confirmation |
| Payroll | Locked authorized payslips | No Partner payroll controls | Draft, review, approve, paid evidence, lock | Workflow guide, safer transition confirmation |
| Notices | Own inbox | Workspace notifications | Publish authorized notices | Inbox search/read filters, publication confirmation |
| HR policy | Assigned effective policy | Propose future rules, limited delegation | Sensitive approval and historical versions | Guided settings navigation |

## Out-of-scope / not falsely claimed as finished
- Automatic government Gazette verification, email/push delivery and scheduled notice distribution.
- Bank/mobile salary disbursement, tax filing, biometric check-in or automatic legal-compliance certification.
- Separate Manager-by-Manager HR policy delegation (current delegation is workspace-based).
- Unbounded server-side historical search: status filters currently cover loaded records; calendar browsing is bounded around current month.
- Automatic confirmed absences or wage deductions without a reviewed workflow.

## Production acceptance gates
1. Test role-specific routes on desktop, 390px and 375px mobile devices.
2. Confirm Manager access is assigned-team-only and Student CSV contains no other user's attendance.
3. Verify search, status filter and pinned links after a reload and across workspace switches.
4. Confirm that cancelling an approval sends zero write requests; confirming submits once.
5. Test calendar date details, monthly navigation, safe spreadsheet exports and historical immutability.
6. Pass PHPUnit, MySQL 8, authenticated Playwright and cPanel release package security checks.
7. Review environment, backups, migrations, rollback and real-user UAT before any later live deployment.
