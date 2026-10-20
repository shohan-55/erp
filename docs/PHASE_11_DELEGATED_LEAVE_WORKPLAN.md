# Phase 11 — Delegated Leave Management (implementation gate)

**Status: PARTIAL / NOT MERGED / NOT VERIFIED.** Development branch:
`feature/phase11-delegated-leave-20261020`.
Last verified baseline: Phase 10 development `3d7d344167c0d5aca047a12855446b6990c9ab31`.

## Completed in isolated development branch
- Additive migration recording optional `leave_requests.initiated_by`, mandatory-for-delegation `delegation_reason`, `withdrawn_by`, `withdrawal_reason`, and `withdrawn_at`. Legacy rows remain unchanged/null.
- Recognized independent, explicit Partner-grant permissions for delegated leave submission and withdrawal in `WorkspaceAccess`. These permissions are **not** in the `OPERATIONS` catalogue; therefore a previous `full` delegation **does not silently receive** them.
- Existing real backend remains unchanged while this phase is incomplete.

## Blocker
The GitHub connector rejected attempted updates of existing application controller and custom-role catalog through its safety checks. No deployment, development merge, application behavior change or CI-pass assertion should follow from the incomplete migration/permission definitions.

## Safe completion criteria
1. Dedicated `POST /leave/delegated` with active explicitly authorized Manager only; target is an active assigned student in same company and Partner workspace, with required distinct business reason, no impersonated student login. Validate again inside the workspace lock.
2. Reuse **all** original LeaveController validation: prior/future date windows, annual notice, working-day computation, half-day, overlap, available credits and frozen payroll periods.
3. Stamp initiator separately from student; independently notify student and owning Partner. A Manager-submitted leave must take an independent Partner-only approval route, even if the workspace preset is Manager-final. Existing requests preserve their frozen approval snapshot.
4. Optional reasoned withdrawal of **only pending** delegated requests that the same Manager initiated, with independent `leave.withdraw_for` permission; separately audit status, actor and reason.
5. UI only for managers with explicit live permissions, scoped student roster, provenance badge for all authorized staff and accessible approval history.
6. Unit/Feature/MySQL tests: zero rights by default, explicit grant and revoke/expiry, cross-workspace and unrelated student denied, positive submission, duplicate and overlap, no student impersonation, date/credit rules, frozen payroll denial, independent reviewer requirement, audit/notifications and double-withdrawal denied.
7. Full PHPUnit/Vite/Browser/MySQL8/cPanel release CI on exact PR HEAD; reviewed merge only to `security/production-readiness-gates`, then exact merge SHA CI. Production never deployed automatically.

**Note:** Do not create PR or merge until the rejected source updates can be safely completed and tested.
