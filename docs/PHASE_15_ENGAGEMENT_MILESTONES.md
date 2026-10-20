# Phase 15 — Audit Engagement Projects, Team Milestones and Progress

Baseline: verified development commit `f60797f2583e34cf2dc556086af1718935ddead7`.
Branch-only; no production deployment or customer data modifications.

## Existing operations preserved
- Workspace-isolated practice Clients, statutory/internal/tax/advisory engagements and unique codes.
- Partner-created assignments, logged working hours in 15-minute increments, capped staff daily hours, student and Manager review then independent Partner final approval.
- Signed-off hours are a **reporting metric**, never client billing, automatic payroll or attendance.
- No disclosure of another Partner's clients, assignments, contact details or working papers.

## Delivered
- **Milestone/Task Register** associated with open engagement, current workspace and an active Staff member who is explicitly assigned to that engagement. Partner defines task instructions and due date inside engagement dates; staff receive notification.
- **Staff-submitted work evidence**: assigned person provides a distinct 15–2000 character work note. Rejected work may be resubmitted with a new, auditable note. No Manager impersonation.
- **Separation of duties**: the assigned Student's active Manager recommends approval/rejection once; owning Partner makes the independent final complete/rework decision. Manager-owned tasks always require Partner final approval. Decisions require a substantive review reason, and individual events are appended to the tenant audit trail.
- **Partner controlled unsubmitted reassignment**: due dates, description, responsible person and rationale may be changed only while Open or Rejected. Every prior assignment change is auditable; submitted/completed records are immutable from the edit route.
- **Progress and exception UI**: completed/visible milestones, work awaiting approval, overdue items, and a planned-versus-approved hours warning for the Partner. Manager and Student see only authorized own/team tasks; the Partner gets practice-wide visibility. The first 700 scoped milestones are displayed per engagement list page; large-scale pagination belongs to performance phase.
- **Lifecycle safeguards**: cannot close an engagement with unfinished milestones, unassign a worker with unfinished milestones, exit or transfer a worker with outstanding tasks. Reassignment must be authorized by the Partner before handoff.

## Limits
- No client invoice or automated revenue recognition.
- Task completion requires Partner approval, not a timesheet approval, and does not alter payroll/leave or attendance.
- Complete engagements with zero milestones keep their historical closure process.
- External client logins, client access and document management are future scope.

## Verification gates
- [x] Isolated feature branch with additive schema, server routes, UI and scoped two-person decision
- [x] Tests for own assignment, cross-partner isolation, Manager recommendation, no self-approval, rejection/rework, unassignment, staff exit, immutable submitted records
- [ ] Latest PR-head PHPUnit/Vite and authenticated Browser QA
- [ ] MySQL 8 complete integration
- [ ] cPanel release package (not deployment)
- [ ] Green and reviewed PR squash into `security/production-readiness-gates` only
- [ ] Exact post-merge SHA CI success
- [ ] Staging/UAT, backup/restore and explicit production release authorization separately
