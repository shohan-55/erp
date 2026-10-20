# Phase 01 — GitHub Code Audit, Gap Register & Acceptance Gates

**Audit date:** 2026-10-20
**Baseline commit:** `925d4e96a0162c1f7694119c82dda0e500f32970` on `security/production-readiness-gates`.
**Other refs examined:** `main` at `c4f80cad2b83e0e3806263b3f7ad94bac0113be9`; PR #25 head `4f0db93b85efbba3a7f4cd77dabe9432043f93c6`; PR #56 head `3a7a28f57f0a80b06217d64970a2ab60662df528`.
**Evidence limitations:** read-only GitHub files, branch/PR metadata and workflow-run metadata. No privileged production account or live database accessed. No local deployment, production migration, or large-data performance test was run in this planning change. The current deployed SHA, DB size and cPanel processes remain unverified.

## Observed implementation versus missing product capabilities

| Area | Verified source / observation | Delivery implication |
|---|---|---|
| SaaS multi-tenancy | `TenantOps`, tenant middleware, `companies/workspaces/memberships`, tenant-scoped tests | Preserve company + workspace boundary on all new joins and URLs |
| Membership identity | `2026_10_18_010000_allow_separate_workspace_memberships.php` changes uniqueness to company+workspace+user | Existing membership IDs must remain stable; **cross-company invitation/account linking** still needs policy and implementation |
| Partner governance | `company_policy_proposals`, `company_policy_votes`, `workspace_policy_overrides`, `PolicyResolver` | Keep multi-partner approval constraints and effective-dated snapshots |
| Delegated roles | On baseline, `WorkspaceAccess` has a small hard-coded permission catalogue and scoped grant record | Design expandable role templates and operation/data/field scope, avoiding automatic platform elevation |
| Student features | `StudentFeatureAccess` lists 7 student-facing features; controls are per-student | Need a general tenant/workspace/role/plan feature registry and dependency validation |
| PR #56 | Open, not merged: adds proxy attendance, scoped on-behalf leave/claims and independent approvals; final head CI passed on Oct 19 | Review and integrate against exact current development SHA before relying on it; CI does not prove production approval |
| Leave and claims | Baseline `LeaveController::decide`, `WorkflowController::decideClaim` implement staged manager/partner logic | Request-specific workflow snapshots, configurable single-stage and threshold stages are **not** present |
| Attendance corrections | Dedicated review flow & audit exists; PR #56 has proxy attendance and initiator attribution | Audit action boundaries, mandatory reason, original timestamps, payroll freeze and forged-location prevention |
| Demo/company lifecycle | `PlatformManagementController::transitionCompany` allows suspend/reactivate/archive/restore, no true purge | Step 02 implements safe demo classification/soft removal first |
| PR #25 | Open, unmerged, based on an older development SHA; proposes soft removal with exact slug + DELETE and safe restore | Do NOT blindly merge a stale 15-file PR; review/apply relevant subset after PR #56 overlap analysis |
| Data storage | `config/filesystems.php` supports S3 driver but default is local; claim receipts call `store(...,'local')` and `Storage::disk('local')` | Build a disk-agnostic tenant media service and backfill plan; avoid mass file migration before inventory |
| Background jobs | `config/queue.php` includes database/redis drivers | Deployment worker/cron, isolation, retries and export throughput unverified; do not claim processing capacity |
| SaaS billing | `SaasBilling`, `saas_subscriptions`, invoices and payment approvals | Separate billing retention/legal obligations from deletable customer operational data |
| Report performance | `PracticeReport::month` calls in-memory `get()` and groups; bounded to selected month/workspace but can still grow with team size | Profile realistic large tenants; evaluate grouping in SQL, indexes, paging and queued export |
| Production release | `docs/PHASE_14_FINAL_PRODUCTION_HANDOFF.md` records prior **NO-GO** pending human/staging gates | Continue NO-GO for new releases until all independent checks are satisfied |
| README | `README.md` still claims only a UI prototype, contradicted by controllers, migrations and tests | Replace stale status narrative in a separately reviewed documentation update |

**Git history at audit:** `security/production-readiness-gates` is 192 commits ahead of `main`, not a production authorization. Branch divergence may evolve; always recheck just before any merge or deployment.

## Risk register (initial; validate continuously)

| ID | Priority | Risk | Containment / required validation |
|---|---|---|---|
| R01 | Critical | Unverified production deployed SHA, DB/worker/backup state | Obtain read-only authorized staging/cPanel inventory before any release; never assume branch = live |
| R02 | Critical | Direct company cascade deletion can destroy linked payroll, billing and audit evidence | No raw company row delete; scoped purge plan, dry run, legal holds, export proof, explicit authorization |
| R03 | High | Stale PR #25 overlaps platform/team/operations and may conflict with PR #56 | Rebase/review diff against current development; integrate targeted changes, run tests |
| R04 | High | Delegated approval can cause self-approval, privilege escalation or cross-workspace data leak | Deny-by-default matrix, initiator != reviewer, scoped queries, negative tests, transaction locks |
| R05 | High | Single/multi-step customization could retroactively change active approvals | Persist per-request workflow version and stage actor decisions, immutable audit ledger |
| R06 | High | 200GB export could saturate single cPanel process/storage | External object storage, bounded workers, streaming/chunks, rate limits, capacity tests, user-visible status |
| R07 | High | Private attachments on local disk hamper export, restore and tenant purge | Build private media abstraction, per-object inventory, scoped signed access, file+DB restore test |
| R08 | High | Existing-account invitation refusal blocks legitimate company switching | Explicit consent-based membership linking, no password reset, MFA and identity conflict review |
| R09 | Medium | Dashboard reports consume growing in-memory datasets | Query plan review, per-tenant indexes, pagination/materialized summaries and benchmarks |
| R10 | Medium | Existing README may mislead deploy operators | Update and maintain one authoritative release status/handoff |
| R11 | High | Immutable finance snapshots and legal records may conflict with auto-cleanup | Data classification/retention schedule, legal hold, retained minimal platform ledger |
| R12 | High | Feature toggles applied only to navigation, not route/API/job | Single server-side feature guard and coverage for all execution paths |

## Integration order for open PRs

1. Keep this Phase 01 blueprint isolated on `planning/phase01-saas-blueprint-20261020`; docs-only, no runtime changes.
2. **PR #56**: fetch fresh head/base and compare all changed code, ensure final head CI and actual review, verify no DB rollback hazard, then merge to designated *development* branch if accepted. Check post-merge SHA CI.
3. **PR #25**: old branch predates newer integration; review stale dependencies. Selectively port company soft-removal/restore and operational controls to a newly based feature branch rather than blindly applying overlapping controller patches.
4. Do not merge either PR to `main` without separate consolidated release, exact SHA checks, staging evidence and explicit production authorization.
5. Future Phase 02 handles demo classification and **recoverable** removal. Actual verified customer offboarding/purge remains Phase 18 after storage/backup machinery exists; never claim phase 02 safely hard-deletes 200GB.

## Phase 01 acceptance checklist

- [x] Confirmed development/default branch identities and compared history.
- [x] Inspected current authorization, data isolation, attendance/leave/claim flows, company lifecycle, storage, queue, reporting, billing, policy and release docs.
- [x] Classified PR #25 and PR #56 by lifecycle and overlap risk; no merge initiated.
- [x] Recorded explicit invariants and 24-phase dependency-aware blueprint.
- [x] Defined safe cancellation/export/purge concept and 200GB benchmark plan.
- [ ] Verify documentation PR CI against its **own** exact head SHA after creation.
- [ ] Obtain independent code/security review comments and resolve any blockers.
- [ ] Verify actual staging/production deployed SHA and infrastructure **read-only** when authorized (a release prerequisite, not permission to change production).
- [ ] Capture approved Phase 02 API/data design in a new feature PR; no irreversible delete in the first implementation.

## Mandatory scenario test matrix for implementation phases

**Identity / tenant scope**
- Same person joins two unrelated companies only after verified invitation/consent; login and MFA continue to work.
- Manager with delegated `leave.final` cannot view other partner workspace, other company salaries, or platform admin metadata.
- Revoked/expired delegated grant rejects route POST, API, queued action and stale session.
- Company's global policy cannot be overridden by a workspace manager when firm-wide voting is required.

**Approval and financial control**
- Company configures single-stage leave with one authorized manager; request reaches approved only after non-initiator's decision.
- Two-stage leaves/claims require distinct approvers; changing policy during an in-flight request does not change its version.
- A manager who submitted a student's request cannot review it themselves; no ability to approve two stages in one identity.
- Claim amount threshold maps to correct extra stage without disclosing receipt to unrelated members.
- Completed payroll refuses historical attendance, approved claims or salary changes without audited adjustments.

**Attendance / proxy**
- Proxy attendance records current server timestamp and actual operator with mandatory reason; no forged student geolocation.
- Backdated correction stores old/new values, reason, evidence, reviewer IDs, immutable audit link and payroll lock handling.

**Data exit / storage**
- Demo tenant remove is reversible and cannot delete a different tenant's files, billing or user credentials.
- Cancellation creates read-only export window and handles expired, interrupted or disputed exports.
- 200GB dataset export resumes per chunk, verifies hashes and file inventory, and does not exhaust memory/disk on app server.
- Reject purge until verified export/delivery, retention cleared, legal holds checked and approval recorded.
- Purge is scoped, idempotent and auditable; shared users remain in other companies; backup version expiration is tracked.

**Performance / resilience**
- Instrument P95 dashboard/attendance latency for tested concurrency with a representative large tenant, including another active small tenant.
- Reports are bounded/paginated; background tasks cannot starve authentication, attendance or payroll UI.
- Simulate queue failure, object-store timeout, DB deadlock, duplicate approval and restore-on-staging; recover safely.

## Phase 02 entry and exit

**Entry:** docs plan reviewed, parent dev SHA rechecked, PR overlaps reviewed and no production operations scheduled.

**Implementation:** a dedicated feature branch adds typed demo/non-demo classification, company lifecycle state machine, audit, dry-run dependency preview, reversible removal and restore permissions, lifecycle UI and negative tests. Keep pre-existing subscription and tenant data intact.

**Exit:** all SQLite/MySQL8/PHPUnit/browser checks for the exact head green; reviewed changes merged to development only; no claim of physical tenant purge or live deployment.

See [Master SaaS Blueprint](PHASE_01_MASTER_SAAS_BLUEPRINT_2026.md) for architecture and all 24 delivery phases.
