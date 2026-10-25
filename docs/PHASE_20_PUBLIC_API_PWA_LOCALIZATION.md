# Phase 20 — Public API, Integrations, Mobile/PWA and Localization

Development baseline: Phase 19 merge `f8b4c8f6ebd4081bb82a388e52f083b7732f72e2`. This is **development-only** and does not deploy to `main`, cPanel, or live customer databases.

## Delivered scope
- Versioned, **read-only** `/api/v1/companies/{firmSlug}` API: `GET /me`, `GET /attendance`, `GET /leaves`, `GET /notifications`. Attendance and leave queries are bounded to 90 days and a maximum of 50 rows. **Even Partners get their own records only**. No payroll, approvals, edits, claims, billing, vault downloads or admin endpoints.
- Self-service token issuing and revocation for the **currently logged-in active tenant membership** via `/c/{firmSlug}/app/settings/integrations`. Requires normal authenticated browser session, completed MFA if applicable, re-entry of current password and CSRF. A token has a unique hashed value stored on the server, explicit scopes, 1–30 day expiry, max five active credentials, and an auditable issue/revoke trail. Plaintext secret shown once and never stored in source database or audit events.
- API bearer requests **do not use browser sessions or substitute for privileged MFA actions**; verified tenant host mapping, exact company slug, membership+workspace+company+user join, active restrictions, revoked/expired state, per-token allowed scopes and live company/workspace/student feature policies are checked afresh every request. Tokens from one company or Partner workspace cannot access another. No CORS wildcard, refresh token service or write operations.
- Per-tenant installable-style web manifest and mobile home-screen instructions. Minimal service worker may cache **only a static generic offline screen**. It never caches private authenticated pages, documents, responses, tokens, JSON or PDFs. Offline entry requires a network connection to resume working with company data.
- Bengali and English strings for the new Integration module; session-persisted preference with locale middleware foundation on authenticated ERP pages. Other enterprise ERP screens are *not* claimed fully translated.
- Documentation of endpoint schemas and deployment caveats.

## Integration quick start
1. Complete ERP login and optional user MFA, then visit `/c/{firmSlug}/app/settings/integrations`.
2. Re-enter account password, select minimum read-only scopes, choose 1–30 days, and create one personal token. Copy the one-time secret.
3. From your own trusted HTTPS client, set `Authorization: Bearer dw20_…` (never put it in a URL/query string).
4. Call `GET https://<verified-erp-host>/api/v1/companies/{firmSlug}/me`. Fetch own attendance, leave or notification data on the corresponding endpoints. Check `401`/ `403` and stop or revoke when access changes.
5. Rotate or revoke from the browser Integration page. A removed membership, suspended Partner, disabled module or offboarded company immediately makes old tokens inoperative.

## Production/security limitations
- No third-party app provider connected, no exported partner secrets, no external OAuth client consent server and no outgoing webhooks in this phase. These integrations require provider-side testing, scopes, billing/licensing and contractual privacy review.
- PWA is a browser installation shortcut, not a native Android/iOS app, and has no offline private functionality. Reliable installability depends on HTTPS, a correctly sized PNG icon, browser checks and production hosting response headers.
- Mobile features that access precise device GPS are deliberately not exposed by read-only API; location-based check-in stays in the existing protected browser workflow.
- Bengali translations cover Phase 20 Integration UI and generic offline shell only, not a claim that every ERP screen is localized.
- Production deployment must verify HTTPS service worker scope per tenant and custom domain, cPanel routing and no cache of confidential data.
- The API is rate-limited, but further production controls such as credential rotation policies, IP allowlists, anomaly alerts and external security testing belong to Phase 22/23.
- **No live deployment, customer token issuance or production API activation occurred.**

## Verification gates
- [ ] Feature tests: hashed tokens, scoped data, second company exclusion, stopped membership, module controls, revoke/expiry, MFA/session boundaries
- [ ] PWA static offline-only cache, locale and private manifest tests
- [ ] Full MySQL 8 / PHPUnit / browser QA / Vite and cPanel release packaging
- [ ] Reviewed development-branch squash merge and exact merged-SHA CI
- [ ] Phase 22/23 security/UAT + deployment signoff remain pending
