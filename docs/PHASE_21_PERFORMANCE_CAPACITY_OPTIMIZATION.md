# Phase 21 — Performance, Scalability & Large-Data Optimization

**Baseline:** Phase 20 merged development SHA \`30767e4cc954ca41883908c4d33535f6b223d8e3\`.
**Release status:** Development-only optimization, pending CI and separate production approval. No access to real customer data, production database or cPanel in this feature branch.

## Delivered

1. **High-volume Practice Reports:** Approved leave counts and conveyance reimbursement totals are calculated with scoped SQL \`GROUP BY membership_id\`, instead of loading every source row into PHP memory. Preserves historical status filters, approved amount fallback, partner workspace isolation, locked payroll semantics and existing CSV formula-injection defense.
2. **Bounded Attendance Reports:** Stable 100-student server-side pagination on monthly authorized attendance aggregates, with preserved month filter and employee privacy. The report never infers absence or payroll penalties.
3. **Additive composite indexes:** Hot tenant-scoped and month/status paths for attendance, approved leave/claims, engagement timesheets, unread notifications, Vault expiry, opt-in renewal scans and company exit work queues. Migration does not update or delete old records. Production DBA should separately assess index-build duration/locks on large MySQL tables and schedule within a maintenance window.
4. **Bounded Vault temp cleanup:** \`php artisan erp:vault:expire-staging --max=200\` (default), up to 1,000 sessions per invocation, offers \`--dry-run\`. Each expired session is still row-locked and quota-reconciled transactionally, only **temporary** chunk files are removed, and committed/held customer objects are never deleted. Work remains for subsequent scheduler runs.
5. **Read-only capacity telemetry:** \`php artisan erp:scale:diagnose --company=123 --json\` gives PII-free counts of pending jobs, failed jobs, private Vault used/reserved/quota bytes, unfinished uploads, company exit workload, opted-in subscriptions and export safety gates. Does not expose document content, payment references, passwords or client identities; no database mutations.
6. **Dataset regression:** 720 conveyance claims, 145 leave requests and 123 student attendance aggregate pages, negative cross-tenant checks, index assertions and non-mutating diagnostics. These tests check **correctness and query-boundedness**, not external benchmark throughput.

## Large customer scaling truth and deployment gates

- Phase 17 Vault is still a **private local-disk pilot** (100MiB/workspace; maximum 48MiB per file unless separately changed/validated). Phase 18 export is **16MiB maximum plaintext**. **This is not tested 200GB customer backup, nor a vendor-certified scale limit.**
- Physical disk headroom, DB consistent snapshots, offsite storage and version retention, secure cloud multipart/resume, large-volume restore tooling and queue worker availability have **not been proved** in this feature. They remain mandatory before a business sells large data tiers or safely closes a large customer.
- Laravel queue database driver is configurable; a cPanel cron scheduler alone does **not** constitute a supervised background queue worker. Do not advertise async jobs until dedicated workers, retry/dead-letter policy, monitoring, backups and recovery runbooks have passed staging tests.
- No unsafe direct manipulation of \`jobs\`, \`failed_jobs\`, backup archives, tenant-private files or payment records.
- The performance diagnostic is an internal CLI tool; it is not a hosted public admin endpoint and does not substitute for metrics/alerts. Global queue counters are platform-wide even when \`--company\` filters Vault and subscription counts.
- On large production tables, test explain plans and online index strategy in staging before applying migrations. SQL indexes are not proof of acceptable p95 response latency.

## Staging acceptance checklist

- [ ] Full existing and new PHPUnit + MySQL 8 suite, browser QA and Vite build
- [ ] cPanel review ZIP assembled, not deployed
- [ ] Development PR squash merged and **exact post-merge commit SHA** fully CI green
- [ ] Staging warm/cold query plans checked on representative volumes; measured p50/p95 before/after
- [ ] External 200GB upload/export/restore drill and object-storage encryption/capacity check **not completed**
- [ ] Explicit production release approval, rollback rehearsal and independent customer-data backup review **not completed**

## Internal operations examples

\`\`\`bash
php artisan erp:scale:diagnose --company=123 --json
php artisan erp:vault:expire-staging --dry-run --max=200
php artisan erp:vault:expire-staging --max=200
\`\`\`

Interpret \`verified_200_gb_end_to_end_export=false\` and \`safe_for_customer_deletion=false\` literally until independently demonstrated otherwise.
