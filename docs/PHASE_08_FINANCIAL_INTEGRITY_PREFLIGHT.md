# Phase 8 — Financial Integrity Staging Preflight

**Status: code-only, read-only, not a production sign-off.**

Run **only with authorized access to an isolated staging database** that contains approved, preferably synthetic test records:

```bash
php artisan erp:integrity:check
php artisan erp:integrity:check --json
```

Both formats expose *aggregate anomaly counts only*. They do not print employee names, salaries, claim routes, bank references, file paths, GPS, database passwords or raw SQL errors. This command intentionally makes **no database writes** and never approves, recalculates, pays, locks or deletes payroll entries.

## Checks implemented

- Approved/paid conveyance claims with missing `approved_amount`, including historical legacy records requiring a documented manual review.
- Approved/paid claims with final approved amount outside the allowed positive and no-greater-than-original range.
- Payroll lines whose allowance + approved claim + adjustment total differs from the recorded amount beyond half a paisa/cent equivalent (BDT decimal values).
- Paid claims lacking a paid payroll run link.
- Paid/locked payroll runs missing expected reference/date/method/reconciled amount/private-evidence path metadata.
- Paid/locked payroll runs where the stated reconciled external payment total differs from the sum of their snapshotted payroll lines.
- Payroll runs containing no payroll lines.
- Members with multiple open attendance sessions.

`Decision: NO_ANOMALIES_DETECTED` and exit 0 mean only that **these targeted database checks found no anomalies**. `Decision: REVIEW_REQUIRED` and exit 1 mean investigated anomaly counts are nonzero. `Decision: NO_GO` and exit 1 indicate an incomplete/unreachable schema or check error; the command suppresses private SQL error messages.

This is *not* a banking/payment-provider reconciliation, physical backup/restore check, virus scan of attachments, settlement proof, verification that an evidence file actually exists on disk, completeness of historical allowance snapshots, legal/policy approval, independent audit or go-live permission. It does not establish that all legitimate expense/payroll records are present. These items remain in [Phase 7–8 release blockers](https://github.com/shohan-55/erp/issues/18).

**Critical policy:** historical `approved_amount IS NULL` entries must not be blindly backfilled or silently approved at their original requested amount. Determine a documented, Partner-approved legacy treatment before using any affected payroll run operationally. No speculative attendance deductions.

### Verification requirements

Before merging, require feature/unit tests on SQLite, full test suite on MySQL 8, production asset/browser CI and cPanel review package checks all green on this commit. Re-run on the consolidated development branch afterward. This PR changes no live cPanel instance, DNS, secrets, database, scheduled tasks or financial records.
