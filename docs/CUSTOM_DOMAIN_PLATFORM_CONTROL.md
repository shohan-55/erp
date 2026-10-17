# DhakaWala ERP — Client Custom Domains and Platform Administration

**Status:** Isolated development branch only. No DNS, cPanel, database, existing company data, certificates, production records or live tenant hostnames were changed.

## Supported now in code
- Platform Admin remains central operator for company creation, lifecycle, memberships, Partner workspaces, auditing and per-company branded-domain onboarding.
- Platform → Manage Company & Accounts → Custom Domain Control: register/replace a client-owned subdomain (e.g. erp.example.com), DNS TXT+CNAME check, record independently verified HTTPS/vhost readiness, activate or disable. One hostname per company in this initial version.
- Client hostname root (/) and /login serve that company's sign-in once enabled. Existing ERP links under /c/{firmSlug}/app/* work when the host matches the company's active mapping, and are rejected for other firm slugs.
- Central https://erp.dhakawala.com/rac remains available to active firms. Domain replacement never deletes users or financial/attendance records.
- Platform operator never gains default access to confidential Partner attendance, payroll, leave or claim data.

## Client and hosting-provider sequence
1. **First verify cPanel/WHM supports custom hostnames safely** for this dedicated ERP account. Domain owner conflicts and multi-customer HTTPS/SNI/AutoSSL may require a hosting-provider change or a dedicated reverse proxy.
2. Platform Admin registers an approved client-owned **subdomain**. Root/apex domain support is not included.
3. Client publishes unproxied CNAME for that subdomain to erp.dhakawala.com plus TXT at _dhakawala-erp.<hostname> with the displayed dhakawala-erp-verification token.
4. Platform Admin selects Check DNS; both records must match. DNS proof does not activate the host.
5. Hosting operator independently provisions and checks valid SSL for the exact host, correct ERP webroot, private storage, secure host-only cookies and access isolation, then records HTTPS/vhost approval.
6. Platform Admin activates only after rechecking DNS. Perform staging authenticated UAT from phones and desktops for login, session isolation, redirects, MFA, invitations, reset emails and logout.
7. Disable reverts custom host routing without changing the central login. Replacement rotates challenge and resets approval.

## Security and unsupported features
- Host input is validated and looked up by exact match; unknown hosts and wrong-company URL paths are rejected. Platform console is available only on the canonical host.
- TLS approval is a human attestation, not automatic certificate provisioning or automated TLS proof. Successful code CI is not evidence of a correct cPanel host or working certificate.
- Do not iframe embedded ERP sessions into arbitrary third-party websites. Use a client-owned HTTPS hostname or a link from their site; review consent/privacy.
- No subscription billing, plan pricing, payment capture, full client-controlled apex domains, automatic AutoSSL/certificate issuance, platform password override or tenant payroll impersonation in this increment.
- Live rollout remains blocked by external staging UAT, security, backup restore, Partner policy/privacy and production signoff tracked in GitHub issue #18. Never merge/deploy automatically.
