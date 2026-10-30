# DhakaWala ERP SaaS — Complete A-to-Z System Blueprint & AI Guardrail Manual (`BLUEPRINT.md`)

> **⚠️ MANDATORY INSTRUCTION FOR ALL AI AGENTS (Antigravity, Cursor, Claude, Gemini, Copilot, Windsurf):**
> 1. **READ THIS FILE FIRST** before modifying any file in this repository.
> 2. **SURGICAL, MODULE-ISOLATED EDITS ONLY:** This project is architected using **Modular Domain Traits (`app/Livewire/Practice/Concerns/*.php`)** and **Modular Blade Partials (`resources/views/livewire/practice/partials/*.blade.php`)**. When fixing a bug or adding a feature in one module (e.g., Payroll), you **MUST ONLY** edit that module's specific Trait and Blade partial. **NEVER** rewrite unrelated files or remove existing methods/properties.
> 3. **ZERO DEMO DATA IN PRODUCTION:** Never add sample/demo firms (`Rahman Anis`, `Sample Practice Alpha`), fake students (`Zubair`, `Nusrat`), or hardcoded fallback rows into `DatabaseSeeder.php` or production code paths (`! app()->runningUnitTests()`).
> 4. **UPDATE THIS BLUEPRINT:** Whenever you add a new feature, database column, route, or method, you **MUST** append an entry to **Section 7 (Chronological Update & Change Log)** of this `BLUEPRINT.md` file before finishing your turn.

---

## ১. প্রজেক্টের মূল উদ্দেশ্য ও আর্কিটেকচার (System Overview)

**DhakaWala ERP** হলো বাংলাদেশের চার্টার্ড অ্যাকাউন্ট্যান্সি (CA) ফার্ম, কনসালটেন্সি ফার্ম, এনজিও এবং কর্পোরেট প্রতিষ্ঠানের জন্য তৈরি একটি মাল্টি-টেন্যান্ট (Multi-Tenant) ক্লাউড ERP প্ল্যাটফর্ম (**Laravel 13 + Livewire 4 + Alpine.js + Tailwind CSS v4**)।

সফটওয়্যারটি দুটি প্রধান স্তরে বিভক্ত:
1. **DhakaWala Platform Super Admin (`/platform/*`):**
   - ঢাকাওয়ালা কর্তৃপক্ষ এখান থেকে নতুন CA ফার্ম/কোম্পানি তৈরি, কাস্টম ডোমেইন/সাবডোমেইন ম্যাপিং, সিট ও পার্টনার ইউনিট বরাদ্দ, কালার প্যালেট নির্ধারণ, মডিউল অন/অফ (`enabled_modules`), ১-ক্লিক সাপোর্ট লগইন (`1-Click Enter`), সাবস্ক্রিপশন বিলিং ও পপআপ রিমাইন্ডার, কিল-সুইচ (`Suspend`), এবং ফুল JSON ক্লাউড ব্যাকআপ ও নিরাপদ ডিলিট নিয়ন্ত্রণ করে।
2. **CA Firm / Client Practice Workspace (`/c/{firmSlug}/*`):**
   - প্রতিটি ফার্মের নিজস্ব ব্র্যান্ডেড ওয়ার্কস্পেস থাকে। একটি ফার্মে একাধিক **Partner (`Partner Unit 01`, `Partner Unit 02` ইত্যাদি)** থাকতে পারে। প্রতিটি পার্টনারের অধীনে তার নিজস্ব **Audit Manager** এবং **Articled Student (ICAB Trainees)** থাকে।

---

## ২. কোন ফিচারের জন্য শুধুমাত্র কোন ফাইলে কাজ করতে হবে (Feature-to-File Surgical Routing Map)

ভবিষ্যতে কোনো প্রবলেম সলভ বা নতুন ফিচার অ্যাড করার সময় **নিচের টেবিল দেখে শুধুমাত্র নির্দিষ্ট ফাইলটি এডিট করুন**—এতে অন্য কোনো ফিচার কখনোই নষ্ট হবে না:

| ফিচার / মডিউল (Module) | ব্যাকএন্ড ফাইল (PHP Class / Trait) | ফ্রন্টএন্ড ভিউ ফাইল (Blade Template) |
| :--- | :--- | :--- |
| **DhakaWala Super Admin Console** (ফার্ম তৈরি, ডোমেইন, বিলিং, কিল-সুইচ, ব্রডকাস্ট) | `app/Livewire/Platform/DashboardPreview.php` | `resources/views/livewire/platform/dashboard-preview.blade.php` |
| **Practice Core Orchestrator** (শুধু প্রপার্টি ডিক্লারেশন, `mount()`, ও `render()`) | `app/Livewire/Practice/PracticePreview.php` | `resources/views/livewire/practice/preview.blade.php` |
| **১. Dashboard, Live Radar & Monthly Calendar** (ড্যাশবোর্ড, কালার-শিফটিং অ্যাটেনডেন্স কার্ড, মান্থলি ক্যালেন্ডার ও ডেইলি ইন্সপেক্টর) | `app/Livewire/Practice/Concerns/ManagesAttendanceCalendarAndRadar.php` | `resources/views/livewire/practice/partials/dashboard.blade.php` |
| **২. GPS Attendance & Multi-Movement** (`Right Time`/`Late`/`Late Present`, `Close` বনাম `On Duty`, Missed Check-out, প্রক্সি ও প্রিন্ট শিট) | `app/Livewire/Practice/Concerns/ManagesAttendanceCalendarAndRadar.php` | `resources/views/livewire/practice/partials/attendance.blade.php` |
| **৩. Leave Application & Partial Approval** (এনগেজড ম্যানেজার মেনশন, ছুটি অনুমোদন, আংশিক অনুমোদন, স্যাংশন লেটার প্রিন্ট) | `app/Livewire/Practice/Concerns/ManagesLeaveWorkflow.php` | `resources/views/livewire/practice/partials/leave.blade.php` |
| **৪. Conveyance Claim & Voucher Lightbox** (মাল্টি-রো ট্রান্সপোর্ট বিল্ডার, অন্যান্য খরচ, ভাউচার প্রিভিউ, টাকা বাড়ানো/কমানো) | `app/Livewire/Practice/Concerns/ManagesConveyanceWorkflow.php` | `resources/views/livewire/practice/partials/conveyance.blade.php` |
| **৫. Monthly Payroll & Stipend Vouchers** (অটো পে-রোল, লেট ও হলিডে ডিডাকশন, পেমেন্ট ভাউচার প্রিন্ট, CSV এক্সপোর্ট) | `app/Livewire/Practice/Concerns/ManagesPayrollAndVouchers.php` | `resources/views/livewire/practice/partials/payroll.blade.php` |
| **৬. Team Roster, ICAB Tracker & Notifications** (মেম্বার যোগ/এডিট/ডিলিট, পাসওয়ার্ড রিসেট, ICAB সার্টিফিকেট, পপআপ নোটিফিকেশন) | `app/Livewire/Practice/Concerns/ManagesTeamAndNotifications.php` | `resources/views/livewire/practice/partials/team.blade.php`<br>`resources/views/livewire/practice/partials/notifications.blade.php` |
| **৭. Partner Policy, Holidays, Color Palette & Self-Profile** (অফিস টাইম, গ্রেস মিনিট, সরকারি ছুটি ও ওভাররাইড, ফার্মের কালার প্যালেট, প্রোফাইল ছবি/পাসওয়ার্ড) | `app/Livewire/Practice/Concerns/ManagesPoliciesHolidaysAndSettings.php` | `resources/views/livewire/practice/partials/settings.blade.php`<br>`resources/views/livewire/practice/partials/policies.blade.php` |
| **৮. Dynamic Brand Color Engine** (DhakaWala Sea Green ও ফার্মের ৮টি কালার প্যালেট + কাস্টম কালার) | `app/Models/Firm.php`<br>`app/Services/FirmRegistry.php` | `resources/views/components/brand-theme-style.blade.php` |

---

## ৩. সম্পূর্ণ ফাইল ও ফোল্ডার স্ট্রাকচার (A-to-Z File Map)

```text
erp-main/
├── .htaccess                                      # cPanel রুট সিকিউরিটি (.env, .git, vendor ব্লক ও public রিরাইট)
├── .env.example                                   # প্রোডাকশন-রেডি এনভায়রনমেন্ট টেমপ্লেট (Asia/Dhaka, MySQL)
├── BLUEPRINT.md                                   # এই মাস্টার ব্লুপ্রিন্ট ও AI গাইডলাইন ফাইল
├── app/
│   ├── Http/Middleware/
│   │   ├── RequireFirmMember.php                  # টেন্যান্ট আইসোলেশন মিডলওয়্যার (এক ফার্ম অন্য ফার্মে ঢুকতে পারবে না)
│   │   ├── RequireRole.php                        # রোল চেক মিডলওয়্যার (platform_admin, partner, manager, student)
│   │   └── RestrictPreviewInProduction.php        # প্রোডাকশনে (/preview) ডেমো রাউট অটো-রিডাইরেক্ট করে
│   ├── Livewire/
│   │   ├── AppShell.php                           # মেইন ল্যান্ডিং পোর্টাল (/) ও ইউনিফায়েড লগইন
│   │   ├── Auth/
│   │   │   ├── PlatformLogin.php                  # ঢাকাওয়ালা সুপার অ্যাডমিন লগইন (/platform/login)
│   │   │   ├── FirmLogin.php                      # ফার্ম-ভিত্তিক লগইন (/c/{firmSlug}/login)
│   │   │   ├── AccountActivation.php              # নতুন মেম্বার ইনভাইটেশন অ্যাক্টিভেশন (/c/{firmSlug}/activate)
│   │   │   ├── ForgotPassword.php                 # পাসওয়ার্ড রিকভারি
│   │   │   └── ResetPassword.php                  # পাসওয়ার্ড রিসেট
│   │   ├── Platform/
│   │   │   └── DashboardPreview.php               # ঢাকাওয়ালা সুপার অ্যাডমিন কন্ট্রোল প্যানেল (/platform/dashboard)
│   │   └── Practice/
│   │       ├── PracticePreview.php                # ফার্ম ওয়ার্কস্পেস কোর অর্কেস্ট্রেটর (/c/{firmSlug}/dashboard/{section?})
│   │       └── Concerns/                          # ★ ৬টি মডুলার ডোমেইন ট্রেইট (AI-Safe Isolation) ★
│   │           ├── ManagesPoliciesHolidaysAndSettings.php
│   │           ├── ManagesAttendanceCalendarAndRadar.php
│   │           ├── ManagesLeaveWorkflow.php
│   │           ├── ManagesConveyanceWorkflow.php
│   │           ├── ManagesPayrollAndVouchers.php
│   │           └── ManagesTeamAndNotifications.php
│   ├── Models/
│   │   ├── Firm.php                               # ফার্ম মডেল + ৮টি কালার প্যালেট ও কাস্টম হেক্স ইঞ্জিন
│   │   ├── User.php                               # ইউজার মডেল (avatar_url, phone, payment_account, articleship dates)
│   │   ├── PartnerPolicy.php                      # পার্টনার ইউনিট পলিসি (office_start, grace_minutes, late_penalty, leave_quotas)
│   │   ├── PracticeHoliday.php                    # সরকারি ছুটি, কাস্টম ছুটি ও Office Open Override মডেল
│   │   ├── Attendance.php                         # হাজিরা মডেল (GPS, movement_logs, distance_km, missed check-out)
│   │   ├── LeaveRequest.php                       # ছুটির আবেদন মডেল (engaged_manager_id, partial approval)
│   │   ├── ConveyanceClaim.php                    # কনভেয়ান্স মডেল (transport_items JSON, other_expense, attachment)
│   │   ├── PayrollRecord.php                      # মাসিক পে-রোল ও ভাউচার মডেল
│   │   ├── PracticeNotification.php               # টার্গেটেড ও পপআপ নোটিফিকেশন মডেল
│   │   ├── PolicyProposal.php                     # পার্টনারদের পলিসি ভোটিং মডেল
│   │   ├── AuditClient.php                        # অডিট ক্লায়েন্ট রেজিস্টার মডেল
│   │   └── AuditLog.php                           # অপরিবর্তনীয় সিকিউরিটি ও অ্যাক্টিভিটি অডিট লগ মডেল
│   └── Services/
│       └── FirmRegistry.php                       # ডাটাবেজ থেকে ফার্ম ও কালার টোকেন লোডার সার্ভিস
├── database/
│   ├── migrations/                                # ১৪টি ক্রমানুসারে সাজানো ডাটাবেজ মাইগ্রেশন ফাইল
│   └── seeders/
│       └── DatabaseSeeder.php                     # ক্লিন প্রোডাকশন সিডার (শুধু ঢাকাওয়ালা সুপার অ্যাডমিন তৈরি করে)
├── resources/views/
│   ├── components/
│   │   └── brand-theme-style.blade.php            # ডাইনামিক সিএসএস ভেরিয়েবল ও কালার প্যালেট ইঞ্জিন
│   ├── layouts/
│   │   ├── app.blade.php                          # মেইন পোর্টাল লেআউট (Sea Green থিম)
│   │   ├── platform.blade.php                     # সুপার অ্যাডমিন লেআউট (Sea Green থিম)
│   │   ├── auth.blade.php                         # লগইন ও অ্যাক্টিভেশন লেআউট
│   │   └── practice.blade.php                     # ফার্ম ওয়ার্কস্পেস লেআউট
│   └── livewire/
│       ├── platform/dashboard-preview.blade.php   # সুপার অ্যাডমিন ভিউ
│       └── practice/
│           ├── preview.blade.php                  # ফার্ম শেল, সাইডবার ও চেক-ইন সেলিব্রেশন অ্যানিমেশন মডাল
│           └── partials/                          # ৯টি আলাদা সেকশন ভিউ
│               ├── dashboard.blade.php
│               ├── attendance.blade.php
│               ├── leave.blade.php
│               ├── conveyance.blade.php
│               ├── payroll.blade.php
│               ├── team.blade.php
│               ├── notifications.blade.php
│               ├── policies.blade.php
│               └── settings.blade.php
└── routes/
    └── web.php                                    # সব ওয়েব রাউট + সিমলিংক-ফ্রি ভাউচার ও অ্যাভাটার ফাইল রাউট
```

---

## ৪. মডুলার ট্রেইট ও মেথড ইনডেক্স (Method-by-Method Blueprint)

### ৪.১ `app/Livewire/Practice/PracticePreview.php` (Core Orchestrator)
- `mount(string $firmSlug, string $previewRole, string $section)`: ফার্ম, রোল, পলিসি, ছুটির ক্যালেন্ডার ও সব মডিউল ইনিশিয়ালাইজ করে।
- `enforceAuthenticatedRoleLock()`: লগইন করা ইউজারের রোল ও ফার্ম আইসোলেশন নিশ্চিত করে।
- `resolveFirmModel()`: বর্তমান `firmSlug` অনুযায়ী ডাটাবেজ থেকে `Firm` মডেল রিটার্ন করে।
- `recordAuditLog(...)` ও `createPracticeNotification(...)`: অডিট লগ ও নোটিফিকেশন সেভ করার গ্লোবাল হেল্পার।
- `render()`: রোল ও পার্টনার ইউনিট অনুযায়ী ডাটা ফিল্টার করে ভিউ রেন্ডার করে।

### ৪.২ `ManagesPoliciesHolidaysAndSettings.php`
- **Partner Unit & Policy:** `setPartnerUnit()`, `loadPartnerPolicy()`, `savePolicySettings()`, `addCustomLeaveType()`, `toggleStudentGrid()`, `toggleManagerPermissionKey()`.
- **Brand Color Palette:** `selectFirmThemePalette(string $paletteKey)`, `saveFirmThemePalette()`.
- **Self-Service Profile & Avatar:** `updateOwnProfileAndPassword()`, `updatePartnerEmail()`.
- **3-Tier Time & Geofence Math:** `isTimeLate()`, `classifyArrivalStatus()` (`Right Time` / `Late` / `Late Present`), `getGraceEndFormattedProperty()`, `dismissCelebration()`, `calculateDistanceKm()`, `resolveTargetGps()`.
- **Holiday Calendar & Office Open Override:** `loadHolidays()`, `seedAnnualGovernmentHolidays()`, `addOrUpdateHoliday()`, `toggleHolidayOfficeOpenOverride()`, `deleteHoliday()`.
- **Governance & Audit Logs:** `loadPolicyProposals()`, `submitPolicyProposal()`, `voteOnPolicyProposal()`, `loadAuditLogs()`, `dismissNotice()`.

### ৪.৩ `ManagesAttendanceCalendarAndRadar.php`
- **Attendance Check-in & Multi-Movement (`Close` vs `On Duty`):** `loadAttendanceRows()`, `togglePreviewSessionWithGps()`, `togglePreviewSession()`, `initiateCheckoutPrompt()`, `confirmCheckoutWithAction()`, `openMovementTimeline()`, `toggleAllPartnersAttendanceView()`.
- **Missed Check-out & Proxy/Edit:** `submitMissedCheckoutRequest()`, `approveMissedCheckoutRequest()`, `saveProxyOrEditAttendance()`, `toggleManagerPermission()`, `acknowledgeDialog()`.
- **Interactive Monthly Calendar & Daily Inspector:** `changeCalendarMonth()`, `selectCalendarDate()`, `loadMonthlyCalendarData()`, `loadSelectedDayActivity()`.
- **Ultra-Premium Analytics & Official Letters:** `getTeamRadarProperty()`, `getClientCostingSummaryProperty()`, `getPunctualityLeaderboardProperty()`, `exportStudentCertificate()`, `exportLeaveSanctionLetter()`, `exportPrintableAttendanceSheet()`, `exportAttendanceCsv()`.

### ৪.৪ `ManagesLeaveWorkflow.php`
- `loadLeaveQueue()`, `submitLeaveApplication()`, `submitLeaveDemo()`, `openPartialLeaveModal()`, `submitPartialLeaveApproval()`, `managerRecommendLeave()`, `partnerApproveLeave()`, `rejectLeave()`.

### ৪.৫ `ManagesConveyanceWorkflow.php`
- `loadClaimQueue()`, `addTransportRow()`, `removeTransportRow()`, `updatedTransportRows()`, `updatedOtherExpenseAmount()`, `recalculateTransportTotal()`, `submitConveyanceClaim()`, `submitClaimDemo()`, `openVoucherPreviewModal()`, `openClaimAdjustModal()`, `submitClaimAdjustment()`, `managerReviewClaim()`, `partnerApproveClaim()`, `rejectClaim()`, `loadAuditClients()`, `openNewClientModal()`, `saveNewClientSite()`, `exportConveyanceCsv()`.

### ৪.৬ `ManagesPayrollAndVouchers.php`
- `calculateLateDeduction()`, `calculateMandatoryHolidayAbsentDeduction()`, `loadPayrollRows()`, `generatePayrollDraft()`, `transitionPayrollPeriod()`, `exportPayrollCsv()`, `exportStudentPayrollVoucher()`.

### ৪.৭ `ManagesTeamAndNotifications.php`
- **Team Roster:** `userCanManageTeam()`, `getAvailableManagersProperty()`, `loadTeamMembers()`, `openAddMemberModal()`, `resetNewMemberFields()`, `updatedNewMemberArticleshipStart()`, `createMember()`, `openEditMemberModal()`, `updateMember()`, `confirmDeleteMember()`, `deleteMember()`.
- **Popup & Targeted Notifications:** `sendPracticeNotification()`, `dismissPopupNotification()`, `loadNotifications()`, `markPreviewRead()`.

---

## ৫. ডাটাবেজ স্কিমা রেফারেন্স (Database Tables & Key Columns)

1. **`firms`**: `id`, `name`, `slug`, `kind`, `email`, `status`, `workspaces`, `seats`, `plan`, `region`, `initials`, `custom_domain`, `custom_subdomain`, `shared_attendance_view`, `is_suspended`, `subscription_fee`, `billing_cycle`, `payment_status`, `subscription_expires_at`, `enabled_modules` (JSON), `theme_palette`, `primary_color`.
2. **`users`**: `id`, `firm_id`, `name`, `email`, `phone`, `avatar_url`, `password`, `role` (`platform_admin`/`partner`/`manager`/`student`), `student_code`, `partner_unit`, `manager_id`, `monthly_allowance`, `payment_account_mode`, `payment_account_no`, `articleship_start_date`, `articleship_end_date`, `can_manage_team`, `can_proxy_attendance`, `can_edit_attendance`, `can_approve_leave`, `can_approve_claims`.
3. **`partner_policies`**: `id`, `firm_id`, `partner_id`, `partner_unit`, `office_start`, `office_end`, `grace_minutes`, `manager_conveyance_limit`, `conveyance_late_days`, `late_penalty_enabled`, `late_days_per_deduction`, `deduction_days_count`, `leave_quotas` (JSON), `student_grids` (JSON), `manager_permissions` (JSON).
4. **`practice_holidays`**: `id`, `firm_id`, `partner_unit`, `holiday_date`, `title`, `category`, `is_office_open_override`, `absent_deduction_days`, `override_reason`.
5. **`attendances`**: `id`, `firm_id`, `user_id`, `date`, `work_mode` (`office`/`client`), `client_site`, `in_time`, `in_lat`, `in_lng`, `in_location_name`, `out_time`, `out_lat`, `out_lng`, `out_location_name`, `status` (`Right Time`/`Late`/`Late Present`/`Incomplete`), `session_state` (`active`/`on_duty_transit`/`closed`), `movement_logs` (JSON), `distance_km`, `location_mismatch`, `correction_requested`, `requested_out_time`, `correction_reason`, `correction_status`, `is_proxy`, `proxy_by`, `edited_by`, `edit_remarks`.
6. **`leave_requests`**: `id`, `firm_id`, `user_id`, `engaged_manager_id`, `leave_type`, `start_date`, `end_date`, `requested_days`, `approved_days`, `approved_dates_summary`, `reason`, `status`, `reviewed_by`, `partner_id`, `manager_remarks`, `partner_remarks`.
7. **`conveyance_claims`**: `id`, `firm_id`, `user_id`, `claim_date`, `client_name`, `route`, `purpose`, `transport_items` (JSON), `other_expense_description`, `other_expense_amount`, `original_amount`, `amount`, `adjustment_remark`, `attachment_name`, `is_late`, `status`, `reviewed_by`, `partner_id`, `manager_remarks`, `partner_remarks`.
8. **`payroll_records`**: `id`, `firm_id`, `user_id`, `partner_unit`, `period` (`YYYY-MM`), `base_allowance`, `approved_conveyance`, `attendance_adjustment`, `holiday_absent_days`, `holiday_absent_deduction`, `net_payable`, `present_days`, `late_days`, `payment_method`, `voucher_no`, `status` (`draft`/`reviewed`/`approved`/`paid`/`locked`), `policy_snapshot` (JSON).
9. **`practice_notifications`**: `id`, `firm_id`, `user_id`, `sender_id`, `sender_name`, `target_role`, `recipient_ids` (JSON), `show_as_popup`, `icon`, `title`, `message`, `is_read`, `read_at`.
10. **`policy_proposals`**, **`audit_clients`**, **`audit_logs`**.

---

## ৬. নতুন ফিচার যোগ বা বাগ ফিক্স করার চেকলিস্ট (Safe Modification Checklist for AI)

যখনই এই প্রজেক্টে নতুন কোনো কাজ করা হবে, নিচের ৫টি নিয়ম মেনে চলতে হবে:
1. **ফাইল সিলেকশন:** সেকশন ২-এর টেবিল দেখে শুধুমাত্র নির্দিষ্ট `Concerns/*.php` ট্রেইট এবং `partials/*.blade.php` ফাইলটি ওপেন করুন।
2. **ডাটাবেজ মাইগ্রেশন:** নতুন কলাম লাগলে পুরনো মাইগ্রেশন ডিলিট না করে `Schema::hasColumn()` চেকসহ নতুন মাইগ্রেশন যোগ করুন।
3. **ডুপ্লিকেট এড়ানো:** কোনো বাটন বা কার্ড যোগ করার আগে ওই Blade ফাইলে চেক করুন একই কাজের বাটন আগে থেকেই আছে কি না।
4. **সিনট্যাক্স চেক:** কাজের শেষে সব PHP ফাইলে ব্র্যাকেট ব্যালেন্স (`{}`, `()`, `[]`), ডুপ্লিকেট ফাংশন নাম এবং ব্লেড ডিরেক্টিভ (`@if`/`@endif`, `@foreach`/`@endforeach`) চেক করুন।
5. **নিচের সেকশন ৭ আপডেট করুন:** কী পরিবর্তন করা হলো এবং কোন কোন ফাইলে হাত দেওয়া হলো তা নিচের লগে লিখে রাখুন।

---

## ৭. ক্রমানুসারে সকল আপডেটের পূর্ণাঙ্গ তালিকা (Chronological Update & Change Log)

### ✅ Update Phase 01 — Security, Middleware & Multi-Tenant Isolation
- **Files:** `app/Http/Middleware/RequireFirmMember.php`, `RequireRole.php`, `RestrictPreviewInProduction.php`, `routes/web.php`, `.htaccess`.
- **Details:** Enforced strict tenant isolation, role-based access control, production preview route blocking, cPanel `.htaccess` protection, and symlink-free routes (`firm.voucher.view`, `firm.avatar.view`).

### ✅ Update Phase 02 — Core DhakaWala Governance & 5 CA Firm Workflows
- **Files:** `DashboardPreview.php`, `PracticePreview.php`, migrations `000006` & `000007`.
- **Details:**
  1. Multi-segment GPS Attendance with `Close` vs `On Duty` popup and movement timeline.
  2. Leave Application with Engaged Manager selection, Partner approval, Partial Leave Approval (`approved_days` < `requested_days`), and custom leave quotas.
  3. Dynamic Multi-Row Transport Conveyance Builder (`From`, `To`, `Mode`, `Amount` + `Add Another Transport`), Other Expenses, and Manager/Partner amount adjustment (increase/decrease).
  4. Automatic Monthly Payroll calculation with Late Penalty deduction rules and Student Dashboard Grid active/deactive toggles.
  5. Targeted Single/Multiple/All Broadcast Notifications with automatic Login Popup modal.

### ✅ Update Phase 03 — Holiday Calendar, Office Open Override, Vouchers, Geofence & Billing
- **Files:** `PracticeHoliday.php`, `DashboardPreview.php`, migration `000008`.
- **Details:**
  1. Annual Bangladesh Government Holiday loader + custom holidays + Partner **"Office Open on Holiday/Weekend Override"** with automatic absent salary deduction.
  2. Printable Monthly Stipend & Conveyance Payment Vouchers.
  3. Haversine GPS distance calculation & Geofence Mismatch warning flag.
  4. DhakaWala Subscription Billing Tracker (`PAID`/`DUE`/`OVERDUE`) & 1-click Partner Renewal Popup Alert.

### ✅ Update Phase 04 — Self-Service Profile, Optional Voucher Lightbox, Missed Check-out & ICAB Tracker
- **Files:** `User.php`, `settings.blade.php`, `conveyance.blade.php`, `attendance.blade.php`, `team.blade.php`, migration `2026_10_29_000001`.
- **Details:**
  1. Self-Service Profile update: Full Name, Email, Mobile Phone, Profile Picture (`avatar_url`), `bKash`/`Nagad`/`Bank A/C` number (printed on vouchers), and Password change.
  2. Strictly optional Conveyance Voucher upload (for rickshaws/local buses without tickets) + Clickable Lightbox Preview Modal.
  3. Student "Missed Check-out" Correction Request & 1-Click Manager/Partner Approval.
  4. ICAB 3-Year Articleship Joining & Completion Date Tracker.
  5. Zero Demo Data cleanup in `DatabaseSeeder.php` and production fallbacks.

### ✅ Update Phase 05 — DhakaWala Sea Green Theme & Dynamic Firm Color Palette Engine
- **Files:** `Firm.php`, `FirmRegistry.php`, `resources/views/components/brand-theme-style.blade.php`, `public/images/dhakawala_logo.svg`.
- **Details:**
  1. Official **Sea Green (`#0D9488` / `#14B8A6`)** branding for DhakaWala Portal & Super Admin Console.
  2. 8 Curated Enterprise Color Palettes (`Sea Green`, `Emerald Forest`, `Royal Blue`, `Ocean Cyan`, `Executive Indigo`, `Imperial Purple`, `Corporate Crimson`, `Classic Gold`) + Custom Hex Color Picker selectable during Firm Creation, in Super Admin Manage Modal, and live in Partner's `Workspace Settings`.

### ✅ Update Phase 06 — Ultra-Premium Live Attendance Grid, Monthly Calendar & Analytics
- **Files:** `ManagesAttendanceCalendarAndRadar.php`, `dashboard.blade.php`, `preview.blade.php`, `team.blade.php`, `leave.blade.php`.
- **Details:**
  1. Super-level control flexibility: DhakaWala `1-Click Support Enter` & Per-Firm Granular Module Switcher (`enabled_modules`).
  2. 3-Tier Arrival Classification (`Right Time` `<= OfficeStart`, `Late` within Grace Window, `Late Present` past Grace Window) + Live Color-Shifting Attendance Grid (`Green -> Yellow -> Soft Red -> Deep Dark Red`) + Check-in Celebration Animation Overlay.
  3. Interactive Current Month Calendar on Dashboard with click-to-inspect Daily Activity Inspector.
  4. Live Field Radar ("Who's Where Right Now" — Office vs Client Site vs On Duty Transit).
  5. Client-wise Man-Hours & Conveyance Costing Table.
  6. 1-Click Official ICAB Articleship & NOC Certificate (`team.blade.php`) and Leave Sanction Letter (`leave.blade.php`).
  7. Punctuality Score (`0–100%`) & On-Time Streak Leaderboard + Top Live Policy Ticker Bar.

### ✅ Update Phase 07 — Modular Trait Architecture & AI Guardrail Blueprint (`2026-10-30`)
- **Files:** `BLUEPRINT.md`, `.cursorrules`, `CLAUDE.md`, `app/Livewire/Practice/PracticePreview.php`, `app/Livewire/Practice/Concerns/*.php`.
- **Details:** Refactored the 5,400-line `PracticePreview.php` component into 6 isolated domain traits in `app/Livewire/Practice/Concerns/` and authored this master `BLUEPRINT.md` so future AI agents can modify individual modules safely without affecting existing features.

---

### 📝 Future Update Log Template (পরবর্তী আপডেট এখানে যোগ করুন)
```markdown
### ✅ Update Phase XX — [Short Title of Change] (`YYYY-MM-DD`)
- **Modified Files:** `path/to/file1.php`, `path/to/file2.blade.php`
- **Summary of Changes:** Describe what was added or fixed and confirm no other modules were altered.
```

### Update Phase 08 — Super Admin Live Color Persistence, Partner Email Edit, Trial/Seat Subscriptions & Top-Deck Attendance Command Dock
- **Self-Healing Runtime Schema & New Migration (`2026_10_30_000001_add_theme_palette_avatar_and_trial_subscription_columns.php` + `Firm::ensureRuntimeSchemaColumns()`)**:
  - Guarantees `theme_palette`, `primary_color`, `subscription_type`, `per_seat_rate`, and `trial_days` columns exist on `firms` (and `avatar_url` on `users`) even on production servers where `2026_10_29_000001` had already been migrated previously.
  - Added automatic runtime column verification (`Firm::ensureRuntimeSchemaColumns()`) in `DashboardPreview`, `PracticePreview`, and `ManagesPoliciesHolidaysAndSettings` so palette changes and subscription updates persist reliably even before `php artisan migrate` is run.
- **DhakaWala Super Admin Live Palette Persistence & Directory Swatch Sync (`DashboardPreview.php` & `dashboard-preview.blade.php`)**:
  - Replaced chained `$set(...)` calls with dedicated server actions `selectAndApplyFirmPalette($slug, $paletteKey)` and `applyFirmCustomColor($slug)` that immediately persist the firm's color to PostgreSQL/SQLite and refresh `$this->firms` + `$this->selectedFirm`.
  - Displayed each organization's active color swatch, palette badge, and HEX code directly in the **Organization Directory** and **Subscription & Access Control Table**.
- **Company Main Email & Full Partner Account Editor in Super Admin (`DashboardPreview.php` & `dashboard-preview.blade.php`)**:
  - Added `editFirmEmail` to the Manage Organization modal so DhakaWala Super Admin can update the company's primary contact email.
  - Upgraded the Partner Accounts section in Super Admin with `partnerEdits[$id]` and `updatePartnerAccount($partnerId)` so Super Admin can edit any Partner's **Full Name, Login Email (with uniqueness validation), Partner Unit, and Password** directly.
- **Mature Free Trial (`7d`/`14d`/`30d`) & Seat-Based Paid Subscription Engine (`DashboardPreview.php` & `dashboard-preview.blade.php`)**:
  - Added `subscription_type` (`paid` vs `trial`), `per_seat_rate`, and `trial_days` support in both **Create Organization** and **Manage Organization** modals.
  - Added 1-click **Free Trial Presets (`7 Days`, `14 Days`, `30 Days`)** via `grantTrialSubscription($slug, $days)` (sets fee to `0 BDT`, status to `active`, and auto-calculates expiry date) and **Seat-Based Paid Auto-Calculator (`Allocated Seats × Per-Seat Rate BDT = Monthly Fee`)** via `applySeatBasedSubscription($slug)`.
- **Ultra-Premium Dashboard Redesign & Top-Deck Policy-Driven Attendance Grid (`dashboard.blade.php`, `attendance.blade.php`, `ManagesAttendanceCalendarAndRadar.php`)**:
  - Moved the **Live Color-Shifting Attendance Command Dock** to the **very top section of the Dashboard** for **Manager / Student / Staff (`$previewRole !== 'partner'`)**, and hid the personal attendance check-in grid on the **Partner Dashboard** (replacing it with a **Partner Executive Command Hero Banner**).
  - Added inline **`Office HQ` vs `Client Site`** mode buttons inside the top Attendance Grid; selecting `Client Site` reveals a smooth inline **Client Name input box with `<datalist>` auto-suggestions** (`wire:model="clientSite"`), automatically creating the client record if a new client name is typed.
  - Removed all manual `Preview Color Shift` test buttons; the Attendance Grid's color state (`Emerald Green <= OfficeStart` -> `Amber Yellow during Grace Window` -> `Soft Coral Red <=30m after Grace` -> `Deep Crimson Dark Red >30m after Grace`) operates 100% automatically from the firm's real-time policy and `Asia/Dhaka` clock.

### Update Phase 09 — Production Go-Live Audit: Unread Approval Inbox, Multi-Role Policy Controls & Session State Persistence
- **Unread Message-Style Approval Inbox & Real-Time Badges (`PracticePreview.php`, `preview.blade.php`, `dashboard.blade.php`, `leave.blade.php`, `conveyance.blade.php`)**:
  - Computes real-time unread/pending approval counters (`$pendingLeaveCount`, `$pendingClaimCount`, `$pendingCorrectionCount`, `$totalPendingApprovalsCount`, `$pendingLeaveItems`, `$pendingClaimItems`) scoped by active role (`manager` vs `partner`).
  - **Left Sidebar Unread Badges (`preview.blade.php`)**: Displays animated pulsing badges next to `Dashboard`, `Leave Requests` (`● X New`), `Conveyance Claims` (`● X New`), and `Attendance` (`X Fix`) whenever unread applications await review.
  - **Top Header Quick-Jump Unread Pills (`preview.blade.php`)**: Displays clickable `📩 X Leave Request(s)` and `🧾 X Conveyance Claim(s)` pills with animated ping indicators right next to the Notification Bell for Managers and Partners.
  - **Dashboard Unread Approval Inbox (`dashboard.blade.php`)**: Added Section `1C` right near the top of the Manager & Partner Dashboard, presenting every pending Leave Application and Conveyance Claim as an Unread Message Card (`NEW UNREAD` pulsing badge, dates/route, reason/amount, and 1-click **Approve / Recommend / Sanction / Reject** buttons directly on the Dashboard).
  - **Approval Queue Highlighting (`leave.blade.php` & `conveyance.blade.php`)**: Pending rows are highlighted with a left accent border (`border-l-4`) and pulsing `NEW UNREAD` badge, ordered newest-first (`orderByDesc('id')`).
- **End-to-End Notification Pipeline Audit (`ManagesLeaveWorkflow.php`, `ManagesConveyanceWorkflow.php`, `ManagesTeamAndNotifications.php`)**:
  - Submitting a Leave Application or Conveyance Claim automatically dispatches `PracticeNotification` records to the supervising Manager and Partner.
  - Manager recommendations and claim escalations automatically dispatch `PracticeNotification` alerts to the Partner; approvals/rejections automatically notify the Student.
  - Fixed `loadNotifications()` role filtering so `target_role` (`manager`, `partner`, `student`) notifications are delivered accurately to authenticated users of that role.
- **SuperAdmin, Partner & Manager Policy Editing Flexibility (`DashboardPreview.php`, `dashboard-preview.blade.php`, `ManagesPoliciesHolidaysAndSettings.php`, `policies.blade.php`, `settings.blade.php`, `leave.blade.php`)**:
  - **SuperAdmin**: Added `saveFirmPolicyFromSuperAdmin($slug)` and the **⚙️ Firm Attendance, Grace & Late Salary Cut Policy** editor inside the DhakaWala Super Admin `Manage / Domain` modal.
  - **Partner & Manager**: Enabled both `partner` and `manager` roles to edit and save Office Hours, Grace Minutes, Late Salary Deduction Rules (`X Late Days = Y Day Cut`), Manager Conveyance Limits, Custom Leave Categories, and Holiday / Office-Open Overrides across both `Policy & Governance` (`policies.blade.php`) and `Workspace Settings` (`settings.blade.php`).
- **Attendance Active Session Persistence Across Page Reloads (`ManagesAttendanceCalendarAndRadar.php`)**:
  - `loadAttendanceRows()` now restores `$this->previewSessionActive`, `$this->sessionState`, `$this->workMode`, `$this->clientSite`, and `$this->currentLocationName` from the authenticated user's today `Attendance` record in the database so browser refreshes preserve the active `Checked In` / `On Duty Move` state.

### Update Phase 10 — Leave & Conveyance Confirmation Popups, Overlapping Date Guard, Form Auto-Reset & SaaS Landing Page Redesign
- **Leave & Conveyance Submission Confirmation Modals (`ManagesLeaveWorkflow.php`, `ManagesConveyanceWorkflow.php`, `leave.blade.php`, `conveyance.blade.php`)**:
  - Clicking **Submit Leave Application** or **Submit Conveyance Claim** first validates inputs and opens a confirmation popup modal (`$showLeaveSubmitConfirmModal` / `$showClaimSubmitConfirmModal`) displaying the full summary with **Cancel / Edit** and **OK, Confirm & Submit** buttons.
- **Duplicate & Overlapping Leave Date Guard + Conveyance Duplicate Guard**:
  - `findOverlappingActiveLeave($startDate, $endDate)` checks both the `leave_requests` database table and in-memory `$leaveQueue` for any `pending_manager`, `pending_partner`, or `approved` leave covering overlapping dates (`start_date <= $endDate && end_date >= $startDate`) and blocks duplicate submissions with an immediate error notice and toast.
  - `findDuplicateActiveClaim(...)` blocks duplicate conveyance claims with identical date, client, route, and amount.
- **Guaranteed Form Auto-Reset (`$formResetIteration`)**:
  - Increments `$this->formResetIteration` upon submission and binds `wire:key="student-leave-form-{{ $formResetIteration }}"` and `wire:key="student-claim-form-{{ $formResetIteration }}"` alongside resetting all properties (`$leaveStart = ''`, `$leaveEnd = ''`, `$leaveReason = ''`, `$claimDate = ''`, `$claimAmount = ''`, `$claimRoute = ''`, `$claimPurpose = ''`, `$transportRows`), forcing browser native date/text inputs to reset 100% cleanly after every submission.
- **Ultra-Premium Privacy-First SaaS Landing Page (`resources/views/livewire/app-shell.blade.php`)**:
  - Redesigned `erp.dhakawala.com` (`app-shell.blade.php`) with an executive Sea-Green & Obsidian cloud SaaS aesthetic and removed the public client firm listing (`Rahman Anis & Co.`) from the visible login card so no single client firm's identity is exposed on the public portal.
  - **Cache-Busted Production CSS Bundle (`public/build/assets/app-DhakaWalaV10.css` + `manifest.json`)**: Updated Vite manifest to point to `assets/app-DhakaWalaV10.css` (while keeping `app-C5heZIna.css` for backward compatibility) and injected `<x-brand-theme-style />` into `layouts/practice.blade.php` `<head>` so cPanel and browsers always load the latest styles without stale cache.

### Update Phase 11 — Two-Step Final Check-out (Accidental-Close Guard), Stale Departure Fix & On-Duty Sweep
- **Two-Step Final Check-out (`ManagesAttendanceCalendarAndRadar.php`, `PracticePreview.php`, `attendance-modals.blade.php`)**: Choosing **Close (End Today's Duty)** no longer ends the day. It opens a **Final Check-out Review** listing every leg of today's movement (in/out times per site), the first check-in, total duty time, live location and time, and a warning when the member is currently `on_duty_transit`. The day ends only after the mandatory checkbox (`finalCloseConfirmed`) is ticked and **Confirm Final Check-out** (`confirmFinalCheckout()`) is pressed; **Not finished — Keep On Duty** stays as the escape hatch. The gate is enforced server-side in `confirmCheckoutWithAction('close')`, so a double click, stale tab or crafted Livewire call cannot close the day. Closing twice is idempotent. New public API: `openFinalCheckoutReview()`, `backToCheckoutChoice()`, `confirmFinalCheckout()`, `buildFinalCheckoutSummary()`.
- **Checkout Dialogs Moved Out of the Attendance Partial (`attendance-modals.blade.php`, `preview.blade.php`)**: The checkout chooser, the final review and the movement timeline are now a dedicated partial included once from the preview layout. Previously they lived inside `attendance.blade.php`, so the dashboard dock's own **Out** button set `showCheckoutModeModal` but rendered nothing (only one section is rendered per request).
- **Stale Departure Time Cleared on Re-Check-in (`ManagesAttendanceCalendarAndRadar.php`)**: When a member checks in again after an **On Duty** move, `out_time`, `out_lat`, `out_lng` and `out_location_name` are reset on the attendance row (and in the in-memory row). Without it the register read "active" and "checked out at 11:20" simultaneously. `in_time` is never cleared — it is what decides Right Time / Grace / Late Present, and `classifyArrivalStatus()` only runs on the first arrival.
- **Missed Check-out Sweep Now Uses `session_state` (`CloseMissedCheckouts.php`)**: The sweep flags rows where `out_time IS NULL` **or** `session_state != 'closed'`. A member who tapped On Duty at 11:00 and never returned has an `out_time` (the departure), so the old `whereNull('out_time')` query treated that day as complete and payroll paid for it. Notifications now distinguish "duty was left OPEN" from "no check-out recorded".
- **Tests & Docs**: `tests/Feature/FinalCheckoutGuardTest.php` (10 tests, 53 assertions) covers the guard, the review data, idempotency, the stale-departure reset, the sweep in all three cases and dialog reachability from the dashboard; `ProductionWorkspaceTest` updated for the two-step close. New **`docs/ATTENDANCE_MOVEMENT.md`** documents the state machine; `docs/AI_HANDOVER.md` gained invariants 10–13 and a change-log entry. Full suite: **168 tests / 675 assertions** (PHP 8.4.25, no-CI harness).

### Update Phase 12 — Flexible Weekly Off Days (per firm) & My Profile Shortcut
- **Weekly Off Days are now a firm setting, not a constant (`firms.weekly_off_days`, migration `2026_10_30_000006`, `Firm.php`, `ManagesAttendanceCalendarAndRadar.php`, `ManagesPoliciesHolidaysAndSettings.php`, `settings.blade.php`, `policies.blade.php`, `dashboard.blade.php`)**: `in_array($dow, [5, 6])` in the attendance calendar was the last hardcoded piece of Bangladesh-specific policy. It is replaced by `$this->isWeeklyOffDay($dow)` reading the firm's JSON list (0 = Sunday … 6 = Saturday). `NULL` keeps the previous behaviour for every existing tenant (Friday + Saturday), `[5]` closes Friday only, `[0]` closes Sunday for a firm that works Saturday, and `[]` declares a genuinely seven-day operation. A Partner edits it in **Workspace Settings → Weekly Off Days** (seven checkbox chips, live repaint, audit log `weekly_off_days_updated`, and a rejection when all seven days are ticked). Managers and Students see the pattern read-only. The policy summary card ("Weekly Holiday") and the dashboard's selected-day panel both render `$weeklyOffSummary` from `Firm::describeWeeklyOffDays()`.
- **My Profile shortcut (`openMyProfile()`, `preview.blade.php`, `settings.blade.php`)**: the header avatar and the sidebar user card are now buttons that jump straight to the self-service profile card (photo, name, email, phone, bKash/Nagad/bank account, password). The card gained `id="my-profile"`, an emerald border and an Alpine `@profile-focus.window` listener that scrolls it into view, so no member has to hunt through "Workspace Settings" for it.
- **Tests & Docs**: `tests/Feature/WeeklyOffDaysAndProfileShortcutTest.php` (10 tests) covers the default weekend, a Sunday weekend, saving, the seven-day refusal, the Manager guard, the profile shortcut, the policy label and the model normaliser. `docs/ATTENDANCE_MOVEMENT.md` gained section 1a, `docs/AI_HANDOVER.md` invariants 14–15. Full suite: **178 tests / 717 assertions** (PHP 8.4.25, no-CI harness).
