# Phase 09 — Configurable Workspace Approval Workflows

Development baseline: `97f070bdce40b3385f12cb3214ebd5535f25ffed`.
No production deployment or live data migrations have been run.

## Working capabilities
- Partner-owned, no-code approval presets for **Leave** and **Conveyance** independently, isolated to the currently selected Partner workspace.
- **Two-step** default: assigned Manager preliminary review, then owning Partner final approval. Existing requests with no `approval_mode` keep this exact legacy behavior; Partners may approve directly only when the student has no active assigned Manager.
- **One-step Partner**: the owning Partner may decide a pending application directly. Manager cannot review or finalize it.
- **One-step Assigned Manager**: the Partner explicitly authorizes assigned Managers for final decisions on NEW applications in that workspace. No role- or global-right escalation; an unassigned student's new requests snapshot Partner-only to prevent deadlock. If an assigned Manager is later disabled, the Partner may make the final decision as fallback.
- Policy choices are stamped into each newly submitted request; changing the current rule **never reroutes existing pending or reviewed requests**.
- Leave final approval preserves balance, overlap, yearly policy, calendar and frozen payroll checks for whichever actor gives final approval. Conveyance final approval preserves duplicate receipt protections, immutable claimed amount, maximum approved amount, reduction reason and payroll lock, whether Partner or assigned Manager authorizes.
- Every review inserts a separate immutable event (actor, stage, status before/after, note, chosen preset), as well as the existing tenant audit and student notification. Duplicate final decisions reject.
- Partner-only Studio has an approval history register; other Partner workspaces cannot access the policy or evidence.
- There is no way to switch off MFA, privacy isolation, monetary controls or audit from this Studio.

## Explicit non-claims
- This phase delivers **three executable one/two-step presets**, not an arbitrary multi-level workflow DAG, unrestricted Manager impersonation, cross-team reviewer, or automatic supervisor delegation. Future role-specific approval chains would need separate database states, security review and regression tests.
- Original approval decisions, payroll records, submitted amounts, student balances and historical entitlements are not rewritten.
- Application display shows the snapshotted policy. Settings are governed by owning Partner; Phase 08's company-wide voting remains separate.

## Acceptance gates
- [x] Isolated feature branch, additive schema, policy snapshots, decision events
- [x] Protected Partner-only configuration and review UI
- [x] Actual Leave/Claims server-side enforcement and legacy default preservation
- [x] Regression tests for direct Partner approval, Manager final claims, amount limits, isolation, frozen pending policy and legacy two-step
- [ ] Full PHPUnit/Vite + MySQL 8 integration + Browser QA + cPanel package
- [ ] Reviewed development PR merge and merge-SHA CI verification
- [ ] Production staging UAT, DB backup restore and explicit go-live authorization (separate)
