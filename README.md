# DhakaWala ERP SaaS — Multi-Tenant CA Practice & Corporate Management Platform

An enterprise, ultra-premium Laravel 13 + Livewire 4 multi-tenant ERP platform tailored for Chartered Accountancy (CA) firms and corporate practices in Bangladesh.

> **AI & Developer Guide:** Always read [`BLUEPRINT.md`](./BLUEPRINT.md) before modifying any file. The codebase uses a strict modular Trait + Blade Partial architecture so features remain isolated and regression-free.

## Technology Stack

- **Framework:** Laravel 13 · PHP 8.4
- **Reactive Frontend:** Livewire 4 · Alpine.js · Blade · Tailwind CSS 4 · Vite 8 · Lucide Icons
- **Database:** PostgreSQL / MySQL / SQLite (with self-healing runtime schema guard `Firm::ensureRuntimeSchemaColumns()`)

## Core Production Modules

| Module | Key Production Capabilities |
|---|---|
| **DhakaWala Super Admin Hub** | Organization provisioning, 1-click Free Trial (`7d`/`14d`/`30d`) & Seat-Based Paid (`Seats × Per-Seat Rate`) billing engine, live Brand Color Palette selector (`Sea Green` default + 8 presets + custom HEX), Company & Partner Email/Name/Unit/Password control, Super Admin Firm Policy override, and full JSON tenant backup |
| **Top-Deck Live Attendance Command Dock** | Placed at the very top of Manager/Student/Staff dashboards (hidden on Partner dashboard); inline `Office HQ` vs `Client Site` selector with direct Client Name input & auto-suggestions; real-time `Asia/Dhaka` policy-driven color shift (`Emerald Green` Right Time → `Amber Yellow` Grace → `Soft/Dark Crimson Red` Late Present); check-in celebration animation; multi-leg `On-Duty Move` vs `Close Duty` timeline |
| **Interactive Monthly Calendar & Field Radar** | Full current-month calendar grid showing daily Right Time/Late/Late Present counts, Holidays (`Office Open` overrides), Leaves, and Conveyance; clickable Daily Activity Inspector; Live Field Radar (`Who's Where Right Now`) |
| **Unread Approval Inbox (Leave & Conveyance)** | Real-time unread badges on Sidebar (`● X New`), Top Header quick-jump pills (`📩 Leave` / `🧾 Claim`), and a dedicated **Unread Approval Inbox** on Manager & Partner dashboards with 1-click Approve, Partial Approve, Adjust (±), Forward, or Reject actions |
| **Multi-Tier Leave & Conveyance Workflows** | Student leave application mentioning engaged Manager, partial day approvals, multi-leg transport builder (`From → To → Mode → Fare`), optional voucher upload, and automatic escalation for late/excess claims |
| **Dynamic Monthly Allowance & Payroll** | Automatic calculation of Base Allowance + Approved Conveyance − Late Penalty Deductions (`e.g. 3 Late Days = 1 Day Cut`) − Mandatory Open-Holiday Absence Cuts, with printable Payment Vouchers and CSV exports |
| **Multi-Role Policy & Governance** | Super Admin, Partner, and Manager policy controls (`Office Start`, `Office End`, `Grace Minutes`, `Late Penalty Rule`, `Manager Conveyance Limit`, `Custom Leave Quotas`, and `Bangladesh Govt. Holidays`) |

## Production Deployment Checklist

> **This project deploys to a split cPanel layout** — the app lives in `~/erp-app`,
> the web root is a separate copy in `~/public_html`. Read
> [`docs/AI_HANDOVER.md`](docs/AI_HANDOVER.md) §1 before deploying; skipping the asset
> sync leaves the site unstyled.

```sh
composer install --optimize-autoloader --no-dev
cp .env.example .env
php artisan key:generate
php artisan migrate --force
php artisan optimize:clear
```

`public/build` is committed, so the server does **not** need Node.js — but if you
compiled different assets locally, run `npm ci && npm run build` and commit the result.
After `git pull` on the host, copy `public/build`, `public/images` and `public/css` into
the web root (one line in `docs/AI_HANDOVER.md` §1).

`php artisan storage:link` is **no longer part of the deployment**: conveyance receipts
and avatars are stored on the private disk and served through authorised routes. Running
`storage:link` only re-exposes legacy `storage/app/public` files.
