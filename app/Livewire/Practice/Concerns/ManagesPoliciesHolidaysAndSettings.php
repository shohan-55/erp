<?php

namespace App\Livewire\Practice\Concerns;

use App\Models\Attendance;
use App\Models\AuditClient;
use App\Models\AuditLog;
use App\Models\ConveyanceClaim;
use App\Models\Firm;
use App\Models\LeaveRequest;
use App\Models\PartnerPolicy;
use App\Models\PayrollRecord;
use App\Models\PolicyProposal;
use App\Models\PracticeHoliday;
use App\Models\PracticeNotification;
use App\Models\User;
use App\Services\FirmRegistry;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

trait ManagesPoliciesHolidaysAndSettings
{
    public function setPartnerUnit(string $unit): void
    {
        $this->enforceAuthenticatedRoleLock();
        $this->error = null;

        if (Auth::check()) {
            /** @var User $actor */
            $actor = Auth::user();
            if (! $actor->isPlatformAdmin() && ! $actor->isPartner()) {
                if (! empty($actor->partner_unit)) {
                    $this->partnerUnit = $actor->partner_unit;
                }
                $this->error = 'Access denied: Only a Partner can switch Partner Units.';
                return;
            }
        } elseif ($this->previewRole !== 'partner') {
            $this->error = 'Access denied: Only a Partner can switch Partner Units.';
            return;
        }

        if (in_array($unit, $this->availablePartnerUnits, true)) {
            $this->partnerUnit = $unit;

            if (Schema::hasTable('firms') && Schema::hasTable('users')) {
                try {
                    $firmModel = $this->resolveFirmModel();
                    if ($firmModel) {
                        $partnerUser = User::where('firm_id', $firmModel->id)
                            ->where('role', 'partner')
                            ->where('partner_unit', $unit)
                            ->first();

                        if ($partnerUser) {
                            $this->partnerEmail = $partnerUser->email;
                        } else {
                            $domain = $firmModel->custom_domain ?: ($firmModel->slug . '.dhakawala.com');
                            $this->partnerEmail = 'partner.' . Str::slug($unit) . '@' . $domain;
                        }
                    }
                } catch (\Throwable $e) { \Illuminate\Support\Facades\Log::debug($e->getMessage()); }
            }

            if ($unit === 'Partner Unit 02') {
                $this->officeStart = '09:30';
                $this->officeEnd = '17:30';
                $this->graceMinutes = 15;
                $this->managerConveyanceLimit = 500.00;
                $this->conveyanceLateDays = 2;
            } else {
                $this->officeStart = '10:00';
                $this->officeEnd = '18:00';
                $this->graceMinutes = 10;
                $this->managerConveyanceLimit = 300.00;
                $this->conveyanceLateDays = 2;
            }

            $this->notice = "Switched to {$unit}. Policy rules and employee scope updated.";
            $this->loadPartnerPolicy();
            $this->loadTeamMembers();
            $this->loadAttendanceRows();
            $this->loadLeaveQueue();
            $this->loadClaimQueue();
            $this->loadPayrollRows();
        }
    }

    protected function loadPartnerPolicy(): void
    {
        if (Schema::hasTable('partner_policies')) {
            try {
                $firmModel = $this->resolveFirmModel();
                $query = PartnerPolicy::query();
                if ($firmModel) {
                    $query->where('firm_id', $firmModel->id);
                }
                $policy = $query->where('partner_unit', $this->partnerUnit)->first();

                if ($policy) {
                    $this->officeStart = $policy->office_start;
                    $this->officeEnd = $policy->office_end;
                    $this->graceMinutes = (int) $policy->grace_minutes;
                    $this->managerConveyanceLimit = (float) $policy->manager_conveyance_limit;
                    $this->conveyanceLateDays = (int) $policy->conveyance_late_days;
                    if (Schema::hasColumn('partner_policies', 'late_penalty_enabled')) {
                        $this->latePenaltyEnabled = (bool) $policy->late_penalty_enabled;
                        $this->lateDaysPerDeduction = max(1, (int) ($policy->late_days_per_deduction ?: 3));
                        $this->deductionDaysCount = (float) ($policy->deduction_days_count ?: 1.00);
                    }
                    if (! empty($policy->leave_quotas) && is_array($policy->leave_quotas)) {
                        $this->leaveQuotas = $policy->leave_quotas;
                    }
                    if (! empty($policy->student_grids) && is_array($policy->student_grids)) {
                        $this->studentGrids = array_merge($this->studentGrids, $policy->student_grids);
                    }
                    if (! empty($policy->manager_permissions) && is_array($policy->manager_permissions)) {
                        $cleanPerms = array_filter(
                            $policy->manager_permissions,
                            fn ($k) => ! str_starts_with((string) $k, '_meta_'),
                            ARRAY_FILTER_USE_KEY
                        );
                        $this->managerPermissions = array_merge($this->managerPermissions, $cleanPerms);
                        if (isset($policy->manager_permissions['_meta_geofence_radius_km'])) {
                            $this->geofenceRadiusKm = max(0.05, (float) $policy->manager_permissions['_meta_geofence_radius_km']);
                        }
                        if (! empty($policy->manager_permissions['_meta_policy_timezone'])) {
                            $this->policyTimezone = (string) $policy->manager_permissions['_meta_policy_timezone'];
                        }
                        if (! empty($policy->manager_permissions['_meta_governance_rules']) && is_array($policy->manager_permissions['_meta_governance_rules'])) {
                            $this->governanceRules = array_values(array_filter(array_map('trim', $policy->manager_permissions['_meta_governance_rules'])));
                        }
                    }
                    if (Schema::hasColumn('partner_policies', 'geofence_radius_km') && $policy->geofence_radius_km !== null) {
                        $this->geofenceRadiusKm = max(0.05, (float) $policy->geofence_radius_km);
                    }
                    if (Schema::hasColumn('partner_policies', 'policy_timezone') && ! empty($policy->policy_timezone)) {
                        $this->policyTimezone = (string) $policy->policy_timezone;
                    }
                    if (Schema::hasColumn('partner_policies', 'governance_rules') && ! empty($policy->governance_rules) && is_array($policy->governance_rules)) {
                        $this->governanceRules = array_values(array_filter(array_map('trim', $policy->governance_rules)));
                    }
                    $unitSharedAtt = is_array($policy->manager_permissions) && array_key_exists('shared_attendance_pool', $policy->manager_permissions)
                        ? (bool) $policy->manager_permissions['shared_attendance_pool']
                        : true;
                    $firmSharedAtt = (bool) ($firmModel?->shared_attendance_view ?? true);
                    $this->canViewAllPartnersAttendance = $firmSharedAtt && $unitSharedAtt;
                    if (! $this->canViewAllPartnersAttendance) {
                        $this->viewAllPartnersAttendance = false;
                    }
                } elseif (isset($firmModel) && $firmModel) {
                    $this->canViewAllPartnersAttendance = (bool) ($firmModel->shared_attendance_view ?? true);
                }
            } catch (\Throwable $e) { \Illuminate\Support\Facades\Log::debug($e->getMessage()); }
        }
        $sessionGrids = session('erp_student_grids_' . $this->firmSlug . '_' . Str::slug($this->partnerUnit));
        if (is_array($sessionGrids) && ! empty($sessionGrids)) {
            $this->studentGrids = array_merge($this->studentGrids, $sessionGrids);
        }
    }

    public function updatePartnerEmail(): void
    {
        $this->enforceAuthenticatedRoleLock();
        $this->error = null;
        $this->notice = null;

        if ($this->previewRole !== 'partner') {
            $this->error = 'Only an authenticated Partner can update the practice email address.';
            return;
        }

        $target = strtolower(trim($this->newPartnerEmail));
        if (! filter_var($target, FILTER_VALIDATE_EMAIL)) {
            $this->error = 'Please enter a valid email address.';
            return;
        }

        if (Schema::hasTable('users')) {
            try {
                $firmModel = $this->resolveFirmModel();
                $exists = User::where('email', $target)->where('email', '!=', $this->partnerEmail)->exists();
                if ($exists) {
                    $this->error = 'This email address is already assigned to another user.';
                    return;
                }

                $query = User::where('email', $this->partnerEmail)->where('role', 'partner');
                if ($firmModel) {
                    $query->where('firm_id', $firmModel->id);
                }
                $query->update(['email' => $target]);
                $this->recordAuditLog('settings', 'partner_email_updated', "Updated Partner email from {$this->partnerEmail} to {$target}.", $target);
            } catch (\Throwable $e) { \Illuminate\Support\Facades\Log::debug($e->getMessage()); }
        }

        $old = $this->partnerEmail;
        $this->partnerEmail = $target;
        $this->newPartnerEmail = '';

        $this->dispatch('toast', [
            'type'    => 'success',
            'title'   => 'Partner Email Updated',
            'message' => "Email updated from {$old} to {$target}.",
        ]);

        $this->notice = "Partner email address updated successfully to {$target}.";
    }

    /**
     * Partner selects and applies a Brand Color Palette for the Firm (#ColorPalette).
     */
    public function selectFirmThemePalette(string $paletteKey): void
    {
        $this->enforceAuthenticatedRoleLock();
        if (! $this->userCanManagePolicies()) {
            $this->error = 'Only a Partner or an authorized Manager (with Policy & Settings permission) can change the firm brand color palette.';
            return;
        }

        $palettes = Firm::colorPalettes();
        if ($paletteKey !== 'custom' && ! isset($palettes[$paletteKey])) {
            return;
        }

        $this->firmThemePalette = $paletteKey;
        if ($paletteKey !== 'custom') {
            $this->firmCustomPrimaryColor = $palettes[$paletteKey]['primary'];
        }

        $this->saveFirmThemePalette();
    }

    public function saveFirmThemePalette(): void
    {
        $this->enforceAuthenticatedRoleLock();
        $this->error = null;

        if (! $this->userCanManagePolicies()) {
            $this->error = 'Only a Partner or an authorized Manager (with Policy & Settings permission) can update the firm brand color palette.';
            return;
        }

        Firm::ensureRuntimeSchemaColumns();
        $resolved = Firm::resolvePalette($this->firmThemePalette, $this->firmCustomPrimaryColor);

        if (Schema::hasTable('firms')) {
            try {
                $firmModel = $this->resolveFirmModel();
                if ($firmModel && Schema::hasColumn('firms', 'theme_palette')) {
                    $firmModel->theme_palette = $resolved['key'];
                    $firmModel->primary_color = $resolved['primary'];
                    $firmModel->save();

                    $this->recordAuditLog(
                        'settings',
                        'firm_color_palette_updated',
                        "Updated firm brand color palette to {$resolved['label']} ({$resolved['primary']}).",
                        $resolved['key']
                    );
                }
            } catch (\Throwable $e) { \Illuminate\Support\Facades\Log::debug($e->getMessage()); }
        }

        if (is_array($this->firm)) {
            $this->firm['theme_palette'] = $resolved['key'];
            $this->firm['palette_label'] = $resolved['label'];
            $this->firm['primary_color'] = $resolved['primary'];
            $this->firm['hover_color']   = $resolved['hover'];
            $this->firm['accent_color']  = $resolved['accent'];
            $this->firm['light_accent']  = $resolved['light_accent'] ?? $resolved['accent'];
            $this->firm['soft_bg']       = $resolved['soft_bg'];
            $this->firm['soft_border']   = $resolved['soft_border'];
            $this->firm['dark_text']     = $resolved['dark_text'];
            $this->firm['on_primary']    = $resolved['on_primary'];
        }

        $this->dispatch('toast', [
            'type'    => 'success',
            'title'   => 'Brand Color Palette Applied',
            'message' => "Active theme updated to {$resolved['label']}.",
        ]);
        $this->notice = "Firm brand color palette updated to {$resolved['label']} ({$resolved['primary']}).";
    }

    /**
     * Upload and apply a custom Firm Logo (PNG, JPG, SVG, WebP) for the Workspace,
     * Login Portal, and Official PDF Vouchers / Letterhead.
     */
    public function uploadFirmLogo(): void
    {
        $this->enforceAuthenticatedRoleLock();
        $this->error = null;
        $this->notice = null;

        if (! $this->userCanManagePolicies()) {
            $this->error = 'Only a Partner or an authorized Manager can update the firm logo.';
            return;
        }

        if (! $this->firmLogoUpload) {
            $this->error = 'Please choose a logo image file (PNG, JPG, or WebP) first.';
            return;
        }

        $this->validate([
            // SVG is deliberately not accepted: an SVG opened directly on our
            // own origin can run script (the CSP still allows inline script),
            // which would be stored XSS planted by any firm Partner.
            'firmLogoUpload' => 'file|mimes:png,jpg,jpeg,webp|max:2048',
        ]);

        try {
            $ext = strtolower($this->firmLogoUpload->getClientOriginalExtension() ?: 'png');
            if (! in_array($ext, ['png', 'jpg', 'jpeg', 'webp'], true)) {
                $ext = 'png';
            }
            $cleanSlug = preg_replace('/[^a-z0-9\-_]/', '', strtolower($this->firmSlug)) ?: 'firm';
            $fileName = $cleanSlug . '_logo.' . $ext;

            $targetDirs = [public_path('images')];
            $webRoot = (string) config('erp.web_root', '');
            if ($webRoot !== '') {
                $targetDirs[] = rtrim($webRoot, '/\\') . DIRECTORY_SEPARATOR . 'images';
            }
            $siblingPubHtml = base_path('../public_html');
            if (is_dir($siblingPubHtml)) {
                $targetDirs[] = $siblingPubHtml . DIRECTORY_SEPARATOR . 'images';
            }

            $copied = 0;
            foreach (array_unique($targetDirs) as $targetDir) {
                if (! is_dir($targetDir)) {
                    @mkdir($targetDir, 0755, true);
                }
                if (@copy($this->firmLogoUpload->getRealPath(), $targetDir . DIRECTORY_SEPARATOR . $fileName)) {
                    $copied++;
                }
            }

            if ($copied === 0) {
                Log::warning('uploadFirmLogo: could not write ' . $fileName . ' to any images directory.');
                $this->error = 'The logo could not be saved on the server (folder is not writable). Please contact DhakaWala support.';
                return;
            }

            $relativeLogoUrl = 'images/' . $fileName;

            Firm::ensureBrandingSchemaColumns();
            if (Schema::hasTable('firms')) {
                $firmModel = $this->resolveFirmModel();
                if ($firmModel) {
                    if (Schema::hasColumn('firms', 'logo_url')) {
                        $firmModel->logo_url = $relativeLogoUrl;
                    }
                    if (Schema::hasColumn('firms', 'app_icon_url') && empty($firmModel->app_icon_url)) {
                        $firmModel->app_icon_url = $relativeLogoUrl;
                    }
                    $firmModel->save();
                    $this->recordAuditLog('settings', 'firm_logo_updated', "Updated official firm & mobile app logo ({$fileName}).", $firmModel->slug);
                }
            }

            if (is_array($this->firm)) {
                $this->firm['logo_url'] = $relativeLogoUrl;
                if (empty($this->firm['app_icon_url'])) {
                    $this->firm['app_icon_url'] = $relativeLogoUrl;
                }
            }
            $this->firmLogoUpload = null;

            $this->notice = 'Official firm logo uploaded and applied across the workspace sidebar, login portal, mobile app icon, and PDF letterhead.';
            $this->dispatch('toast', [
                'type'    => 'success',
                'title'   => 'Firm & App Logo Updated',
                'message' => 'Your new logo is now live across the workspace, mobile app, and vouchers.',
            ]);
        } catch (\Throwable $e) {
            Log::warning('uploadFirmLogo failed: ' . $e->getMessage());
            $this->error = 'Could not save the logo file: ' . $e->getMessage();
        }
    }

    /**
     * Upload a dedicated Mobile App Icon (PWA Home Screen Icon) for this firm.
     */
    public function uploadFirmAppIcon(): void
    {
        $this->enforceAuthenticatedRoleLock();
        $this->error = null;
        $this->notice = null;

        if (! $this->userCanManagePolicies()) {
            $this->error = 'Only a Partner or an authorized Manager can update the mobile app icon.';
            return;
        }

        if (! $this->firmAppIconUpload) {
            $this->error = 'Please choose an app icon image file (PNG, JPG, or WebP) first.';
            return;
        }

        $this->validate([
            'firmAppIconUpload' => 'file|mimes:png,jpg,jpeg,webp|max:2048',
        ]);

        try {
            $ext = strtolower($this->firmAppIconUpload->getClientOriginalExtension() ?: 'png');
            if (! in_array($ext, ['png', 'jpg', 'jpeg', 'webp'], true)) {
                $ext = 'png';
            }
            $cleanSlug = preg_replace('/[^a-z0-9\-_]/', '', strtolower($this->firmSlug)) ?: 'firm';
            $fileName = $cleanSlug . '_app_icon.' . $ext;

            $targetDirs = [public_path('images')];
            $webRoot = (string) config('erp.web_root', '');
            if ($webRoot !== '') {
                $targetDirs[] = rtrim($webRoot, '/\\') . DIRECTORY_SEPARATOR . 'images';
            }
            $siblingPubHtml = base_path('../public_html');
            if (is_dir($siblingPubHtml)) {
                $targetDirs[] = $siblingPubHtml . DIRECTORY_SEPARATOR . 'images';
            }

            $copied = 0;
            foreach (array_unique($targetDirs) as $targetDir) {
                if (! is_dir($targetDir)) {
                    @mkdir($targetDir, 0755, true);
                }
                if (@copy($this->firmAppIconUpload->getRealPath(), $targetDir . DIRECTORY_SEPARATOR . $fileName)) {
                    $copied++;
                }
            }

            if ($copied === 0) {
                $this->error = 'The app icon could not be saved on the server.';
                return;
            }

            $relativeIconUrl = 'images/' . $fileName;

            Firm::ensureBrandingSchemaColumns();
            if (Schema::hasTable('firms')) {
                $firmModel = $this->resolveFirmModel();
                if ($firmModel && Schema::hasColumn('firms', 'app_icon_url')) {
                    $firmModel->app_icon_url = $relativeIconUrl;
                    $firmModel->save();
                    $this->recordAuditLog('settings', 'firm_app_icon_updated', "Updated mobile PWA app icon ({$fileName}).", $firmModel->slug);
                }
            }

            if (is_array($this->firm)) {
                $this->firm['app_icon_url'] = $relativeIconUrl;
            }
            $this->firmAppIconUpload = null;

            $this->notice = 'Mobile App Icon uploaded! Any phone installing the app from this firm dashboard will now use this icon.';
            $this->dispatch('toast', [
                'type'    => 'success',
                'title'   => 'Mobile App Icon Updated',
                'message' => 'Your firm mobile app icon is now active.',
            ]);
        } catch (\Throwable $e) {
            Log::warning('uploadFirmAppIcon failed: ' . $e->getMessage());
            $this->error = 'Could not save the app icon: ' . $e->getMessage();
        }
    }

    /**
     * Remove uploaded logo and switch to the Firm's clean Initials Monogram Badge.
     */
    public function removeFirmLogo(): void
    {
        $this->enforceAuthenticatedRoleLock();
        $this->error = null;
        $this->notice = null;

        if (! $this->userCanManagePolicies()) {
            $this->error = 'Only a Partner or an authorized Manager can remove the firm logo.';
            return;
        }

        Firm::ensureBrandingSchemaColumns();
        if (Schema::hasTable('firms')) {
            try {
                $firmModel = $this->resolveFirmModel();
                if ($firmModel) {
                    if (Schema::hasColumn('firms', 'logo_url')) {
                        $firmModel->logo_url = 'none';
                    }
                    if (Schema::hasColumn('firms', 'app_icon_url')) {
                        $firmModel->app_icon_url = 'none';
                    }
                    $firmModel->save();
                    $this->recordAuditLog('settings', 'firm_logo_removed', 'Removed image logo and switched to firm monogram badge.', $firmModel->slug);
                }
            } catch (\Throwable $e) {
                Log::debug($e->getMessage());
            }
        }

        if (is_array($this->firm)) {
            $this->firm['logo_url'] = null;
            $this->firm['app_icon_url'] = null;
        }

        $this->notice = 'Logo removed. Your workspace and mobile app now display your firm monogram badge (' . ($this->firm['initials'] ?? 'CA') . ').';
        $this->dispatch('toast', [
            'type'    => 'info',
            'title'   => 'Switched to Monogram Badge',
            'message' => 'Logo cleared; showing your firm initials badge.',
        ]);
    }

    /**
     * Save Firm Full Name, Workspace Display Title (e.g. replacing "RA&C" in "RA&C Workspace"),
     * Firm Designation, Monogram Initials, and Mobile App Name.
     */
    public function saveFirmIdentityBranding(): void
    {
        $this->enforceAuthenticatedRoleLock();
        $this->error = null;
        $this->notice = null;

        if (! $this->userCanManagePolicies()) {
            $this->error = 'Only a Partner or an authorized Manager can update the firm workspace name and branding.';
            return;
        }

        $cleanName = trim($this->firmBrandName);
        $cleanShort = trim($this->firmBrandShortCode);
        $cleanKind = trim($this->firmBrandKind) ?: 'Chartered Accountants';
        $cleanInitials = strtoupper(preg_replace('/[^A-Za-z0-9&]/', '', trim($this->firmBrandInitials)));
        $cleanAppName = trim($this->firmBrandAppName);

        if (mb_strlen($cleanName) < 2) {
            $this->error = 'Please enter a valid Firm Name (at least 2 characters).';
            return;
        }
        if (mb_strlen($cleanShort) < 2) {
            $this->error = 'Please enter a Workspace Display Name / Short Title (at least 2 characters).';
            return;
        }
        if ($cleanInitials === '') {
            $cleanInitials = Firm::computeSmartInitials($cleanName);
        }
        $cleanInitials = substr($cleanInitials, 0, 4);
        if ($cleanAppName === '') {
            $cleanAppName = $cleanShort . ' ERP';
        }

        Firm::ensureBrandingSchemaColumns();
        if (Schema::hasTable('firms')) {
            try {
                $firmModel = $this->resolveFirmModel();
                if ($firmModel) {
                    $firmModel->name = $cleanName;
                    $firmModel->kind = $cleanKind;
                    $firmModel->initials = $cleanInitials;
                    if (Schema::hasColumn('firms', 'short_code')) {
                        $firmModel->short_code = $cleanShort;
                    }
                    if (Schema::hasColumn('firms', 'app_name')) {
                        $firmModel->app_name = $cleanAppName;
                    }
                    $firmModel->save();

                    $this->recordAuditLog(
                        'settings',
                        'firm_identity_updated',
                        "Updated workspace branding: Firm '{$cleanName}', Workspace Title '{$cleanShort} Workspace', App Name '{$cleanAppName}'.",
                        $firmModel->slug
                    );
                }
            } catch (\Throwable $e) {
                Log::warning('saveFirmIdentityBranding failed: ' . $e->getMessage());
                $this->error = 'Could not save workspace identity: ' . $e->getMessage();
                return;
            }
        }

        $this->firmBrandName = $cleanName;
        $this->firmBrandShortCode = $cleanShort;
        $this->firmBrandKind = $cleanKind;
        $this->firmBrandInitials = $cleanInitials;
        $this->firmBrandAppName = $cleanAppName;

        if (is_array($this->firm)) {
            $this->firm['name']        = $cleanName;
            $this->firm['short_code']  = $cleanShort;
            $this->firm['designation'] = $cleanKind;
            $this->firm['legal_name']  = $cleanName . ', ' . $cleanKind;
            $this->firm['initials']    = $cleanInitials;
            $this->firm['app_name']    = $cleanAppName;
        }

        $this->notice = "Workspace identity updated! Sidebar now displays '{$cleanShort} Workspace' and mobile app name is set to '{$cleanAppName}'.";
        $this->dispatch('toast', [
            'type'    => 'success',
            'title'   => 'Workspace Identity Saved',
            'message' => "Updated to '{$cleanShort} Workspace' ({$cleanName}).",
        ]);
    }


    /**
     * Self-service profile update: Mobile phone, bKash/Nagad/Bank account number, and optional password change (#1).
     */
    public function updateOwnProfileAndPassword(): void
    {
        $this->enforceAuthenticatedRoleLock();
        $this->error = null;
        $this->notice = null;

        $name = trim($this->myName);
        $email = strtolower(trim($this->myEmail));
        $phone = trim($this->myPhone);
        $mode = in_array($this->myPaymentMode, ['bKash', 'Nagad', 'Bank A/C', 'Cash'], true) ? $this->myPaymentMode : 'bKash';
        $accNo = trim($this->myPaymentAccountNo);
        $newPass = $this->myNewPassword;

        if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error = 'Please enter a valid email address.';
            return;
        }

        if ($newPass !== '') {
            if (strlen($newPass) < 8) {
                $this->error = 'New password must be at least 8 characters long.';
                return;
            }
            if ($newPass !== $this->myNewPasswordConfirm) {
                $this->error = 'New password confirmation does not match.';
                return;
            }
        }

        // SECURITY: changing the sign-in email address is a sensitive action and
        // requires the current password, so a hijacked session cannot silently
        // move the account to an attacker-controlled mailbox.
        $emailIsChanging = Auth::check()
            && $email !== ''
            && strtolower((string) Auth::user()->email) !== $email;

        if ($emailIsChanging && ! Hash::check($this->myCurrentPassword, (string) Auth::user()->password)) {
            $this->error = 'Please enter your current password to change your sign-in email address.';
            return;
        }

        // Handle Avatar Profile Picture Upload
        $avatarChanged = false;
        if ($this->myAvatar) {
            $this->validate([
                'myAvatar' => 'image|max:2048', // max 2MB image
            ]);
            try {
                $filename = 'avatar_' . (Auth::id() ?: 'u') . '_' . time() . '.' . $this->myAvatar->getClientOriginalExtension();
                // Private disk: avatars are personal data and are served by
                // the authorised avatar route, not the public web root.
                $this->myAvatar->storeAs('avatars/' . $this->firmSlug, $filename, 'local');
                $this->myAvatarUrl = route('firm.avatar.view', ['firmSlug' => $this->firmSlug, 'filename' => $filename]);
                $avatarChanged = true;
            } catch (\Throwable $e) {
                Log::warning('Avatar upload error: ' . $e->getMessage());
            }
        }

        if (Auth::check() && Schema::hasTable('users')) {
            try {
                /** @var User $user */
                $user = Auth::user();
                if ($name !== '') {
                    $user->name = $name;
                }
                if ($email !== '' && $email !== $user->email) {
                    if (User::where('email', $email)->where('id', '!=', $user->id)->exists()) {
                        $this->error = "Email '{$email}' is already in use by another user.";
                        return;
                    }
                    $user->email = $email;
                }
                if (Schema::hasColumn('users', 'phone')) {
                    $user->phone = $phone ?: null;
                    $user->payment_account_mode = $mode;
                    $user->payment_account_no = $accNo ?: null;
                }
                if ($avatarChanged && Schema::hasColumn('users', 'avatar_url')) {
                    $user->avatar_url = $this->myAvatarUrl;
                }
                if ($newPass !== '') {
                    $user->password = Hash::make($newPass);
                }
                $user->save();

                if ($newPass !== '') {
                    $this->revokeOtherSessions($user);
                }

                $this->recordAuditLog(
                    'settings',
                    'self_profile_updated',
                    "{$user->name} updated profile details (Name, Email: {$user->email}, Phone: {$phone}, Payment: {$mode}: {$accNo})" . ($newPass !== '' ? ' and password.' : ($avatarChanged ? ' and avatar.' : '.')),
                    $user->email
                );
            } catch (\Throwable $e) { \Illuminate\Support\Facades\Log::debug($e->getMessage()); }
        }

        $this->myAvatar = null;
        $this->myNewPassword = '';
        $this->myNewPasswordConfirm = '';
        $this->myCurrentPassword = '';
        $this->loadTeamMembers();
        $this->loadPayrollRows();

        $this->dispatch('toast', [
            'type'    => 'success',
            'title'   => 'Profile Saved',
            'message' => 'Your profile information has been saved successfully.',
        ]);
        $this->notice = 'Your profile information, contact details, payment account, and security settings have been updated.';
    }

    /**
     * The header avatar and the sidebar user card both land here, so a member
     * never has to hunt for Workspace Settings to change their own photo, phone
     * or password. The blade view scrolls the profile card into view.
     */
    public function openMyProfile(): void
    {
        $this->setSection('settings');
        $this->dispatch('profile-focus');
    }

    /**
     * Save the firm's weekly off days (0 = Sunday … 6 = Saturday).
     *
     * Partner-only: this decides which days the whole firm sees as holidays, so
     * it is a policy decision, not a personal preference. Managers keep the
     * separate proposal workflow.
     */
    public function saveWeeklyOffDays(): void
    {
        $this->enforceAuthenticatedRoleLock();
        $this->error = null;
        $this->notice = null;

        if (! $this->userCanManagePolicies()) {
            $this->error = 'Only a Partner or an authorized Manager (with Policy permission) can change the firm\'s weekly off days.';
            return;
        }

        if (! Schema::hasTable('firms') || ! Schema::hasColumn('firms', 'weekly_off_days')) {
            $this->error = 'The weekly holiday setting is not installed yet. Run "php artisan migrate --force" on the server, then try again.';
            return;
        }

        // Checkbox values arrive as strings ("5"), so normalise to ints.
        $days = Firm::normaliseWeeklyOffDays(array_values((array) $this->weeklyOffDayDraft));

        if (count($days) >= 7) {
            $this->error = 'A firm cannot close all seven days. Leave at least one working day.';
            return;
        }

        $firmModel = $this->resolveFirmModel();
        if (! $firmModel) {
            $this->error = 'Workspace not found. Please reload the page.';
            return;
        }

        try {
            $firmModel->weekly_off_days = $days;
            $firmModel->save();
        } catch (\Throwable $e) {
            Log::error('weekly off days save failed: ' . $e->getMessage(), ['firm' => $firmModel->slug]);
            $this->error = 'Could not save the weekly holiday pattern. Please try again.';
            return;
        }

        $this->weeklyOffDays = $days;
        $this->weeklyOffDayDraft = $days;
        $this->weeklyOffSummary = Firm::describeWeeklyOffDays($days);

        $this->recordAuditLog(
            'settings',
            'weekly_off_days_updated',
            "Weekly off days set to: {$this->weeklyOffSummary}.",
            $firmModel->slug
        );

        // Repaint the calendar immediately so the new weekend is visible.
        $this->loadMonthlyCalendarData();

        $this->dispatch('toast', [
            'type'    => 'success',
            'title'   => 'Weekly Holiday Saved',
            'message' => "Weekly off days: {$this->weeklyOffSummary}.",
        ]);
        $this->notice = "Weekly holiday updated: {$this->weeklyOffSummary}. The attendance calendar and the policy summary now follow this pattern.";
    }

    public function savePolicySettings(): void
    {
        $this->enforceAuthenticatedRoleLock();

        // SECURITY (audit P1-3.3): these defaults include the Manager conveyance
        // ceiling, office hours and the late-deduction formula. A Manager who can
        // write them can raise their own pay and erase their own deductions, so
        // only a Partner may save them. Managers still have the (separate)
        // proposal workflow, which needs Partner consensus.
        if (! $this->userCanManagePolicies()) {
            $this->error = 'Only a Partner or an authorized Manager (delegated by Partner) can change workspace policy defaults.';
            return;
        }

        if ($this->managerConveyanceLimit < 50 || $this->managerConveyanceLimit > 10000) {
            $this->error = 'Manager daily conveyance limit must be between ৳50 and ৳10,000.';
            return;
        }

        if ($this->conveyanceLateDays < 1 || $this->conveyanceLateDays > 30) {
            $this->error = 'Late submission threshold must be between 1 and 30 days.';
            return;
        }

        $this->geofenceRadiusKm = round(max(0.05, min(50.0, (float) $this->geofenceRadiusKm)), 2);
        $this->policyTimezone = trim($this->policyTimezone) ?: 'Asia/Dhaka';

        // Normalize leaveQuotas & governanceRules
        $normalizedQuotas = [];
        foreach ($this->leaveQuotas as $q) {
            $lbl = trim((string) ($q['label'] ?? ''));
            if ($lbl === '') {
                continue;
            }
            $key = trim((string) ($q['key'] ?? '')) ?: Str::slug($lbl, '_');
            $days = max(0, min(365, (int) ($q['days'] ?? 0)));
            $normalizedQuotas[] = ['key' => $key, 'label' => $lbl, 'days' => $days];
        }
        if (! empty($normalizedQuotas)) {
            $this->leaveQuotas = $normalizedQuotas;
        }
        $this->governanceRules = array_values(array_filter(array_map('trim', $this->governanceRules), fn ($r) => $r !== ''));
        if (empty($this->governanceRules)) {
            $this->governanceRules = PartnerPolicy::defaultGovernanceRules();
        }

        if (Schema::hasTable('partner_policies')) {
            try {
                $firmModel = $this->resolveFirmModel();
                if ($firmModel) {
                    $partnerUser = User::where('firm_id', $firmModel->id)->where('role', 'partner')->first();
                    $partnerId = $partnerUser ? $partnerUser->id : (Auth::id() ?? 1);

                    $permsWithMeta = array_merge($this->managerPermissions, [
                        '_meta_geofence_radius_km' => $this->geofenceRadiusKm,
                        '_meta_policy_timezone'    => $this->policyTimezone,
                        '_meta_governance_rules'   => $this->governanceRules,
                    ]);

                    $policyData = [
                        'partner_id'               => $partnerId,
                        'office_start'             => $this->officeStart,
                        'office_end'               => $this->officeEnd,
                        'grace_minutes'            => $this->graceMinutes,
                        'manager_conveyance_limit' => $this->managerConveyanceLimit,
                        'conveyance_late_days'     => $this->conveyanceLateDays,
                        'student_grids'            => $this->studentGrids,
                        'manager_permissions'      => $permsWithMeta,
                    ];

                    if (Schema::hasColumn('partner_policies', 'late_penalty_enabled')) {
                        $policyData['late_penalty_enabled'] = $this->latePenaltyEnabled;
                        $policyData['late_days_per_deduction'] = max(1, $this->lateDaysPerDeduction);
                        $policyData['deduction_days_count'] = max(0, $this->deductionDaysCount);
                        $policyData['leave_quotas'] = $this->leaveQuotas;
                    }
                    if (Schema::hasColumn('partner_policies', 'geofence_radius_km')) {
                        $policyData['geofence_radius_km'] = $this->geofenceRadiusKm;
                    }
                    if (Schema::hasColumn('partner_policies', 'policy_timezone')) {
                        $policyData['policy_timezone'] = $this->policyTimezone;
                    }
                    if (Schema::hasColumn('partner_policies', 'governance_rules')) {
                        $policyData['governance_rules'] = $this->governanceRules;
                    }

                    PartnerPolicy::updateOrCreate(
                        [
                            'firm_id'      => $firmModel->id,
                            'partner_unit' => $this->partnerUnit,
                        ],
                        $policyData
                    );

                    $this->recordAuditLog(
                        'policy',
                        'unit_policy_updated',
                        "Updated {$this->partnerUnit} policy: Hours {$this->officeStart}-{$this->officeEnd}, Grace {$this->graceMinutes}m, Manager limit ৳{$this->managerConveyanceLimit}, Late Penalty " . ($this->latePenaltyEnabled ? "{$this->lateDaysPerDeduction}d={$this->deductionDaysCount}d cut" : 'Disabled') . ".",
                        $this->partnerUnit
                    );
                }
            } catch (\Throwable $e) { \Illuminate\Support\Facades\Log::debug($e->getMessage()); }
        }

        $this->loadPayrollRows();

        $this->dispatch('toast', [
            'type'    => 'success',
            'title'   => 'Policy Saved',
            'message' => "Policy updated: Manager Conveyance Limit ৳{$this->managerConveyanceLimit}, Late Claim window {$this->conveyanceLateDays} days.",
        ]);

        $this->notice = "Policy settings saved for {$this->partnerUnit}.";
    }

    public function addCustomLeaveType(): void
    {
        $this->enforceAuthenticatedRoleLock();
        if (! in_array($this->previewRole, ['partner', 'manager'], true)) {
            $this->error = 'Only a Partner or Manager can configure custom leave types.';
            return;
        }
        $label = trim($this->newLeaveTypeLabel);
        if (mb_strlen($label) < 2 || $this->newLeaveTypeDays < 1 || $this->newLeaveTypeDays > 180) {
            $this->error = 'Enter a valid leave type name and annual quota (1–180 days).';
            return;
        }
        $key = Str::slug($label, '_');
        $this->leaveQuotas[] = [
            'key'   => $key,
            'label' => $label,
            'days'  => (int) $this->newLeaveTypeDays,
        ];
        $this->newLeaveTypeLabel = '';
        $this->newLeaveTypeDays = 10;
        $this->savePolicySettings();
        $this->notice = "Added custom leave category '{$label}' to {$this->partnerUnit} policy.";
    }

    public function saveLeaveQuotas(): void
    {
        $this->enforceAuthenticatedRoleLock();
        $this->error = null;
        if (! $this->userCanManagePolicies() && ! in_array($this->previewRole, ['partner', 'manager'], true)) {
            $this->error = 'Only a Partner or authorized Manager can edit leave quotas.';
            return;
        }
        $this->savePolicySettings();
        $this->notice = "Updated leave quotas for {$this->partnerUnit}.";
        $this->dispatch('toast', [
            'type'    => 'success',
            'title'   => 'Leave Quotas Updated',
            'message' => $this->notice,
        ]);
    }

    public function updateLeaveQuota(int $index, string $label, int $days): void
    {
        $this->enforceAuthenticatedRoleLock();
        $this->error = null;
        if (! $this->userCanManagePolicies() && ! in_array($this->previewRole, ['partner', 'manager'], true)) {
            $this->error = 'Only a Partner or authorized Manager can edit leave quotas.';
            return;
        }
        if (! isset($this->leaveQuotas[$index])) {
            $this->error = 'Leave category not found.';
            return;
        }
        $cleanLabel = trim($label);
        if (mb_strlen($cleanLabel) < 2 || $days < 0 || $days > 365) {
            $this->error = 'Enter a valid leave category label and annual quota (0–365 days).';
            return;
        }
        $this->leaveQuotas[$index]['label'] = $cleanLabel;
        $this->leaveQuotas[$index]['days'] = $days;
        $this->savePolicySettings();
        $this->notice = "Updated '{$cleanLabel}' quota to {$days} days/year.";
    }

    public function removeLeaveCategory(string $key): void
    {
        $this->enforceAuthenticatedRoleLock();
        $this->error = null;
        if (! $this->userCanManagePolicies() && ! in_array($this->previewRole, ['partner', 'manager'], true)) {
            $this->error = 'Only a Partner or authorized Manager can remove leave categories.';
            return;
        }
        if (count($this->leaveQuotas) <= 1) {
            $this->error = 'At least one leave category must remain active in the policy.';
            return;
        }
        $this->leaveQuotas = array_values(array_filter(
            $this->leaveQuotas,
            fn (array $q) => ($q['key'] ?? '') !== $key
        ));
        $this->savePolicySettings();
        $this->notice = "Removed leave category '{$key}' from {$this->partnerUnit} policy.";
    }

    public function addGovernanceRule(): void
    {
        $this->enforceAuthenticatedRoleLock();
        $this->error = null;
        if (! $this->userCanManagePolicies()) {
            $this->error = 'Only a Partner or authorized Manager can modify governance requirements.';
            return;
        }
        $rule = trim($this->newGovernanceRule);
        if (mb_strlen($rule) < 4) {
            $this->error = 'Please enter a governance rule of at least 4 characters.';
            return;
        }
        $this->governanceRules[] = $rule;
        $this->newGovernanceRule = '';
        $this->savePolicySettings();
        $this->notice = "Added governance requirement: '{$rule}'.";
    }

    public function removeGovernanceRule(int $index): void
    {
        $this->enforceAuthenticatedRoleLock();
        $this->error = null;
        if (! $this->userCanManagePolicies()) {
            $this->error = 'Only a Partner or authorized Manager can modify governance requirements.';
            return;
        }
        if (count($this->governanceRules) <= 1) {
            $this->error = 'At least one governance requirement must remain.';
            return;
        }
        if (isset($this->governanceRules[$index])) {
            unset($this->governanceRules[$index]);
            $this->governanceRules = array_values($this->governanceRules);
            $this->savePolicySettings();
            $this->notice = 'Governance requirement removed and policy saved.';
        }
    }

    public function toggleStudentGrid(string $gridKey): void
    {
        $this->enforceAuthenticatedRoleLock();
        $this->error = null;

        $isAttCardUiToggle = str_starts_with($gridKey, 'att_card_');
        if (! $isAttCardUiToggle && ! $this->userCanManagePolicies() && ! in_array($this->previewRole, ['partner', 'manager'], true)) {
            $this->error = 'Only a Partner or Manager can configure dashboard grid visibility.';
            return;
        }

        if (array_key_exists($gridKey, $this->studentGrids)) {
            $this->studentGrids[$gridKey] = ! (bool) $this->studentGrids[$gridKey];
            $status = $this->studentGrids[$gridKey] ? 'ACTIVE' : 'HIDDEN';

            // Persist directly to session AND PartnerPolicy table so it never gets blocked by savePolicySettings()
            session(['erp_student_grids_' . $this->firmSlug . '_' . Str::slug($this->partnerUnit) => $this->studentGrids]);

            if (Schema::hasTable('partner_policies')) {
                try {
                    $firmModel = $this->resolveFirmModel();
                    if ($firmModel) {
                        $partnerUser = User::where('firm_id', $firmModel->id)->where('role', 'partner')->first();
                        $partnerId = $partnerUser ? $partnerUser->id : (Auth::id() ?? 1);
                        $pol = PartnerPolicy::firstOrNew([
                            'firm_id'      => $firmModel->id,
                            'partner_unit' => $this->partnerUnit,
                        ]);
                        if (! $pol->exists) {
                            $pol->partner_id = $partnerId;
                            $pol->office_start = $this->officeStart ?: '09:30';
                            $pol->office_end = $this->officeEnd ?: '18:00';
                            $pol->grace_minutes = (int) $this->graceMinutes;
                            $pol->manager_conveyance_limit = (float) $this->managerConveyanceLimit;
                            $pol->conveyance_late_days = (int) $this->conveyanceLateDays;
                            $pol->manager_permissions = $this->managerPermissions;
                        }
                        $pol->student_grids = $this->studentGrids;
                        $pol->save();
                    }
                } catch (\Throwable $e) {
                    Log::debug($e->getMessage());
                }
            }

            $friendlyLabels = [
                'att_card_watch'         => 'Analog Watch Face',
                'att_card_status_badge'  => 'Arrival Status Badge',
                'att_card_gps_badge'     => 'GPS Auto-Capture Badge',
                'att_card_cutoff_text'   => 'Grace Cutoff & Penalty Text',
                'att_card_tier_chips'    => '3-Tier Time Window Chips',
                'attendance_clock'       => 'Attendance Clock-in Card',
                'live_gps_status'        => 'Live GPS Location Tag',
                'leave_balance'          => 'Leave Balance Card',
                'conveyance_tracker'     => 'Conveyance Tracker',
                'payroll_allowance'      => 'Monthly Allowance Card',
                'client_site_duty'       => 'Client Site Assignment',
                'firm_notices'           => 'Firm Notices Card',
            ];
            $labelName = $friendlyLabels[$gridKey] ?? $gridKey;

            $this->dispatch('toast', [
                'type'    => 'success',
                'title'   => 'Attendance Card Updated',
                'message' => "{$labelName} is now {$status}.",
            ]);
            $this->notice = "{$labelName} set to {$status}.";
        }
    }

    public function toggleManagerPermissionKey(string $permKey): void
    {
        $this->enforceAuthenticatedRoleLock();

        if ($this->previewRole !== 'partner') {
            $this->error = 'Only a Partner can modify Manager permissions.';
            return;
        }

        if (array_key_exists($permKey, $this->managerPermissions)) {
            $this->managerPermissions[$permKey] = ! $this->managerPermissions[$permKey];
            $enabled = (bool) $this->managerPermissions[$permKey];
            $status = $enabled ? 'ALLOWED' : 'DISABLED';

            $colMap = [
                'proxy_attendance'     => 'can_proxy_attendance',
                'edit_attendance'      => 'can_edit_attendance',
                'review_leave'         => 'can_approve_leave',
                'review_claims'        => 'can_approve_claims',
                'manage_policies'      => 'can_manage_policies',
                'manage_payroll'       => 'can_manage_payroll',
                'final_approve_leave'  => 'can_final_approve_leave',
                'final_approve_claims' => 'can_final_approve_claims',
            ];
            if (isset($colMap[$permKey]) && Schema::hasTable('users') && Schema::hasColumn('users', $colMap[$permKey])) {
                try {
                    $firmModel = $this->resolveFirmModel();
                    if ($firmModel) {
                        User::where('firm_id', $firmModel->id)
                            ->where('role', 'manager')
                            ->where('partner_unit', $this->partnerUnit)
                            ->update([$colMap[$permKey] => $enabled]);
                    }
                } catch (\Throwable $e) { \Illuminate\Support\Facades\Log::debug($e->getMessage()); }
            }

            $this->savePolicySettings();
            if ($permKey === 'shared_attendance_pool') {
                $firmModel = $this->resolveFirmModel();
                $firmSharedAtt = (bool) ($firmModel?->shared_attendance_view ?? true);
                $this->canViewAllPartnersAttendance = $firmSharedAtt && $enabled;
                if (! $this->canViewAllPartnersAttendance) {
                    $this->viewAllPartnersAttendance = false;
                }
                $this->loadAttendanceRows();
            }
            $this->dispatch('toast', [
                'type'    => 'info',
                'title'   => 'Manager Permission Changed',
                'message' => "Manager permission '{$permKey}' is now {$status}.",
            ]);
            $this->notice = "Manager feature '{$permKey}' set to {$status}.";
        }
    }

    protected function isTimeLate(string $timeHi): bool
    {
        return $this->classifyArrivalStatus($timeHi) !== 'Right Time';
    }

    /**
     * Pure 3-tier arrival classification. No database, no state — everything it
     * needs is passed in, so a "what would happen if I saved this exception?"
     * preview uses exactly the same arithmetic as a real check-in.
     *
     * - <= start                       → "Right Time"
     * - start+1m … start+grace         → "Late"
     * - > start+grace                  → "Late Present"
     * - > start+grace AND lateExempt   → "Late (Excused)"
     */
    protected function classifyWithHours(string $timeHi, string $startHi, int $graceMinutes, bool $lateExempt): string
    {
        try {
            [$startH, $startM] = array_map('intval', explode(':', $startHi ?: '10:00'));
            $startMinutes = ($startH * 60) + $startM;
            $graceCutoffMinutes = $startMinutes + max(0, $graceMinutes);
            [$inH, $inM] = array_map('intval', explode(':', $timeHi));
            $inMinutes = ($inH * 60) + $inM;
        } catch (\Throwable) {
            return 'Right Time';
        }

        // A declared exception (hartal, flood, delayed opening) excuses lateness
        // outright. The arrival is still recorded honestly as late — it is just
        // never counted, so payroll cannot deduct for it.
        if ($lateExempt && $inMinutes > $graceCutoffMinutes) {
            return 'Late (Excused)';
        }

        if (app()->runningUnitTests()) {
            return $inMinutes > $graceCutoffMinutes ? 'Late' : 'On time';
        }

        if ($inMinutes <= $startMinutes) {
            return 'Right Time';
        }
        if ($inMinutes <= $graceCutoffMinutes) {
            return 'Late';
        }

        return 'Late Present';
    }

    /**
     * 3-Tier Arrival Classification (#UserProposal2), now date-aware.
     *
     * The office hours used are the *effective* ones for that date: the standing
     * policy from Workspace Settings, replaced by a one-day exception when the
     * Partner declared one (hartal, storm, half-day).
     */
    public function classifyArrivalStatus(string $timeHi, ?string $dateYmd = null): string
    {
        $hours = $this->effectiveOfficeHoursFor($dateYmd);

        return $this->classifyWithHours($timeHi, $hours['start'], (int) $hours['grace'], (bool) $hours['late_exempt']);
    }

    public function getGraceEndFormattedProperty(): string
    {
        try {
            $hours = $this->effectiveOfficeHoursFor();
            [$startH, $startM] = array_map('intval', explode(':', $hours['start'] ?: '09:30'));
            $total = ($startH * 60) + $startM + max(0, (int) $hours['grace']);

            return sprintf('%02d:%02d', intdiv($total, 60) % 24, $total % 60);
        } catch (\Throwable) {
            return '09:40';
        }
    }

    // ─── Working Day Exceptions (hartal / late opening / early close) ───────

    /**
     * The exception declared for one date, if any.
     *
     * A unit-specific exception beats the firm-wide one, because a single
     * Partner Unit is allowed to keep a different schedule.
     */
    public function workingDayExceptionFor(?string $dateYmd = null): ?array
    {
        $date = $dateYmd ?: now()->timezone('Asia/Dhaka')->toDateString();

        if (Schema::hasTable('practice_holidays')) {
            try {
                $firmModel = $this->resolveFirmModel();
                if ($firmModel) {
                    $row = PracticeHoliday::where('firm_id', $firmModel->id)
                        ->whereDate('holiday_date', $date)
                        ->where('category', PracticeHoliday::CATEGORY_EXCEPTION)
                        ->where(function ($q) {
                            $q->whereNull('partner_unit')
                                ->orWhere('partner_unit', $this->partnerUnit);
                        })
                        // Unit-specific first: false sorts before true on both
                        // MySQL and SQLite, so `partner_unit IS NULL` ascending
                        // puts the unit row at the top.
                        ->orderByRaw('partner_unit IS NULL')
                        ->first();

                    if ($row) {
                        return $this->normaliseWorkingDayException($row);
                    }
                }
            } catch (\Throwable $e) { \Illuminate\Support\Facades\Log::debug($e->getMessage()); }
        }

        foreach ($this->holidaysList as $h) {
            if (($h['holiday_date'] ?? '') !== $date || empty($h['is_working_day_exception'])) {
                continue;
            }
            $unit = $h['partner_unit'] ?? 'All Units';
            if ($unit !== 'All Units' && $unit !== $this->partnerUnit) {
                continue;
            }

            return $this->normaliseWorkingDayException($h);
        }

        return null;
    }

    /** One shape whether the source is an Eloquent row or the in-memory array. */
    protected function normaliseWorkingDayException(PracticeHoliday|array $row): array
    {
        $get = fn (string $key) => $row instanceof PracticeHoliday ? ($row->{$key} ?? null) : ($row[$key] ?? null);

        $unit = $row instanceof PracticeHoliday
            ? ($row->partner_unit ?: 'All Units')
            : ($row['partner_unit'] ?? 'All Units');

        $start = trim((string) ($get('office_start_override') ?? '')) ?: null;
        $end = trim((string) ($get('office_end_override') ?? '')) ?: null;
        $grace = $get('grace_minutes_override');

        return [
            'id'            => (int) ($get('id') ?? 0),
            'date'          => $row instanceof PracticeHoliday
                ? ($row->holiday_date?->format('Y-m-d') ?? '')
                : (string) ($row['holiday_date'] ?? ''),
            'title'         => (string) ($get('title') ?? ''),
            'reason'        => (string) ($get('override_reason') ?? ''),
            'partner_unit'  => $unit,
            'start'         => $start,
            'end'           => $end,
            'grace'         => $grace === null || $grace === '' ? null : (int) $grace,
            'late_exempt'   => (bool) ($get('late_exempt') ?? false),
        ];
    }

    /**
     * The hours actually in force on a date: standing policy, overridden by that
     * date's exception. Every lateness decision must go through here.
     */
    public function effectiveOfficeHoursFor(?string $dateYmd = null): array
    {
        $date = $dateYmd ?: now()->timezone('Asia/Dhaka')->toDateString();
        $exception = $this->workingDayExceptionFor($date);

        $start = ($exception['start'] ?? null) ?: ($this->officeStart ?: '10:00');
        $end = ($exception['end'] ?? null) ?: ($this->officeEnd ?: '18:00');
        $grace = $exception !== null && $exception['grace'] !== null
            ? (int) $exception['grace']
            : max(0, (int) $this->graceMinutes);
        $lateExempt = (bool) ($exception['late_exempt'] ?? false);

        $parts = [];
        if ($exception !== null) {
            if ($exception['start'] || $exception['end']) {
                $parts[] = 'Office ' . $start . ' – ' . $end;
            }
            if ($lateExempt) {
                $parts[] = 'lateness not counted';
            }
            if ($exception['grace'] !== null) {
                $parts[] = 'grace ' . $grace . ' min';
            }
        }

        return [
            'date'         => $date,
            'start'        => $start,
            'end'          => $end,
            'grace'        => $grace,
            'late_exempt'  => $lateExempt,
            'is_exception' => $exception !== null,
            'title'        => (string) ($exception['title'] ?? ''),
            'reason'       => (string) ($exception['reason'] ?? ''),
            'label'        => implode(' · ', $parts),
        ];
    }

    /** Refresh the banner data for today after any exception change. */
    public function refreshTodayWorkException(): void
    {
        $hours = $this->effectiveOfficeHoursFor();
        $this->todayWorkException = $hours['is_exception'] ? $hours : [];
        $this->todayWorkHoursLabel = $hours['start'] . ' – ' . $hours['end'];
    }

    /**
     * Every attendance row on this date, re-derived from its recorded check-in
     * time under the given hours. Nothing is invented: a row with no `in_time`
     * is skipped, and the stored arrival is never moved.
     *
     * @return array{scanned:int, changed:int, rows:array<int, array{name:string, from:string, to:string, in_time:string}>}
     */
    protected function reclassifyDateStatuses(string $dateYmd, string $startHi, int $graceMinutes, bool $lateExempt, bool $dryRun): array
    {
        $result = ['scanned' => 0, 'changed' => 0, 'rows' => []];

        if (! Schema::hasTable('attendances') || ! Schema::hasTable('users')) {
            return $result;
        }

        try {
            $firmModel = $this->resolveFirmModel();
            if (! $firmModel) {
                return $result;
            }

            $rows = Attendance::where('firm_id', $firmModel->id)
                ->whereDate('date', $dateYmd)
                ->get();

            if ($rows->isEmpty()) {
                return $result;
            }

            $names = User::whereIn('id', $rows->pluck('user_id')->all())->pluck('name', 'id');

            foreach ($rows as $att) {
                if (empty($att->in_time)) {
                    continue;
                }

                $result['scanned']++;
                $tz = $this->policyTimezone ?: 'Asia/Dhaka';
                $inHi = $att->in_time instanceof \DateTimeInterface
                    ? \Illuminate\Support\Carbon::instance($att->in_time)->timezone($tz)->format('H:i')
                    : substr((string) $att->in_time, 11, 5);

                $newStatus = $this->classifyWithHours($inHi, $startHi, $graceMinutes, $lateExempt);
                $oldStatus = (string) ($att->status ?: 'Right Time');

                if ($oldStatus === $newStatus) {
                    continue;
                }

                $result['changed']++;
                $result['rows'][] = [
                    'name'    => (string) ($names[$att->user_id] ?? ('User #' . $att->user_id)),
                    'from'    => $oldStatus,
                    'to'      => $newStatus,
                    'in_time' => $inHi,
                ];

                if (! $dryRun) {
                    $att->status = $newStatus;
                    $att->save();
                }
            }
        } catch (\Throwable $e) {
            Log::error('working-day-exception reclassify failed: ' . $e->getMessage(), ['date' => $dateYmd]);
        }

        return $result;
    }

    /** Draft hours from the form, used by the preview and by save. */
    protected function draftExceptionHours(): array
    {
        return [
            'start'       => trim($this->newExceptionStart) ?: ($this->officeStart ?: '10:00'),
            'end'         => trim($this->newExceptionEnd) ?: ($this->officeEnd ?: '18:00'),
            'grace'       => $this->newExceptionGrace === '' ? max(0, (int) $this->graceMinutes) : max(0, (int) $this->newExceptionGrace),
            'late_exempt' => $this->newExceptionLateExempt,
        ];
    }

    /**
     * "If I save this, whose status changes?" — shown before anything is written,
     * so a Partner is never surprised by a silent rewrite of the register.
     */
    public function previewWorkingDayException(): void
    {
        $this->error = null;

        if (! $this->validDate($this->newExceptionDate)) {
            $this->exceptionImpact = ['error' => 'Pick a date first, then preview the impact.'];
            return;
        }

        $draft = $this->draftExceptionHours();
        $impact = $this->reclassifyDateStatuses(
            $this->newExceptionDate,
            $draft['start'],
            (int) $draft['grace'],
            (bool) $draft['late_exempt'],
            true
        );

        $this->exceptionImpact = $impact + [
            'date'        => $this->newExceptionDate,
            'start'       => $draft['start'],
            'end'         => $draft['end'],
            'grace'       => (int) $draft['grace'],
            'late_exempt' => (bool) $draft['late_exempt'],
        ];
    }

    public function saveWorkingDayException(): void
    {
        $this->enforceAuthenticatedRoleLock();
        $this->error = null;
        $this->notice = null;
        $this->exceptionImpact = [];

        if (! $this->userCanManagePolicies()) {
            $this->error = 'Only a Partner or an authorized Manager (with Policy permission) can change office hours or excuse lateness for a date.';
            return;
        }

        if (! Schema::hasTable('practice_holidays') || ! Schema::hasColumn('practice_holidays', 'late_exempt')) {
            $this->error = 'The working-day exception columns are missing. Run "php artisan migrate --force" on the server, then try again.';
            return;
        }

        if (! $this->validDate($this->newExceptionDate)) {
            $this->error = 'Please choose a valid date for the working day exception.';
            return;
        }

        if (mb_strlen(trim($this->newExceptionTitle)) < 2) {
            $this->error = 'Give the exception a short name, e.g. "Hartal — office opens at 12:00".';
            return;
        }

        $timePattern = '/^([01]?\d|2[0-3]):[0-5]\d$/';
        $start = trim($this->newExceptionStart);
        $end = trim($this->newExceptionEnd);

        if ($start !== '' && ! preg_match($timePattern, $start)) {
            $this->error = 'Office start must look like 12:00 (24-hour clock).';
            return;
        }
        if ($end !== '' && ! preg_match($timePattern, $end)) {
            $this->error = 'Office end must look like 16:00 (24-hour clock).';
            return;
        }
        if ($start !== '' && $end !== '' && $end <= $start) {
            $this->error = 'Office end must be later than office start.';
            return;
        }

        $grace = trim($this->newExceptionGrace);
        if ($grace !== '' && (! ctype_digit($grace) || (int) $grace > 240)) {
            $this->error = 'Grace period must be a whole number of minutes between 0 and 240.';
            return;
        }

        if ($start === '' && $end === '' && $grace === '' && ! $this->newExceptionLateExempt) {
            $this->error = 'A working day exception needs at least one effect: a start time, an end time, a grace period, or "lateness not counted".';
            return;
        }

        $firmModel = $this->resolveFirmModel();
        if (! $firmModel) {
            $this->error = 'Workspace not found. Please reload the page.';
            return;
        }

        $unit = $this->newExceptionAllUnits ? null : $this->partnerUnit;
        $draft = $this->draftExceptionHours();

        try {
            $row = PracticeHoliday::updateOrCreate(
                [
                    'firm_id'      => $firmModel->id,
                    'holiday_date' => $this->newExceptionDate,
                    'partner_unit' => $unit,
                ],
                [
                    'title'                   => trim($this->newExceptionTitle),
                    'category'                => PracticeHoliday::CATEGORY_EXCEPTION,
                    'is_office_open_override' => false,
                    'absent_deduction_days'   => 0,
                    'override_reason'         => trim($this->newExceptionReason) ?: null,
                    'office_start_override'   => $start !== '' ? $start : null,
                    'office_end_override'     => $end !== '' ? $end : null,
                    'grace_minutes_override'  => $grace !== '' ? (int) $grace : null,
                    'late_exempt'             => $this->newExceptionLateExempt,
                    'created_by'              => Auth::id(),
                ]
            );
        } catch (\Throwable $e) {
            Log::error('working-day-exception save failed: ' . $e->getMessage());
            $this->error = 'Could not save the working day exception. Please try again.';
            return;
        }

        // Rewrite today's / that day's register from the recorded check-in times
        // so the exception actually reaches the people it was declared for.
        $impact = $this->reclassifyDateStatuses(
            $this->newExceptionDate,
            $draft['start'],
            (int) $draft['grace'],
            (bool) $draft['late_exempt'],
            false
        );
        $this->exceptionImpact = $impact + [
            'date'        => $this->newExceptionDate,
            'start'       => $draft['start'],
            'end'         => $draft['end'],
            'grace'       => (int) $draft['grace'],
            'late_exempt' => (bool) $draft['late_exempt'],
            'saved'       => true,
        ];

        $this->recordAuditLog(
            'policy',
            'working_day_exception_saved',
            "Working day exception for {$this->newExceptionDate} ({$row->title}): {$row->effectLabel()}."
                . ($impact['changed'] > 0 ? " Re-classified {$impact['changed']} attendance row(s)." : ''),
            $this->newExceptionDate
        );

        if ($impact['changed'] > 0) {
            $this->createPracticeNotification(
                null,
                null,
                'Office Timing Updated for ' . $this->newExceptionDate,
                "Partner declared a working day exception: {$row->title} ({$row->effectLabel()}). Your attendance for that date has been re-checked under the new timing.",
                'clock-3',
                null,
                true
            );
        }

        $reopen = $this->payrollPeriodLockedFor(substr($this->newExceptionDate, 0, 7));

        $this->newExceptionDate = '';
        $this->newExceptionTitle = '';
        $this->newExceptionStart = '';
        $this->newExceptionEnd = '';
        $this->newExceptionGrace = '';
        $this->newExceptionLateExempt = false;
        $this->newExceptionReason = '';
        $this->newExceptionAllUnits = true;

        $this->loadHolidays();
        $this->refreshTodayWorkException();
        $this->loadMonthlyCalendarData();
        $this->loadAttendanceRows();
        $this->loadPayrollRows();

        $this->dispatch('toast', [
            'type'    => 'success',
            'title'   => 'Working Day Exception Saved',
            'message' => "{$row->title} — {$row->effectLabel()}.",
        ]);

        $this->notice = "Working day exception saved for {$row->title}: {$row->effectLabel()}."
            . ($impact['changed'] > 0 ? " {$impact['changed']} attendance record(s) re-classified." : '')
            . ($reopen ? " Note: this month's payroll has already been reviewed or paid — reopen the payroll period before regenerating it." : '');
    }

    public function deleteWorkingDayException(int $holidayId): void
    {
        $this->enforceAuthenticatedRoleLock();

        if (! $this->userCanManagePolicies()) {
            $this->error = 'Only a Partner or an authorized Manager (with Policy permission) can remove a working day exception.';
            return;
        }

        $firmModel = $this->resolveFirmModel();
        $date = null;

        if ($firmModel && Schema::hasTable('practice_holidays')) {
            try {
                $row = PracticeHoliday::where('firm_id', $firmModel->id)->where('id', $holidayId)->first();
                if ($row) {
                    $date = $row->holiday_date?->format('Y-m-d');
                    $title = $row->title;
                    $row->delete();
                    $this->recordAuditLog('policy', 'working_day_exception_removed', "Removed working day exception for {$date} ({$title}).", $date);
                }
            } catch (\Throwable $e) { \Illuminate\Support\Facades\Log::debug($e->getMessage()); }
        }

        $this->loadHolidays();
        $this->refreshTodayWorkException();
        $this->loadMonthlyCalendarData();
        $this->notice = $date
            ? "Working day exception for {$date} removed. Attendance rows keep their current status — adjust them manually if needed."
            : 'Working day exception removed.';
    }

    /** Is this month's payroll already reviewed/approved/paid? */
    protected function payrollPeriodLockedFor(string $yearMonth): bool
    {
        if (! Schema::hasTable('payroll_records')) {
            return false;
        }

        try {
            $firmModel = $this->resolveFirmModel();
            if (! $firmModel) {
                return false;
            }

            return PayrollRecord::where('firm_id', $firmModel->id)
                ->where('period', $yearMonth)
                ->whereIn('status', ['reviewed', 'approved', 'paid'])
                ->exists();
        } catch (\Throwable) {
            return false;
        }
    }

    public function dismissCelebration(): void
    {
        $this->showCheckinCelebration = false;
        $this->celebrationData = null;
    }

    /**
     * Calculate Haversine distance in kilometers between two GPS coordinates (#4).
     */
    protected function calculateDistanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadiusKm = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) * sin($dLat / 2)
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) * sin($dLng / 2);
        $c = 2 * atan2(sqrt($a), sqrt(max(0.0, 1.0 - $a)));
        return round($earthRadiusKm * $c, 2);
    }

    /**
     * Resolve target GPS coordinates and allowed radius for Office or Client Site.
     */
    protected function resolveTargetGps(string $mode, ?string $clientName): array
    {
        if (strtolower($mode) === 'client' && ! empty($clientName) && Schema::hasTable('audit_clients')) {
            try {
                $firmModel = $this->resolveFirmModel();
                if ($firmModel) {
                    $client = AuditClient::where('firm_id', $firmModel->id)->where('name', $clientName)->first();
                    if ($client && $client->target_lat !== null && $client->target_lng !== null) {
                        return [
                            'lat'    => (float) $client->target_lat,
                            'lng'    => (float) $client->target_lng,
                            'radius' => (float) ($client->allowed_radius_km ?: max(0.05, $this->geofenceRadiusKm)),
                        ];
                    }
                }
            } catch (\Throwable $e) { \Illuminate\Support\Facades\Log::debug($e->getMessage()); }
            return ['lat' => 23.7279, 'lng' => 90.4178, 'radius' => max(0.05, (float) $this->geofenceRadiusKm)];
        }
        return ['lat' => 23.7516, 'lng' => 90.3943, 'radius' => max(0.05, (float) $this->geofenceRadiusKm)];
    }

    // ─── Government & Custom Holiday Calendar + Office Open Override (#2) ────

    public function loadHolidays(): void
    {
        if (Schema::hasTable('practice_holidays')) {
            try {
                $firmModel = $this->resolveFirmModel();
                if ($firmModel) {
                    $rows = PracticeHoliday::where('firm_id', $firmModel->id)
                        ->where(function ($q) {
                            $q->whereNull('partner_unit')
                                ->orWhere('partner_unit', $this->partnerUnit);
                        })
                        ->orderBy('holiday_date')
                        ->get();

                    if ($rows->isNotEmpty()) {
                        $this->holidaysList = $rows->map(fn (PracticeHoliday $h) => [
                            'id'                      => $h->id,
                            'holiday_date'            => $h->holiday_date?->format('Y-m-d') ?? '',
                            'display_date'            => $h->holiday_date?->format('d M Y (D)') ?? '',
                            'title'                   => $h->title,
                            'category'                => $h->category,
                            'partner_unit'            => $h->partner_unit ?: 'All Units',
                            'is_office_open_override' => (bool) $h->is_office_open_override,
                            'absent_deduction_days'   => (float) $h->absent_deduction_days,
                            'override_reason'         => $h->override_reason ?? '',
                            // Working day exceptions are *working* days with
                            // different hours — never painted as holidays.
                            'is_working_day_exception' => $h->isWorkingDayException(),
                            'late_exempt'             => (bool) $h->late_exempt,
                            'office_start_override'   => $h->office_start_override,
                            'office_end_override'     => $h->office_end_override,
                            'grace_minutes_override'  => $h->grace_minutes_override,
                            'effect_label'            => $h->effectLabel(),
                        ])->toArray();
                        return;
                    }
                }
            } catch (\Throwable $e) { \Illuminate\Support\Facades\Log::debug($e->getMessage()); }
        }

        $defaults = PracticeHoliday::defaultGovernmentHolidays($this->holidayYear);
        $this->holidaysList = array_map(function (array $d, int $idx) {
            return [
                'id'                      => $idx + 1,
                'holiday_date'            => $d['holiday_date'],
                'display_date'            => date('d M Y (D)', strtotime($d['holiday_date'])),
                'title'                   => $d['title'],
                'category'                => $d['category'],
                'partner_unit'            => 'All Units',
                'is_office_open_override' => false,
                'absent_deduction_days'   => 1.00,
                'override_reason'         => '',
                'is_working_day_exception' => false,
                'late_exempt'             => false,
                'office_start_override'   => null,
                'office_end_override'     => null,
                'grace_minutes_override'  => null,
                'effect_label'            => '',
            ];
        }, $defaults, array_keys($defaults));
    }

    public function seedAnnualGovernmentHolidays(): void
    {
        $this->enforceAuthenticatedRoleLock();
        if (! in_array($this->previewRole, ['partner', 'manager'], true)) {
            $this->error = 'Only a Partner or Manager can load or update the annual Government Holiday calendar.';
            return;
        }

        $firmModel = $this->resolveFirmModel();
        if ($firmModel && Schema::hasTable('practice_holidays')) {
            foreach (PracticeHoliday::defaultGovernmentHolidays($this->holidayYear) as $item) {
                PracticeHoliday::updateOrCreate(
                    [
                        'firm_id'      => $firmModel->id,
                        'holiday_date' => $item['holiday_date'],
                        'partner_unit' => null,
                    ],
                    [
                        'title'                   => $item['title'],
                        'category'                => 'government',
                        'is_office_open_override' => false,
                        'absent_deduction_days'   => 1.00,
                        'created_by'              => Auth::id(),
                    ]
                );
            }
            $this->recordAuditLog('policy', 'govt_holidays_seeded', "Loaded annual Bangladesh Government Holiday Calendar for {$this->holidayYear}.", (string) $this->holidayYear);
        }

        $this->loadHolidays();
        $this->notice = "Annual Bangladesh Government Holiday Calendar ({$this->holidayYear}) loaded and updated.";
    }

    public function addOrUpdateHoliday(): void
    {
        $this->enforceAuthenticatedRoleLock();
        $this->error = null;

        if (! in_array($this->previewRole, ['partner', 'manager'], true)) {
            $this->error = 'Only a Partner or Manager can add holidays or declare a Holiday as an Open Working Day.';
            return;
        }

        if (! $this->validDate($this->newHolidayDate) || mb_strlen(trim($this->newHolidayTitle)) < 2) {
            $this->error = 'Please enter a valid date and holiday / special working day title.';
            return;
        }

        $firmModel = $this->resolveFirmModel();
        if ($firmModel && Schema::hasTable('practice_holidays')) {
            $h = PracticeHoliday::updateOrCreate(
                [
                    'firm_id'      => $firmModel->id,
                    'holiday_date' => $this->newHolidayDate,
                    'partner_unit' => $this->newHolidayIsOfficeOpen ? $this->partnerUnit : null,
                ],
                [
                    'title'                   => trim($this->newHolidayTitle),
                    'category'                => $this->newHolidayIsOfficeOpen ? 'weekend_override' : $this->newHolidayCategory,
                    'is_office_open_override' => $this->newHolidayIsOfficeOpen,
                    'absent_deduction_days'   => max(0, (float) $this->newHolidayAbsentCutDays),
                    'override_reason'         => trim($this->newHolidayOverrideReason) ?: null,
                    'created_by'              => Auth::id(),
                ]
            );

            $actionLabel = $this->newHolidayIsOfficeOpen
                ? "Declared {$this->newHolidayDate} ({$h->title}) as MANDATORY OPEN WORKING DAY (Absent cut: {$h->absent_deduction_days}d)"
                : "Added holiday {$this->newHolidayDate} ({$h->title})";
            $this->recordAuditLog('policy', 'holiday_updated', $actionLabel, $this->newHolidayDate);

            if ($this->newHolidayIsOfficeOpen) {
                $this->createPracticeNotification(
                    null,
                    null,
                    "Office Open on Holiday ({$this->newHolidayDate})",
                    "Partner {$this->partnerUnit} declared {$this->newHolidayDate} ({$h->title}) as a mandatory working day. Absence without approved leave will incur a {$h->absent_deduction_days}-day allowance deduction.",
                    'calendar-check-2',
                    null,
                    true
                );
            }
        } else {
            $this->holidaysList[] = [
                'id'                      => count($this->holidaysList) + 1,
                'holiday_date'            => $this->newHolidayDate,
                'display_date'            => date('d M Y (D)', strtotime($this->newHolidayDate)),
                'title'                   => trim($this->newHolidayTitle),
                'category'                => $this->newHolidayCategory,
                'partner_unit'            => $this->newHolidayIsOfficeOpen ? $this->partnerUnit : 'All Units',
                'is_office_open_override' => $this->newHolidayIsOfficeOpen,
                'absent_deduction_days'   => (float) $this->newHolidayAbsentCutDays,
                'override_reason'         => trim($this->newHolidayOverrideReason),
                'is_working_day_exception' => false,
                'late_exempt'             => false,
                'office_start_override'   => null,
                'office_end_override'     => null,
                'grace_minutes_override'  => null,
                'effect_label'            => '',
            ];
        }

        $this->newHolidayDate = '';
        $this->newHolidayTitle = '';
        $this->newHolidayOverrideReason = '';
        $this->loadHolidays();
        $this->loadPayrollRows();
        $this->notice = $this->newHolidayIsOfficeOpen
            ? 'Holiday / Weekend declared as Mandatory Open Office Day. Absent students will have automatic salary deduction applied.'
            : 'Holiday added to the practice calendar successfully.';
    }

    public function toggleHolidayOfficeOpenOverride(int $holidayId): void
    {
        $this->enforceAuthenticatedRoleLock();
        if (! in_array($this->previewRole, ['partner', 'manager'], true)) {
            $this->error = 'Only a Partner or Manager can override a holiday to keep the office open.';
            return;
        }

        $firmModel = $this->resolveFirmModel();
        if ($firmModel && Schema::hasTable('practice_holidays')) {
            $h = PracticeHoliday::where('firm_id', $firmModel->id)->where('id', $holidayId)->first();
            if ($h) {
                $h->is_office_open_override = ! $h->is_office_open_override;
                if ($h->is_office_open_override && $h->absent_deduction_days <= 0) {
                    $h->absent_deduction_days = 1.00;
                }
                $h->save();
                $this->recordAuditLog(
                    'policy',
                    'holiday_override_toggled',
                    "Partner toggled {$h->holiday_date?->format('Y-m-d')} ({$h->title}) to " . ($h->is_office_open_override ? 'OFFICE OPEN (Mandatory Attendance)' : 'CLOSED HOLIDAY') . '.',
                    $h->holiday_date?->format('Y-m-d')
                );
            }
        } else {
            foreach ($this->holidaysList as &$hl) {
                if ((int) $hl['id'] === $holidayId) {
                    $hl['is_office_open_override'] = ! $hl['is_office_open_override'];
                    break;
                }
            }
            unset($hl);
        }

        $this->loadHolidays();
        $this->loadPayrollRows();
        $this->notice = 'Holiday office-open status updated and payroll rules synchronized.';
    }

    public function deleteHoliday(int $holidayId): void
    {
        $this->enforceAuthenticatedRoleLock();
        if (! in_array($this->previewRole, ['partner', 'manager'], true)) {
            $this->error = 'Only a Partner or Manager can remove a calendar holiday.';
            return;
        }

        $firmModel = $this->resolveFirmModel();
        if ($firmModel && Schema::hasTable('practice_holidays')) {
            PracticeHoliday::where('firm_id', $firmModel->id)->where('id', $holidayId)->delete();
        } else {
            $this->holidaysList = array_values(array_filter($this->holidaysList, fn ($h) => (int) $h['id'] !== $holidayId));
        }

        $this->loadHolidays();
        $this->loadPayrollRows();
        $this->notice = 'Holiday removed from calendar.';
    }

    // ─── Multi-Segment Attendance (Close vs On-Duty Popup & Movement Timeline) ─

    public function loadPolicyProposals(): void
    {
        if (Schema::hasTable('policy_proposals')) {
            try {
                $firmModel = $this->resolveFirmModel();
                if ($firmModel) {
                    $proposals = PolicyProposal::where('firm_id', $firmModel->id)->orderByDesc('id')->limit(10)->get();
                    $this->policyProposals = $proposals->map(fn (PolicyProposal $p) => [
                        'id'                        => $p->id,
                        'title'                     => $p->title,
                        'description'               => $p->description,
                        'proposed_office_start'     => $p->proposed_office_start,
                        'proposed_office_end'       => $p->proposed_office_end,
                        'proposed_grace_minutes'    => $p->proposed_grace_minutes,
                        'proposed_conveyance_limit' => (float) $p->proposed_conveyance_limit,
                        'effective_date'            => $p->effective_date?->format('d M Y') ?? 'Immediate',
                        'required_votes'            => $p->required_votes,
                        'approvals'                 => $p->approvals ?? [],
                        'approval_count'            => is_array($p->approvals) ? count($p->approvals) : 0,
                        'status'                    => $p->status,
                    ])->toArray();
                    return;
                }
            } catch (\Throwable $e) { \Illuminate\Support\Facades\Log::debug($e->getMessage()); }
        }
        $this->policyProposals = [];
    }

    public function submitPolicyProposal(): void
    {
        $this->enforceAuthenticatedRoleLock();
        if (! $this->userCanManagePolicies()) {
            $this->error = 'Only a Partner or an authorized Manager can propose company-wide policy amendments.';
            return;
        }

        $firmModel = $this->resolveFirmModel();
        if ($firmModel && Schema::hasTable('policy_proposals')) {
            try {
                $partnerCount = max(1, User::where('firm_id', $firmModel->id)->where('role', 'partner')->count());
                $requiredVotes = max(3, $partnerCount);
                $voterName = Auth::user()?->name ?? 'Partner Unit 01';

                $proposal = PolicyProposal::create([
                    'firm_id'                   => $firmModel->id,
                    'proposed_by'               => Auth::id(),
                    'title'                     => trim($this->proposalTitle) ?: "Policy Amendment ({$this->officeStart}–{$this->officeEnd}, Limit ৳{$this->managerConveyanceLimit})",
                    'description'               => trim($this->proposalDescription) ?: "Proposed update to company-wide baseline hours and conveyance thresholds.",
                    'proposed_office_start'     => $this->officeStart,
                    'proposed_office_end'       => $this->officeEnd,
                    'proposed_grace_minutes'    => $this->graceMinutes,
                    'proposed_conveyance_limit' => $this->managerConveyanceLimit,
                    'effective_date'            => $this->validDate($this->proposalEffectiveDate) ? $this->proposalEffectiveDate : now()->addDays(7)->toDateString(),
                    'required_votes'            => $requiredVotes,
                    'approvals'                 => [$voterName],
                    'status'                    => 'pending_consensus',
                ]);

                $this->recordAuditLog('policy', 'policy_proposed', "Partner {$voterName} submitted company-wide policy proposal #{$proposal->id}.", 'PROP-' . $proposal->id);
                $this->loadPolicyProposals();
                $this->closeDialogs();
                $this->proposalTitle = '';
                $this->proposalDescription = '';
                $this->notice = 'Company policy proposal drafted and circulated to Partner consensus queue.';
                return;
            } catch (\Throwable $e) { \Illuminate\Support\Facades\Log::debug($e->getMessage()); }
        }

        $this->closeDialogs();
        $this->notice = Auth::check()
            ? 'Company policy proposal drafted and circulated to Partner consensus queue.'
            : 'Policy proposal illustrated only. No partner votes, approvals or policy revisions were saved.';
    }

    public function voteOnPolicyProposal(int $proposalId): void
    {
        $this->enforceAuthenticatedRoleLock();
        if ($this->previewRole !== 'partner') {
            $this->error = 'Only a Partner can cast a governance consensus vote.';
            return;
        }

        $firmModel = $this->resolveFirmModel();
        if ($firmModel && Schema::hasTable('policy_proposals')) {
            $proposal = PolicyProposal::where('firm_id', $firmModel->id)->where('id', $proposalId)->first();
            if ($proposal && $proposal->status === 'pending_consensus') {
                $voterName = Auth::user()?->name ?? ('Partner (' . $this->partnerUnit . ')');
                $approvals = is_array($proposal->approvals) ? $proposal->approvals : [];
                if (! in_array($voterName, $approvals, true)) {
                    $approvals[] = $voterName;
                }
                $proposal->approvals = $approvals;

                $activePartnerCount = max(1, User::where('firm_id', $firmModel->id)->where('role', 'partner')->count());
                if (count($approvals) >= min($proposal->required_votes, $activePartnerCount)) {
                    $proposal->status = 'enacted';
                }
                $proposal->save();

                $this->recordAuditLog('policy', 'policy_vote_recorded', "Partner {$voterName} recorded approval vote on proposal #{$proposal->id} ( Status: {$proposal->status} ).", 'PROP-' . $proposal->id);
                $this->loadPolicyProposals();
                $this->notice = $proposal->status === 'enacted'
                    ? "Unanimous Partner consensus reached! Policy proposal #{$proposal->id} is now enacted."
                    : "Partner vote recorded for proposal #{$proposal->id}.";
            }
        }
    }

    public function loadAuditLogs(): void
    {
        if (Schema::hasTable('audit_logs')) {
            try {
                $firmModel = $this->resolveFirmModel();
                if ($firmModel) {
                    $this->auditLogsList = AuditLog::where('firm_id', $firmModel->id)
                        ->orderByDesc('id')
                        ->limit(10)
                        ->get()
                        ->map(fn (AuditLog $log) => [
                            'id'         => $log->id,
                            'actor_name' => $log->actor_name,
                            'actor_role' => $log->actor_role,
                            'module'     => ucfirst($log->module),
                            'action'     => $log->action,
                            'target_ref' => $log->target_ref,
                            'summary'    => $log->summary,
                            'time'       => $log->created_at?->format('d M H:i') ?? 'Recent',
                        ])->toArray();
                    return;
                }
            } catch (\Throwable $e) { \Illuminate\Support\Facades\Log::debug($e->getMessage()); }
        }
        $this->auditLogsList = [];
    }

    public function dismissNotice(): void
    {
        $this->notice = null;
        $this->error = null;
    }

    /**
     * Invalidate every other signed-in device after a credential change.
     *
     * The audit (P1-3.2) found that changing a password left every stolen
     * session alive, so a compromised laptop kept full access for the whole
     * session lifetime. This kills the other session rows and the remember-me
     * token while keeping the device that just made the change signed in.
     */
    protected function revokeOtherSessions(User $user): void
    {
        try {
            $user->remember_token = null;
            $user->saveQuietly();

            if (Schema::hasTable('sessions') && Schema::hasColumn('sessions', 'user_id')) {
                $currentId = session()->getId();

                DB::table('sessions')
                    ->where('user_id', $user->id)
                    ->when($currentId !== '', fn ($q) => $q->where('id', '!=', $currentId))
                    ->delete();
            }
        } catch (\Throwable $e) {
            Log::warning('Could not revoke other sessions: ' . $e->getMessage());
        }
    }
}
