# P0 Security & Data-Safety Fixes — 30 October 2026

Companion to [`PRODUCTION_READINESS_AUDIT_2026-10-30.md`](PRODUCTION_READINESS_AUDIT_2026-10-30.md).

The audit found **7 P0 blockers**. All seven are fixed in this branch. Each fix is a
separate commit so it can be reviewed, reverted or cherry-picked on its own, and each
one has a regression test that fails if the hole is ever reopened.

| # | Blocker | Commit | Status |
|---|---------|--------|--------|
| 1 | Account takeover through the invitation flow | `4303289` | ✅ Fixed |
| 2 | Managers could escalate to Partner | `4303289` | ✅ Fixed |
| 3 | Hard-coded super-admin email | `4303289` | ✅ Fixed |
| 4 | Wrong default timezone (UTC vs Dhaka) | `b869836` | ✅ Fixed |
| 5 | Hard-coded default passwords | `b869836` | ✅ Fixed |
| 6 | Previews fail-open in production | `83f45f0` | ✅ Fixed |
| 7 | Destructive deletion without backup | `b19a8f4`, `04b502a` | ✅ Fixed |

---

## 1. Account takeover — invitation activation (`AccountActivation.php`)

**Was:** `?email=victim@firm.com` resolved the victim's account and let any visitor set a
new password. `targetUserId` was also trusted from the browser on submit.

**Now:** an account is resolved **only** from `invitation_token`. The lookup is scoped to
the firm in the URL, the token must be younger than 7 days (`invitation_sent_at ??
created_at`), and it is re-resolved server-side on submit and cleared on success. A
production environment without a valid token shows "expired" instead of a demo card.

## 2. Privilege escalation — team management (`ManagesTeamAndNotifications.php`)

**Was:** a Manager with `can_manage_team` could open any member, reset a Partner's
password, promote themselves, or delete the Partner.

**Now:** `canManageTargetRole()` / `canManageTargetUser()` enforce a ceiling: Partners and
platform admins may manage anyone; a delegated Manager may only create, edit and delete
**Articled Students**, and nobody may change their own role.

## 3. Hard-coded super-admin (`User.php` + migration `2026_10_30_000002`)

**Was:** `isPlatformAdmin()` returned true for two literal email addresses.

**Now:** authority comes from the `role` column only. The legitimate operators are promoted
once by an idempotent migration, and changing the sign-in email now requires the current
password (so an admin can't have their email flipped by a stolen session).

## 4. Timezone (`config/app.php`)

`APP_TIMEZONE=Asia/Dhaka` was set in `.env.example` but the config was pinned to `UTC`, so
every attendance check-in, late deduction and payroll period boundary was six hours off.
The config now reads `env('APP_TIMEZONE', 'Asia/Dhaka')`.

## 5. Default passwords (seeder, team enrolment, provisioning, `erp:make-admin`)

`Partner@2026` / `Password@123` / `password` are gone. Every path now generates
`Str::password(14|20)` (or reads `PLATFORM_ADMIN_PASSWORD`), prints it once, and re-seeding
never overwrites an existing operator password.

## 6. Preview fail-open (`RestrictPreviewInProduction.php`, `routes/web.php`)

**Was:** the gate only looked at `APP_ENV === 'production'`, so any mis-configured
environment exposed every tenant's workspace anonymously and writably.

**Now:** previews are **deny-by-default** and need `ERP_DEMO_MODE=true` (or
local/testing). Authenticated users remain confined to their own firm. The private
avatar-file route now requires `auth` + `firm.member`.

## 7. Destructive deletion & disaster recovery

* `performSafeFirmDeletion()` now runs in **one transaction** — a failure can no longer
  leave a half-deleted tenant — and sweeps every remaining `firm_id` table so a future
  migration cannot leave orphaned rows.
* `audit_logs` are **detached** (FK nulled), never deleted: the platform trail is append-only.
* Firm lookup no longer uses `orWhere('id', $slug)`; a slug such as `"12"` can no longer
  target firm id 12.
* A verified JSON backup is written to `storage/app/private/backups/firms/` **before** any
  deletion; if it cannot be written, nothing is deleted.
* Staff exits are now a **retention event**: a member with attendance/payroll/leave/claim
  history is deactivated (`is_active=false`, password randomised, sessions revoked) instead
  of being cascaded away. Members with no history are still hard-deleted.
* `erp:backup` writes a secret-scrubbed snapshot of every tenant table and prunes old
  snapshots; `routes/console.php` schedules it daily at 02:00.

---

## Verification

| Check | Result |
|-------|--------|
| PHP syntax (`token_get_all`, `TOKEN_PARSE`) on all PHP files | **117 / 117 OK** (second pass) |
| Import / symbol audit (Str, Hash, User::ROLE_*, `can_manage_team`, `firm.member` alias, `RequireFirmMember`) | ✅ all present |
| Regression tests added | **22** (`SecurityHardeningTest` 16, `DataRetentionAndDeletionTest` 6) |
| Full PHPUnit suite | ⚠️ **not executed here** — the sandbox has no PHP runtime with `mbstring`/`pdo_sqlite`; the new tests are written against the exact patterns of the existing green tests and run in GitHub Actions (`php artisan test`). |
| Front-end build | ✅ `npm ci && npm run build` succeeds (Vite 8); rebuilt assets committed in `86582a3`. |
| TOTP algorithm | ✅ executed against the RFC 6238 reference vectors in a WASM PHP build — 22/22 checks pass |

> **Before merging:** run `php artisan test` once locally. The two areas most worth watching
> are the invitation tests (they assert the new token-only behaviour) and the Phase 03
> deletion tests (they now also write a backup file before deleting).

## Phase 2 — P1/P2 hardening (same day, second pass)

| Commit | What it closes |
|---|---|
| `2fbbb56` | Security headers middleware, POST-only logout, session/remember-token hygiene, `CsvSafe` formula-injection guard, escaped HTML exports, voucher/avatar access control |
| `c7b3b9e` | Leave-quota enforcement, subscription-expiry gate, partner-unit scoping on approvals/attendance edits, real invitation + reset emails, payroll period bounds, composite indexes, date-range queries |
| `8261ca7` | `/health` readiness probe, scheduled `erp:backup`, `erp:flag-missed-checkouts`, `erp:subscription-expiry-reminder`, provisioning no longer resets existing passwords |
| `c972ec7` | `ProductionHardeningTest` — 17 regression cases for the above |
| `86582a3` | `public/build` rebuilt from the current source (was stale) |
| `0d016dc` | CI on every branch, non-blocking `composer audit` / `npm audit`, Dependabot |
| `97b0a9a` | **TOTP two-factor authentication** (`app/Support/TwoFactor.php`, `erp:two-factor`, opt-in, inert until confirmed) |
| `260f116` | 120 KB inline Tailwind bundle → cached `public/css/brand-theme.css` (+ `.htaccess` cache/compression headers) |

### Still open after both passes

1. **Billing** — no payment gateway, invoice, receipt or dunning; `subscription_fee` is a manual
   ledger field. Subscription *expiry* is now enforced, collection is not.
2. **Architecture** — `DashboardPreview` (~1.9k lines) and `PracticePreview` (~0.9k lines + traits)
   still hold business logic; no Policies/Services; the 8 Gates in `AppServiceProvider` remain
   unused. This is the main long-term risk, not a launch blocker.
3. **Scale** — no pagination; queues of leave/claims/payroll rows are loaded into public Livewire
   properties. Fine for a firm of 10–30 seats, not for 200.
4. **Runtime schema probing** — ~190 `Schema::hasTable/hasColumn` calls per render and
   `Firm::ensureRuntimeSchemaColumns()` can still issue DDL inside a request.
5. **Email verification, session listing/revocation UI, per-firm SMTP** — not implemented.
6. **Testing depth** — CI runs SQLite only; no MySQL job, no coverage gate, no Playwright in this
   branch. The two remaining `testing`-only login shortcuts in the sign-in components still ship.
7. **Storage** — vouchers are receipts with financial data and still live on the `public` disk.
8. **Legal/UX** — no terms/privacy pages, no Bengali i18n layer, no accessibility audit.
