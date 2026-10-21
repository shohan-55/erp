# Phase 04 — Multi-company identity, invitation consent and company switching

Status: proposed development implementation. No production release authorization.

## Implemented
- Canonical-host global account login/logout and **My Companies** selector. One authenticated account can hold roles in several independent organizations and partner workspaces.
- Switching validates the current user's active membership plus company active/not-archived/not-removed and workspace active status. Previous membership session binding is cleared, regenerated and replaced; multi-workspace companies use the existing Partner workspace chooser.
- Existing-user invitations use a **separate SHA-256-hashed 64-character one-time token**, 7-day expiry, authenticated same-user session, MFA verified middleware and current-password confirmation. Tokens are sent only to the existing account's email and stored only as digests; accepting atomically activates the *specific* membership.
- All four invitation entry points handled: initial Platform company owner, additional Partner workspace, Platform staff invitation, and Partner team invite/resend.
- No password resets, session terminations, global User name overwrites, shared credentials changes or automatic company membership for existing people.
- Routes are canonical-host-only. A signed-out recipient uses `/account/login` and then reopens the email link; MFA challenge returns to My Companies.
- View and controller paths are audited tenant/company scoped; revocation/expiry/suspension immediately blocks selection or acceptance.

## Limitations and safety
- Phase 04 is identity/company switching, **not custom roles or cross-workspace data access**. Phase 05 onward implements role/permission rules.
- Changing from a client-owned custom domain to canonical global account portal may require signing in again due browser cookie domain isolation. Global action URLs intentionally use `APP_URL`.
- New ERP accounts still follow the existing one-time activate-and-create-password flow. Existing ERP accounts never receive a new-password activation link.
- No live migration or deployment. All test coverage uses isolated CI databases.
- Distinct per-company roles/memberships remain enforced by selected tenant context. Platform admin has no implicit access to tenant data.

## Acceptance
- [x] Preserved established workspace session/membership architecture
- [x] Implemented one-time existing-user linking with password + MFA
- [x] Multi-company switch and tenant/MFA guard
- [x] User-facing login, switcher and invitation UI
- [x] Negative tests for replay, invalid password, other identity, expiry, suspension and unauthorized company selection
- [ ] PHPUnit/Vite + MySQL8 + Browser CI all green for exact head
- [ ] Reviewed PR and merged only to `security/production-readiness-gates`
- [ ] Merge-commit CI green
- [ ] Production staging UAT, backup restore and explicit release approval
