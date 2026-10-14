# DhakaWala ERP — Phase 1 Foundation & Security

Status: **IMPLEMENTATION IN REVIEW; NOT A PRODUCTION RELEASE APPROVAL**.
Baseline branch: security/production-readiness-gates.
Pilot tenant: fictional qa-pilot. Do not load actual RA&C payroll or private personnel data before final controls and policies are signed off.

## 1. Phase 1 deliverables and acceptance criteria

| Requirement | Current state / Phase 1 acceptance |
| --- | --- |
| Tenant identifier and routing | Every real request resolves an active company from a validated slug and an active workspace from the authenticated membership. The platform owner sees metadata only. |
| Authorization | A server-side role and scoped membership check precedes operations; hiding an item in the UI never substitutes for authorization. |
| Partner separation | Partners, managers and students cannot read or modify other Partner workspaces even when company IDs match. |
| Cross-company protection | A user logged into one company must not gain another company's data by editing the URL, resource IDs or API payloads. |
| Financial privacy | Managers cannot implicitly read payroll; students can see only own **locked** statements, not draft payroll. |
| MFA | Platform operator: compulsory. Tenant accounts including Partners: recommended/optional by user; once enabled, enforced on sign-in. Company-mandated MFA for privileged payroll roles is a later policy decision. |
| Encryption release gate | Reject malformed APP_KEY, wrongly sized decoded keys or invalid cipher/key combinations; never echo the key in diagnostics. Never rotate live APP_KEY casually. |
| Audit design | Append actor, company/workspace, action, record type/ID, timestamp and nonsecret details for sensitive mutations. Never silently overwrite source attendance/claims after approvals. |
| Backup/restore | Document an encrypted off-site database **and** private-file backup, staged restore proof, rollback and app-key custody. Actual drill and off-site automated schedule are **pending operator action**. |
| Data handling | Private receipts, payroll proofs and .env stay outside the public document root and are served through tenant-authorized controller routes. |
| Verification | Independent PHPUnit and MySQL CI, browser and release packaging checks plus staged manual UAT before any reviewed code goes live. |

### Exit criteria

- [x] Review current implementation and identify gaps
- [x] Add server-side encryption-key validity regression tests
- [x] Add cross-company / cross-workspace / manager scope HTTP regression tests
- [x] Document actual vs planned role matrix and operational backup policy
- [ ] Latest CI must pass on MySQL and SQLite, actual browser QA and cPanel packaging
- [ ] Independent security review, staging UAT, backup/restore drill and user authorization
- [ ] Product decisions on roles, attendance GPS retention, approver delegation and statutory payroll rules

**This design does not constitute a claim of 100% safety or final production readiness.**

## 2. Tenant boundaries & identifiers

The application is a single central Laravel service with company-level tenancy and optional independent Partner workspaces.

- users: global authentication identity. A person may belong to distinct companies via explicit memberships.
- companies: slug, timezone, baseline office schedule, policy governance.
- workspaces: belongs to one company; Partner management boundary.
- memberships: exactly one company+workspace+role (currently one membership per user per company).
- Every attendance, leave, conveyance, approval, payroll, notification and audit row must be scoped to company and workspace, not only a user-provided ID.
- Tenant ID and actor role are read from the authenticated membership and middleware, never from an editable form field.
- Records obtained by ID must carry both company and workspace predicates. Manager access further checks the assigned manager relationship.
- Platform operators may configure company metadata but do not implicitly receive tenant memberships or access private tenant records.
- A plain /company-slug URL and verified custom domains are future phases; each must resolve to the **same** tenant context before authentication rather than bypassing it.

### Security invariants to keep testing

1. Same-company, other-workspace access denied.
2. Other-company URL and resource-ID tampering denied.
3. Manager assigned team only; no privilege over unassigned employees.
4. Student own personal records only.
5. Sensitive downloadable evidence requires scoped authorization, not a public storage URL.
6. Closed/locked payroll cannot be rewritten from attendance or claim endpoints.
7. Audit events identify the actual actor even for "on behalf of" actions; no fabricated student sign-in or GPS values.
8. Tenant access always fails closed when membership or workspace is inactive.

## 3. Roles & permissions — current behavior vs agreed future model

| Operation | Platform operator | Partner / Principal | Manager | Student |
| --- | --- | --- | --- | --- |
| Create company / workspace invitation | Yes (metadata and invitation) | No platform access | No | No |
| Access another Partner's private data | No | No | No | No |
| Browse own workspace records | No automatic membership | Yes | Assigned team where applicable | Self only |
| Attend / check-in | N/A | Currently self check-in limited to Student | Currently self check-in limited to Student | Yes |
| Correct existing checkout | N/A | Current code: Partner only, audit required | Future delegated permission | Future regularization request |
| Review leave or claim | No | Current code permits direct final decision | Assigned student: preliminary review | Submit own |
| Final payroll approval | No | Own workspace | No | No |
| Change payroll allowance / team invitation | No | Own workspace | No | No |
| MFA | Mandatory | Optional but recommended | Optional | Optional |
| Configure policy | Metadata only | Versioned company proposal or own workspace override | Future delegation with Partner signoff | No |

**Decisions still to confirm with the owner:** strict manager-then-principal leave approval, approval delegation, proxy attendance, partial claim approval, payroll deduction treatment, company-specific holiday exceptions, GPS retention and whether high-privilege tenant MFA becomes mandatory.

**Default governance recommendation** (not automatically deployed): Flexible Company Policy / Option C with conservative manager-recommendation-then-partner-approval rules. No new deduction percentages, thresholds or penalties are silently assumed.

## 4. Audit and evidence design for subsequent phases

Current table: tenant_audit_events with company_id, workspace_id, actor_membership_id, action, entity_type, entity_id, JSON details, created_at.

Future sensitive-change events must additionally retain original source values vs approved/adjusted values, a human reason, the requesting user, a timestamp, approval chain and which rule version was effective. Store the original source attendance or claim alongside a correction/approval record. Do not silently rewrite its evidentiary history. The current audit trail does not yet provide an independently cryptographically immutable ledger; that remains a separate audit requirement.

Never put plain passwords, APP_KEY, MFA seeds, recovery codes, reset/invite tokens, or unnecessary raw GPS coordinates in human-readable audit details.

## 5. Security and operational risks that are not solved by this phase

- Live off-site encrypted backups, tested restores, cPanel cron/scheduler, alerting, and intrusion detection are not proved by passing CI.
- Single-server/cPanel downtime and filesystem permissions are operational risks.
- Shared hosting may have security/availability restrictions for recurring jobs and sensitive uploads.
- Geolocation from browsers requires consent, precise retention limits, and antifraud caveats.
- Automatic salary penalties and half-day deductions require explicit signed policies and legal/contractual validation.
- Payroll bank settlement and evidence require a separate independent approval process.
- Company slug rac may have legacy special-case behavior; should be generalized before a multi-company commercial launch.
- Formal security penetration testing, restore testing and role-wise field UAT are still required.

## 6. Phase 1 QA checklist for qa-pilot

1. Platform operator authenticates with enforced MFA; cannot open private tenant claims or salary.
2. Test Partner signs in, opts into MFA in Settings / Security, completes challenge and can disable only with password + code (while company policy permits).
3. Create a second fictional company plus a second Partner workspace inside a fictional company **in staging**, not in RA&C's real account.
4. Verify cross-company URL changes, cross-workspace resource ID changes, receipt downloads and manager role restrictions all fail closed.
5. Verify original financial and attendance records have durable audit entries with actor and scope.
6. Verify a restore of encrypted DB and private files to separate staging under documented access control.
7. Confirm release artifacts/public_html contain no source code, .env or private user data.

Only progress to Phase 2 after automated test success and a review of open Phase 1 items. The later application rollout will use existing qa-pilot data where appropriate, not reseed or reset the user's cPanel database.
