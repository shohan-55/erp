# Phase 18 — Company Data Export, Verified Integrity, Retention and Safe Exit

**Development baseline:** Phase 17 verified `f0e05a73284e318bae57b57e03e65dfda66c28f6`. Phase 18 uses an isolated feature branch. No changes to existing customers, `main`, cPanel, production database or live WHM resources.

## Scope and concrete boundaries

Phase 18 implements a **bounded private encrypted company-exit package pilot**, an owner-driven offboarding workflow, a read-only data inventory and **non-destructive** legal/contract retention preflight. It intentionally DOES NOT implement any physical purge. A package called `prepared` or `integrity_verified` must **never** be described as an independently restored complete backup.

### Company-wide Partner approval

1. An active Partner provides the exact company slug, a meaningful cancellation/export reason and a retention date at least the configurable minimum (currently 30 days).
2. Active, unsuspended Partner memberships throughout the company are snapshotted as voters. Every one of these Partner memberships must approve before any sensitive company-wide data can be prepared, verified or downloaded. A rejection halts the case. Voting is independently audited. A Partner sees only metadata/consent until unanimity.
3. The global Platform Super Admin has no tenant-content download/export route. Student and Manager roles are refused on all exit endpoints. Export authorization never derives from a guessed company ID or URL slug alone.
4. The owner can view status; a dedicated Laravel scheduler handles approved cases. It never automatically runs for merely cancelled or suspended accounts.

### Private export format and integrity

- The prepared package is a series of **encrypted** records on a separate non-public `erp_exit` disk. It uses Laravel app-key authenticated encryption. Encryption-at-rest is provided by the application; deliverable artifact downloads are authenticated and per-segment hash checked. Key rotation/backup must retain the original app encryption key until packages are delivered.
- Export includes operational database tables carrying `company_id` and an `id` (schema-selected, not SQL supplied by a tenant), `payroll_lines` scoped through an owned `payroll_run`, and sanitized global identity rows for accounts explicitly linked by membership. Server credentials, password/MFA/token columns, platform-control records and authentication tables are excluded and called out in the manifest.
- Approved, explicitly recognized file sources are private Vault objects, practice documents, staff documents, conveyance receipt files and brand assets. All source file paths are scope-validated; unknown paths or missing files **stop** an export. The private provider is read-only from the exporter.
- Rows are serialized to ordered JSONL segments, file contents to ordered bounded binary segments, all encrypted at rest. Each artifact carries its own SHA-256, size, source table/source ID and part order.
- An encrypted manifest includes preflight row/file counts, individual artifact digests, explicit exclusions and the caveat that a staging restore is independently required. The entire package is capped by configurable **16 MiB** plaintext in this pilot. Oversize tenants are marked `blocked` without claiming a successful export or deleting originals.
- An inventory comparison checks for insert/delete drift while the job runs. This is **not** a transactionally consistent snapshot under concurrent writes and is not sufficient evidence of correct point-in-time restoration.

### Verification, retention, customer delivery

- `prepared`: encrypted artifacts and manifest staged. Customer and authorized Partners may download the private individual parts.
- `integrity_verified`: a Partner explicitly re-checks every retained encrypted artifact against its SHA-256 and manifest hash. This proves corruption detection on stored segments **only**, not a production-quality restore.
- `acknowledged`: original requesting Partner confirms actual package receipt using the exact manifest checksum. Acknowledgement is a recorded statement, not proof of download completeness.
- `restore_attested`: a DIFFERENT Partner user account records a separately performed staging restore and reconciliation evidence. The software records this attestation; it does not automatically run or independently certify the restoration.
- A Partner can place/release reasoned retention holds without affecting originals. Vault legal holds, open upload sessions and the chosen retention date are displayed in a **read-only** purge preflight; `physical_purge_enabled=false` is immutable in this phase.
- Pre-existing platform-company soft-remove/suspend/archive workflow is unchanged. Archived/recoverable customer data is never physically purged as an exit side effect.

### Required production gaps

- This 16-MiB **pilot is NOT the requested 200-GB customer backup**. Large-scale exports need durable offsite encrypted multipart object storage, resumable streaming workers with fair queue scheduling, a consistent database snapshot/reconciliation strategy, downloadable client-side restore tooling, and a successful external restore drill.
- Export coverage intentionally excludes global auth/password/MFA credentials, infrastructure backups and object-storage previous versions. Some non-`company_id` linked records other than payroll lines may also need explicit exporters; a production departure package cannot be certified until a schema ownership matrix proves completeness.
- Current customer download UI displays a capped artifact list. Full automated package collection, acknowledgements and independently measured end-to-end delivery still need validation. No decrypted ZIP is staged on a webroot.
- Protection of provider backups, contractual retention windows, legal advice, compliance basis, deletion from versioned cloud object stores, caches and downstream systems requires independent operational work.
- **No database cascade deletion, tenant hard-delete, background purge, drop table or storage-object delete for a completed customer** exists in this phase. Safe purge requires separately reviewed destructive workflow and explicit production signoff after restore and hold checks.

## QA and merge gates

- [ ] GitHub PR-head PHPUnit/Feature, MySQL 8 and browser CI
- [ ] Vite build and cPanel release artifact verification (packaging, not deployment)
- [ ] Development branch squash merge only after CI passes; exact post-merge SHA green
- [ ] Live restore, offsite infrastructure, encryption key lifecycle, customer legal-retention signoff remain mandatory before production release

## Admin / customer usage

After deployment approval and working scheduler, an authenticated Partner visits `/c/{firmSlug}/app/company-exit`. The Partner requests export, all active Partners consent, the scheduled `php artisan erp:exit:prepare` job assembles the package, and Partners verify/download the segmented manifest/files. The requester acknowledges actual receipt, and another Partner may attest an external restore. `php artisan erp:exit:prepare --dry-run` lists queued cases without mutation. **Neither these steps nor a GitHub green CI deletes a company's data.**
