# Launch Hardening & Go-Live Declaration — 30 October 2026

Companion to [`PRODUCTION_READINESS_AUDIT_2026-10-30.md`](PRODUCTION_READINESS_AUDIT_2026-10-30.md)
and [`SECURITY_FIXES_2026-10-30.md`](SECURITY_FIXES_2026-10-30.md).

This is the document I sign off against. It says three things: what was hardened in this
round, exactly what was verified and how, and **what is still unverified** — because a
readiness declaration that hides its caveats is worth nothing.

---

## 1. What this round fixed

Two of the six pre-launch blockers I raised for the 10–15 firm launch were code problems.
Both are now closed, with regression tests.

### 1.1 The test-login bypass (the serious one)

**Was.** `FirmLogin`, `PlatformLogin` and `AppShell` each contained a fallback: if the
password check failed, *and* `app()->environment('testing')`, they signed you in as a demo
persona (`partner@dhakawala.com`, `manager@dhakawala.com`, …) with **any** password. The
gate was `APP_ENV`. One stray `APP_ENV=testing` in a production `.env` — a routine
copy-paste or cPanel mistake — turns the sign-in form into an open door where any visitor
becomes a Partner and sees every client, payroll and audit record of that firm.

**Now.** Authority comes from an explicit flag, `config('erp.allow_test_logins')`
(`config/erp.php`), default **false**, read from `ERP_ALLOW_TEST_LOGINS`. Only
`phpunit.xml` sets it true. `.env.example` documents it as false with a "never enable in
production" note.

- `app/Livewire/Auth/FirmLogin.php`
- `app/Livewire/Auth/PlatformLogin.php`
- `app/Livewire/AppShell.php`

Tests: `test_the_flag_defaults_to_off_when_nobody_sets_it`,
`test_demo_persona_cannot_sign_in_without_the_flag`,
`test_platform_demo_operator_cannot_sign_in_without_the_flag`,
`test_a_real_account_still_signs_in_normally` (the last one proves the fix did **not**
break ordinary sign-in).

### 1.2 Receipts and avatars were world-readable

**Was.** Conveyance vouchers (`storage/app/public/conveyance-receipts/…`) and profile
avatars (`storage/app/public/avatars/…`) were written to the **public** disk. A standard
`php artisan storage:link` on a cPanel host publishes that directory at
`/storage/...` with no authentication. The only protection was an unguessable filename —
and the route that served them re-checked nothing except `file_exists()`.

**Now.**

- Both uploads write to the **private** disk (`storeAs(..., 'local')`) —
  `ManagesConveyanceWorkflow.php`, `ManagesPoliciesHolidaysAndSettings.php`.
- `app/Support/FirmFile.php` resolves a file from `storage/app/private/…` first, then the
  **legacy** `storage/app/public/…` path, so old history keeps working. It sanitises the
  firm slug and applies `basename()` to the filename, so `../` cannot walk out.
- Both routes in `routes/web.php` (`/c/{firmSlug}/voucher-file/{filename}`,
  `/c/{firmSlug}/avatar-file/{filename}`) now use `FirmFile::locate()`. They remain behind
  `auth` + `firm.member`, so a firm slug from another tenant is a 403 before any lookup.
- `php artisan erp:privatise-uploads [--dry-run]` moves existing files from the public disk
  to the private one, so nobody has to re-upload receipts. `--dry-run` prints the plan
  without touching anything.

Tests: `test_voucher_route_rejects_guests`, `test_voucher_route_rejects_another_firms_member`,
`test_voucher_is_served_from_the_private_disk`, `test_legacy_public_voucher_still_served`,
`test_firm_file_rejects_path_traversal`, plus the migration command with and without
`--dry-run`.

---

## 2. What was verified, and how

CI is out of minutes, so **everything below was run locally** in this sandbox on
PHP 8.4.25 (`php-wasm`) against the real `vendor/` tree. The procedure is reproducible —
see [`RUNNING_TESTS_WITHOUT_CI.md`](RUNNING_TESTS_WITHOUT_CI.md).

| Check | Command | Result |
|---|---|---|
| Full test suite | `phpunit tests` | ✅ **OK (152 tests, 590 assertions)** |
| New guard tests only | `phpunit tests/Feature/FileAndLoginGuardTest.php` | ✅ OK (13 tests, 27 assertions) |
| Syntax of every changed PHP file | `token_get_all(…, TOKEN_PARSE)` | ✅ 10/10 OK |
| Whole app lint | `token_get_all` over `app/ config/ routes/` | ✅ 121/121 OK |
| Migrations from empty DB | `php artisan migrate:fresh --seed --force` | ✅ 19 migrations + seed |
| Route cache | `php artisan route:cache` | ✅ cached |
| Config cache | `php artisan config:cache` | ✅ cached |
| Blade compile | `php artisan view:cache` | ✅ compiled |

Note on the test suite: it runs on SQLite `:memory:` and every test re-runs the whole
migration set, so a broken migration fails the suite before it can reach production.
`migrate:fresh --seed` above was additionally executed against a **file-backed** SQLite
database to prove the seeder path end-to-end.

### 2.1 One harness quirk worth recording (not an app bug)

Laravel's `SessionGuard::attempt()` wraps the credential check in a `Timebox` that
**sleeps 200 ms** when the attempt fails (a brute-force speed bump). The wasm runtime used
for the no-CI harness aborts inside that sleep path (`RuntimeError: unreachable`), which
initially looked like the demo-persona tests were failing. Bisecting with step markers
proved the app code was never reached — the crash is in `Timebox → Sleep::usleep()`.

The two tests that deliberately exercise a **failed** login now call `Sleep::fake()`, which
is the framework's own idiom for this and is reset by Laravel after every test. The
assertions are unchanged: the demo persona still must not be let in.

---

## 3. The declaration

**From my side, this codebase is production-ready for the launch it was built for: a
single-operator deployment serving the first 10–15 chartered-accountancy firms
(≈225 users) on DhakaWala's own hosting.**

That statement covers **code**. It does **not** mean "launched". Six things on my checklist
are environment and process, not code, and five of them must be done by whoever holds the
server and the domain — none of them are optional.

### Still open before the first firm signs in (owner actions)

1. **MySQL rehearsal.** Everything above ran on SQLite. Run
   `php artisan migrate:fresh --seed --force` against the real MySQL/MariaDB instance on
   the cPanel account before pointing users at it. This is the single largest untested
   surface; the schema uses indexes and generated columns that SQLite tolerates more
   loosely than MySQL does.
2. **Backup + restore drill.** Turn on cPanel's scheduled backups *and* restore one archive
   into a scratch database once. An untested backup is a hope, not a backup. (The
   destructive-delete guards from the P0 round assume this exists.)
3. **Real SMTP.** Invitations and password resets go through the configured mailer. Send a
   test invitation and a test reset from the production host to a Gmail address and confirm
   both arrive (deliverability, SPF/DKIM on the domain).
4. **Cron.** `* * * * * cd /path/to/erp && php artisan schedule:run >> /dev/null 2>&1` plus
   `php artisan queue:work` (or `queue:listen`) under a supervisor. Payroll reminders,
   probation alerts and the audit-log pruning all depend on the scheduler.
5. **Platform-admin second factor.** `php artisan erp:make-admin <email>` then enrol TOTP on
   the platform admin account — the 2FA path exists but must be *used* by every operator.
6. **Files migration.** After deploying this branch, run
   `php artisan erp:privatise-uploads --dry-run`, check the list, then run it for real, and
   remove the now-unnecessary public exposure of `storage/app/public` (delete the
   `public/storage` symlink once the migration reports zero files left). Old links keep
   working because `FirmFile` falls back to the legacy path.

### What "production-ready" does **not** cover

- **No billing / payment collection.** Subscriptions and trial columns exist; there is no
  gateway. Firms are onboarded by hand. Do not advertise self-serve signup.
- **Other verticals are settings, not features.** The Company and Garments modules are not
  built to the depth of the CA-practice one. Sell the CA-practice product; the verticals
  are a later release.
- **Browser end-to-end specs were not run.** There is no Playwright/Cypress suite in this
  repo and no CI budget to add one. Verification is server-side (feature tests + manual
  smoke test of the sign-in, attendance, payroll and voucher screens).
- **Load was not tested.** 225 concurrent-ish users on one cPanel host is a plausible
  target, not a measured one. Watch the first week.
- **PHP 8.4 was the test runtime.** Confirm the host runs PHP 8.3 or newer (`composer.json` requires `^8.3`);
  a 7.4 or 8.0 host will not boot this code.

---

## 4. Rollback

Every change in this branch is additive or reversible:

- **Code:** revert the merge commit and re-deploy. Note that the *old* code only read
  `storage/app/public/`, so any receipt uploaded after this branch went live would become
  unreachable. Before reverting, copy `storage/app/private/conveyance-receipts/*` and
  `storage/app/private/avatars/*` back into `storage/app/public/`.
- The `erp:privatise-uploads` command moves files; it does not copy, so keep a backup of
  `storage/app/public/conveyance-receipts` and `storage/app/public/avatars` until you are
  satisfied the new routes serve everything.
- Database: this round adds **no migrations**. Nothing to roll back.

---

*Written after a full local suite run of 152 tests / 590 assertions on 30 October 2026.*
