# Dedicated cPanel ERP Database Setup — Secret-free Checklist

## Status

The dedicated ERP cPanel account and its database/database user are created. PHP 8.4 web/CLI was reviewed. No real database connectivity or Laravel migration has yet been executed.

**Security block:** The initial DB password was visible in a screenshot. Treat that password as compromised. Do not use it to connect, deploy or initialize Laravel. Rotate the DB user password privately in cPanel BEFORE placing any DB password in the private ERP configuration. Never paste real passwords into chats, tickets, terminal commands, screenshots or GitHub.

## Preparatory work while password is not rotated

- Verify DB name and DB user are linked in cPanel Manage My Databases, using only metadata (do not show password).
- Check the separate ERP account, PHP 8.4, Cron, SSL and intended private application folder layout.
- Build/review GitHub deployment release ZIPs, cPanel integration tests and backup/restore procedure.
- Do NOT run migrations, deploy real student data or bootstrap production users.

## After password rotation, on a verified private app folder

Configure an ERP-specific .env in /home/<ACTUAL_CPANEL_USER>/erp-app/ using a new unique secret from a password manager. Set:

APP_ENV=production
APP_DEBUG=false
APP_URL=https://erp.dhakawala.com
DB_CONNECTION=mysql
DB_HOST=localhost  # verify actual cPanel MySQL host; it can differ
DB_DATABASE=<DATABASE_NAME_FROM_CPANEL>
DB_USERNAME=<ERP_DATABASE_USER>
DB_PASSWORD=<ROTATED_SECRET_SET_PRIVATELY>
SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
SESSION_ENCRYPT=true
QUEUE_CONNECTION=sync

The APP_KEY must be created only once for a brand-new blank instance and stored securely. Never regenerate it after encrypting data or enrolling MFA.

Next read-only checks: php artisan config:clear ; php artisan erp:db:check. On an empty database the command should show a reachable connection but missing ERP schema. It does not print server exception details or credentials.

Only after staging, backup/restore, and explicit migration approval: php artisan migrate --force ; php artisan erp:db:check ; php artisan erp:hosting:check ; php artisan erp:release:check.

An ERP-ready schema does NOT prove that authentication, SMTP, TLS, MFA recovery, payroll policy approval, remote backups or security controls are production verified.

Do not use the main DhakaWala hosting business database, or reuse a DB user for another application.

## Operational boundary

This is preparation only. No actual cPanel DB login, password rotation, migration or production deployment was performed through GitHub.
