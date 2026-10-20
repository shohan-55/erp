# Phase 11 — Advanced Leave Management & Delegated Operations

Base: verified development SHA `3d7d344167c0d5aca047a12855446b6990c9ab31`.
**Scope:** development branch only; deployment and customer data unchanged.

## Delivered in Phase 11

- Explicit partner-controlled, revocable `leave.submit_for` and `leave.withdraw_for` operation grants for Managers / designated Moderators with Manager memberships; supported by the legacy workspace access grant and custom role catalogues. Existing `full` delegation **does not silently include** these new permissions.
- Dedicated tenant-scoped manager-only `POST /leave/delegated` and `POST /leave/{id}/delegated-withdraw` routes. Student's normal submission endpoint forbids targeting another account; no session/account impersonation.
- Managers may submit for only their actively assigned, unsuspended students in the same company and Partner workspace. Permission and assignment are checked again **inside a serialized database transaction**.
- All leave validations share the existing `LeaveController` submission function: annual notice, leave type, year constraints, no unsupported retroactive leave, working-day/half-day calculation, overlap, reserved entitlements, credits and frozen payroll.
- Every submission stores `initiated_by` and a distinct, mandatory `delegation_reason` separate from the student's leave justification. Historic records retain null initiator and remain valid.
- **Self-approval prohibited:** every manager-initiated application snapshots `partner_only` regardless of a Manager-Final workspace setting. Assigned Manager cannot approve/reject their own delegated application. Partner makes an independent final decision through the existing ApprovalFlow and balance check.
- Permission-gated withdrawal of *only pending requests initiated by the exact same Manager*, with an independent minimum-length reason, `withdrawn_by`, `withdrawn_at` and a student/Partner notification. No approved or already withdrawn request can be edited.
- Restricted UI shows only assigned students to authorized Managers, captures separate leave and delegation reasons, displays actor provenance and offers reasoned owned-pending withdrawal. Partner and student own forms unaffected.
- Existing tenant-scoped audit events and notifications record the initiating actor and beneficiary without leaking cross-workspace identities.

## Backwards compatibility / boundaries

No company-wide HR policy changes, no salary or payroll history rewrites, no extra approved leaves, no retroactive self-approval. Leave Balance and Phase 09 workflow snapshot logic stay intact. Arbitrary third-party proxy access, impersonation, moderator final financial decisions or cross-tenant grants are not added.

## Verification gates

- [x] Isolated branch, additive schema, permission keys, routes, UI, audit
- [x] Shared Leave Controller validations for student and delegated workflows
- [x] Automated tests for delegated permission, revocation, student identity, cross-company target, independent Partner approval, notice/overlap, payroll freeze, withdrawal identity, historical self-service
- [ ] Latest PR head PHPUnit/Vite and Browser QA
- [ ] MySQL 8 Integration and cPanel package validation
- [ ] Reviewed squash merge only into `security/production-readiness-gates`
- [ ] Post-merge exact-SHA CI check
- [ ] Staging restoration, live release approval and production migration are separate
