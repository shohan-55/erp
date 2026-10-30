# DhakaWala ERP — Full Production Readiness Audit

**Repository:** `shohan-55/erp`
**Branch / Commit:** `arena/714c0ceb-erp` @ `16c15ae`
**Audit date:** 30 October 2026
**Stack:** Laravel 13 · PHP 8.4 · Livewire 4 · Blade · Tailwind 4 · Vite 8
**Codebase size:** 177 files, ~20,050 lines (PHP + Blade), 24 Blade views, 20 migrations

> ### 📌 Remediation status — updated 30 October 2026
>
> This document is the **original snapshot** of the codebase at `16c15ae`; the findings below
> are kept verbatim so the reasoning stays auditable. What has since been fixed:
>
> **P0 (all 7) — fixed.** Invitation-based account takeover, manager→partner escalation,
> hard-coded super-admin emails, the wrong default timezone, hard-coded passwords, fail-open
> previews and destructive deletion without a backup. See
> [`SECURITY_FIXES_2026-10-30.md`](SECURITY_FIXES_2026-10-30.md).
>
> **P1 — fixed so far.** Security headers + trusted-proxy-aware sessions; GET→POST logout;
> email delivery for invitations and password resets (`TeamInvitationMail`, `Mail::raw`);
> leave-quota enforcement; payroll period bounds and month-length-independent daily rate;
> partner-unit scoping on attendance/leave/claims; subscription-expiry enforcement
> (`RequireActiveSubscription`); CSV formula-injection guard (`CsvSafe`); escaped HTML
> exports; composite indexes on the hot paths; date-range queries instead of `LIKE '2026-10%'`;
> **TOTP two-factor authentication** for platform, firm and portal sign-in (opt-in, inert
> until confirmed); scheduled `erp:backup`, `erp:flag-missed-checkouts`,
> `erp:subscription-expiry-reminder`; `/health` readiness probe; CI on every branch plus
> Dependabot; the 120 KB inline CSS bundle moved into a cached stylesheet.
>
> **P2 — fixed so far.** `LOG_LEVEL=warning`, session encryption, 120-minute sessions,
> 14-day log retention, `robots.txt` no longer invites crawlers into the app, provisioning
> no longer overwrites an existing user's password, `deleteOrganization()` no longer
> resolves slugs through `orWhere('id', …)`, and the stale `public/build` was rebuilt.
>
> **Still open (honest list):** payment gateway / invoicing / dunning; Policies + Service
> extraction out of the Livewire monoliths (still 1.9k + 0.9k lines); pagination and
> `#[Lazy]` for the 70-property public state; removal of per-request
> `Schema::hasTable/hasColumn` probing and runtime DDL; MySQL service container in CI;
> soft deletes and tamper-evident audit logs; email-verification enforcement; Sentry /
> error tracking; private storage with signed URLs for vouchers; i18n and an accessibility
> pass; terms/privacy pages.
>
> **Verification caveat:** this branch was still developed without a PHP runtime. Every PHP
> file was parse-checked (`token_get_all`, 117/117), the TOTP implementation was executed
> against the RFC 6238 vectors in a WASM PHP build, and the full PHPUnit suite must be run
> once on a real host (`php artisan test`) before merging.

---

## 0. Audit Method & Limitations

| Item | Status |
|---|---|
| Full static review (routes, middleware, models, Livewire components, traits, migrations, views, config, CI) | ✅ Done |
| Multi-tenancy / authorization trace | ✅ Done |
| Database schema & migration review | ✅ Done |
| Frontend production build (`npm ci && npm run build`) | ✅ Done — build succeeds |
| PHP test suite (`php artisan test`) | ❌ **Not executable in this sandbox** (no PHP/Composer binary, no package network) |
| Runtime / penetration testing | ❌ Not performed (static reasoning only) |
| Browser/Playwright run | ❌ Not performed |

> **Important:** every P0 below was verified by reading the actual code path, not by guessing. Still, before go-live the test suite and a manual pen-test must be run on a real PHP 8.4 environment.

---

## 1. Verdict

### ❌ **NOT production-ready.** Do not onboard a real client firm with real payroll/attendance data yet.

The UI/UX layer is genuinely strong (this is a polished product), but the **security model, time handling, and operational layer are not yet at production grade**. There are **7 blocking (P0) issues**, several of which allow one user to take over another account or the whole tenant.

**Estimated remaining work to a safe v1: 3–4 weeks** (1 senior Laravel dev), of which the first **3–5 days** are pure security hot-fixes.

### Scorecard

| Area | Score | Comment |
|---|---|---|
| UI / UX / Design system | **8.5 / 10** | Polished, consistent, branded, responsive |
| Feature breadth (functionality) | **7.5 / 10** | Attendance, leave, conveyance, payroll, governance all exist |
| Multi-tenant data isolation | **6 / 10** | `firm_id` scoping is applied consistently, but no Policies/global scope — one missed filter = leak |
| Authentication & authorization | **3 / 10** | Account takeover, role escalation, hardcoded super-admin emails, unused Gates |
| Data accuracy (time/money) | **4 / 10** | Timezone is wrong by 6 hours; payroll math is fragile |
| SaaS/billing readiness | **3 / 10** | No payment gateway, no invoice, no enforcement of expiry |
| Operations / observability | **3 / 10** | No scheduler, no queue usage, no backups, no monitoring, no error tracking |
| Performance / scalability | **4 / 10** | No pagination, full-table loads into Livewire state, ~40–60 schema introspection calls per page |
| Testing / QA | **5 / 10** | 86 tests / 378 assertions exist, but mostly UI-preview; almost no negative authz tests |
| Code architecture / maintainability | **5 / 10** | 2 giant components (1,888 + 869 lines) + 6 traits; business logic in the UI layer; `catch (\Throwable) {}` everywhere |

---

## 2. 🔴 P0 — BLOCKERS (fix before any real data)

### P0-1. Account takeover through the activation link (critical)

`app/Livewire/Auth/AccountActivation.php:56–77` and `:146–200`

The activation page resolves the target account **without any token validation**:

```php
$emailParam = request()->query('email');
...
} elseif ($emailParam) {
    $query->where('email', strtolower(trim((string) $emailParam)));
}
$dbUser = $query->first();
$this->targetUserId = $dbUser->id;
```

…and then `submitActivation()` writes a brand-new password with **no token, no expiry, no auth check**:

```php
$dbUser->password = Hash::make($this->password);
$dbUser->invitation_token = null;
$dbUser->save();
```

**Attack:** anyone who knows `firm-slug` + a staff email can open
`/c/rac/activate?email=mohitul.haq@rahmananis.com`, type a password, and own that account — including a Partner.

Also: `invitation_token` is never checked for expiry (`'expires_in' => '48 hours'` is cosmetic text only), and the same token can be replayed until it is consumed.

**Fix:** require a signed, hashed, time-limited token that matches the user row; refuse the `email` path entirely; rate-limit; invalidate token on use; add a negative test.

---

### P0-2. A delegated Manager can reset the Partner's password, promote themselves, or delete the Partner

`app/Livewire/Practice/Concerns/ManagesTeamAndNotifications.php:607–700` (`updateMember`), `:732–770` (`deleteMember`)

Both actions only check `userCanManageTeam()` (which is true for **any** manager holding the `can_manage_team` flag). After that, the target user is fetched by `firm_id` + `id` with **no role ceiling**:

```php
if (! empty(trim($this->editMemberNewPassword)) && strlen(...) >= 8) {
    $user->password = bcrypt(trim($this->editMemberNewPassword));   // ← resets ANY user's password
}
$user->role = $this->editMemberRole;                                // ← can promote self/anyone to partner
```

`deleteMember()` likewise allows deleting a Partner account (and cascade-deletes their records).

**Attack:** a manager with team rights resets the Partner's password → full control of the firm.

**Fix:** role hierarchy / ceiling (`manager` may only manage `student`; only `partner` may manage `partner`/`manager`), forbid password reset of accounts above your level, forbid self-role escalation, forbid deleting the last Partner.

---

### P0-3. Hardcoded super-admin emails + self-service email change = platform takeover

`app/Models/User.php:125–130`

```php
return $this->role === self::ROLE_PLATFORM_ADMIN
    || strtolower($this->email) === 'infobd.sohan@gmail.com'
    || strtolower($this->email) === 'admin@dhakawala.com';
```

`app/Livewire/Practice/Concerns/ManagesPoliciesHolidaysAndSettings.php:242–330` lets **any authenticated user change their own email** with no verification and no password re-entry:

```php
if ($email !== '' && $email !== $user->email) {
    if (User::where('email', $email)->where('id', '!=', $user->id)->exists()) { ... }
    $user->email = $email;
}
```

The README deployment checklist runs `migrate --force` but **not** `db:seed`, so on a fresh deployment no `admin@dhakawala.com` row exists. Any Student can set their email to `admin@dhakawala.com` and is instantly a **platform super-admin** over every tenant.

**Fix:** delete the email fallback (role column only); require email re-verification before an email change takes effect; add a unique-index-based guard; make `db:seed` part of the deploy or create the platform admin only via `erp:make-admin`.

---

### P0-4. Timezone is hardcoded to UTC — every attendance time is 6 hours wrong

`config/app.php:68`

```php
'timezone' => 'UTC',
```

…while `.env.example:5` declares `APP_TIMEZONE=Asia/Dhaka` — **that variable is never read by any config file**. Meanwhile the code mixes both worlds:

- Check-in stores `$att->in_time = now();` → **UTC** (`ManagesAttendanceCalendarAndRadar.php:1310`)
- The live row displays `now()->timezone('Asia/Dhaka')->format('H:i')` → **Dhaka** (`:1252`)
- After a page refresh, `loadAttendanceRows()` formats the stored timestamp in app timezone → **UTC** (`:88`)

**Result:** a student checks in at 10:00 Dhaka, sees 10:00, refreshes, and sees **04:00**. The attendance `date` also flips to “yesterday” between 00:00–06:00 Dhaka. Late/grace classification comes from a Dhaka-formatted string, while deductions compare against stored UTC times → payroll penalties can be wrong.

**Fix:** `'timezone' => env('APP_TIMEZONE', 'Asia/Dhaka')` + a single helper for “business time”; backfill/verify existing rows; add timezone tests.

---

### P0-5. Default weak passwords, printed on screen

| Location | Default |
|---|---|
| `database/seeders/DatabaseSeeder.php:19` | `env('PLATFORM_ADMIN_PASSWORD', 'password')` — plain `password` |
| `ManagesTeamAndNotifications.php:502` | `'Password@123'` for every invited member |
| `DashboardPreview.php:570, 886, 1223` | `'Partner@2026'` for provisioned / reset Partners |

All three are echoed back to the UI (`previewNotice`, `notice`) in clear text. `DatabaseSeeder` also runs `updateOrCreate` on the admin emails, so **re-running `db:seed` in production resets the super-admin password to a known value**.

**Fix:** generate `Str::password(16)` random values, force change on first login, deliver via email/one-time link, never display; seeder must refuse to run in production without an explicit env password.

---

### P0-6. The “preview” surface fails **open** — the only thing standing between an anonymous visitor and your database is `APP_ENV=production`

- `app/Http/Middleware/RestrictPreviewInProduction.php:18`
  ```php
  if (app()->environment('production') && ! config('app.preview_enabled', false)) { ... }
  ```
  (`app.preview_enabled` **does not exist** in `config/app.php` — dead switch.)
- `PracticePreview::mount()` (`:303`) has **no authentication requirement**.
- Almost every write path only checks the component property `$this->previewRole` — for example `partnerApproveLeave()` (`ManagesLeaveWorkflow.php:553`) writes `$lr->save()` when `previewRole === 'partner'`, with **no `Auth::check()`**.

So on a staging box, a typo in `APP_ENV` (`local` instead of `production`), or a mis-set `.env` after a `git pull`, the entire multi-tenant DB becomes writable anonymously as “Partner”.

**Fix:** defence in depth — `mount()` must `abort_unless(Auth::check())`; every mutation must go through a Policy/Gate, not a preview property; the preview route must be disabled by config **and** by a hard auth check.

---

### P0-7. No backup / disaster-recovery, and destructive deletes wipe financial history

- `deleteOrganization()` → `performSafeFirmDeletion()` (`DashboardPreview.php:1590`) disables FK checks and **permanently deletes** firm, users, attendance, leave, conveyance, payroll, audit logs. The JSON backup is optional and not mandatory before deletion.
- `deleteMember()` hard-deletes the user; `payroll_records`, `attendances`, `leave_requests`, `conveyance_claims` are `cascadeOnDelete` → **payroll history for a departed articled student silently disappears**.
- There is **no scheduled backup** anywhere (`routes/console.php` only has `inspire`; no `schedule()` at all).

For a CA firm this is a compliance problem (ICAB records, payroll proof, audit trail), not just an ops problem.

**Fix:** soft deletes + `deleted_at` scoping; retain financial records; enforce “backup before delete”; add daily DB + file backup job and restore drill; make `audit_logs` append-only.

---

## 3. 🟠 P1 — HIGH (fix before onboarding 2nd tenant / scaling)

### 3.1 Email is effectively not wired
- `.env.example` SMTP host is `127.0.0.1` → password reset links never leave the server.
- **Invitations are never emailed at all** — `createMember()` shows a temp password in a toast and leaves `invitation_token` unused.
- No `Mail::`, no `Notification::` anywhere in `app/` (verified by grep).

### 3.2 Authentication hardening missing
- No 2FA for Partner / Platform Admin (highest-value accounts in the system).
- Changing password/email requires **no current password** and does not invalidate other sessions (`Auth::logoutOtherDevices` never called).
- `email_verified_at` is set but **never checked** at login.
- Password policy is inconsistent: activation requires uppercase+digit, reset additionally requires a special char, profile update requires only 8 chars.
- Logout is exposed as **GET** (`routes/web.php:32`) → CSRF logout.

### 3.3 Privilege boundaries inside a firm are soft
- A **Manager can edit workspace policy**, including `manager_conveyance_limit` — i.e. a manager can raise their own claim ceiling (`ManagesPoliciesHolidaysAndSettings.php:337`).
- A manager can proxy/edit attendance for **any** student in the firm, not only their own unit/direct reports (`ManagesAttendanceCalendarAndRadar.php:1015`).
- Partner-unit isolation exists for *viewing* but is not enforced for *approving* (any manager can approve any unit's leave/claims).

### 3.4 Business rules not enforced
- **Leave quota is never checked** — `leaveQuotas` is only used to validate the *type label* (`ManagesLeaveWorkflow.php:277`). No balance ledger, no “remaining days”, no carry-forward.
- No attendance auto-close / missed check-out escalation job.
- Subscription expiry (`subscription_expires_at`) is **not enforced** anywhere — only surfaced as a banner. `is_suspended` is enforced in `mount()` only.
- No payment gateway (bKash/Nagad/SSLCommerz/Stripe), no invoice, no receipt, no dunning. Billing is a manual ledger of `payment_status`, `subscription_fee`.
- Payroll: penalty math uses `base / 30` regardless of month length; `present_days` counts any attendance row (including a 5-minute check-in); late-day statuses are string-matched (`'Late'`, `'Late Present'`) which is brittle.

### 3.5 Architecture: business logic lives in the UI layer
- `DashboardPreview.php` = **1,888 lines / 37 public methods**; `PracticePreview.php` = **869 lines** + 6 traits (~3,700 lines total).
- **8 Gates are defined in `AppServiceProvider` and used exactly zero times** (grep `Gate::`/`authorize(`/`can(` in `app/Livewire` → 0 hits). All authorization is ad-hoc `if ($this->previewRole === ...)`.
- No FormRequests, no Policies, no Service classes, no Actions, no DTOs, no Events.
- **86 `catch (\Throwable)` blocks, ~51 of them completely empty** — DB failures vanish silently (data loss invisible to the user and to you).

### 3.6 Runtime schema introspection and DDL on the request path
- **192** `Schema::hasTable`/`hasColumn` occurrences (126 + 66).
- `Firm::ensureRuntimeSchemaColumns()` (`Firm.php:54`) can execute `Schema::table(...)` — **DDL — inside a web request** (called from `PracticePreview::mount()`, platform dashboard, theme save).
- Every dashboard render triggers roughly 40–60 `information_schema` lookups.
- Consequence: slow pages on MySQL, migration drift silently “self-heals”, and schema changes can race under load.

### 3.7 Frontend delivery problems
- `resources/views/components/brand-theme-style.blade.php` is **128 KB of inlined Tailwind CSS embedded in every single page**, on top of the 111 KB Vite CSS → ~240 KB of CSS, uncacheable.
- Committed `public/build` is **stale**: rebuilding produces a different hash (`app-DrYBAUlL.css`, 118 KB vs committed `app-C5heZIna.css`, 111 KB), and `app-DhakaWalaV10.css` is an orphan not referenced by `manifest.json`.
- Google Fonts loaded from `fonts.googleapis.com` (extra DNS/TLS + privacy/GDPR consideration); no self-hosting.

### 3.8 Scale / performance
- No pagination anywhere: `loadLeaveQueue`, `loadClaimQueue`, `loadAttendanceRows`, `loadPayrollRows`, `loadTeamMembers` pull **all** rows of the firm into **public Livewire properties**, which are then serialised into every request/response payload.
- Livewire 4 public state on 70+ properties per page → large morph payloads on mobile networks (your target users are on Dhaka mobile data).
- No indexes beyond FKs; `attendances(date)` queries use `LIKE '2026-10%'` on a `date` column (`ManagesPayrollAndVouchers.php:240`) → **index-defeating**; no composite index on `(firm_id, user_id, date)`.
- N+1 risk on `studentRows` (mitigated by `with('manager')`, but `editor`/`proxyUser` only eager-loaded for today's rows).

### 3.9 Security hardening gaps
- No `TrustProxies` configuration — behind Cloudflare/cPanel proxy, `$request->isSecure()` may be false; secure-cookie/session behaviour becomes host-dependent.
- No security headers (CSP, `X-Frame-Options`, `Referrer-Policy`, `X-Content-Type-Options`).
- `/c/{firmSlug}/avatar-file/{filename}` (`routes/web.php:146`) has **no auth middleware** → avatar enumeration/IDOR.
- Voucher viewer let any firm member read **any** colleague's receipt.
- CSV exports do not guard against **spreadsheet formula injection** (`=`, `+`, `-`, `@`).
- HTML exports interpolate `$firmName` into `<title>` **unescaped** (`ManagesAttendanceCalendarAndRadar.php:880`, `:918`) → stored XSS if a firm name contains markup.
- No `Referrer-Policy` on voucher/avatar file responses; no `Content-Disposition` hardening on the HTML certificates.
- File uploads use `getClientOriginalExtension()` (bounded by `image`/`mimes` validation, acceptable, but extension is attacker-chosen).

### 3.10 Testing gaps
- 86 test methods / 378 assertions is a decent start, but they are **mostly happy-path UI previews**.
- **No test** covers: `?email=` activation path, manager-vs-partner role ceiling, cross-firm IDOR, email change → admin escalation, timezone correctness, payroll rounding, concurrency on `updateOrCreate`.
- Tests use `RefreshDatabase` on SQLite `:memory:` — **MySQL behaviour (FKs, JSON columns, `LIKE` on dates) is never exercised in CI**.
- Test fixtures pass non-existent columns (`short_code`, `tier`) that are silently discarded by Eloquent mass-assignment — a sign the suite is not asserting schema reality.

---

## 4. 🟡 P2 — MEDIUM (fix before scaling, not launch-blocking)

| # | Finding | Location |
|---|---|---|
| 1 | `audit_logs` are not tamper-evident (no hash chain, updatable/deletable) | `2026_10_28_000006...php:96` |
| 2 | No soft deletes on any model | all models |
| 3 | `Firm::findByHost()` builds a case-insensitive subdomain lookup without normalising `custom_subdomain`; only `*.dhakawala.com` supported | `Firm.php:236` |
| 4 | Platform provisioning **overwrites an existing user's password** when the email already exists | `DashboardPreview.php:660` |
| 5 | `deleteOrganization()` resolves by `->orWhere('id', $slug)` — a numeric-looking slug can target the wrong firm | `DashboardPreview.php:1673` |
| 6 | Platform dashboard has no per-action permission split (any super-admin can delete production orgs) | `DashboardPreview.php:211` |
| 7 | `enterFirmWorkspaceAsSupport()` = platform admin operates inside a tenant with the tenant's own role; only an audit row protects the tenant | `DashboardPreview.php:1448` |
| 8 | No account lockout / CAPTCHA beyond 5-per-minute per email+IP | `PlatformLogin.php:84` |
| 9 | Sessions: `SESSION_ENCRYPT=false`, `SESSION_LIFETIME=480` (8h) — long-lived **unencrypted** DB sessions | `.env.example:29–30` |
| 10 | `LOG_LEVEL=error` in production → warnings (e.g. swallowed exceptions) never recorded | `.env.example:19` |
| 11 | Queue is set to `database` but **zero jobs are dispatched anywhere** and no worker is documented | `.env.example:36` |
| 12 | No scheduler entries (`schedule()` absent) → no reminders, no rollovers, no cleanup | `routes/console.php` |
| 13 | `robots.txt` is `Disallow:` (index everything) — login/admin pages crawlable | `public/robots.txt` |
| 14 | No terms/privacy pages for a SaaS that stores payroll data | — |
| 15 | No i18n: Bengali text is hardcoded in Blade instead of `lang/bn` | views |
| 16 | No accessibility pass (modal focus trapping, `aria-*` partially present) | views |
| 17 | No `Pint`/`PHPStan`/`composer audit`/`npm audit` in CI; no Dependabot | `.github/workflows/laravel-ui.yml` |
| 18 | Workflow runs only on `main` + two feature branches for `push` (PRs covered) — branch protection unknown | workflow `on:` |
| 19 | No health/readiness probe content (`/up` only), no Sentry/Telescope in prod | `bootstrap/app.php:18` |
| 20 | Storage on `public` disk; vouchers are receipts with financial data (should be private + signed URLs) | `filesystems.php:41` |
| 21 | `README` claims PostgreSQL/MySQL/SQLite support and “self-healing schema guard”; only SQLite is tested, and runtime DDL is a liability | `README.md` |
| 22 | Single commit history (`16c15ae`) — no reviewable history for a 20k-line codebase | `git log` |

## 5. 🟢 P3 — Nice-to-have / roadmap

- PDF generation for payroll vouchers, certificates, sanction letters (currently “print HTML”).
- Bulk attendance import (biometric/Excel), geo-fence map picker.
- Client/matter (audit engagement) module, time-sheet & billing to clients — currently only an `audit_clients` name list.
- Notification channels: email/SMS/WhatsApp (Bangladesh: SSLWireless/Twilio) alongside in-app.
- Mobile PWA / offline check-in.
- Report builder, Excel export, ICAB-format registers.
- Platform-level analytics (MRR, churn, seat utilisation).
- API for external integrations + webhooks.
- Data retention & export-on-termination policy (tenant offboarding).
- Livewire performance: `#[Lazy]`, computed properties, pagination, `wire:model.live.debounce`.
- Split the two monolith components into Services + Policies (biggest code-quality win).

---

## 6. What is genuinely good ✅

1. **Multi-tenant scoping is consistent** — every query path I traced carries `firm_id`; cross-firm access is blocked by `RequireFirmMember` + `mount()` checks + tests.
2. **Blade escaping is disciplined** — `grep "{!!"` over all 24 views returns **0 hits**. No raw-echo XSS in the Blade layer.
3. **cPanel deployment is thought through** — root `.htaccess` blocks `.env`, `.git`, `vendor`, `app/`, `storage/`, and rewrites into `public/`.
4. **Login rate limiting** is implemented twice (route throttle + `RateLimiter` 5/min per email+IP), and password reset is privacy-first (no user enumeration).
5. **Payroll lifecycle** Draft → Reviewed → Approved → Paid → **Locked** is real: locked periods are immutable, policy snapshots are stored per record, approver/paid timestamps are kept.
6. **Audit logging** exists across attendance (proxy/edit), leave, conveyance, payroll, policy, team and platform actions.
7. **86 tests / 378 assertions** and a CI workflow that builds Vite, runs PHPUnit, boots a real server and runs Playwright with screenshot artifacts.
8. **Provisioning transactionality** — org creation wraps firm + partner + policy + audit in `DB::transaction`.
9. Dark/light brand palette engine with 8 presets + custom hex and computed hover/soft/border tokens is well done.
10. `npm run build` succeeds cleanly (1,891 modules, 381 ms).

---

## 7. Recommended Roadmap

### Sprint 0 — Security hot-fix (3–5 days) 🚨 *do this first*
1. Fix `AccountActivation` (token-only, hashed, expiring, single-use).
2. Add role ceiling to `updateMember` / `deleteMember` / `createMember`.
3. Remove email-based `isPlatformAdmin()`; require email re-verification on change.
4. Fix `config/app.php` timezone → `env('APP_TIMEZONE', 'Asia/Dhaka')`.
5. Random passwords + forced first-login reset; seeder safety guard.
6. `abort_unless(Auth::check())` in every tenant component `mount()`; convert mutations to Gates/Policies.
7. Auth on `/avatar-file/`; per-owner check on `/voucher-file/`.
8. GET → POST logout.

**Deliverable:** a pen-test pass where a Student/Manager cannot escalate, and anonymous users cannot write.

### Sprint 1 — Correctness & auth hardening (1 week)
- Email delivery (SMTP/API), real invitations, password-reset E2E test.
- Current-password re-auth for email/password change; `logoutOtherDevices`; session invalidation.
- 2FA (TOTP) for Partner + Platform Admin.
- Enforce partner-unit scope on approvals and attendance edits.
- Leave balance ledger + quota enforcement.
- Payroll: month-length-aware daily rate, present-day threshold, rounding tests.
- Indexes: `(firm_id, user_id, date)`, `(firm_id, status)`, replace `LIKE` date filters with ranges.
- CSV injection guard, HTML export escaping.

### Sprint 2 — Ops & SaaS (1 week)
- Scheduler: backup, subscription expiry enforcement, payroll reminder, missed check-out auto-close.
- Queue worker (or remove the queue config), failed-jobs table, alerting.
- Error tracking (Sentry/Bugsnag) + structured logs, `LOG_LEVEL=warning`.
- Payment gateway (SSLCommerz/bKash) + invoice/receipt + dunning emails.
- Soft deletes + retention policy; “backup before delete” enforcement.
- Trusted proxies, security headers middleware, storage private + signed URLs.
- Health endpoint, uptime monitor, deploy script/rollback doc.

### Sprint 3 — Architecture & scale (1 week)
- Extract `PracticeService`, `PayrollService`, `AttendanceService`, `LeaveService` + Policies.
- Pagination + `#[Lazy]` + computed properties; drop public 70-property state.
- Remove runtime `ensureRuntimeSchemaColumns()`; migrate-driven schema only; remove `Schema::hasColumn` guards.
- Move `brand-theme-style` inline CSS into the Vite bundle (save ~128 KB/page).
- Rebuild committed `public/build`; remove orphan asset; stop committing build output OR document it clearly.
- CI: Pint, PHPStan level 6, `composer audit`, `npm audit`, MySQL service container for tests, coverage gate.
- Add the missing negative/security tests (11 cases listed in §3.10).

---

## 8. Go-Live Checklist (after Sprint 0–2)

```sh
# 1. Environment
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:...            # php artisan key:generate
APP_TIMEZONE=Asia/Dhaka       # used only after the config fix
SESSION_SECURE_COOKIE=true
SESSION_ENCRYPT=true
LOG_LEVEL=warning

# 2. Database & mail
php artisan migrate --force
php artisan erp:make-admin you@domain.com   # NOT db:seed in production
# configure real SMTP/API and send a test reset link

# 3. Build & cache
npm ci && npm run build
php artisan optimize
php artisan storage:link

# 4. Process supervision
* * * * * php artisan schedule:run
php artisan queue:work --tries=3   (supervisor/systemd)

# 5. Verify
/up returns 200
/login redirects correctly on custom domain
cross-firm access returns 403 (manual test)
timezone shows Asia/Dhaka in attendance
daily backup exists + restore drilled
```

---

## 9. Appendix — Findings Index

| ID | Severity | Area | File:Line |
|---|---|---|---|
| P0-1 | Critical | Account takeover | `Auth/AccountActivation.php:56–77, 146–200` |
| P0-2 | Critical | Privilege escalation | `Concerns/ManagesTeamAndNotifications.php:607–700, 732–770` |
| P0-3 | Critical | Privilege escalation | `Models/User.php:125`, `Concerns/ManagesPoliciesHolidaysAndSettings.php:242` |
| P0-4 | Critical | Data accuracy | `config/app.php:68`, `Concerns/ManagesAttendanceCalendarAndRadar.php:88, 1252, 1310` |
| P0-5 | High | Credentials | `DatabaseSeeder.php:19`, `ManagesTeamAndNotifications.php:502`, `DashboardPreview.php:570/886/1223` |
| P0-6 | Critical | Fail-open preview | `Middleware/RestrictPreviewInProduction.php:18`, `PracticePreview.php:303` |
| P0-7 | Critical | Data retention | `DashboardPreview.php:1590`, migrations cascade FKs |
| P1-1…10 | High | Auth/UX/mail | §3.1–§3.4 |
| P1-11…20 | High | Architecture/perf | §3.5–§3.10 |
| P2-1…22 | Medium | Hardening | §4 |

---

*Report generated from a full static review of the repository. Dynamic verification (PHPUnit, Playwright, penetration testing) must be completed on a PHP 8.4 host before launch.*
