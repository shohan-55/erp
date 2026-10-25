# Phase 05 — Custom Roles, Effective Permissions and Isolation

Development-only feature. Based on `security/production-readiness-gates` at `2e9872faf68ca3f58fa89b44acf311ac5466490c`.

## Scope completed in this phase
- Workspace-specific custom role definitions (name, immutable slug, description, JSON list of recognized permissions, active status). A Partner creates, edits, disables/enables and audits roles via **Custom Role Studio**.
- One assignable custom role per active Manager (the immutable base Partner/Manager/Student membership and employee/payroll ownership never change). Partner can reassign/revoke an assignment with a reason and audit event.
- A tenant/workspace-scoped **server-side** permission evaluator reads fresh database state on each request. Revocation, suspended member or inactive custom role cease to authorize at once. Unknown permission keys deny by default. Manager cannot edit own or anyone else's role; Partner is the only settings owner in this phase.
- Executable permission catalogue only includes actions actually checked in backend:
  - `attendance.propose`: already-supported manager-initiated corrections for **assigned students only**, with unchanged Partner final review and immutable original evidence;
  - `organization.view`: read office/team/department metadata within selected workspace;
  - `organization.manage`: edit organizational hierarchy and membership unit assignments within selected workspace, including view.
- Existing independent workspace access grants are retained (the role is an additional route to `attendance.propose`, not a replacement for previously granted rights). Partner rights and CA firm governance are not made customizable or silently removed.
- New tables are additive: `workspace_custom_roles`, `workspace_role_assignments`. No destructive migrations; no retroactive user, payroll, attendance, subscription or tenant changes.
- Manager custom-role grant is restricted to the current company + workspace. Students, system/platform administrators and members of other workspaces cannot be assigned these privileges.

## Explicit boundaries
- Custom job-title names (HR, Accounts, Moderator) are **labels**, not a grant of privileges beyond the selected server-enforced operations. In particular this release does **not** enable payroll, salary, account-holder change, private documents, leave-final approval, proxy attendance, platform administration or manager-controlled delegation.
- Phase 06 will implement time-bounded manager/moderator delegation, approval-lifecycle extensions in Phase 09, and remaining module-specific permission enforcement in their corresponding module phases. Never expose checkbox rights not enforced by backend endpoints.
- A disabled custom role does not revoke a separately authorized legacy workspace access grant; users can have different independent authorizations, all listed separately under Company Access Control.
- No physical deletion, no live production deployment, no entitlement to read other Partner workspaces.

## Security test plan
1. Partner creates, updates, disables and assigns a role, with reason + audit.
2. Manager can view organization structure through explicit `organization.view` but cannot mutate it until `organization.manage` exists.
3. Explicit `organization.manage` allows only same-workspace structure; loss of right, role disable and assignment removal take effect on the next request.
4. `attendance.propose` uses existing supervisor-of-student checks, preserves original attendance and a separate Partner approval. A custom role does not confer `attendance.final`.
5. Manager cannot edit roles, assign self, grant platform status, promote students or choose cross-workspace/company members/roles.
6. Unknown permission names and duplicate scoped slugs fail.
7. Old non-custom role behavior and partner routes remain backwards compatible.

## Release gates
- [x] Isolated development branch, additive schema and UI
- [x] Server-side scoped evaluator integrated with existing operations
- [x] Regression tests for tenant isolation and revocation
- [ ] Full PHPUnit/Vite, MySQL 8 integration, browser QA and cPanel-package CI green for exact PR head
- [ ] Reviewed development PR merged only to `security/production-readiness-gates`
- [ ] Post-merge exact SHA CI green
- [ ] Production staging, rollback drill and explicit live deployment approval (**not granted here**)
