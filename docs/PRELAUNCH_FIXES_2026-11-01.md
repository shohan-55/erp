# Pre-launch fixes — 1 November 2026

Result of an independent static review of the 30 Oct snapshot. **No PHP runtime was available for
this pass, so none of these changes (or the new tests) have been executed.** Run `php artisan test`
before deploying.

## Fixed
| # | Issue | Change |
|---|---|---|
| 1 | **Cross-tenant access.** `PracticePreview::$firmSlug` was public and unlocked, so a Partner of firm A could repoint the component at firm B. `hydrate()` locked the role but not the tenant. | `#[Locked]` on `$firmSlug`; new `assertWorkspaceAccess()` runs in `mount()` and `hydrate()` (every Livewire request) and 403s unless the user belongs to that firm or is a platform admin. |
| 2 | Preview surface opened on `APP_ENV=local/testing`. | `RestrictPreviewInProduction` no longer looks at `APP_ENV`; only `ERP_DEMO_MODE=true` (or the test runner) opens it. `PracticePreview` and `DashboardPreview` also refuse anonymous callers outside demo mode. |
| 3 | `.env.example` shipped `PLATFORM_ADMIN_PASSWORD="password"` and a personal email. | Password blank (seeder then generates a random one-time password); personal default removed from `.env.example` and the seeder. |
| 4 | No CI in the repo although docs referenced it. | `.github/workflows/ci.yml`: test suite, `composer audit`, and `migrate:fresh --seed` on real MySQL 8. |

Regression tests: `tests/Feature/TenantLockdownTest.php`.

## Not done (needs real execution or is a larger project)
* Test suite, MySQL rehearsal, load test — run via the new CI.
* ~112 empty `catch (\Throwable) {}` blocks and ~229 runtime `Schema::has*` probes — a blind mass
  edit is riskier than the problem; do it with the suite running.
* CSP still allows `unsafe-inline`/`unsafe-eval` (Livewire/Alpine requirement).
* Policies, splitting the 2k-line components, pagination, billing/payment gateway.
* Environment: SMTP, cron + queue worker, backup restore drill, admin 2FA enrolment.
