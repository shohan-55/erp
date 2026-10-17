# RA&C ERP — WHM Dedicated cPanel Account Setup

**Target:** `erp.dhakawala.com` on a **separate reseller-created cPanel user**.
**Hosting:** existing WHM/cPanel reseller provider; no VPS.
**Release state:** PREPARATION ONLY — no real student or payroll data until staging and all go/no-go release gates pass.

## Step 0 — Check cross-account subdomain ownership first

Before creating the account, search WHM **List Accounts** for `dhakawala.com` and `erp.dhakawala.com`.

- `erp.dhakawala.com` must not already be assigned to any account or addon domain. If it exists, stop: do not remove a live domain without verifying backups and ownership.
- If `dhakawala.com` is already assigned to another cPanel account, WHM may reject separate-account creation because **cross-account subdomains are restricted**.
- Do NOT change server-global `Allow cPanel users to create subdomains across accounts` / `Allow WHM users to create subdomains across accounts` security settings to bypass this. cPanel explicitly advises against enabling these globally. Contact the upstream reseller hosting provider to assess a supported, scoped solution. If the provider cannot securely create this account, pause the separate-account plan and seek another isolated deployment approach rather than disable cross-account safeguards.

Official references:
- https://docs.cpanel.net/whm/account-functions/create-a-new-account/
- https://docs.cpanel.net/whm/server-configuration/tweak-settings/domains/

## Step 1 — Create a suitable WHM package

WHM **Packages → Add a Package**, if the reseller permission is available:

| Field | Recommended initial setting | Note |
| --- | --- | --- |
| Name | `RAC-ERP-Internal` | Private operational account, not one of the public ৳21 hosting offers. |
| Disk | ≥ 5 GB available | Initial estimate only; allow growth for receipts, audit evidence, logs and backups. |
| Monthly bandwidth | Sufficient for staff/mobile usage | Verify reseller allocation; don't overpromise. |
| Email accounts | Only if a local sender mailbox is used | External SMTP is also acceptable. |
| MySQL databases | At least one dedicated database | Prefix isolation in cPanel. |
| SSL | AutoSSL/Let's Encrypt if available | Certificate must validate live domain. |
| Cron Jobs | Enabled | Required for Laravel scheduler. |
| Terminal/SSH | Enabled **only for trusted admin** where supported | Never provide to ERP end users. |
| PHP | At least PHP 8.3; PHP 8.4 preferred | Verify both web and CLI executables. |

If WHM package creation or feature management is not available to the reseller, contact the provider and use an existing package that meets the same requirements.

## Step 2 — WHM Create a New Account

WHM **Account Functions → Create a New Account**

- Primary Domain: `erp.dhakawala.com` (not `dhakawala.com`).
- Username: `racerp` only if unused and accepted; never assume its assigned home folder until WHM confirms.
- Password: use WHM's generator and save to a secure password manager. Do not paste it into chat, GitHub, screenshots or email.
- Contact email: an address controlled by the platform operator; ensure account alerts are delivered.
- Package: `RAC-ERP-Internal` (or verified equivalent).
- Ownership: keep under the authorized reseller account; do not enable resale privileges for this ERP user.
- DNS settings: do **not** overwrite existing `dhakawala.com` DNS records or change nameserver delegation. Do not select a setting that overwrites an existing conflicting DNS zone without verifying the existing authoritative parent zone.
- Create the account only if WHM accepts the domain without security exceptions.

After creation capture **only** the account username, primary domain, hosting IPv4, feature list and package limits. Hide credentials/session links in screenshots.

## Step 3 — DNS in the authoritative dhakawala.com zone

The registered domain already uses external child nameservers `ns1.dhakawala.com` and `ns2.dhakawala.com` according to the project setup; confirm current authoritative DNS before changing anything.

- Add/update `erp.dhakawala.com` **A** record pointing to the new cPanel account's actual hosting IPv4, **in the authoritative parent `dhakawala.com` DNS zone**.
- If the new cPanel IP equals the main reseller server IP, use that verified IP. Do **not** guess an IP.
- Do not create a conflicting A/CNAME/AAAA combination. If an `AAAA` exists, validate it actually serves the site or remove only with DNS-owner approval.
- A separate `erp.dhakawala.com` DNS zone, if WHM creates one, is not automatically authoritative unless the parent DNS delegates to it; an A record in the parent zone is typically simpler.
- Do not change `dhakawala.com` registrar nameservers or its `www`/mail records for this deployment.

Wait for DNS validation before relying on AutoSSL.

## Step 4 — Verify the new cPanel account

Open the account via WHM **List Accounts → cPanel**. Verify:

1. cPanel **Domains** lists `erp.dhakawala.com` as the account's main domain; the webroot is expected to be `/home/<VERIFIED_USERNAME>/public_html`.
2. **MultiPHP Manager / Select PHP Version** shows PHP 8.3+, preferably PHP 8.4. PHP web version and CLI PHP version must be checked separately.
3. **Cron Jobs** is available; note minimum interval. No cron should be configured before the app and its paths exist.
4. **MySQL Databases** allows a new ERP-only database and least-privilege user.
5. **Terminal/SSH** access is available to the trusted deploy operator, or the hosting provider can run preflight commands.
6. **SSL/TLS Status / AutoSSL** certificate is valid for `erp.dhakawala.com`.
7. `public_html` must contain *only* ERP public assets. Do not put `.env`, `vendor/`, `artisan`, `storage/`, `routes/` or database exports there.

## Step 5 — Upload the reviewed artifacts (ONLY AFTER account checks)

Release artifact source: the latest fully successful GitHub Actions run for the approved immutable commit.

The GitHub Actions artifact **erp-cpanel-release-review** contains:
- `erp-cpanel-application.zip`
- `erp-cpanel-split-webroot.zip`
- `SHA256SUMS.txt`

For a **dedicated ERP cPanel account with fixed main domain `public_html`**, the final private/public layout is:

```
/home/<VERIFIED_USERNAME>/erp-app/          <- extract erp-cpanel-application.zip
/home/<VERIFIED_USERNAME>/public_html/       <- copy ONLY contents of erp-webroot/
```

The public `index.php` in the split-webroot ZIP expects the private Laravel app in a sibling folder called `erp-app`. Do not rename it without correcting, reviewing and testing the bootstrap references. Don't upload the outer `erp-webroot` folder itself as an extra nested directory.

Before extraction, verify SHA256 hashes locally or on the cPanel account. Do not upload the composite GitHub Actions artifact ZIP directly into `public_html`.

The package intentionally excludes `.env`, databases and QA users. Set secrets only in the protected private app folder using the account's secure file manager/SSH and administrator access. See `docs/CPANEL_RESELLER_DEPLOYMENT.md` for safe example environment variables and cron.

## Step 6 — Verify staging, not production payroll

With CLI PHP in the private `erp-app` folder:

```bash
php -v
php artisan erp:hosting:check
php artisan migrate:status
php artisan erp:release:check
php artisan schedule:list
```

Do not run `migrate --force` on a live/valuable database until a restore drill has been completed and migration approval is given. Only run with the correct stage-specific `.env`.

The production security preflight intentionally fails unless HTTPS, secure cookies, production mailer, database and MFA guards are configured.

## Step 7 — No-go requirements

- Email/password recovery/invitations delivered from real SMTP/provider, including account enumeration safety.
- Actual Partner MFA/recovery tested and user authorization scopes signed off.
- Multiple cPanel sites cannot read each other's private `.env` and documents.
- Encrypted offsite database **and private-file** backup **restored successfully** to independent staging.
- Payroll approval and external disbursement reconciliation verified by an authorized RA&C approver.
- Full staged 3-partner UAT, immutable release SHA and rollback evidence.

### What to send in chat to continue

Send a redacted screenshot of WHM **Create a New Account** after filling `erp.dhakawala.com` and choosing the package (hide password and contact details), or the WHM error if creation is blocked.

Do not send cPanel cookies, passwords, secret keys, live server database information or account login URLs.
