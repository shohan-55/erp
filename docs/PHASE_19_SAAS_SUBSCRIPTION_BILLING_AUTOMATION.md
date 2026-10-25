# Phase 19 — SaaS Plans, Trials, Renewal Invoices and Paid Storage Add-ons

Baseline: Phase 18 squash-merged SHA `4c6ab907b8b0d19e6afc009d65ac8d87820986ca`.

## Delivered

- Reuse Phase 13 platform-only subscription, invoice, manually reconciled payments, membership/workspace caps and domain eligibility. No other customer's data is visible to SaaS operators, and no private practice documents are loaded.
- Per-plan **annual discount percent** (0–40), **free-trial duration** (0–30 days), priced Vault storage add-on units (MiB and monthly BDT). Commercial plan terms cannot rewrite historical invoice snapshots.
- An existing company remains unbilled until deliberately enrolled. Platform operator optionally starts the configured trial. Trial has a bounded date, zero fake payments, zero invoiced value, zero renewal entitlement and no new custom-domain activation. After trial expires, the account stays available but has `pending` billing status. It is never automatically suspended.
- Invoicing continues to support 1/3/6/12 months. **Yearly discount applies only to 12-month BASE subscription charge.** Storage add-ons are billed at full per-month unit price, 12 months when applicable. The invoice freezes its annual discount, add-on unit count, MiB count, add-on and discount BDT amounts.
- Platform-only `auto_invoice` opt-in, billing frequency and mandatory audited reason. Daily `erp:billing:renewals` (and `--dry-run`) issues **UNPAID** renewal invoices only within 7 days prior to paid-period end or 30 days after, when no open invoice or prior automatic invoice for that end date exists. Locks the company and subscription, and uses unique subscription+date ledger to resist concurrent double issuing. It does **not** charge cards/bank accounts, create fake payment references, mark an invoice paid or suspend a company.
- Storage add-ons are **quoted** to the next invoice; no entitlement changes until actual payment evidence is manually and externally verified and the invoice fully settles. No quote changes while an invoice is pending or part paid. Paid units update the subscription only on full settlement.
- Platform operator can allocate paid Vault bytes to an existing **active** workspace using the Phase 17 quota ledger. Enforces a company pooled max of 100MiB per active workspace plus the purchased add-on units, rejects other company's workspace IDs, and cannot shrink below used+reserved. Requires active/grace paid status. Existing unaffiliated/unbilled customers' storage data and public DNS remain untouched.
- Company Partners get read-only subscription status, trial date, auto-invoice setting, paid add-on count and immutable invoice snapshots; no platform payment references or bank credentials.

## Boundary and safety

- Existing paid invoices and subscription periods are not repriced; plan edits are not supported (publish a new plan code). Existing paid subscriptions remain supported when a plan is retired.
- Free trial is application status, **not** complimentary verified customer service, and it never auto-funds a paid domain entitlement.
- No SaaS payment gateway integration, external subscription charge, direct debit, Stripe, SMS, invoice dispatch, fake bank receipts or automatic access suspension.
- The commercial storage add-on controls **software Vault quota**, not infrastructure disk provisioning or payment for unallocated legacy document storage. Platform must independently validate physical capacity, private encryption and backups before offering high storage tiers; no 200GB performance certification.
- A change of paid add-on quantity should not be priced mid-cycle; this implementation quotes new units against a **new** invoice period and applies them on full settlement. Decreasing paid units requires reducing all overallocated workspace quotas first, but cannot remove already stored data.
- Provider-side cloud storage, currency/tax rate tables, coupons/proration and actual automated payments remain future commercial expansion. This avoids presenting billing automation as an external charge.

## Verification

- [ ] PHPUnit, tenant negative-access and idempotency tests
- [ ] MySQL 8 real migrations + entire suite
- [ ] Authenticated browser QA, Vite build
- [ ] cPanel ZIP package build (not deployment)
- [ ] PR review, squash merge into `security/production-readiness-gates`, exact-SHA post-merge CI
- [ ] No `main` change and no live cPanel/ERP production change without explicit owner release approval
