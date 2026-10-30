# AI Agent Guardrail & Surgical Editing Rules (DhakaWala ERP)

**Stop and read [`docs/AI_HANDOVER.md`](docs/AI_HANDOVER.md) before your first edit.**
It records the deployment topology (the app lives in `~/erp-app`, the web root is a
separate copy in `~/public_html`), the rule that `public/build` must be rebuilt *and*
synced whenever Blade/CSS changes, the list of fixes that must not regress, and how the
suite is verified without CI. Ignoring it has already cost a production outage.

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
5. **Keep `docs/AI_HANDOVER.md` Updated:** Every change that alters deployment, build
   output, user-visible copy or a verified guarantee must be appended to its
   "Change log" and, if relevant, to "Do not regress these".
6. **Assets Are Compiled Output:** Editing a Blade file is not enough. If you introduce
   Tailwind classes, run `npm ci && npm run build`, commit `public/build`, and tell the
   operator to re-run the docroot sync line in `docs/AI_HANDOVER.md` §1.
