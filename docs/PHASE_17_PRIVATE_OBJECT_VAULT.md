# Phase 17 — Private Object Vault, Quota Reservation and File Lifecycle

**Baseline**: Phase 16 verified merge `69b0dc22bf1bdeb980199ecae1431970a6816fa5`.
**Scope**: additive tenant-safe private vault pilot. No production deployment or rewrite of existing customer files. The next phases (18, 21–24) remain required for 200GB verified backup/export, scaling, DR and production acceptance.

## Delivered

1. Dedicated **private** Flysystem disk (default `erp_vault`, non-public local root). The vault refuses arbitrary/public disks and refuses unsupported S3 configuration rather than falling back or sharing an insecure filesystem.
2. Workspace-isolated quota ledger: 100 MiB default workspace maximum, pending uploads reserve storage atomically under the workspace row lock; bytes move exactly once from reserved to used after durable hash/MIME/size verification. No Partner can self-increase the quota from the browser. Defaults are an application pilot limit, **not a paid subscription promise**.
3. Partner-only browser chunk uploader, 4 MiB chunks, 48 MiB maximum verified file. The private staging directory contains only incomplete chunks. A chunk is accepted once per upload/part index; a retry with identical content is idempotent, a changed retry is rejected.
4. Finalization is sequential and stream-based, verifies per-chunk hashes, full SHA-256 and content MIME. Only PDF, PNG and JPEG files are permitted. The complete object is written to an opaque server-generated company/workspace path and downloads pass current membership/visibility checks. No public links or direct client-selected storage keys.
5. Per-object visibility: Partner-only, recipient+Partner, or recipient+assigned Manager+Partner; cross-tenant IDs and raw object paths cannot authorize downloads. Access and mutations are audited without revealing document contents.
6. Partner archive and hold/release with written reasons. Archive **never deletes physical data** or reduces billed quota; retention/legal hold blocks future irreversible purge design. No general-purpose customer deletion implemented.
7. Hourly expired-staging command `php artisan erp:vault:expire-staging` with `--dry-run`. Reclaims unused **reservations and temporary chunks**, never committed objects. Idempotent and bounded by 50 sessions per batch.
8. Feature gate inherits existing Document Center permission switches, MFA and tenant middleware. Responsive Private Object Vault UI shows used/reserved/maximum totals and explicit pending-upload cancellation.

## What this phase does NOT claim

- **No activated production S3 or 200GB direct multipart upload.** The optional S3 path requires separately installed Flysystem S3 adapter, HTTPS credentials, private encrypted bucket/region policy, server-side malware scanning, lifecycle policies, network benchmark and independently verified multipart signing. Default cPanel-safe pilot stores only the 48MiB maximum file size on private disk.
- **Legacy documents, receipt files, staff documents, existing brand assets, database records and backup copies are not counted in the *new vault* usage**. They remain private and unchanged. This quota is the **managed vault quota only**, not total organizational storage billing. Unified storage metering and migration of legacy objects require reconciliation and capacity testing before billing quotas.
- Physical object encryption at rest is provided by the storage infrastructure/provider, not by this PHP code. Object immutability/WORM, malware scanning, disaster recovery and cloud credential setup are additional deployment gates.
- No browser direct-to-S3 signed multipart implementation or gigabyte-size object upload. A 200GB tenant needs staged object storage/worker scaling and Phase 21 benchmarks.
- No automatic permanent purge of archived/held data and no automatic retention expiry without verified export, contractual retention and authorized approval (Phase 18).
- The uploader verifies MIME and hash, **not malware safety**. Deploy only after the required malware scanning and security readiness work.

## Verification gates

- [ ] Latest pull request PHPUnit tests, MySQL 8 suite, browser workflows and Vite build
- [ ] Release ZIP package verification; this is NOT a deployment
- [ ] PR review, development branch squash-merge, exact-SHA post-merge CI
- [ ] Staging upload/restore to private bucket and actual cron verification before production
- [ ] Explicit production owner signoff, restore drill, encryption/antimalware readiness, cloud provider and customer privacy review

## Browser workflow

Use `/c/{firmSlug}/app/vault` in an authorized Partner account. The uploader starts a reserved session, sends numbered chunks, commits verified bytes and displays the private object. If a browser is interrupted, cancel the pending session (or allow it to expire) to reclaim reserved bytes. The UI does not claim resume-from-browser-restart support.

## Production safety

Main branch, existing ERP customers, cPanel, live database and old files are unchanged by this development-only feature. The Phase 17 migration is additive. Post-merge GitHub CI does **not** constitute safe production acceptance.
