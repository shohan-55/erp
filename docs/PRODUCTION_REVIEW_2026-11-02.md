# Production Review & Complete Hardening Report — 2 November 2026

## Summary of Fixes Applied & Verified

| # | Severity | Issue | Resolution |
|---|---|---|---|
| 1 | High | `FirmLogin::fillDemoCredentials` public Livewire action exposed staff emails when called anonymously. | Guarded behind `config('erp.allow_test_logins') \|\| config('app.demo_mode', false)`. |
| 2 | High | `$payrollRows` serialized all unit members' bKash/bank numbers and allowances to student browsers. | Scoped `$payrollRows` via `payrollRowsOwnedByViewer` so students only ever receive their own row. |
| 3 | High | `exportDisbursementSheetCsv`, `exportPayrollCsv`, `exportStudentPayrollVoucher` lacked server-side role guards. | Blocked students from `exportDisbursementSheetCsv` and scoped all payroll/voucher exports to viewer-visible rows. |
| 4 | High | `$studentRows`, `$teamMembers`, `$claimQueue`, and `$leaveQueue` Livewire state & exports needed strict viewer-level isolation. | Query-scoped `$studentRows` to `Auth::id()` for students; redacted colleague emails, phones, accounts, promotion history, and leave balances in `$teamMembers`; added server-side role/ownership guards on `exportPrintableAttendanceSheet`, `exportAttendanceCsv`, `exportConveyanceCsv`, and `exportStudentCertificate`. |
| 5 | Medium | Wrong 2FA codes did not increment the login rate limiter (`RateLimiter::hit`). | `ConfirmsTwoFactor` sets `$twoFactorCodeWasWrong = true` and `FirmLogin`, `PlatformLogin`, and `AppShell` increment `RateLimiter::hit`. |
| 6 | Medium | cPanel split layout (`~/erp-app` vs `~/public_html`) caused uploaded firm logos not to appear in the web root. | Added `ERP_WEB_ROOT` (`config('erp.web_root')`) mirror support and explicit write-failure reporting in `uploadFirmLogo()`. |
| 7 | Medium | SVG logo uploads risked stored XSS when opened directly on the same origin. | Removed `svg` from accepted logo upload MIME types and extensions (`png,jpg,jpeg,webp` only). |
| 8 | Medium | `User::ensureRuntimeSchemaColumns()` and `LeaveRequest::ensureRuntimeSchemaColumns()` ran `Schema::hasColumn` checks on web requests. | Gated all `ensureRuntimeSchemaColumns()` helpers to `local`/`testing` environments only; production relies exclusively on `php artisan migrate --force`. |
| 9 | Low | Two migration files shared the `2026_11_02_000001` prefix. | Renamed the second migration to `2026_11_02_000002_add_leave_entitlements_and_articleship_years_to_users_table.php`. |
| 10 | Low | `period` property and holiday absent deduction math (`$baseStipend / 30.0`). | Enforced strict `YYYY-MM` validation in `updatedPeriod()` and updated `calculateMandatoryHolidayAbsentDeduction()` to divide by `$this->daysInPayrollPeriod()`. |

## Deployment Checklist for Server Owner (cPanel)
1. Configure `.env`:
   - `APP_ENV=production`
   - `APP_DEBUG=false`
   - `ERP_DEMO_MODE=false`
   - `ERP_ALLOW_TEST_LOGINS=false`
   - `ERP_WEB_ROOT=/home/<cpanel-user>/public_html`
   - `ERP_REQUIRE_PLATFORM_2FA=true` (after enrolling your platform admin TOTP)
2. Run database migrations: `php artisan migrate --force`
3. Verify cron (`* * * * * php artisan schedule:run`) and off-server backup copies.
