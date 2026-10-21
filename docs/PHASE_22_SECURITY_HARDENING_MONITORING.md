# Phase 22 — Advanced Security & Compliance Controls

**Development baseline:** Verified Phase 21 merge `5bfd22187d51e1954c8af0c5c9ccb5b2808e167a`. Implemented on an isolated feature branch; no `main`, live ERP, cPanel, or production customer database changes.

## Verified design scope (subject to CI)

- **Credential lifecycle hardening:** Successful tenant password recovery revokes *all* of the account's outstanding integration bearer tokens across all companies. Disabling optional MFA with current password and a fresh unused authenticator code / unused recovery code also revokes every outstanding integration token. Tenant memberships, billing records, original files and customer data remain untouched.
- **Anti-replay maintained:** Existing MFA validation rejects an already consumed TOTP step. Replayed code may not disable MFA, even if an authenticated browser session exists. Existing platform operators are prohibited from disabling compulsory MFA.
- **Minimal monitoring ledger:** An additive `erp_security_events` table records allowlisted login accepted/denied, global account sign-ins, password resets, MFA downgrades and API key creation/revocation. It stores only company ID, verified user ID (never an asserted user for a failed login), event category, severity, **day-rotated HMAC of request IP** and timestamp. No e-mail, account login names, passwords, recovery code, bearer token, raw IP, user-agent, document contents or payment details are recorded.
- **Self-only activity:** Settings > Security shows latest 20 events for the signed-in person's own account under the active company, plus global sign-ins not tied to any tenant. Other members' history or activity from unrelated companies is not rendered. The earlier workspace audit trails remain unchanged.
- **Server-only incident counts:** `php artisan erp:security:report --company=123 --days=7 --json` provides a 1–30 day *read-only aggregated* event count breakdown, without identities or source details. There is no public incident reporting API or external SIEM integration.
- **HTTP hardening:** Same-origin form back-navigation must continue to work. ERP, MFA, platform, account and tenant API responses now receive `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy: same-origin`, `Permissions-Policy` (same-origin geolocation retained for attendance), strict anti-framing CSP directive if none exists and private `no-store` caching. The middleware does not rewrite the existing full CSP, deploy HSTS to unknown custom domains or relax partner authorization.
- **Monitoring is metadata-only:** Security events are not a tamper-proof immutable SIEM. Rate-limit defenses already present on login/token endpoints remain in force. No automatic account suspension, deletion or unreviewed notifications were added.

## Compliance / operational boundaries

- These controls materially improve account safety but do **not** certify ISO 27001, SOC 2, Bangladesh Data Protection compliance, 200GB-safe offboarding, penetration-test success or full DR certification.
- App-level database administrators could still modify audit records. Tamper-evident external logging, WAF, incident detection/response, log retention approval, penetration testing, disaster recovery, secrets/key-rotation drills and professional regulatory review are separate requirements.
- Daily IP fingerprints are keyed with `APP_KEY`. Do not expose application key or use audit fingerprints for identity claims. Raw IP addresses and attempted account identifiers are intentionally excluded.
- Production infrastructure still needs HTTPS, correctly trusted proxy headers, secured session cookies, cookie-domain policy for custom tenants, rate limiting at an edge proxy, backup/restore drills and monitored rotation of compromised passwords/API credentials.
- Existing MFA testing-only bypass is production-inaccessible as presently configured; its removal and broad regression compatibility must be reviewed in a later production audit.
- Existing Laravel `sessions` table is assumed by password recovery/logout flows; actual session driver and centralized invalidation should be verified on staging before release.
- Performance and capacity of security audit inserts under hostile request floods should be load tested with realistic traffic; no benchmarking was run on production customer data.

## Development CI and release gates

- [ ] New MFA replay, global API revoke, privacy, headers and security reporting regression suite
- [ ] Existing PHPUnit, MySQL 8, authenticated browser, Vite and cPanel package checks
- [ ] Squash merge into `security/production-readiness-gates` only after PR-head green
- [ ] Exact merged SHA CI all green (not only PR head)
- [ ] Production release approval, staging penetration testing, independent risk review and rollback plan **pending**

Usage: `php artisan erp:security:report --company=123 --days=7 --json`.

**Important:** New security event rows are additional; historic past events cannot be retroactively reconstructed or certified.
