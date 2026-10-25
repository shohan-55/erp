# Phase 23 — Production Readiness, Disaster Recovery and UAT

**Development baseline:** Phase 22 merge ee121f27fbb68ad7012c488b575afe5d8905cf88.
**Status:** Application-side gates and test-only rehearsal; real infrastructure DR, human signoffs and production deployment are **NOT YET VERIFIED**.

## Delivered
- Fail-closed read-only CLI: php artisan erp:release:evidence --sha=REVIEWED_COMMIT --evidence=PRIVATE_NONSECRET_JSON --artifacts=REVIEW_DIRECTORY --json
- Inspects only a bounded (64 KiB) nonsecret evidence file under storage/app/private/release-evidence. Requires exact SHA, isolated staging, separate reviewer and operator aliases, UTC times within 30 days, matching encrypted backup transfer SHA256 hashes and eleven witnessed checks.
- Compares both cPanel review ZIP SHA256 digests against release-manifest.json and requires its source SHA match. Rejects any package that claims deployment_authorized=true. Existing scripts/verify-cpanel-release.sh remains mandatory for archive content / private path verification.
- Even a complete packet yields PACKET_READY_FOR_INDEPENDENT_REVIEW, with deployment_authorized=false. This checks an evidence INDEX; it cannot cryptographically authenticate external witness signatures or prove production-safe restore.
- CI-only phase23-synthetic-restore.php uses random temporary fake SQLite rows and a dummy private file, encrypts them with ephemeral AES-256-GCM, checks transfer hashes, restores in another temporary directory and verifies ciphertext tampering is rejected. It does not touch existing databases, .env or customer records.
- CI verifies both synthetic restore and refusal of absent real restoration evidence. New unit/feature tests reject wrong SHA, stale/self-approved or missing witnesses, public/oversize files, corrupted packages, and any implicit deployment permission.

## Actual staging DR — operator and independent reviewer must do this separately
1. Obtain explicit permission and freeze exact commit and archive SHA256 values. Prepare an isolated separate staging account, least-privilege MySQL instance, and *separate* private storage destination.
2. Identify MySQL data, attendance, payroll/receipt evidence, private Vault objects, historical legacy uploads, audit records, and encrypted fields. Ensure the existing APP_KEY is safely escrowed outside archive and never printed or rotated.
3. Take a transactionally consistent database and private-file snapshot from an approved checkpoint, encrypt before offsite upload, confirm provider isolation, backups and recovery credentials. Check SHA256 before and after retrieval.
4. **Never restore onto a live cPanel account, live MySQL database or production object-store prefix.** Restrict staging network. Disable sending emails, SMS, payment/webhook calls and all scheduled tasks until explicitly enabled for isolated testing.
5. Restore database, private objects and app encryption settings on staging. Compare row counts/approved transaction snapshots/files, access to actual private attachments, tenant isolation (cross-Partner negative checks) and immutable/audit histories.
6. Test full auth, optional/required MFA, lost-device recovery, real mailer under a safe sandbox, queue workers, scheduler observation, partner approvals, financial reconciliation, API token lifecycle and company exit legal holds.
7. Rehearse a **staging-only** rollback to prior reviewed artifact; check schema backward compatibility, reconcile intervening writes, do not run lossy down-migrations or delete current customer records.
8. Record measured backup age (RPO), elapsed restore (RTO), real signed external evidence digests, and separate reviewer/operator identities in a secure evidence store. Link only nonsecret SHA256 references in the index. Obtain all required Partner UAT signoffs.
9. Request a **separate, explicit, exact-SHA production deployment authorization** for a scheduled release/rollback window. This Phase 23 implementation does not grant it.

## Human UAT matrix
- Platform owner: MFA, tenant create/archive, billing/payment approval, hosting/admin isolation and custom domains
- All required independent Partners: workspace separation, company policy vote, manager delegated grants/revocation, financial approval
- Manager/Moderator: delegated attendance correction, leave and conveyance workflow; no implicit confidential salary access
- Student: self check-in/out, optional GPS consent, own leave/conveyance/notifications and no cross-tenant data
- Payroll reviewer: draft/review/external evidence/paid/locked states, financial audit and reconciliation
- Security operator: API bearer revoke on password reset/MFA downgrade, forbidden scopes, private files, secrets, audit and alerting
- Offboarding operator: actual complete export, contract retention/legal hold checks, no premature erasure

## Required witness keys
- encrypted_offsite_backup
- isolated_database_restore
- private_files_restore
- app_key_recovery
- tenant_isolation_negative_test
- smtp_delivery_test
- queue_scheduler_observation
- financial_policy_signoff
- partner_user_acceptance
- rollback_rehearsal
- independent_security_review

Each witness requires status=passed, the SHA256 of real independently retained proof, and actual UTC observation time. Do not fabricate passing attestations. Example evidence TEMPLATE is deliberately pending.

## Limits and release decision
- A test-only 2-row SQLite rehearsal does not prove MySQL transactional backup, encrypted offsite retention or a large live data restore.
- Phase 18 export is still a small (16 MiB) pilot. 200GB backup/export/restore and infrastructure throughput **are not certified**.
- Proposed recovery service goals require measured validation; do not treat 4-hour RPO/8-hour RTO as achieved.
- Human security review, penetration test, full UAT, SMTP, worker supervision, backup/access retention and privacy/legal compliance are still independent signoff gates.
- No production system, customer data, main branch, cPanel or real payment record is altered by this development work.
- Production release decision remains **NO-GO** until independently reviewed real evidence AND separate owner approval.
