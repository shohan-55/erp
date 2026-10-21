# DhakaWala ERP — Master SaaS Architecture Blueprint (Phase 01)

Status: **Proposed design / review draft**, not deployed or implemented.
Baseline: `security/production-readiness-gates` at `925d4e96a0162c1f7694119c82dda0e500f32970` (2026-10-20 review).
Scope: extensible Laravel ERP for CA firms, corporate entities, NGOs and service businesses. Existing code and tenant records take precedence over convenience of migration.

## Product promise and boundaries

One common ERP engine; configurable **organization types**, roles, workspaces, policies, modules, documents and workflows. The default UI should stay simple: show each user only their permitted actions. Settings and customization must be usable without editing code. Core invariants must NOT be optional: authentication, tenant isolation, audit provenance, prevention of unjustified backdating, financial integrity, safe deletion, and separation of requester and approver.

- **Platform owner** manages SaaS tenants, subscriptions, storage metering and metadata; gains **no implicit access to private HR/audit documents**.
- **Company/Firm** owns its operational data and defines permissible organization-wide defaults.
- **Workspace** may be a partner practice, branch, department or program. CA-firm partners retain private workspaces. RA&C-like firm governance can require unanimous partner approval for firm-wide changes, configurable per company and not bypassable through manager delegation.
- **User identity** may join many companies and workspaces, each through an explicit membership and selected active context. No password reset or silent credential sharing when another tenant invites an existing account.
- **Delegates** may act only within explicitly granted company/workspace/record scopes. A delegated manager/moderator can perform authorized proxy submissions or approvals; delegated privileges never include platform ownership and cannot exceed the grantor's rights.

## Logical architecture and component responsibilities

```text
Browser/mobile PWA
    |
HTTPS / session & MFA / tenant-host validation
    |
Laravel application: tenancy context, RBAC, domain services,
  approval policy, audit, billing/plan enforcement
    |            |              |                |
MySQL        Cache           Queue workers     Object storage
(operational (prefer Redis  (exports, reports, (S3-compatible;
 records,     as scale needs) backup, notices)   receipts, files,
indexes)                                          large archives)
```

Initial shared/cPanel infrastructure may be adequate for small pilots, but **a configured queue driver or S3 disk alone does not prove working workers or external storage**. Scale workers, DB and app independently once load tests justify it. Avoid exporting, scanning or ZIP-compressing hundreds of GB in a web request or on the application webroot.

### Identity and organization model

- Add organization profiles/templates: `ca_firm`, `corporate`, `ngo`, `consultancy`, `other`; templates define defaults, not hardcoded product forks.
- Hierarchy: company -> organizational unit(s) -> team -> membership; map existing CA-firm partner `workspaces` without collapsing old privacy boundaries.
- Support employee/manager belonging to several teams under an explicitly selected company/workspace context. Plan backwards-compatible migration from existing membership indexes/constraints.
- Define separation between global identity, tenant-specific profile, partner workspace membership, reporting manager and billing seat. Each association must have unique/scoped constraints.
- Explicitly map legacy routes and custom domains to the same resolved company context; neither a URL slug nor supplied numeric ID alone can grant access.

### Customization engine

Implement a versioned typed **Settings Registry**, not arbitrary executable customer rules:

1. **Feature registry**: module > feature > operation, with dependencies and plan entitlements. Hiding menus is never a substitute for server-side enforcement.
2. **Role templates + Custom Role Builder**: reusable permission sets; granular `view/create/edit/submit/review/final/export/manage` by resource; data-scope and field sensitivity, including salary, ID, receipt and audit evidence.
3. **Permission evaluation**: first platform safety invariant, then plan entitlement, then company/workspace policy, then role and explicit user grants/restrictions; deny on ambiguity. Revocation must take effect on the next request; don't cache privileges without safe invalidation.
4. **Policy precedence**: immutable platform security constraints -> company-approved policy ceilings -> authorized workspace overrides -> explicitly permitted team/employee exception. More specific values cannot exceed a higher-level restriction.
5. **Policy versioning**: editor, reason, prior value, approval votes, effective date and affected scope. Prospective changes do not silently recalculate closed payroll.
6. **No-code customization**: typed form fields, validation, permissioned layout, branded documents and saved reporting configurations. Never accept customer PHP/SQL/JavaScript as a policy formula.

### Approval workflow engine

Store immutable workflow definition versions, a per-request **workflow snapshot**, stages, approver eligibility and decision events:

- One-step, two-step and optional multi-step approvals by company/workspace/module; conditions on leave duration, claim value, role and request category.
- Explicit reviewer/final-approver authorization; partner can authorize single manager approval *where company policy permits*. Manager / moderator labels alone convey no automatic authority.
- A person may not approve their own request or own proxy submission. When two-person approval is configured, two **distinct principals** must act. Do not downgrade an already-started request when policy is changed.
- Approval deadlines, delegate substitutes, absence coverage, escalation and notifications; reject/withdraw/resubmit decisions are independently audited.
- For payroll, maintain immutable run snapshots, cents-based financial arithmetic, period locks and separate approved adjustment records; no automatic overwrite of paid periods.
- Every request records the actual operator, subject, creation time, reason and evidence. Proxy attendance uses real server clock; historical edits are reviewable corrections, not rewritten evidence.

### Company offboarding, ownership and data lifecycle

Define states `active -> suspended/read_only -> export_requested -> exporting -> export_verified -> awaiting_retention -> purge_eligible -> purging -> purged` with recoverable error/hold states. **Lifecycle transitions must be idempotent and auditable**.

- Distinguish ordinary customer cancellation, nonpayment, demo tenant cleanup, company archive, temporary suspension, and verified permanent purge.
- On cancellation, impose a contractually defined export/grace window; keep scoped authorized access for export. Default 30 days is a proposed business policy **pending legal/contract review**; do not hardcode a mandatory universal duration.
- Produce a **tenant data inventory**: row counts, MySQL schema/version, document/object inventory, sizes, file hashes, time ranges and archive format. Include attendance, HR, payroll, leave, claims, audit engagements, user-facing settings and approved documents; exclude other tenants' and global account secrets.
- Use resumable background chunked exports with consistent snapshot or verified reconciliation, encrypted outputs, manifests, integrity checks and staging restore testing. Prefer direct export to customer-owned object storage or staged segmented download for 200GB.
- A successful upload alone is **not verified customer delivery**. Record customer/admin acknowledgement and an evidence-based export verification/restore result.
- Only after retention period, absence of legal/contractual hold and independent confirmation can an authorized asynchronous purge remove tenant-scoped rows and objects. Preserve the minimum platform-level invoices, payment references and audit evidence if legally/contractually required, isolated from active tenant records.
- Object versions, multipart uploads, temporary artifacts, CDN cache and aged encrypted backups need specific lifecycle rules. State clearly when storage is actually reclaimed; physical deletion from provider backups can lag.
- Never implement bulk `DELETE companies` using DB cascade as an offboarding shortcut. Require preflight dependency inventory, dry-run counts, exact slug + MFA/second confirmation, authorization and rollback/restore plan **before** any destructive production operation.

### Large tenant performance (e.g., 200GB)

- Put large PDFs, Word/Excel, scans, receipts and archives in **private object storage**, not webroot or MySQL BLOB columns. DB holds scoped metadata and storage keys.
- Enforce tenant-aware composite indexes, pagination/cursors, bounded joins, date filters and query plans. Avoid unbounded `get()` for operational dashboards.
- Precompute or safely cache home-dashboard aggregate summaries. Invalidate scoped cache when the underlying source changes.
- Use direct multipart signed uploads/downloads, virus/malware scanning, size/type limits, encryption and expiring authorized URLs; avoid memory-buffering large attachments.
- Isolate heavy exports/payroll/report generation to capacity-limited job queues with rate limits, fairness per tenant, backpressure, retry/deduplication and cancellation.
- Monitor p50/p95 response latency, slow DB queries, worker backlog, object growth, failures, costs, storage quota and tenant-level resource consumption.
- **Proposed target, not a guarantee:** p95 dashboard response under 2 seconds at agreed realistic concurrency/device/network; acceptance requires repeatable benchmark on representative 10/50/100/200GB tenant-sized fixture, cross-tenant contention test and failover test.
- Add DB partitioning, read replicas or separate tenant database/storage only when measured thresholds warrant them; the service contract should permit these behind a stable tenant access layer.

### Security, legal and operations

Tenant data is highly sensitive (salary, attendance and client documents). Require tenant-scoped authorization on every endpoint, MFA for privileged roles, CSRF protection, secure file URLs, tamper-evident event provenance, secret rotation, encryption in transit/at rest, offsite encrypted backups, tested restores and documented incident response. Define retention by data class and jurisdiction/contract with professional review, not arbitrary customer code.

Development PR and CI success do **not** establish production approval. No change to `main`, cPanel, existing DB, customers or active credentials from planning work. Use isolated feature branches, review, SQLite + MySQL8 tests, browser role/UAT, staging restore and explicit deployment approval.

## Twenty-four delivery phases (incremental; reuse existing code)

| Phase | Deliverable | Principal gate |
|---:|---|---|
| 01 | Baseline audit, architecture ADR, gap/risk register | Reviewed blueprint and existing-code inventory |
| 02 | Tenant lifecycle/demo archive, dry-run remove & restore foundations | Live data preserved; demo vs real classified |
| 03 | Organization profiles and scoped organizational units | Old workspace IDs and privacy preserved |
| 04 | Multi-company identity, explicit membership and context switching | No cross-tenant account linkage or leaks |
| 05 | Role templates, permission model, field and record scope | Deny-by-default and revocation tests |
| 06 | Scoped manager/moderator delegation and temporary access | No privilege escalation or cross-workspace access |
| 07 | Feature dependency registry and no-code settings UI | Routes and data enforce disabled modules |
| 08 | Policy inheritance, effective dates, immutable versions | Company/partner votes and payroll locks retained |
| 09 | Configurable single/two/multi-step approval engine | No self-approval, stage version snapshot |
| 10 | Attendance, shifts, proxy, correction and evidence | Original record always recoverable |
| 11 | Leave types, balance, holiday logic and workflow | Concurrency/overlap and policy tests |
| 12 | Claims, receipts, reimbursement and expenses | Financial integrity and private evidence |
| 13 | Salary structures, payroll adjustment and settlement | Paid period immutable |
| 14 | Staff lifecycle, letters, transfers and exit | Scoped role/identity history |
| 15 | Engagements, client projects and timesheets | Audit client privacy |
| 16 | Forms, reports, documents and notification rules | Validated schemas and scoped export |
| 17 | Object storage, quotas, multipart files and lifecycle | Encryption and true storage accounting |
| 18 | Customer export, verified backup, retention and safe purge | Restore test, delivery proof and hold controls |
| 19 | SaaS subscription, trial, quota add-ons, domains | Separate billing from private tenant data |
| 20 | Localization, supported integrations, API/PWA | Rate limit and tenant tokens |
| 21 | Indexing, cache, queues, 200GB benchmarks | Cross-tenant fairness and measured SLO |
| 22 | Security, MFA, audit and privacy hardening | Independent negative-access/security review |
| 23 | Staging acceptance, failure/restore and automated QA | Full integration and rollback evidence |
| 24 | Release train, runbooks, documentation and go-live | Explicit production signoff, exact SHA |

Earlier phases must include **basic security, bounded queries, compatible storage APIs and backup discipline** from the outset; phases 17/18/21/22 intensify and validate these rather than introducing safety late.

## Decision log and release ownership

1. Stable PHP/Laravel/MySQL codebase; avoid rewrite; optimize by demonstrated need.
2. Do not merge stale competing PRs blind. PR #25 and PR #56 each need refreshed comparison/review against current development and each other.
3. Split **tenant operations** from **platform SaaS billing/control**; no platform super-admin implicit tenant contents access.
4. Non-destructive settings/migrations by default; every migration has compatibility and rollback risk analysis.
5. No irreversible deletion without verified export, retention clearance, dry-run dependency graph and authorized signoff.
6. Phase completion requires merged reviewed development SHA plus CI/results; **production remains separately gated**.

Next document: [Phase 01 Code Audit and Acceptance](PHASE_01_CODE_AUDIT_AND_ACCEPTANCE_2026.md).
