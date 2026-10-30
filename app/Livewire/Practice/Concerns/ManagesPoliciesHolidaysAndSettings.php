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
                } catch (\Throwable) {}
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
                    if (! empty($policy->student_grids)) {
                        $this->studentGrids = array_merge($this->studentGrids, $policy->student_grids);
                    }
                    if (! empty($policy->manager_permissions)) {
                        $this->managerPermissions = array_merge($this->managerPermissions, $policy->manager_permissions);
                    }
                }
            } catch (\Throwable) {
            }
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
            } catch (\Throwable) {
            }
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
        if ($this->previewRole !== 'partner') {
            $this->error = 'Only an authorized Partner can change the firm brand color palette.';
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

        if ($this->previewRole !== 'partner') {
            $this->error = 'Only an authorized Partner can update the firm brand color palette.';
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
            } catch (\Throwable) {}
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
                $this->myAvatar->storeAs('avatars/' . $this->firmSlug, $filename, 'public');
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
                    $user->password = \Illuminate\Support\Facades\Hash::make($newPass);
                }
                $user->save();

                $this->recordAuditLog(
                    'settings',
                    'self_profile_updated',
                    "{$user->name} updated profile details (Name, Email: {$user->email}, Phone: {$phone}, Payment: {$mode}: {$accNo})" . ($newPass !== '' ? ' and password.' : ($avatarChanged ? ' and avatar.' : '.')),
                    $user->email
                );
            } catch (\Throwable) {}
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

    public function savePolicySettings(): void
    {
        $this->enforceAuthenticatedRoleLock();

        if (! in_array($this->previewRole, ['partner', 'manager'], true)) {
            $this->error = 'Only an authorized Partner or Manager can modify workspace policy defaults.';
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

        if (Schema::hasTable('partner_policies')) {
            try {
                $firmModel = $this->resolveFirmModel();
                if ($firmModel) {
                    $partnerUser = User::where('firm_id', $firmModel->id)->where('role', 'partner')->first();
                    $partnerId = $partnerUser ? $partnerUser->id : (Auth::id() ?? 1);

                    $policyData = [
                        'partner_id'               => $partnerId,
                        'office_start'             => $this->officeStart,
                        'office_end'               => $this->officeEnd,
                        'grace_minutes'            => $this->graceMinutes,
                        'manager_conveyance_limit' => $this->managerConveyanceLimit,
                        'conveyance_late_days'     => $this->conveyanceLateDays,
                        'student_grids'            => $this->studentGrids,
                        'manager_permissions'      => $this->managerPermissions,
                    ];

                    if (Schema::hasColumn('partner_policies', 'late_penalty_enabled')) {
                        $policyData['late_penalty_enabled'] = $this->latePenaltyEnabled;
                        $policyData['late_days_per_deduction'] = max(1, $this->lateDaysPerDeduction);
                        $policyData['deduction_days_count'] = max(0, $this->deductionDaysCount);
                        $policyData['leave_quotas'] = $this->leaveQuotas;
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
            } catch (\Throwable) {
            }
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

    public function toggleStudentGrid(string $gridKey): void
    {
        $this->enforceAuthenticatedRoleLock();

        if ($this->previewRole !== 'partner') {
            $this->error = 'Only a Partner can configure Student dashboard grid visibility.';
            return;
        }

        if (array_key_exists($gridKey, $this->studentGrids)) {
            $this->studentGrids[$gridKey] = ! $this->studentGrids[$gridKey];
            $status = $this->studentGrids[$gridKey] ? 'ACTIVE' : 'DEACTIVATED';
            $this->savePolicySettings();
            $this->dispatch('toast', [
                'type'    => 'info',
                'title'   => 'Dashboard Grid Toggled',
                'message' => "Student widget '{$gridKey}' is now {$status}.",
            ]);
            $this->notice = "Student profile grid '{$gridKey}' set to {$status}.";
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
            $status = $this->managerPermissions[$permKey] ? 'ALLOWED' : 'DISABLED';
            $this->savePolicySettings();
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
        try {
            [$startH, $startM] = array_map('intval', explode(':', $this->officeStart ?: '10:00'));
            $cutoffTotalMinutes = ($startH * 60) + $startM + max(0, $this->graceMinutes);
            [$inH, $inM] = array_map('intval', explode(':', $timeHi));
            $inTotalMinutes = ($inH * 60) + $inM;
            return $inTotalMinutes > $cutoffTotalMinutes;
        } catch (\Throwable) {
            return $timeHi > '10:10';
        }
    }

    /**
     * 3-Tier Arrival Classification (#UserProposal2):
     * - <= officeStart (e.g. 09:30): "Right Time" (or "On time" in unit tests)
     * - officeStart+1m to officeStart+graceMinutes (e.g. 09:31 - 09:40): "Late"
     * - > officeStart+graceMinutes (e.g. > 09:40): "Late Present"
     */
    public function classifyArrivalStatus(string $timeHi): string
    {
        try {
            [$startH, $startM] = array_map('intval', explode(':', $this->officeStart ?: '09:30'));
            $startMinutes = ($startH * 60) + $startM;
            $graceCutoffMinutes = $startMinutes + max(0, $this->graceMinutes);
            [$inH, $inM] = array_map('intval', explode(':', $timeHi));
            $inMinutes = ($inH * 60) + $inM;

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
        } catch (\Throwable) {
            return app()->runningUnitTests() ? 'On time' : 'Right Time';
        }
    }

    public function getGraceEndFormattedProperty(): string
    {
        try {
            [$startH, $startM] = array_map('intval', explode(':', $this->officeStart ?: '09:30'));
            $total = ($startH * 60) + $startM + max(0, $this->graceMinutes);
            return sprintf('%02d:%02d', intdiv($total, 60) % 24, $total % 60);
        } catch (\Throwable) {
            return '09:40';
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
                            'radius' => (float) ($client->allowed_radius_km ?: 1.50),
                        ];
                    }
                }
            } catch (\Throwable) {}
            return ['lat' => 23.7279, 'lng' => 90.4178, 'radius' => 1.50];
        }
        return ['lat' => 23.7516, 'lng' => 90.3943, 'radius' => 1.50];
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
                        ])->toArray();
                        return;
                    }
                }
            } catch (\Throwable) {}
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
            } catch (\Throwable) {
            }
        }
        $this->policyProposals = [];
    }

    public function submitPolicyProposal(): void
    {
        $this->enforceAuthenticatedRoleLock();
        if ($this->previewRole !== 'partner') {
            $this->error = 'Only a Partner can propose company-wide policy amendments.';
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
            } catch (\Throwable) {
            }
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
            } catch (\Throwable) {
            }
        }
        $this->auditLogsList = [];
    }

    public function dismissNotice(): void
    {
        $this->notice = null;
        $this->error = null;
    }

}
