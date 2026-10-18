#!/usr/bin/env bash
set -Eeuo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DIST="$ROOT/dist"
TEMP="$(mktemp -d)"
trap 'rm -rf "$TEMP"' EXIT

test -s "$DIST/erp-cpanel-application.zip"
test -s "$DIST/erp-cpanel-split-webroot.zip"
test -s "$DIST/SHA256SUMS.txt"
test -s "$DIST/release-manifest.json"
# Artifact claims must match actual content and the immutable CI checkout.
# Even a perfectly matching manifest never authorizes live deployment.
python3 - "$DIST" <<'PY'
import hashlib, json, os, pathlib, sys
root=pathlib.Path(sys.argv[1])
data=json.loads((root/'release-manifest.json').read_text())
assert data['project']=='DhakaWala ERP'
assert data['deployment_authorized'] is False
sha=os.environ.get('GITHUB_SHA')
if sha:
    assert data['source_commit_sha']==sha, 'SHA does not match reviewed checkout'
for name in ('erp-cpanel-application.zip','erp-cpanel-split-webroot.zip'):
    assert data['artifacts_sha256'][name]==hashlib.sha256((root/name).read_bytes()).hexdigest(), 'Archive manifest mismatch'
print('PASS: exact SHA / archive-manifest integrity (not production authorization)')
PY

# Portable manifests must contain relative archive names only; no CI runner paths.
if grep -Eq '(/home/runner/|/mnt/|/tmp/)' "$DIST/SHA256SUMS.txt"; then
  echo "SECURITY FAILURE: checksum manifest contains build-machine paths" >&2
  exit 1
fi
test "$(wc -l < "$DIST/SHA256SUMS.txt")" -eq 2

(
  cd "$DIST"
  sha256sum -c SHA256SUMS.txt
)

# Unpack both archives into the same private account home, simulating the
# exact dedicated-cPanel layout with a fixed public_html document root.
mkdir -p "$TEMP/home"
unzip -q "$DIST/erp-cpanel-application.zip" -d "$TEMP/home"
unzip -q "$DIST/erp-cpanel-split-webroot.zip" -d "$TEMP/home"
mv "$TEMP/home/erp-webroot" "$TEMP/home/public_html"

APP="$TEMP/home/erp-app"
WEB="$TEMP/home/public_html"

test -f "$APP/artisan"
test -f "$APP/vendor/autoload.php"
test -f "$APP/bootstrap/app.php"
test -f "$APP/public/build/manifest.json"
test -f "$WEB/build/manifest.json"
test -f "$WEB/index.php"
test -f "$WEB/.htaccess"

# The public front controller must reference files located outside
# document root, in a sibling /home/<user>/erp-app folder.
grep -Fq "__DIR__.'/../erp-app/vendor/autoload.php'" "$WEB/index.php"
grep -Fq "__DIR__.'/../erp-app/bootstrap/app.php'" "$WEB/index.php"
grep -Fq "__DIR__.'/../erp-app/storage/framework/maintenance.php'" "$WEB/index.php"
php -l "$WEB/index.php"

for forbidden in app artisan bootstrap config database resources routes storage vendor node_modules tests .git .env .env.production composer.json; do
  if test -e "$WEB/$forbidden"; then
    echo "SECURITY FAILURE: private path exists in web root: $forbidden" >&2
    exit 1
  fi
done

for forbidden in .env database/database.sqlite database/seeders database/factories node_modules tests .git; do
  if test -e "$APP/$forbidden"; then
    echo "SECURITY FAILURE: disallowed deploy item exists in private archive: $forbidden" >&2
    exit 1
  fi
done

# No accidental .env file anywhere in either archive.
if unzip -Z1 "$DIST/erp-cpanel-application.zip" | grep -E '(^|/)\.env$'; then
  echo "SECURITY FAILURE: .env present in application ZIP" >&2
  exit 1
fi
if unzip -Z1 "$DIST/erp-cpanel-split-webroot.zip" | grep -E '(^|/)\.env$'; then
  echo "SECURITY FAILURE: .env present in webroot ZIP" >&2
  exit 1
fi

echo "PASS: dedicated WHM cPanel release ZIP integrity, split webroot, Laravel bootstrap, PHP syntax, and private file exclusion."
