# DhakaWala ERP SaaS — Multi-Tenant CA Practice Management Platform

An enterprise Laravel + Livewire multi-tenant ERP platform tailored for Chartered Accountancy (CA) firms in Bangladesh (such as **Rahman Anis & Co.**).

## Technology Stack

Laravel 13 · PHP 8.4 · Livewire 4 · Blade · Tailwind CSS 4 · Vite 8 · Lucide Icons.

## Core Production Modules

| Module | Capabilities |
|---|---|
| **Multi-Tenant Platform Hub** | Super Admin organization provisioning, custom domain/subdomain routing, full JSON tenant backup & transactional deletion with `RequireRole` & server-side authorization guards |
| **Role & Tenant Security** | Middleware-protected production routes (`auth`, `firm.member`, `role`, `production.preview`), Livewire hydration role-locking against client-side role spoofing, and strict `firm_id` query isolation |
| **Office & Client Geo-Attendance** | Live GPS check-in/out, dynamic Partner grace-minute lateness calculation, database-persisted Manager/Partner Proxy & Attendance Edits, and CSV export |
| **2-Tier Leave Management** | Student leave application (`Annual`, `Sick`, `Emergency`, `ICAB Exam`, `ICAB Study`), Manager verification & recommendation, and Partner final sanction |
| **Conveyance Claims & Vouchers** | Audit client tagging, supporting voucher/receipt file upload (`JPG`, `PNG`, `PDF`), Manager limit enforcement, automatic escalation for excess or late claims, and CSV export |
| **Monthly Allowance & Payroll** | Dynamic student stipend + approved conveyance reconciliation, policy snapshot capture, full `Draft → Reviewed → Approved → Paid → Locked` lifecycle, and CSV export |
| **Governance, Notifications & Audit Trail** | Independent Partner Unit policies, Multi-Partner consensus policy proposals & voting, database-backed in-app notifications, and immutable `audit_logs` |

## Production & Preview Routes

| Surface | Route | Protection |
|---|---|---|
| Unified Portal & Login | `/` | Public entry (redirects authenticated users) |
| Platform Super Admin Login | `/platform/login` | Guest / Throttled (`20,1`) |
| Platform Admin Hub (Production) | `/platform/dashboard` | `auth`, `role:platform_admin` |
| Firm Member Login | `/c/{firmSlug}/login` | Guest / Throttled (`20,1`) |
| Account Invitation Activation | `/c/{firmSlug}/activate?token=...` | Token-verified onboarding |
| Firm Practice Workspace (Production) | `/c/{firmSlug}/dashboard/{section?}` | `auth`, `firm.member` |
| UI Design Preview Routes | `/c/{firmSlug}/preview/{previewRole?}/{section?}` | Restricted in production via `production.preview` middleware |

## Local Setup & Deployment

~~~sh
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
npm ci
npm run build
php artisan serve
~~~
