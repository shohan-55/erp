# Running the test suite without CI

GitHub Actions minutes for this repository are exhausted, so the suite has to be
runnable (and trustworthy) outside CI. It is:

```bash
composer install              # once
cp .env.example .env          # if you have no .env yet
php artisan key:generate      # only when APP_KEY is empty
composer test                 # = php artisan config:clear && php artisan test
```

`phpunit.xml` already pins everything the suite needs (in-memory SQLite, array
cache/session/mail, `BCRYPT_ROUNDS=4`), so no database server or `.env` tweaking
is required.

Expected output:

```
Tests: 139, Assertions: 563
OK
```

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
| `php artisan test` (full suite) | **139 tests, 563 assertions — OK** |
| MySQL/PostgreSQL | not exercised: the suite runs on SQLite `:memory:` |
| `php artisan migrate` (fresh database, all 17 migrations) | OK |
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

## If CI minutes come back

`.github/workflows/laravel-ui.yml` is already wired for `pull_request` pushes to
`main`/`feature/**`/`fix/**`/`arena/**`, runs `php artisan test
--stop-on-failure`, and keeps `composer audit` / `npm audit` as non-blocking
steps. Nothing else needs changing — the suite it runs is the one above.
