# Running the test suite without CI

Run the suite locally with the following commands. CI also runs the SQLite suite and a MySQL 8 service job; a green local SQLite run is not a replacement for the MySQL rehearsal. It is:

```bash
composer install              # once
cp .env.example .env          # if you have no .env yet
php artisan key:generate      # only when APP_KEY is empty
composer test                 # = php artisan config:clear && php artisan test
```

`phpunit.xml` already pins everything the suite needs (in-memory SQLite, array
cache/session/mail, `BCRYPT_ROUNDS=4`), so no database server or `.env` tweaking
is required.

The exact test/assertion count changes as regressions are added; the expected result is an `OK` full-suite summary with zero failures.

The suite takes ~20 s on a normal laptop (and ~1 min on a slow cPanel box).

---

## What was actually verified, and how

The 2026-10-30 hardening pass was developed in a sandbox without a PHP binary.
To avoid shipping "green by inspection", the suite was executed there anyway
against a **real PHP 8.4.25 runtime** (a 64-bit WebAssembly build with
`mbstring`, `openssl`, `pdo_sqlite`, `dom`, `fileinfo`, `tokenizer`, `zip`),
with `vendor/` reconstructed from `composer.lock` (every locked package is an
`api.github.com` zipball, and the lock records its own autoload rules).

Results:

| Check | Result |
|---|---|
| `php artisan test` (full suite) | **152 tests, 590 assertions — OK** |
| MySQL/PostgreSQL | not exercised: the suite runs on SQLite `:memory:` |
| `php artisan migrate` (fresh database, all 19 migrations) | OK |
| `php artisan optimize` / `route:cache` / `config:cache` | route + config + view cache all build |
| `php artisan view:cache` (compiles every Blade file) | OK |
| `npm ci && npm run build` (Vite) | OK — assets in `public/build` |
| Playwright browser specs (`tests/browser/*.mjs`) | **not run** — no browser in the sandbox |
| PHP 8.3 / 8.2 | not tested; `composer.json` requires `^8.3` and the code targets 8.4 |

Six failures surfaced on that first run. Four were real application bugs
(check-out persistence, the payroll ledger header, reopening a locked period,
guest redirects into tenant workspaces) and two were stale test expectations;
all are fixed in `830672e` and pinned by the suite.

## Before a release you still want a human to do this

1. `composer test` on the deployment machine (same PHP version as production).
2. Apply the migrations against a **copy** of the production database, not just
   an empty one.
3. Run `tests/browser/*.mjs` with `ERP_DEMO_MODE=true` (see that folder's README)
   if the UI changed.
4. Click through one real sign-in, one check-in/check-out and one payroll
   period on staging.

## CI

`.github/workflows/laravel-ui.yml` runs the SQLite feature/unit suite and Vite build, blocking Composer/npm production audits, browser checks, and a separate MySQL 8 feature/unit test job. In this remediation branch, native PHP/Composer are absent. A temporary PHP-WASM run passed PHP lint, fresh SQLite migrations, Blade view caching, the Unit suite (1 test), and one non-database feature test; DB-backed PHPUnit tests crash the WASM runtime and are not counted as passed. Treat CI/staging on the production PHP runtime as required before release.
