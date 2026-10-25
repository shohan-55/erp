# Phase 13 — Advanced Payroll Reconciliation & Review Integrity

Baseline: verified Phase 12 development SHA `b41ed561b8acc8304990d13d97b1593adbc0840d`. Branch-only; **no live database or cPanel changes**.

## Existing financial flows preserved
- Partner-only payroll draft/review/approval/paid/locked state transitions, no implicit Manager salary access
- Effective-dated staff compensation, approved student conveyance reimbursements and explicit reasoned/voidable Partner adjustments
- One run per Partner workspace/month; immutable reviewed lines and private finalized payslip visibility
- Private bank settlement evidence, external reference duplication checks, exact cents reconciliation and claim reservation
- Attendance and leave are **informational** review flags, not automatic salary deductions or claims of verified absences

## New controls
1. Review-time **canonical digest** of ordered scoped student lines, with integer-cent columns for base allowance, approved claims, authorized adjustment and net. Every line is checked to reconcile before the Partner freezes the review. Snapshot includes totals, SHA-256, reviewed actor/time and exception counts.
2. For reviewed runs with the new snapshot, approve, record-paid and lock transitions **recompute and compare the canonical payroll digest** inside the scoped transaction. Changed amounts/beneficiaries/line counts fail closed, preventing stale settlement. Historical reviewed runs with no digest retain prior behavior (not silently rewritten).
3. Show the reviewed financial totals, original review note, truncated hash and original reviewer in Partner UI. Partner may provide an optional auditable review note about unresolved exceptions. This is change detection, **not cryptographic assurance against a database administrator** or external bank evidence.
4. Correct attendance aggregation: pending/rejected Manager proxy check-ins are explicitly excluded from recorded/open/late attended counts. Display pending/rejected proxy exceptions separately; the review snapshot preserves the contemporaneous exception counts.
5. No automatic payroll penalty or reopening of paid records. Existing Partner-led adjustments, approvals, payment evidence and student privacy are unchanged.

## Regression acceptance
- [x] Additive schema and safe nullable review provenance
- [x] Deterministic scoped financial digest and amount arithmetic validation
- [x] Review, approval, record-paid and lock integrity enforcement
- [x] Payroll overview reflects **verified** proxy status, pending exceptions remain separately visible
- [x] Tests for cents, tampering after review, payment evidence cleanup, proxy count reconciliation, cross-workspace privacy
- [ ] Latest SHA PHPUnit/Vite/Browser QA, MySQL 8, cPanel package
- [ ] Review and squash merge only to `security/production-readiness-gates`; exact merge SHA CI
- [ ] Separate customer staging validation, backup/restore, production approval and deployment
