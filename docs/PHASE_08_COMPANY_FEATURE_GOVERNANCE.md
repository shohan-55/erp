# Phase 08 — Policy Inheritance, Immutable Versions & Unanimous Company Feature Approval

**Base development commit:** `6782ef0af96ea40e3cb5e7cf60c388a162348102`.
**Deployment:** development branch only; no production migration, no live data access.

## Existing controls preserved

The established policy engine already has:
- Immutable, future-month company office-hour proposals and individual Partner votes;
- Workspace-specific office-hour and holiday overrides;
- HR policy snapshots, approval and date-specific calendar exceptions;
- Frozen payroll/attendance protection.

Phase 08 keeps all of those mechanisms. It extends Phase 07's module settings with **company-level proposal versioning** rather than rewriting working policy logic.

## New company module governance

- Partner creates an append-only company feature policy proposal for one of the 13 supported operational modules. Operation: `enable`, `disable` or `reset`.
- Effective date is the **first of a future month** in the tenant timezone. Backdating and same-month execution are blocked.
- Eligible Partner identities are frozen from current active, non-suspended Partner memberships whose workspaces are active; a configured company minimum of active Partners is enforced. RA&C-style three-Partner firms require **three distinct individual approvals** (not just proposer approval).
- Every voter is authenticated, still an active Partner, and can vote once. A rejection ends the proposal. A changed Partner roster, missed date or quorum gap suspends an outstanding proposal.
- All votes, rejected/suspended proposals and earlier approved versions persist as an audit trail. A subsequent approved version for a **later** month supersedes the prior one, but cannot overwrite it.
- Status `pending` and even `approved` do not alter module access before the effective month. The runtime resolver selects the latest approved version at or before the tenant-local date, **without requiring a background cron or mutating historical records**.
- Existing immediate company override rows from single-Partner tenants are preserved; after effective date, the approved company version takes precedence over that baseline. Reset restores the inherited company baseline. Workspace, role, user and historical student restrictions still deny independently.
- Feature Studio displays the company-wide proposal form, active electorate, current/past proposal register, votes and status. Company-wide immediate edits remain prohibited when there are multiple active Partner workspaces. Workspace/role/user settings remain independent and immediate.
- Proposal/vote transactions lock the company row, then proposal row, to serialize competing votes/roster-sensitive approvals; tenant ownership and authorization are checked again in the transaction.
- Audit records include proposal identity, intended version/effective date, original electorate, votes and suspension events. All active eligible Partners receive on-platform notices in their own workspaces.
- No module toggle for security, tenant access, audit, subscription, billing, governance pages, or other recovery mechanisms.

## Boundaries and follow-up

- A company-wide policy version is signed off under the electorate at approval time; later personnel changes do not retroactively rewrite an approved historical version.
- This phase does **not** reauthorize final leave approval, private salaries or payroll actions, and does not mix private Partner operational data.
- SaaS plan-feature entitlements and long-term large-dataset performance remain separate phases.
- Company defaults can be more restrictive than Partner workspaces; Partner workspace overrides cannot re-enable a disabled company module.

## Verification gates
- [x] Separate branch, additive schema and immutable company policy source
- [x] Future-date inheritance resolver and Partner-only proposal/voting controllers
- [x] Feature Studio UI + audit/notification integration
- [x] Security/regression tests: 3 distinct votes, early-access denial, rejected/suspended/changed roster, min quorum, independent workspace denial, future version/reset, cross-tenant, invalid/backdated date
- [ ] Exact PR SHA: full PHPUnit/Vite, MySQL 8 integration and Browser QA
- [ ] Reviewed and merged to `security/production-readiness-gates` only
- [ ] Post-merge SHA CI
- [ ] Staging UAT/backup restore and explicit live release approval (separate go-live gate)
