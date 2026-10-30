# AI Agent Guardrail & Surgical Editing Rules (DhakaWala ERP)

Please read `BLUEPRINT.md` first before making any changes to this repository.

1. **Modular Architecture:**
   - Practice workspace PHP logic is split into 6 isolated traits under `app/Livewire/Practice/Concerns/`:
     - `ManagesAttendanceCalendarAndRadar.php`
     - `ManagesLeaveWorkflow.php`
     - `ManagesConveyanceWorkflow.php`
     - `ManagesPayrollAndVouchers.php`
     - `ManagesPoliciesHolidaysAndSettings.php`
     - `ManagesTeamAndNotifications.php`
   - Practice workspace Blade views are split into 9 isolated partials under `resources/views/livewire/practice/partials/`.
   - Platform Super Admin logic lives in `app/Livewire/Platform/DashboardPreview.php` and `resources/views/livewire/platform/dashboard-preview.blade.php`.
2. **Surgical Edits Only:** Modify only the specific Trait and Blade partial relevant to the task. Never delete or overwrite unrelated methods, properties, or features.
3. **Database Migrations:** Always create a new timestamped migration file when adding schema columns, and register runtime fallback checks in `Firm::ensureRuntimeSchemaColumns()`.
4. **Keep `BLUEPRINT.md` Updated:** Append a summary of every update phase to Section 7 of `BLUEPRINT.md`.
