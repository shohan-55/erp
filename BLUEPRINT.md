# DhakaWala ERP — Multi-Tenant Enterprise Architecture Blueprint

## 1. System Overview
**DhakaWala ERP** is a multi-tenant cloud ERP platform built for Chartered Accountancy (CA) practices, audit and assurance firms, consultancies, NGOs, and corporate organizations (**Laravel 13 + Livewire 4 + Alpine.js + Tailwind CSS v4**).

## 2. Core Modules & File Routing Map
| Module | Backend Trait / Class | Frontend Blade View |
| :--- | :--- | :--- |
| **Platform Super Admin Console** | `app/Livewire/Platform/DashboardPreview.php` | `resources/views/livewire/platform/dashboard-preview.blade.php` |
| **Practice Core Orchestrator** | `app/Livewire/Practice/PracticePreview.php` | `resources/views/livewire/practice/preview.blade.php` |
| **1. Dashboard, Live Radar & Monthly Calendar** | `app/Livewire/Practice/Concerns/ManagesAttendanceCalendarAndRadar.php` | `resources/views/livewire/practice/partials/dashboard.blade.php` |
| **2. GPS Attendance & Multi-Movement** | `app/Livewire/Practice/Concerns/ManagesAttendanceCalendarAndRadar.php` | `resources/views/livewire/practice/partials/attendance.blade.php` |
| **3. Leave Entitlements (3-Yr 180d / 4-Yr 240d & Corporate) & Approval** | `app/Livewire/Practice/Concerns/ManagesLeaveWorkflow.php` | `resources/views/livewire/practice/partials/leave.blade.php` |
| **4. Conveyance Claim & Voucher Lightbox** | `app/Livewire/Practice/Concerns/ManagesConveyanceWorkflow.php` | `resources/views/livewire/practice/partials/conveyance.blade.php` |
| **5. Monthly Payroll & Stipend Vouchers** | `app/Livewire/Practice/Concerns/ManagesPayrollAndVouchers.php` | `resources/views/livewire/practice/partials/payroll.blade.php` |
| **6. Team Roster, Designations, Promotions & Notifications** | `app/Livewire/Practice/Concerns/ManagesTeamAndNotifications.php` | `resources/views/livewire/practice/partials/team.blade.php`<br>`resources/views/livewire/practice/partials/notifications.blade.php` |
| **7. Partner Policy, Holidays, Color Palette & Self-Profile** | `app/Livewire/Practice/Concerns/ManagesPoliciesHolidaysAndSettings.php` | `resources/views/livewire/practice/partials/settings.blade.php`<br>`resources/views/livewire/practice/partials/policies.blade.php` |
| **8. Dynamic Brand Color Engine** | `app/Models/Firm.php`<br>`app/Services/FirmRegistry.php` | `resources/views/components/brand-theme-style.blade.php` |
