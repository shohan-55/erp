#!/usr/bin/env bash
set -Eeuo pipefail

# Build only from a CLEAN CI checkout. Never use the browser QA workspace
# after fake users have been seeded into an SQLite database.
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DIST="$ROOT/dist"
APP="$DIST/erp-app"
WEB="$DIST/erp-webroot"
rm -rf "$DIST"
mkdir -p "$APP" "$WEB"

test -f "$ROOT/vendor/autoload.php"
test -f "$ROOT/public/build/manifest.json"
test ! -f "$ROOT/.env"

cp -a "$ROOT/app" "$ROOT/bootstrap" "$ROOT/config" "$ROOT/database" \
      "$ROOT/public" "$ROOT/resources" "$ROOT/routes" "$ROOT/vendor" "$APP/"
cp "$ROOT/artisan" "$ROOT/composer.json" "$ROOT/composer.lock" "$APP/"

# Never ship local databases, synthetic QA seeding code, tests, screenshots,
# or environment material as part of the release payload.
rm -rf "$APP/database/factories" "$APP/database/seeders"
rm -f "$APP/database/database.sqlite"
rm -rf "$APP/public/screenshots"
rm -rf "$APP/bootstrap/cache"
mkdir -p "$APP/bootstrap/cache" \
         "$APP/storage/app/private" "$APP/storage/app/public" \
         "$APP/storage/framework/cache/data" "$APP/storage/framework/sessions" \
         "$APP/storage/framework/testing" "$APP/storage/framework/views" \
         "$APP/storage/logs"

# Both layouts are published because some cPanel primary domains have a
# fixed public_html document root; NEVER place the private ERP app there.
cp -a "$APP/public/." "$WEB/"
python3 - "$WEB/index.php" <<'PY'
import pathlib, sys
p=pathlib.Path(sys.argv[1])
s=p.read_text()
links={
    "__DIR__.'/../storage/framework/maintenance.php'": "__DIR__.'/../erp-app/storage/framework/maintenance.php'",
    "__DIR__.'/../vendor/autoload.php'": "__DIR__.'/../erp-app/vendor/autoload.php'",
    "__DIR__.'/../bootstrap/app.php'": "__DIR__.'/../erp-app/bootstrap/app.php'",
}
for old,new in links.items():
    if s.count(old)!=1:
        raise SystemExit(f"Unexpected Laravel index.php layout: {old}")
    s=s.replace(old,new)
p.write_text(s)
PY

for forbidden in "$APP/.env" "$APP/database/database.sqlite" \
                 "$APP/database/seeders" "$APP/tests" "$APP/node_modules" \
                 "$APP/.git" "$APP/public/screenshots"; do
    test ! -e "$forbidden" || { echo "Disallowed release file: $forbidden" >&2; exit 1; }
done

test -f "$APP/public/build/manifest.json"
test -f "$WEB/build/manifest.json"
test -f "$APP/vendor/autoload.php"
test -f "$WEB/.htaccess"
test -f "$WEB/index.php"

(
    cd "$DIST"
    zip -q -r -9 erp-cpanel-application.zip erp-app
    zip -q -r -9 erp-cpanel-split-webroot.zip erp-webroot
)

( cd "$DIST" && sha256sum erp-cpanel-*.zip > SHA256SUMS.txt )
echo "Release package created: no environment file, personal data, QA seeders or database."
echo "Do not deploy without target-account verification and signed release approval."
