# Phase 12 — Advanced Conveyance & Expense Management

Base: verified Phase 11 development commit `9f3efeda8ded955f47cb46fe3e093a747c1f0407`.
**Development only.** No production deployment, live database migrations or personal customer data touched.

## Delivered

- Explicit separately revocable `claim.submit_for` and `claim.withdraw_for` rights in both workspace grant and custom-role catalogues. Legacy `full` workspace grants **do not** silently include new expense rights.
- Dedicated POST `/claims/delegated` and `/claims/{id}/delegated-withdraw`, each tenant-hosted, authenticated, MFA-protected and throttle-limited.
- Authorized Manager/Moderator may submit a new conveyance claim only for an **active assigned Student** in their own company/Partner workspace. Delegation reason (minimum 15 chars) is distinct from the work purpose.
- Student and Manager share the **exact same validated controller path**, including future-date denial, supported expense categories, 2 MB PDF/JPG/PNG receipt validation, 50,000 BDT per-claim validation, Partner yearly expense policy, claim count/budget, duplicate SHA-256 receipt, and payroll freeze.
- Receipt is always privately stored against the **actual Student beneficiary** in Laravel's local private filesystem. Never the Manager's public media or the Student's GPS; unauthorized or invalid submissions delete newly stored evidence on failed transactions. Existing approved evidence is never deleted by withdrawal.
- For any Manager-created claim the original `initiated_by` and `delegation_reason` are snapshotted, and `approval_mode` is forced to `partner_only`. Manager may neither review nor approve/reject their own delegated claim, even if the ordinary workspace policy is `manager_final`.
- Partner final decisions preserve immutable claimed amount, cents-based approved amount cap, reduction explanation, reserved/paid payroll locks, and separate audited approval events.
- Optional Manager withdrawal is **only** available for pending, unreserved, unpaid claims that the **same Manager initiated**, with an independently granted live right and a written reason. It preserves the original evidence and adds actor/time/reason provenance.
- Student receives on-platform notification of delegated submission or withdrawal. Partner is notified through existing workspace team recipient logic. Tenant audit records exact initiator, beneficiary, amount, receipt SHA-256, delegation reason, approval and withdrawal; no session impersonation.
- No-code assigned-student form in current Claims UI, submission provenance and withdrawal button, with backend permission enforcement on every operation. Student self-service and legacy claims continue unchanged.

## Safeguards and explicit limits

- No new automatic expense payments or payroll adjustments: approved claims continue into the existing Partner-reviewed payroll process, where reservation/payment is idempotent.
- A Manager cannot act on an unrelated team, another Partner's workspace, an inactive member, an unassigned Student, or an expired/revoked grant.
- Partner-level Expense Policy and Export remain Partner-only, and CSV formula-injection protections are preserved.
- Actual receipt/document quotas and object-storage lifecycle are Phase 17; scalable payroll integrations are separate future phases.

## Acceptance gates

- [x] Isolated branch, additive migration, permission catalogues, delegated routes and UI
- [x] Shared submission workflow and independent final authorization
- [x] Automated negative and positive tests for explicit grant, private receipt, student identity, amount limit, duplicate file, frozen payroll, cancellation, revocation, tenant scope, and legacy self-service
- [ ] Latest PR-head PHPUnit/Vite + Browser QA
- [ ] MySQL 8 full Integration
- [ ] cPanel release package (not deployment)
- [ ] PR review and squash merge only into `security/production-readiness-gates`
- [ ] Merge-SHA CI success and confirmation
- [ ] Independent staging/UAT, restore drill and explicit production go-live approval
