# DhakaWala ERP — Backup, Restore & Rollback Runbook (Phase 1)

**Status: operator action required; no actual backup or restore is claimed by this document.**

This describes the dedicated cPanel layout under a verified account, currently:
- Private Laravel application: /home/erpdhakawala/erp-app
- Public webroot assets: /home/erpdhakawala/public_html
- Separate least-privilege cPanel MySQL database

Never put database dumps, private receipts, private payroll files, session exports, .env, the APP_KEY or backup archives in public_html, GitHub, chat or publicly accessible storage.

## 1. What must be captured together

- A transactionally consistent MySQL backup of the ERP database, including schema, migrations, all tenants, memberships, payroll snapshots, attendance, notifications and audit rows.
- All private Laravel storage files used by receipts and payroll evidence, including future upload directories; verify the actual configured storage disk paths before setting a backup exclusion.
- The exact reviewed application commit SHA and artifact checksums for rollback.
- Securely escrow the current APP_KEY and relevant encryption settings in an authorized password/secret manager **separately** from the archive. Do not regenerate an existing instance's key as a migration workaround: MFA secrets and other encrypted fields depend on it.
- Necessary operating configuration values, but never check secrets into source control.

A database-only snapshot is not sufficient because receipt and payroll evidence may be stored on disk. A files-only copy is not sufficient because records/permissions live in the database.

## 2. Access, retention and proposed service goals

Assign one accountable platform operator and one independent backup/restore reviewer.

Proposed **targets for business approval**: daily encrypted off-site backups, additional backup immediately before each schema or payroll-critical release, 30-day rolling retention plus a monthly archival tier, Recovery Point Objective (RPO) 24 hours and Recovery Time Objective (RTO) 8 hours. These are planning targets, not an SLA or a statement of existing capability.

- Backups must be encrypted **before** upload to a separate provider/account or isolated storage with limited credentials.
- Encryption passphrases/keys must not be stored alongside archives.
- Test that the provider retains delete/overwrite history and sends notifications on failures.
- Limit read/restore permission and log access; test retention and deletion against signed privacy requirements before entering any employee data.
- Keep versioned evidence of backup completion: date, source instance identifier (nonsecret), archive checksums, file count, operator and offsite storage proof. Do not expose client records inside evidence logs.

## 3. Prepare a safe staging restore drill

1. Obtain the user's written permission and confirm a staging account is fully separate from live production.
2. Set a maintenance window or transactional consistency method; back up the database and private files from a consistent checkpoint. Do not discard any original records.
3. Take encrypted archives **outside** public_html, verify SHA256 checksums after transfer to staging.
4. Provision a new isolated staging database and private storage path. **Never run the restore into erpdhakawala_main or a live server account.**
5. Restrict staging network access. Disable outbound invitation, password-reset and payroll notice delivery, live webhooks and any tasks that could modify a production service.
6. Restore the database and matching private files in staging; use appropriately guarded test credentials and approved access to the needed app encryption key. Do not share keys with unauthorized staff.
7. Verify table counts/migration status and that a test Partner cannot access another workspace's payslips, receipts or attendance. Confirm actual private evidence retrieval on staging.
8. Restore all account features and perform an authenticated test login with authorized staging identities. Do not email real users from restored data.
9. Record the time taken, errors, immutable checksums, gaps and signed reviewer acceptance. Retire staging securely according to the retention plan.

## 4. Release rollback policy

- Freeze the reviewed Git commit and archive before deployment; preserve the prior working release artifact and verify app-only rollback on staging.
- Before any risky migration, separately back up both the DB and private evidence files. Be sure restoration is rehearsed.
- On failure, stop new financial/attendance writes; avoid running down-migrations on real data without a verified lossless rollback.
- An application-code rollback may be performed from the last reviewed artifact after verifying schema compatibility. Do not rotate APP_KEY as part of rollback.
- A database restore could overwrite recent employee entries: reconcile new activity and obtain written authorization before proceeding.
- Check login, tenant access, receipt privacy, scheduling and payroll status after rollback.

## 5. cPanel-specific validation checklist

- [ ] Confirm daily encrypted database snapshot really exists (not just a provider backup toggle).
- [ ] Confirm the private receipt/payroll storage path is included.
- [ ] Confirm backups do not live in public_html or a web-accessible URL.
- [ ] Confirm offsite encrypted copies and independent recovery access.
- [ ] Restore to a separate staging account/database and record evidence.
- [ ] Verify permission isolation and receipt access after restore.
- [ ] Configure backup failure notifications and review retention.
- [ ] Schedule a periodic restore test and record the person responsible.
- [ ] Verify the Laravel scheduler and monitoring separately; backup automation must not be coupled to the app's cron alone.

If any item remains unchecked, Phase 1 **infrastructure signoff is blocked**, even if GitHub Actions, the website and erp:release:check show success.
