# Phase 14 — Staff Lifecycle, Clearance and Permission Hygiene

Baseline: verified Phase 13 development SHA `4760226d02286028dcd9e2c84149ec74ef4ca23f`. Development only; live production and customer data untouched.

## Existing workflows preserved
- Partner-only appointment/profile edits, designation history, ICAB articleship dates, employee private files and own-record access
- Effective future salary authorization, prior payroll snapshots, transfer through source proposal + receiving Partner consent
- Partner-controlled staff exit/rejoin, with no erasure of historical payroll, leave, attendance, approved expense, transfers or private documents

## New capabilities
1. **Exit Clearance Preview** at `/staff/{id}/exit-clearance` accessible only to the owning Partner; previews outstanding team assignments, open punches, pending corrections, unpaid claims, future leave, open current-month payroll, scheduled future salary decisions, pending transfer, pending Manager-initiated leave/expense and unverified Manager proxy attendance. There is no access to other Partners' HR data.
2. **Atomic Staff Exit:** the same checklist is re-evaluated inside a company/workspace/membership locked transaction; the Partner supplies a mandatory reason. Exit never bypasses unresolved pending financial or approval obligations.
3. **Prevent privilege resurrection:** exit revokes local active Workspace Manager/Moderator grants and direct subdelegations, deletes the live custom-role assignment after recording its old role/id in the immutable lifecycle/audit event. Rejoining staff do not get back those permissions: a Partner must expressly reauthorize. Base employee financial and historical records remain.
4. **Transfer/Exit race safety:** exit/rejoin takes the same Company row lock as two-Partner transfer acceptance. Approved transferred source memberships may not be resurrected using old Partner rejoin forms.
5. **Rejoin provenance:** only truly exited, activated and unsuspended staff without a completed transfer can return. Each exit/rejoin is separately appended to the staff lifecycle and tenant audit records.
6. **Privacy-first UX:** per-staff exit readiness and privilege warning, reasoned exit/rejoin form, no public endpoint and no destructive data clearing.

## Limits & release gates
- This release does not automate final settlement or disbursement, does not rewrite payroll or create salary penalties from lateness.
- It does not silently upgrade a Manager into Partner.
- It does not perform offboarding of a separate company identity or global login when a user belongs to more than one company.
- [x] Isolated feature branch with scoped clearance implementation, UI and security regression tests
- [ ] Exact PR head PHPUnit/Vite/Browser QA + MySQL8 integration + cPanel package
- [ ] Reviewed squash merge only to `security/production-readiness-gates`
- [ ] Exact post-merge SHA CI success
- [ ] Staging smoke tests, backup recovery, live owner authorization and production deployment are separate tasks
