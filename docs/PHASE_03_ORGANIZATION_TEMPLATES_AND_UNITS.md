# Phase 03 — Multi-Organization Templates and Scoped Structure

**Development-only implementation.** Baseline: `security/production-readiness-gates`, after successful Phase 02 merge at `68668e664ed8aa497c6156c6af0f1577a03538bd`.

## Delivered scope
1. **Organization templates:** `ca_firm`, `corporate`, `ngo`, `consultancy`, `other`, defined centrally in `App\Support\OrganizationTemplate`. New platform onboarding validates the chosen type, sets the template-specific primary workspace display name, records the selected type in platform audit, and preserves existing invitation activation security. Older onboarding with no type still defaults to `ca_firm` and `Partner Unit 01`. An existing tenant's `organization_type` is never silently overwritten.
2. **Optional organizational units:** create office, branch, division, department, practice, program or team in a selected existing workspace; each unit can have a same-workspace parent with nesting capped to five levels. Presets are suggestions, not a list of compulsory departments. Unit's slug, kind, parent and tenant/workspace owner are not retroactively changed. Rename, deactivate/reactivate with reason and membership assignments have audit events.
3. **Member assignment:** link one active workspace membership to multiple organizational units; revoke that link without removing the user's account, payroll, attendance or role. Each create/assign/rename/toggle is authorized as a **Partner-only** write on the authenticated selected workspace; delegates and cross-company actors do not automatically inherit rights.
4. **Responsive UI:** platform create-firm type picker and profile display; workspace-level Organization Structure screen and navigation; members may only be selected from active/current-workspace identities.
5. **Non-destructive schema:** `organization_units` and `organization_unit_assignments` are additive tables. No mass backfill, no existing partner/workspace rekey, no operations on customers or live server.

## Required security invariants

- A unit's parent must have **the same company and same workspace**; foreign company/workspace IDs are always rejected, even if a submitted ID exists in the DB.
- Actor must be authenticated with the selected firm's member context, currently with `partner` role. The DhakaWala platform operator remains unable to view tenant-private units or assign members.
- Staff assignments require an active membership from the **same company + workspace**. Unit management does not itself authorize transfer between workspaces or promotion.
- Unit deactivation is blocked while it contains assignments or active child units. A disabled unit can be reactivated only under an active parent.
- Historic tenant data, payments, membership IDs, payroll owners, and CA firm's unanimous governance remain untouched.
- Tests must prove unknown organization types are rejected; CA-firm default is backwards-compatible and new corporate onboarding is functional.

## Honest limitations and later phases

- Phase 03 creates structural metadata. It does **not** add custom employee roles, rewrite old `partner/manager/student` permissions, or automatically change existing payroll/leave/attendance aggregation by department; those are separate reviewed integrations in Phases 04/05/14.
- Existing Partner workspaces remain the hard privacy boundary even for corporate types; a branch/unit under one workspace is not a license to see another Partner's data.
- Template type remains fixed after onboarding until an explicit audited migration workflow is built; changing business types cannot silently reinterpret finance or staff records.
- List entry is bounded to 500 units/members per workspace for this initial admin UI; scaling & pagination are Phase 21 tasks, not proof of 200GB production capacity.
- Release to live cPanel is **not approved** by this feature or green CI. Follow separate staging restore, manual UAT, and owner signoff.

## Delivery gates
- [x] Separate branch, additive migration and server-side tenant scoping
- [x] Company onboarding profile and organization unit/member management UI
- [x] Automated tests for template validation, nested units, role/tenant isolation, archived state and no user migration
- [ ] Complete MySQL 8, PHPUnit/Vite, browser and package CI for exact PR SHA
- [ ] Code review, merge into development branch only, post-merge CI
- [ ] Manual staging user acceptance/actual deployment (separate go-live gate)

See the [Phase 01 architecture](PHASE_01_MASTER_SAAS_BLUEPRINT_2026.md) for the larger 24-phase roadmap.
