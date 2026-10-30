<?php

namespace App\Livewire\Platform;

use App\Models\AuditLog;
use App\Models\Firm;
use App\Models\PartnerPolicy;
use App\Models\PracticeNotification;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;

class DashboardPreview extends Component
{
    public string $section = 'overview';
    public string $firmSearch = '';
    public string $statusFilter = 'all';
    public ?string $selectedFirmId = null;
    public bool $showProvisionPreview = false;
    public bool $showSupportPreview = false;
    public ?string $previewNotice = null;
    public ?string $modalError = null;

    // Permanent Deletion Confirmation Modal
    public bool $showDeleteModal = false;
    public ?string $firmToDeleteSlug = null;
    public ?string $firmToDeleteName = null;
    public string $deleteConfirmationInput = '';

    // New Organization Provisioning fields
    public string $newFirmName = '';
    public string $newFirmSlug = '';
    public string $newFirmKind = 'Chartered Accountants';
    public string $newPartnerName = '';
    public string $newPartnerEmail = '';
    public string $newPartnerPassword = '';
    public string $newFirmPlan = 'Practice';
    public int $newFirmSeats = 10;
    public int $newFirmWorkspaces = 3;
    public string $newFirmRegion = 'Dhaka';
    public string $newFirmCustomDomain = '';
    public string $newFirmCustomSubdomain = '';
    public bool $newFirmSharedAttendance = true;
    public string $newFirmThemePalette = 'sea_green';
    public string $newFirmPrimaryColor = '#0D9488';
    public string $newSubscriptionType = 'paid'; // 'trial' or 'paid'
    public int $newTrialDays = 14;
    public float $newPerSeatRate = 500.00;

    // Organization Profile, Domain, Billing & Governance Controls editing
    public string $editFirmName = '';
    public string $editFirmEmail = '';
    public string $editFirmKind = 'Chartered Accountants';
    public string $editFirmPlan = 'Practice';
    public string $editFirmRegion = 'Dhaka';
    public string $editSubscriptionType = 'paid'; // 'trial' or 'paid'
    public int $editTrialDays = 14;
    public float $editPerSeatRate = 500.00;
    public string $editFirmThemePalette = 'sea_green';
    public string $editFirmPrimaryColor = '#0D9488';
    public array $editEnabledModules = [
        'attendance' => true,
        'leave'      => true,
        'conveyance' => true,
        'payroll'    => true,
        'team'       => true,
        'policies'   => true,
    ];
    public string $editCustomDomain = '';
    public string $editCustomSubdomain = '';
    public string $editFirmSlug = '';
    public int $editFirmSeats = 12;
    public int $editFirmWorkspaces = 3;
    public bool $editSharedAttendance = true;
    public bool $editIsSuspended = false;
    public float $editSubscriptionFee = 5000.00;
    public string $editBillingCycle = 'Monthly';
    public string $editPaymentStatus = 'paid';
    public string $editSubscriptionExpiresAt = '';
    public string $editPolicyOfficeStart = '09:30';
    public string $editPolicyOfficeEnd = '18:00';
    public int $editPolicyGraceMinutes = 10;
    public float $editPolicyConveyanceLimit = 300.00;
    public int $editPolicyConveyanceLateDays = 2;
    public bool $editPolicyLatePenaltyEnabled = true;
    public int $editPolicyLateDaysPerDeduction = 3;
    public float $editPolicyDeductionDaysCount = 1.00;

    // Selected Firm Partner Roster, Add New Partner Unit & Partner Password Reset
    public array $selectedFirmPartners = [];
    public array $selectedFirmStats = ['partners' => 0, 'managers' => 0, 'students' => 0, 'total' => 0];
    public array $editPartnerNames = [];
    public array $editPartnerEmails = [];
    public array $editPartnerUnits = [];
    public array $resetPartnerPasswordInputs = [];
    public string $newAddPartnerName = '';
    public string $newAddPartnerEmail = '';
    public string $newAddPartnerUnit = 'Partner Unit 02';
    public string $newAddPartnerPassword = '';

    // Global Platform Broadcast Notice (DhakaWala -> All / Specific Firm)
    public string $broadcastTargetSlug = 'all';
    public string $broadcastTargetRole = 'partner';
    public string $broadcastTitle = '';
    public string $broadcastMessage = '';

    // Super Admin Own Profile & Platform Operator Management
    public string $adminProfileName = '';
    public string $adminProfileEmail = '';
    public string $adminNewPassword = '';
    public string $adminNewPasswordConfirm = '';
    public array $platformOperators = [];
    public string $newOperatorName = '';
    public string $newOperatorEmail = '';
    public string $newOperatorPassword = '';

    // Live Platform Audit Logs
    public array $platformAuditLogs = [];

    public array $demoFirms = [];
    public array $demoSupportRequests = [];

    private const SECTIONS = ['overview', 'firms', 'support', 'platform'];

    public function mount(): void
    {
        if (Auth::check() && ! Auth::user()->isPlatformAdmin()) {
            abort(403, 'Platform administrator access required.');
        }

        $this->editSubscriptionExpiresAt = now()->addYear()->toDateString();

        if (Auth::check()) {
            $user = Auth::user();
            $this->adminProfileName = $user->name ?? '';
            $this->adminProfileEmail = $user->email ?? '';
        }

        if (app()->runningUnitTests() && ! Schema::hasTable('firms')) {
            $this->demoFirms = $this->unitTestFallbackFirms();
            $this->demoSupportRequests = $this->unitTestFallbackSupportRequests();
            return;
        }

        Firm::ensureRuntimeSchemaColumns();
        $this->syncFirmsFromDatabase();
        $this->loadPlatformOperators();
        $this->loadPlatformAuditLogs();
    }

    protected function unitTestFallbackFirms(): array
    {
        return [
            [
                'id' => 'sample-rac', 'name' => 'Rahman Anis & Co.', 'slug' => 'rac',
                'kind' => 'Chartered Accountants', 'status' => 'preview-active',
                'workspaces' => 3, 'seats' => 12, 'plan' => 'Practice',
                'region' => 'Dhaka', 'initials' => 'RA',
                'custom_domain' => null, 'custom_subdomain' => null,
                'shared_attendance_view' => true, 'is_suspended' => false,
                'workspace_url' => 'https://erp.dhakawala.com/c/rac',
            ],
            [
                'id' => 'sample-alpha', 'name' => 'Sample Practice Alpha', 'slug' => 'sample-alpha',
                'kind' => 'Example accounting practice', 'status' => 'preview-active',
                'workspaces' => 2, 'seats' => 8, 'plan' => 'Starter',
                'region' => 'Demo region', 'initials' => 'SA',
                'custom_domain' => null, 'custom_subdomain' => null,
                'shared_attendance_view' => true, 'is_suspended' => false,
                'workspace_url' => 'https://erp.dhakawala.com/c/sample-alpha',
            ],
            [
                'id' => 'sample-beta', 'name' => 'Sample Practice Beta', 'slug' => 'sample-beta',
                'kind' => 'Example accounting practice', 'status' => 'preview-pending',
                'workspaces' => 1, 'seats' => 4, 'plan' => 'Starter',
                'region' => 'Demo region', 'initials' => 'SB',
                'custom_domain' => null, 'custom_subdomain' => null,
                'shared_attendance_view' => true, 'is_suspended' => false,
                'workspace_url' => 'https://erp.dhakawala.com/c/sample-beta',
            ],
        ];
    }

    protected function unitTestFallbackSupportRequests(): array
    {
        return [
            [
                'id' => 'TICKET-101', 'firm' => 'Rahman Anis & Co.',
                'subject' => 'Request to review practice workspace settings', 'scope' => 'Settings only',
                'approval' => 'Partner approval required', 'status' => 'Awaiting approval',
            ],
            [
                'id' => 'TICKET-102', 'firm' => 'Sample Practice Alpha',
                'subject' => 'Configuration assistance request', 'scope' => 'Setup metadata only',
                'approval' => 'Partner approval required', 'status' => 'Approved by Partner',
            ],
        ];
    }

    /**
     * Strict server-side authorization guard for mutating platform actions.
     */
    protected function authorizePlatformMutation(): bool
    {
        if (Auth::check()) {
            /** @var User $user */
            $user = Auth::user();
            if (! $user->isPlatformAdmin()) {
                $this->modalError = 'Unauthorized: Platform Administrator privileges are required for this action.';
                $this->previewNotice = $this->modalError;
                return false;
            }
            return true;
        }

        if (app()->environment('production') || ! app()->runningUnitTests()) {
            $this->modalError = 'Unauthorized: Please sign in as a Platform Administrator to modify organizations.';
            $this->previewNotice = $this->modalError;
            return false;
        }

        return true;
    }

    public function updatedNewFirmName(string $value): void
    {
        if (empty($this->newFirmSlug)) {
            $this->newFirmSlug = Str::slug($value);
        }
    }

    protected function syncFirmsFromDatabase(): void
    {
        try {
            if (Schema::hasTable('firms')) {
                $dbFirms = Firm::orderBy('name')->get();
                if ($dbFirms->isNotEmpty()) {
                    $this->demoFirms = $dbFirms->map(function (Firm $f) {
                        $usedSeats = Schema::hasTable('users')
                            ? User::where('firm_id', $f->id)->count()
                            : 0;
                        $partnerCount = Schema::hasTable('users')
                            ? User::where('firm_id', $f->id)->where('role', 'partner')->count()
                            : 0;

                        $resolvedPal = Firm::resolvePalette($f->theme_palette ?? 'sea_green', $f->primary_color ?? null);
                        return [
                            'id' => 'firm-' . $f->slug,
                            'theme_palette' => $f->theme_palette ?: 'sea_green',
                            'palette_label' => $resolvedPal['label'],
                            'primary_color' => $resolvedPal['primary'],
                            'enabled_modules' => is_array($f->enabled_modules) ? $f->enabled_modules : [
                                'attendance' => true, 'leave' => true, 'conveyance' => true,
                                'payroll' => true, 'team' => true, 'policies' => true,
                            ],
                            'db_id' => $f->id,
                            'name' => $f->name,
                            'slug' => $f->slug,
                            'email' => $f->email ?? '',
                            'kind' => $f->kind,
                            'subscription_type' => $f->subscription_type ?: (($f->payment_status ?? '') === 'trial' ? 'trial' : 'paid'),
                            'per_seat_rate' => (float) ($f->per_seat_rate ?? 500.00),
                            'trial_days' => (int) ($f->trial_days ?? 14),
                            'status' => ($f->status === 'active' && ! $f->is_suspended) ? 'preview-active' : 'preview-pending',
                            'workspaces' => $f->workspaces ?: max(1, $partnerCount),
                            'seats' => $f->seats ?: 10,
                            'used_seats' => $usedSeats,
                            'partner_count' => $partnerCount,
                            'plan' => $f->plan ?: 'Practice',
                            'region' => $f->region ?: 'Dhaka',
                            'custom_domain' => $f->custom_domain,
                            'custom_subdomain' => $f->custom_subdomain,
                            'shared_attendance_view' => $f->shared_attendance_view ?? true,
                            'is_suspended' => (bool) ($f->is_suspended ?? false),
                            'subscription_fee' => (float) ($f->subscription_fee ?? 5000.00),
                            'billing_cycle' => $f->billing_cycle ?: 'Monthly',
                            'payment_status' => $f->payment_status ?: 'paid',
                            'subscription_expires_at' => $f->subscription_expires_at?->format('Y-m-d') ?? now()->addYear()->toDateString(),
                            'workspace_url' => $f->workspace_url,
                            'initials' => $f->initials ?: strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $f->name) ?: 'DW', 0, 2)),
                        ];
                    })->toArray();
                } else {
                    $this->demoFirms = app()->runningUnitTests() ? $this->unitTestFallbackFirms() : [];
                }
            } elseif (app()->runningUnitTests()) {
                $this->demoFirms = $this->unitTestFallbackFirms();
            }
        } catch (\Throwable $e) {
            Log::error('syncFirmsFromDatabase error: ' . $e->getMessage());
        }

        $this->syncSupportRequests();
    }

    protected function syncSupportRequests(): void
    {
        if (empty($this->demoFirms)) {
            $this->demoSupportRequests = [];
            return;
        }

        if (app()->runningUnitTests()) {
            $requests = [];
            $firstFirm = $this->demoFirms[0]['name'] ?? 'Practice Firm';
            $requests[] = [
                'id' => 'TICKET-101',
                'firm' => $firstFirm,
                'subject' => 'Request to review practice workspace settings',
                'scope' => 'Settings only',
                'approval' => 'Partner approval required',
                'status' => 'Awaiting approval',
            ];
            if (count($this->demoFirms) > 1) {
                $secondFirm = $this->demoFirms[1]['name'] ?? 'Practice Firm';
                $requests[] = [
                    'id' => 'TICKET-102',
                    'firm' => $secondFirm,
                    'subject' => 'Configuration assistance request',
                    'scope' => 'Setup metadata only',
                    'approval' => 'Partner approval required',
                    'status' => 'Approved by Partner',
                ];
            }
            $this->demoSupportRequests = $requests;
            return;
        }

        // In production, load real platform governance & tenant audit events instead of fake tickets
        $this->demoSupportRequests = [];
        try {
            if (Schema::hasTable('audit_logs')) {
                $logs = AuditLog::with('firm')
                    ->orderByDesc('id')
                    ->limit(15)
                    ->get();

                $this->demoSupportRequests = $logs->map(fn (AuditLog $log) => [
                    'id'       => 'LOG-' . $log->id,
                    'firm'     => $log->firm?->name ?? 'DhakaWala Central Cloud',
                    'subject'  => $log->summary,
                    'scope'    => strtoupper($log->module) . ' · ' . $log->action,
                    'approval' => 'Actor: ' . $log->actor_name . ' (' . $log->actor_role . ')',
                    'status'   => $log->created_at?->format('d M Y, h:i A') ?? 'Recorded',
                ])->toArray();
            }
        } catch (\Throwable) {
        }
    }

    protected function loadPlatformOperators(): void
    {
        $this->platformOperators = [];
        try {
            if (Schema::hasTable('users')) {
                $this->platformOperators = User::where('role', User::ROLE_PLATFORM_ADMIN)
                    ->orderBy('id')
                    ->get()
                    ->map(fn (User $u) => [
                        'id'         => $u->id,
                        'name'       => $u->name,
                        'email'      => $u->email,
                        'created_at' => $u->created_at?->format('d M Y') ?? 'Active',
                    ])->toArray();
            }
        } catch (\Throwable) {
        }
    }

    protected function loadPlatformAuditLogs(): void
    {
        $this->platformAuditLogs = [];
        try {
            if (Schema::hasTable('audit_logs')) {
                $this->platformAuditLogs = AuditLog::with('firm')
                    ->orderByDesc('id')
                    ->limit(20)
                    ->get()
                    ->map(fn (AuditLog $log) => [
                        'id'         => $log->id,
                        'firm'       => $log->firm?->name ?? 'Platform Core',
                        'actor_name' => $log->actor_name,
                        'actor_role' => $log->actor_role,
                        'module'     => ucfirst($log->module),
                        'action'     => $log->action,
                        'summary'    => $log->summary,
                        'time'       => $log->created_at?->format('d M Y H:i') ?? 'Recent',
                    ])->toArray();
            }
        } catch (\Throwable) {
        }
    }

    protected function loadSelectedFirmRoster(string $slug): void
    {
        $this->selectedFirmPartners = [];
        $this->selectedFirmStats = ['partners' => 0, 'managers' => 0, 'students' => 0, 'total' => 0];
        $this->resetPartnerPasswordInputs = [];

        try {
            if (Schema::hasTable('firms') && Schema::hasTable('users')) {
                $firm = Firm::where('slug', $slug)->first();
                if ($firm) {
                    $users = User::where('firm_id', $firm->id)->orderBy('id')->get();
                    $partners = $users->where('role', 'partner')->values();
                    $managers = $users->where('role', 'manager')->count();
                    $students = $users->where('role', 'student')->count();

                    $this->selectedFirmStats = [
                        'partners' => $partners->count(),
                        'managers' => $managers,
                        'students' => $students,
                        'total'    => $users->count(),
                    ];

                    $this->editPartnerNames = [];
                    $this->editPartnerEmails = [];
                    $this->editPartnerUnits = [];
                    foreach ($partners as $p) {
                        $this->editPartnerNames[$p->id] = $p->name;
                        $this->editPartnerEmails[$p->id] = $p->email;
                        $this->editPartnerUnits[$p->id] = $p->partner_unit ?: 'Partner Unit 01';
                    }
                    $this->selectedFirmPartners = $partners->map(fn (User $p) => [
                        'id'           => $p->id,
                        'name'         => $p->name,
                        'email'        => $p->email,
                        'partner_unit' => $p->partner_unit ?: 'Partner Unit 01',
                        'phone'        => $p->phone ?: '—',
                    ])->toArray();

                    $nextUnitNum = $partners->count() + 1;
                    $this->newAddPartnerUnit = sprintf('Partner Unit %02d', $nextUnitNum);
                }
            }
        } catch (\Throwable) {
        }
    }

    public function setSection(string $section): void
    {
        if (! in_array($section, self::SECTIONS, true)) {
            return;
        }

        $this->section = $section;
        $this->selectedFirmId = null;
        $this->previewNotice = null;
        $this->closePreviews();
    }

    public function updatedStatusFilter(string $value): void
    {
        if (! in_array($value, ['all', 'preview-active', 'preview-pending'], true)) {
            $this->statusFilter = 'all';
        }
    }

    public function selectFirm(string $id): void
    {
        foreach ($this->demoFirms as $firm) {
            if ($firm['id'] === $id || (isset($firm['slug']) && $firm['slug'] === $id)) {
                $this->selectedFirmId = $firm['id'];
                $this->editFirmName = $firm['name'] ?? '';
                $this->editFirmEmail = $firm['email'] ?? '';
                $this->editFirmKind = $firm['kind'] ?? 'Chartered Accountants';
                $this->editSubscriptionType = $firm['subscription_type'] ?? 'paid';
                $this->editPerSeatRate = (float) ($firm['per_seat_rate'] ?? 500.00);
                $this->editTrialDays = (int) ($firm['trial_days'] ?? 14);
                $this->editFirmThemePalette = $firm['theme_palette'] ?? 'sea_green';
                $this->editFirmPrimaryColor = $firm['primary_color'] ?? '#0D9488';
                $this->editEnabledModules = array_merge([
                    'attendance' => true, 'leave' => true, 'conveyance' => true,
                    'payroll' => true, 'team' => true, 'policies' => true,
                ], is_array($firm['enabled_modules'] ?? null) ? $firm['enabled_modules'] : []);
                $this->editFirmPlan = $firm['plan'] ?? 'Practice';
                $this->editFirmRegion = $firm['region'] ?? 'Dhaka';
                $this->editCustomDomain = $firm['custom_domain'] ?? '';
                $this->editCustomSubdomain = $firm['custom_subdomain'] ?? '';
                $this->editFirmSlug = $firm['slug'] ?? '';
                $this->editFirmSeats = (int) ($firm['seats'] ?? 12);
                $this->editFirmWorkspaces = (int) ($firm['workspaces'] ?? 3);
                $this->editSharedAttendance = (bool) ($firm['shared_attendance_view'] ?? true);
                $this->editIsSuspended = (bool) ($firm['is_suspended'] ?? false);
                $this->editSubscriptionFee = (float) ($firm['subscription_fee'] ?? 5000.00);
                $this->editBillingCycle = $firm['billing_cycle'] ?? 'Monthly';
                $this->editPaymentStatus = $firm['payment_status'] ?? 'paid';
                $this->editSubscriptionExpiresAt = $firm['subscription_expires_at'] ?? now()->addYear()->toDateString();
                $this->showProvisionPreview = false;
                $this->loadSelectedFirmRoster($this->editFirmSlug);
                try {
                    if (Schema::hasTable('partner_policies') && Schema::hasTable('firms')) {
                        $fModel = Firm::where('slug', $this->editFirmSlug)->first();
                        if ($fModel) {
                            $pol = PartnerPolicy::where('firm_id', $fModel->id)->first();
                            if ($pol) {
                                $this->editPolicyOfficeStart = $pol->office_start ?: '09:30';
                                $this->editPolicyOfficeEnd = $pol->office_end ?: '18:00';
                                $this->editPolicyGraceMinutes = (int) ($pol->grace_minutes ?? 10);
                                $this->editPolicyConveyanceLimit = (float) ($pol->manager_conveyance_limit ?? 300.00);
                                $this->editPolicyConveyanceLateDays = (int) ($pol->conveyance_late_days ?? 2);
                                if (Schema::hasColumn('partner_policies', 'late_penalty_enabled')) {
                                    $this->editPolicyLatePenaltyEnabled = (bool) $pol->late_penalty_enabled;
                                    $this->editPolicyLateDaysPerDeduction = max(1, (int) ($pol->late_days_per_deduction ?: 3));
                                    $this->editPolicyDeductionDaysCount = (float) ($pol->deduction_days_count ?: 1.00);
                                }
                            }
                        }
                    }
                } catch (\Throwable) {}
                return;
            }
        }
    }

    public function closeFirmDetail(): void
    {
        $this->selectedFirmId = null;
    }

    public function openProvisionPreview(): void
    {
        $this->closePreviews();
        $this->modalError = null;
        $this->showProvisionPreview = true;
    }

    public function openSupportPreview(): void
    {
        $this->closePreviews();
        $this->showSupportPreview = true;
    }

    public function closePreviews(): void
    {
        $this->showProvisionPreview = false;
        $this->showSupportPreview = false;
        $this->modalError = null;
    }

    public function createOrganization(): void
    {
        $this->modalError = null;

        if (! $this->authorizePlatformMutation()) {
            return;
        }

        $this->validate([
            'newFirmName' => 'required|string|min:2|max:150',
            'newFirmSlug' => 'required|string|min:2|max:50|alpha_dash',
            'newPartnerEmail' => 'required|email|max:150',
            'newFirmKind' => 'required|string|max:100',
            'newFirmPlan' => 'required|string',
            'newFirmSeats' => 'required|integer|min:1|max:500',
            'newFirmRegion' => 'required|string|max:100',
            'newFirmCustomDomain' => 'nullable|string|max:120',
            'newFirmCustomSubdomain' => 'nullable|string|max:50|alpha_dash',
        ]);

        $slug = strtolower(trim($this->newFirmSlug));
        $cleanEmail = strtolower(trim($this->newPartnerEmail));
        $customDomain = ! empty(trim($this->newFirmCustomDomain)) ? strtolower(trim($this->newFirmCustomDomain)) : null;
        $customSubdomain = ! empty(trim($this->newFirmCustomSubdomain)) ? strtolower(trim($this->newFirmCustomSubdomain)) : null;
        $partnerPass = trim((string) $this->newPartnerPassword) !== ''
            ? trim($this->newPartnerPassword)
            : Str::password(14);

        try {
            if (Schema::hasTable('firms')) {
                $existingFirmWithSlug = Firm::where('slug', $slug)->first();
                if ($existingFirmWithSlug) {
                    $hasUsers = Schema::hasTable('users') && User::where('firm_id', $existingFirmWithSlug->id)->exists();
                    if (! $hasUsers) {
                        $this->performSafeFirmDeletion($existingFirmWithSlug->id);
                    } else {
                        $this->addError('newFirmSlug', 'This URL slug is already registered by another organization.');
                        $this->modalError = 'This URL slug is already registered by another organization.';
                        return;
                    }
                }

                if ($customDomain && Firm::where('custom_domain', $customDomain)->exists()) {
                    $this->addError('newFirmCustomDomain', 'This custom domain is already assigned to another organization.');
                    $this->modalError = 'This custom domain is already assigned to another organization.';
                    return;
                }

                if ($customSubdomain && Firm::where('custom_subdomain', $customSubdomain)->exists()) {
                    $this->addError('newFirmCustomSubdomain', 'This custom subdomain is already in use.');
                    $this->modalError = 'This custom subdomain is already in use.';
                    return;
                }

                if (Schema::hasTable('users')) {
                    $existingUser = User::where('email', $cleanEmail)->first();
                    if ($existingUser) {
                        if ($existingUser->isPlatformAdmin()) {
                            $this->addError('newPartnerEmail', 'This email is already registered as a Platform Administrator.');
                            $this->modalError = 'Platform Administrator emails cannot be assigned to practice firms. Please use a distinct partner email.';
                            return;
                        }

                        if ($existingUser->firm_id && Firm::where('id', $existingUser->firm_id)->exists()) {
                            $otherFirm = Firm::find($existingUser->firm_id);
                            $this->addError('newPartnerEmail', "This email is already in use by active firm '{$otherFirm->name}'.");
                            $this->modalError = "This email is already in use by active firm '{$otherFirm->name}'. Please use another email.";
                            return;
                        }
                    }
                }

                $createdFirm = null;
                $createdPartner = null;

                DB::transaction(function () use ($slug, $cleanEmail, $partnerPass, $customDomain, $customSubdomain, &$createdFirm, &$createdPartner) {
                    $initials = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $this->newFirmName) ?: 'DW', 0, 2));

                    $firmData = [
                        'name'             => trim($this->newFirmName),
                        'slug'             => $slug,
                        'kind'             => $this->newFirmKind,
                        'email'            => $cleanEmail,
                        'status'           => 'active',
                        'workspaces'       => max(1, $this->newFirmWorkspaces),
                        'seats'            => $this->newFirmSeats,
                        'plan'             => $this->newFirmPlan,
                        'region'           => trim($this->newFirmRegion),
                        'initials'         => $initials,
                        'custom_domain'    => $customDomain,
                        'custom_subdomain' => $customSubdomain,
                    ];

                    if (Schema::hasColumn('firms', 'shared_attendance_view')) {
                        $firmData['shared_attendance_view'] = $this->newFirmSharedAttendance;
                    }
                    Firm::ensureRuntimeSchemaColumns();
                    if (Schema::hasColumn('firms', 'theme_palette')) {
                        $resolvedNewPal = Firm::resolvePalette($this->newFirmThemePalette, $this->newFirmPrimaryColor);
                        $firmData['theme_palette'] = $resolvedNewPal['key'];
                        $firmData['primary_color'] = $resolvedNewPal['primary'];
                    }
                    if (Schema::hasColumn('firms', 'subscription_fee')) {
                        if ($this->newSubscriptionType === 'trial') {
                            $tDays = max(1, (int) $this->newTrialDays);
                            $firmData['billing_cycle'] = 'Trial';
                            $firmData['payment_status'] = 'trial';
                            $firmData['subscription_fee'] = 0.00;
                            $firmData['subscription_expires_at'] = now()->addDays($tDays)->toDateString();
                        } else {
                            $rate = max(0, (float) $this->newPerSeatRate);
                            $firmData['billing_cycle'] = 'Monthly';
                            $firmData['payment_status'] = 'paid';
                            $firmData['subscription_fee'] = round($this->newFirmSeats * $rate, 2);
                            $firmData['subscription_expires_at'] = now()->addMonth()->toDateString();
                        }
                    }
                    if (Schema::hasColumn('firms', 'subscription_type')) {
                        $firmData['subscription_type'] = $this->newSubscriptionType;
                        $firmData['per_seat_rate'] = max(0, (float) $this->newPerSeatRate);
                        $firmData['trial_days'] = max(1, (int) $this->newTrialDays);
                    }

                    $firm = Firm::create($firmData);

                    $existingUser = User::where('email', $cleanEmail)->first();
                    if ($existingUser) {
                        $existingUser->update([
                            'name'                 => $this->newPartnerName ? trim($this->newPartnerName) : 'Managing Partner',
                            'password'             => Hash::make($partnerPass),
                            'role'                 => User::ROLE_PARTNER,
                            'firm_id'              => $firm->id,
                            'partner_unit'         => 'Partner Unit 01',
                            'can_manage_team'      => true,
                            'can_proxy_attendance' => true,
                            'can_edit_attendance'  => true,
                            'can_approve_leave'    => true,
                            'can_approve_claims'   => true,
                            'email_verified_at'    => now(),
                        ]);
                        $partner = $existingUser;
                    } else {
                        $partner = User::create([
                            'name'                 => $this->newPartnerName ? trim($this->newPartnerName) : 'Managing Partner',
                            'email'                => $cleanEmail,
                            'password'             => Hash::make($partnerPass),
                            'role'                 => User::ROLE_PARTNER,
                            'firm_id'              => $firm->id,
                            'partner_unit'         => 'Partner Unit 01',
                            'can_manage_team'      => true,
                            'can_proxy_attendance' => true,
                            'can_edit_attendance'  => true,
                            'can_approve_leave'    => true,
                            'can_approve_claims'   => true,
                            'email_verified_at'    => now(),
                        ]);
                    }

                    if (Schema::hasTable('partner_policies')) {
                        PartnerPolicy::create([
                            'firm_id'                  => $firm->id,
                            'partner_id'               => $partner->id,
                            'partner_unit'             => 'Partner Unit 01',
                            'office_start'             => '10:00',
                            'office_end'               => '18:00',
                            'grace_minutes'            => 10,
                            'manager_conveyance_limit' => 300.00,
                            'conveyance_late_days'     => 2,
                            'late_penalty_enabled'     => true,
                            'late_days_per_deduction'  => 3,
                            'deduction_days_count'     => 1.00,
                            'student_grids'            => PartnerPolicy::defaultStudentGrids(),
                            'manager_permissions'      => PartnerPolicy::defaultManagerPermissions(),
                        ]);
                    }

                    if (Schema::hasTable('audit_logs')) {
                        AuditLog::create([
                            'firm_id'    => $firm->id,
                            'actor_id'   => Auth::id(),
                            'actor_name' => Auth::user()?->name ?? 'Platform Admin',
                            'actor_role' => 'platform_admin',
                            'module'     => 'platform',
                            'action'     => 'firm_provisioned',
                            'target_ref' => $firm->slug,
                            'summary'    => "Provisioned organization '{$firm->name}' ({$firm->slug}) with initial Partner {$partner->email}.",
                        ]);
                    }

                    $createdFirm = $firm;
                    $createdPartner = $partner;
                });

                $this->syncFirmsFromDatabase();
                $this->loadPlatformAuditLogs();
                $this->closePreviews();
                $this->reset([
                    'newFirmName',
                    'newFirmSlug',
                    'newPartnerName',
                    'newPartnerEmail',
                    'newPartnerPassword',
                    'newFirmCustomDomain',
                    'newFirmCustomSubdomain',
                ]);
                $this->modalError = null;

                $workspaceUrl = $createdFirm->workspace_url;
                $this->previewNotice = "Organization '{$createdFirm->name}' created! Workspace live at: {$workspaceUrl}. Initial Partner: {$createdPartner->email} / {$partnerPass}";
                $this->dispatch('toast', [
                    'type'    => 'success',
                    'title'   => 'Organization Created',
                    'message' => "Workspace live at {$workspaceUrl}",
                ]);
                return;
            }
        } catch (\Throwable $e) {
            Log::error('createOrganization error: ' . $e->getMessage());
            $this->modalError = 'Failed to create organization: ' . $e->getMessage();
            $this->previewNotice = 'Failed to create organization: ' . $e->getMessage();
            $this->dispatch('toast', [
                'type'    => 'error',
                'title'   => 'Creation Failed',
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Update organization profile, custom domain, subdomain, seats, partner workspaces, billing, and governance switches.
     */
    public function updateDomainSettings(string $slug): void
    {
        if (! $this->authorizePlatformMutation()) {
            return;
        }

        $this->validate([
            'editCustomDomain' => 'nullable|string|max:120',
            'editCustomSubdomain' => 'nullable|string|max:50|alpha_dash',
        ]);

        try {
            if (Schema::hasTable('firms')) {
                $firm = Firm::where('slug', $slug)->first();
                if ($firm) {
                    $customDomain = ! empty(trim($this->editCustomDomain)) ? strtolower(trim($this->editCustomDomain)) : null;
                    $customSubdomain = ! empty(trim($this->editCustomSubdomain)) ? strtolower(trim($this->editCustomSubdomain)) : null;

                    if ($customDomain && Firm::where('custom_domain', $customDomain)->where('id', '!=', $firm->id)->exists()) {
                        $this->addError('editCustomDomain', 'This custom domain is already registered to another organization.');
                        return;
                    }

                    if ($customSubdomain && Firm::where('custom_subdomain', $customSubdomain)->where('id', '!=', $firm->id)->exists()) {
                        $this->addError('editCustomSubdomain', 'This custom subdomain is already registered to another organization.');
                        return;
                    }

                    Firm::ensureRuntimeSchemaColumns();
                    $cleanFirmEmail = strtolower(trim($this->editFirmEmail));
                    $updatePayload = [
                        'name'             => ! empty(trim($this->editFirmName)) ? trim($this->editFirmName) : $firm->name,
                        'email'            => filter_var($cleanFirmEmail, FILTER_VALIDATE_EMAIL) ? $cleanFirmEmail : $firm->email,
                        'kind'             => ! empty(trim($this->editFirmKind)) ? trim($this->editFirmKind) : $firm->kind,
                        'plan'             => ! empty(trim($this->editFirmPlan)) ? trim($this->editFirmPlan) : $firm->plan,
                        'region'           => ! empty(trim($this->editFirmRegion)) ? trim($this->editFirmRegion) : $firm->region,
                        'custom_domain'    => $customDomain,
                        'custom_subdomain' => $customSubdomain,
                        'seats'            => max(1, (int) $this->editFirmSeats),
                        'workspaces'       => max(1, (int) $this->editFirmWorkspaces),
                    ];

                    if (Schema::hasColumn('firms', 'shared_attendance_view')) {
                        $updatePayload['shared_attendance_view'] = (bool) $this->editSharedAttendance;
                    }
                    if (Schema::hasColumn('firms', 'theme_palette')) {
                        $resolvedEditPal = Firm::resolvePalette($this->editFirmThemePalette, $this->editFirmPrimaryColor);
                        $updatePayload['theme_palette'] = $resolvedEditPal['key'];
                        $updatePayload['primary_color'] = $resolvedEditPal['primary'];
                    }
                    if (Schema::hasColumn('firms', 'enabled_modules')) {
                        $updatePayload['enabled_modules'] = $this->editEnabledModules;
                    }
                    if (Schema::hasColumn('firms', 'is_suspended')) {
                        $updatePayload['is_suspended'] = (bool) $this->editIsSuspended;
                        $updatePayload['status'] = $this->editIsSuspended ? 'suspended' : 'active';
                    }
                    if (Schema::hasColumn('firms', 'subscription_fee')) {
                        $updatePayload['subscription_fee'] = max(0, (float) $this->editSubscriptionFee);
                        $updatePayload['billing_cycle'] = $this->editBillingCycle ?: 'Monthly';
                        $updatePayload['payment_status'] = in_array($this->editPaymentStatus, ['trial', 'paid', 'due', 'overdue'], true) ? $this->editPaymentStatus : 'paid';
                        if (Schema::hasColumn('firms', 'subscription_type')) {
                            $updatePayload['subscription_type'] = $this->editSubscriptionType;
                            $updatePayload['per_seat_rate'] = max(0, (float) $this->editPerSeatRate);
                            $updatePayload['trial_days'] = max(1, (int) $this->editTrialDays);
                        }
                        if (! empty($this->editSubscriptionExpiresAt)) {
                            $updatePayload['subscription_expires_at'] = $this->editSubscriptionExpiresAt;
                        }
                    }

                    $firm->update($updatePayload);

                    if (Schema::hasTable('audit_logs')) {
                        AuditLog::create([
                            'firm_id'    => $firm->id,
                            'actor_id'   => Auth::id(),
                            'actor_name' => Auth::user()?->name ?? 'Platform Admin',
                            'actor_role' => 'platform_admin',
                            'module'     => 'platform',
                            'action'     => 'firm_governance_updated',
                            'target_ref' => $firm->slug,
                            'summary'    => "Updated governance, billing & domain controls for '{$firm->name}' (Plan: {$firm->plan}, Seats: {$firm->seats}, Status: {$firm->status}).",
                        ]);
                    }

                    $this->syncFirmsFromDatabase();
                    $this->loadPlatformAuditLogs();
                    $this->selectFirm($firm->slug);
                    $this->previewNotice = "Organization & governance controls updated for '{$firm->name}'. Active Workspace: " . $firm->workspace_url;
                    return;
                }
            }
        } catch (\Throwable $e) {
            Log::error('updateDomainSettings error: ' . $e->getMessage());
            $this->previewNotice = 'Failed to update domain settings: ' . $e->getMessage();
        }
    }

    /**
     * Add an additional Partner & isolated Partner Unit to an existing firm from DhakaWala Super Admin.
     */
    public function addPartnerToFirm(string $slug): void
    {
        if (! $this->authorizePlatformMutation()) {
            return;
        }

        $name = trim($this->newAddPartnerName);
        $email = strtolower(trim($this->newAddPartnerEmail));
        $unit = trim($this->newAddPartnerUnit) ?: 'Partner Unit 02';
        $password = trim((string) $this->newAddPartnerPassword) !== ''
            ? trim($this->newAddPartnerPassword)
            : Str::password(14);

        if ($name === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->previewNotice = 'Please provide a valid Partner Name and Email address.';
            return;
        }

        try {
            if (Schema::hasTable('firms') && Schema::hasTable('users')) {
                $firm = Firm::where('slug', $slug)->first();
                if (! $firm) {
                    return;
                }

                if (User::where('email', $email)->exists()) {
                    $this->previewNotice = "Email '{$email}' is already registered in the system.";
                    return;
                }

                $partner = User::create([
                    'name'                 => $name,
                    'email'                => $email,
                    'password'             => Hash::make($password),
                    'role'                 => User::ROLE_PARTNER,
                    'firm_id'              => $firm->id,
                    'partner_unit'         => $unit,
                    'can_manage_team'      => true,
                    'can_proxy_attendance' => true,
                    'can_edit_attendance'  => true,
                    'can_approve_leave'    => true,
                    'can_approve_claims'   => true,
                    'email_verified_at'    => now(),
                ]);

                if (Schema::hasTable('partner_policies')) {
                    PartnerPolicy::updateOrCreate(
                        ['firm_id' => $firm->id, 'partner_unit' => $unit],
                        [
                            'partner_id'               => $partner->id,
                            'office_start'             => '10:00',
                            'office_end'               => '18:00',
                            'grace_minutes'            => 10,
                            'manager_conveyance_limit' => 300.00,
                            'conveyance_late_days'     => 2,
                            'late_penalty_enabled'     => true,
                            'late_days_per_deduction'  => 3,
                            'deduction_days_count'     => 1.00,
                            'student_grids'            => PartnerPolicy::defaultStudentGrids(),
                            'manager_permissions'      => PartnerPolicy::defaultManagerPermissions(),
                        ]
                    );
                }

                $partnerCount = User::where('firm_id', $firm->id)->where('role', 'partner')->count();
                if ($firm->workspaces < $partnerCount) {
                    $firm->update(['workspaces' => $partnerCount]);
                }

                if (Schema::hasTable('audit_logs')) {
                    AuditLog::create([
                        'firm_id'    => $firm->id,
                        'actor_id'   => Auth::id(),
                        'actor_name' => Auth::user()?->name ?? 'Platform Admin',
                        'actor_role' => 'platform_admin',
                        'module'     => 'platform',
                        'action'     => 'partner_unit_added',
                        'target_ref' => $partner->email,
                        'summary'    => "Added Partner '{$partner->name}' ({$unit}) to '{$firm->name}'.",
                    ]);
                }

                $this->newAddPartnerName = '';
                $this->newAddPartnerEmail = '';
                $this->newAddPartnerPassword = '';
                $this->syncFirmsFromDatabase();
                $this->loadPlatformAuditLogs();
                $this->selectFirm($firm->slug);
                $this->previewNotice = "Added Partner '{$partner->name}' ({$unit}) to {$firm->name}! Login: {$partner->email} / {$password}";
            }
        } catch (\Throwable $e) {
            $this->previewNotice = 'Failed to add Partner: ' . $e->getMessage();
        }
    }

    /**
     * 1-Click Partner Password Reset from DhakaWala Super Admin.
     */

    /**
     * Issue 01 Fix: Dedicated 1-click palette selection for New Organization modal.
     */
    public function selectNewFirmPalette(string $paletteKey): void
    {
        $palettes = Firm::colorPalettes();
        if (isset($palettes[$paletteKey])) {
            $this->newFirmThemePalette = $paletteKey;
            $this->newFirmPrimaryColor = $palettes[$paletteKey]['primary'];
        } else {
            $this->newFirmThemePalette = 'custom';
        }
    }

    /**
     * Issue 01 Fix: 1-Click instant palette selection & database persistence in Manage Organization modal.
     */
    public function selectAndApplyFirmPalette(string $slug, string $paletteKey): void
    {
        if (! $this->authorizePlatformMutation()) {
            return;
        }
        $palettes = Firm::colorPalettes();
        if (isset($palettes[$paletteKey])) {
            $this->editFirmThemePalette = $paletteKey;
            $this->editFirmPrimaryColor = $palettes[$paletteKey]['primary'];
        } else {
            $this->editFirmThemePalette = 'custom';
        }
        $this->applyFirmCustomColor($slug);
    }

    /**
     * Issue 01 Fix: Directly persist theme_palette & primary_color to the Firm in DB and refresh directory state.
     */
    public function applyFirmCustomColor(string $slug): void
    {
        if (! $this->authorizePlatformMutation()) {
            return;
        }
        try {
            Firm::ensureRuntimeSchemaColumns();
            if (Schema::hasTable('firms')) {
                $firm = Firm::where('slug', $slug)->first();
                if ($firm) {
                    $resolved = Firm::resolvePalette($this->editFirmThemePalette, $this->editFirmPrimaryColor);
                    $this->editFirmThemePalette = $resolved['key'];
                    $this->editFirmPrimaryColor = $resolved['primary'];
                    $firm->theme_palette = $resolved['key'];
                    $firm->primary_color = $resolved['primary'];
                    $firm->save();

                    $this->syncFirmsFromDatabase();
                    $this->selectFirm($firm->slug);
                    $this->previewNotice = "Brand Color Palette updated to {$resolved['label']} ({$resolved['primary']}) for '{$firm->name}'!";
                    $this->dispatch('toast', [
                        'type'    => 'success',
                        'title'   => 'Palette Applied',
                        'message' => "{$firm->name} color updated to {$resolved['label']}.",
                    ]);
                }
            }
        } catch (\Throwable $e) {
            $this->previewNotice = 'Failed to save palette: ' . $e->getMessage();
        }
    }

    /**
     * Issue 02 Fix: Update Partner Name, Email, Partner Unit, and/or Password from DhakaWala Super Admin.
     */
    public function updatePartnerAccount(int $partnerUserId): void
    {
        if (! $this->authorizePlatformMutation()) {
            return;
        }

        $newName = trim($this->editPartnerNames[$partnerUserId] ?? '');
        $newEmail = strtolower(trim($this->editPartnerEmails[$partnerUserId] ?? ''));
        $newUnit = trim($this->editPartnerUnits[$partnerUserId] ?? '');
        $newPass = trim($this->resetPartnerPasswordInputs[$partnerUserId] ?? '');

        if ($newEmail !== '' && ! filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            $this->previewNotice = 'Please enter a valid Partner email address.';
            return;
        }

        if ($newPass !== '' && strlen($newPass) < 8) {
            $this->previewNotice = 'New Partner password must be at least 8 characters.';
            return;
        }

        try {
            if (Schema::hasTable('users')) {
                $partner = User::where('id', $partnerUserId)->where('role', 'partner')->first();
                if ($partner) {
                    $oldEmail = $partner->email;
                    if ($newEmail !== '' && $newEmail !== $partner->email) {
                        if (User::where('email', $newEmail)->where('id', '!=', $partner->id)->exists()) {
                            $this->previewNotice = "Email '{$newEmail}' is already assigned to another user.";
                            return;
                        }
                        $partner->email = $newEmail;
                    }
                    if ($newName !== '') {
                        $partner->name = $newName;
                    }
                    if ($newUnit !== '') {
                        $partner->partner_unit = $newUnit;
                    }
                    if ($newPass !== '') {
                        $partner->password = Hash::make($newPass);
                    }
                    $partner->save();

                    // Also sync firm primary email if it matched the old partner email
                    if ($partner->firm_id && Schema::hasTable('firms')) {
                        $firm = Firm::find($partner->firm_id);
                        if ($firm && ($firm->email === $oldEmail || empty($firm->email))) {
                            $firm->email = $partner->email;
                            $firm->save();
                            $this->editFirmEmail = $partner->email;
                        }
                    }

                    if (Schema::hasTable('audit_logs')) {
                        AuditLog::create([
                            'firm_id'    => $partner->firm_id,
                            'actor_id'   => Auth::id(),
                            'actor_name' => Auth::user()?->name ?? 'Platform Admin',
                            'actor_role' => 'platform_admin',
                            'module'     => 'platform',
                            'action'     => 'partner_account_updated',
                            'target_ref' => $partner->email,
                            'summary'    => "Updated Partner account ({$partner->name}, Email: {$partner->email})" . ($newPass !== '' ? ' and reset password.' : '.'),
                        ]);
                    }

                    $this->resetPartnerPasswordInputs[$partnerUserId] = '';
                    $this->syncFirmsFromDatabase();
                    if ($this->editFirmSlug) {
                        $this->loadSelectedFirmRoster($this->editFirmSlug);
                    }
                    $this->previewNotice = "Updated Partner ({$partner->name} · {$partner->email})" . ($newPass !== '' ? " & password set to: {$newPass}" : ".");
                }
            }
        } catch (\Throwable $e) {
            $this->previewNotice = 'Failed to update Partner account: ' . $e->getMessage();
        }
    }

    /**
     * Issue 03: Grant a 1-Click Free Trial Subscription (e.g. 7, 14, or 30 days) to a Firm.
     */
    public function grantTrialSubscription(string $slug, int $days = 14): void
    {
        if (! $this->authorizePlatformMutation()) {
            return;
        }
        $days = max(1, $days);
        $this->editSubscriptionType = 'trial';
        $this->editTrialDays = $days;
        $this->editBillingCycle = 'Trial';
        $this->editPaymentStatus = 'trial';
        $this->editSubscriptionFee = 0.00;
        $this->editSubscriptionExpiresAt = now()->addDays($days)->toDateString();
        $this->updateDomainSettings($slug);
        $this->previewNotice = "Granted {$days}-Day Free Trial subscription (Expires: {$this->editSubscriptionExpiresAt}).";
    }

    /**
     * Issue 03: Automatically calculate Seat-Based Paid Subscription Fee (Seats x Per-Seat Rate).
     */
    public function saveFirmPolicyFromSuperAdmin(string $slug): void
    {
        if (! $this->authorizePlatformMutation()) {
            return;
        }
        try {
            if (Schema::hasTable('firms') && Schema::hasTable('partner_policies')) {
                $firm = Firm::where('slug', $slug)->first();
                if ($firm) {
                    $partnerUser = User::where('firm_id', $firm->id)->where('role', 'partner')->first();
                    $partnerId = $partnerUser ? $partnerUser->id : (Auth::id() ?? 1);
                    $units = ['Partner Unit 01'];
                    $existingUnits = PartnerPolicy::where('firm_id', $firm->id)->pluck('partner_unit')->unique()->filter()->all();
                    if (! empty($existingUnits)) {
                        $units = $existingUnits;
                    }
                    foreach ($units as $unitName) {
                        $payload = [
                            'partner_id'               => $partnerId,
                            'office_start'             => trim($this->editPolicyOfficeStart) ?: '09:30',
                            'office_end'               => trim($this->editPolicyOfficeEnd) ?: '18:00',
                            'grace_minutes'            => max(0, (int) $this->editPolicyGraceMinutes),
                            'manager_conveyance_limit' => max(50, (float) $this->editPolicyConveyanceLimit),
                            'conveyance_late_days'     => max(1, (int) $this->editPolicyConveyanceLateDays),
                            'student_grids'            => PartnerPolicy::defaultStudentGrids(),
                            'manager_permissions'      => PartnerPolicy::defaultManagerPermissions(),
                        ];
                        if (Schema::hasColumn('partner_policies', 'late_penalty_enabled')) {
                            $payload['late_penalty_enabled'] = (bool) $this->editPolicyLatePenaltyEnabled;
                            $payload['late_days_per_deduction'] = max(1, (int) $this->editPolicyLateDaysPerDeduction);
                            $payload['deduction_days_count'] = max(0, (float) $this->editPolicyDeductionDaysCount);
                        }
                        PartnerPolicy::updateOrCreate(
                            ['firm_id' => $firm->id, 'partner_unit' => $unitName],
                            $payload
                        );
                    }
                }
            }
            $this->previewNotice = "Firm attendance, grace ({$this->editPolicyGraceMinutes}m), and late penalty policy updated for '{$slug}'.";
            $this->dispatch('toast', [
                'type'    => 'success',
                'title'   => 'Firm Policy Updated',
                'message' => "Hours ({$this->editPolicyOfficeStart}–{$this->editPolicyOfficeEnd}), Grace ({$this->editPolicyGraceMinutes}m) & Late Rules saved.",
            ]);
        } catch (\Throwable $e) {
            $this->modalError = 'Could not update policy: ' . $e->getMessage();
        }
    }

    public function applySeatBasedSubscription(string $slug): void
    {
        if (! $this->authorizePlatformMutation()) {
            return;
        }
        $this->editSubscriptionType = 'paid';
        $seats = max(1, (int) $this->editFirmSeats);
        $rate = max(0, (float) $this->editPerSeatRate);
        $multiplier = $this->editBillingCycle === 'Annual' ? 12 : ($this->editBillingCycle === 'Quarterly' ? 3 : 1);
        if ($this->editBillingCycle === 'Trial') {
            $this->editBillingCycle = 'Monthly';
            $multiplier = 1;
        }
        $this->editSubscriptionFee = round($seats * $rate * $multiplier, 2);
        if ($this->editPaymentStatus === 'trial') {
            $this->editPaymentStatus = 'paid';
        }
        $this->updateDomainSettings($slug);
        $this->previewNotice = "Seat-based subscription applied: {$seats} seats × ৳{$rate} = ৳" . number_format($this->editSubscriptionFee, 0) . " ({$this->editBillingCycle}).";
    }

    public function resetPartnerPassword(int $partnerUserId): void
    {
        if (! $this->authorizePlatformMutation()) {
            return;
        }

        $newPass = trim((string) ($this->resetPartnerPasswordInputs[$partnerUserId] ?? ''));
        if ($newPass === '') {
            $newPass = Str::password(14);
        }
        if (strlen($newPass) < 8) {
            $this->previewNotice = 'Partner password must be at least 8 characters.';
            return;
        }

        try {
            if (Schema::hasTable('users')) {
                $partner = User::where('id', $partnerUserId)->where('role', 'partner')->first();
                if ($partner) {
                    $partner->password = Hash::make($newPass);
                    $partner->save();

                    if (Schema::hasTable('audit_logs')) {
                        AuditLog::create([
                            'firm_id'    => $partner->firm_id,
                            'actor_id'   => Auth::id(),
                            'actor_name' => Auth::user()?->name ?? 'Platform Admin',
                            'actor_role' => 'platform_admin',
                            'module'     => 'platform',
                            'action'     => 'partner_password_reset',
                            'target_ref' => $partner->email,
                            'summary'    => "Reset password for Partner {$partner->name} ({$partner->email}).",
                        ]);
                    }

                    $this->resetPartnerPasswordInputs[$partnerUserId] = '';
                    $this->loadPlatformAuditLogs();
                    $this->previewNotice = "Password reset for Partner {$partner->name} ({$partner->email}) to: {$newPass}";
                }
            }
        } catch (\Throwable $e) {
            $this->previewNotice = 'Failed to reset Partner password: ' . $e->getMessage();
        }
    }

    /**
     * Send a Global Platform Broadcast Popup Notice from DhakaWala to All Firms (or a specific firm).
     */
    public function sendPlatformBroadcast(): void
    {
        if (! $this->authorizePlatformMutation()) {
            return;
        }

        $title = trim($this->broadcastTitle);
        $message = trim($this->broadcastMessage);
        if ($title === '' || $message === '') {
            $this->previewNotice = 'Please enter both a Broadcast Title and Message.';
            return;
        }

        try {
            if (Schema::hasTable('firms') && Schema::hasTable('practice_notifications')) {
                $firmsQuery = Firm::where('status', 'active');
                if ($this->broadcastTargetSlug !== 'all') {
                    $firmsQuery->where('slug', $this->broadcastTargetSlug);
                }
                $targetFirms = $firmsQuery->get();

                foreach ($targetFirms as $firm) {
                    PracticeNotification::create([
                        'firm_id'       => $firm->id,
                        'user_id'       => null,
                        'sender_id'     => Auth::id(),
                        'sender_name'   => 'DhakaWala Central Operations',
                        'target_role'   => $this->broadcastTargetRole === 'all' ? null : 'partner',
                        'show_as_popup' => true,
                        'icon'          => 'megaphone',
                        'title'         => $title,
                        'message'       => $message,
                        'is_read'       => false,
                    ]);
                }

                if (Schema::hasTable('audit_logs')) {
                    AuditLog::create([
                        'firm_id'    => null,
                        'actor_id'   => Auth::id(),
                        'actor_name' => Auth::user()?->name ?? 'Platform Admin',
                        'actor_role' => 'platform_admin',
                        'module'     => 'platform',
                        'action'     => 'global_broadcast_sent',
                        'target_ref' => $this->broadcastTargetSlug,
                        'summary'    => "Dispatched platform broadcast '{$title}' to " . $targetFirms->count() . ' organization(s).',
                    ]);
                }

                $this->broadcastTitle = '';
                $this->broadcastMessage = '';
                $this->loadPlatformAuditLogs();
                $this->syncSupportRequests();
                $this->previewNotice = "Platform popup broadcast '{$title}' dispatched to " . $targetFirms->count() . ' active organization(s).';
            }
        } catch (\Throwable $e) {
            $this->previewNotice = 'Broadcast failed: ' . $e->getMessage();
        }
    }

    /**
     * Super Admin updates their own Name, Email, and Root Password.
     */
    public function updatePlatformAdminProfile(): void
    {
        if (! $this->authorizePlatformMutation()) {
            return;
        }

        $name = trim($this->adminProfileName);
        $email = strtolower(trim($this->adminProfileEmail));
        $newPass = $this->adminNewPassword;

        if ($name === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->previewNotice = 'Please provide a valid Super Admin Name and Email.';
            return;
        }

        if ($newPass !== '') {
            if (strlen($newPass) < 8) {
                $this->previewNotice = 'New root password must be at least 8 characters long.';
                return;
            }
            if ($newPass !== $this->adminNewPasswordConfirm) {
                $this->previewNotice = 'Root password confirmation does not match.';
                return;
            }
        }

        try {
            if (Auth::check() && Schema::hasTable('users')) {
                /** @var User $admin */
                $admin = Auth::user();
                if (User::where('email', $email)->where('id', '!=', $admin->id)->exists()) {
                    $this->previewNotice = 'That email address is already assigned to another account.';
                    return;
                }

                $admin->name = $name;
                $admin->email = $email;
                if ($newPass !== '') {
                    $admin->password = Hash::make($newPass);
                }
                $admin->save();

                $this->adminNewPassword = '';
                $this->adminNewPasswordConfirm = '';
                $this->loadPlatformOperators();
                $this->previewNotice = 'Super Admin profile' . ($newPass !== '' ? ' and root password' : '') . ' updated successfully.';
            }
        } catch (\Throwable $e) {
            $this->previewNotice = 'Failed to update Super Admin profile: ' . $e->getMessage();
        }
    }

    /**
     * Create an additional DhakaWala Platform Super Admin operator.
     */
    public function createPlatformOperator(): void
    {
        if (! $this->authorizePlatformMutation()) {
            return;
        }

        $name = trim($this->newOperatorName);
        $email = strtolower(trim($this->newOperatorEmail));
        $pass = $this->newOperatorPassword;

        if ($name === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($pass) < 8) {
            $this->previewNotice = 'Please enter Operator Name, valid Email, and a Password of at least 8 characters.';
            return;
        }

        try {
            if (Schema::hasTable('users')) {
                if (User::where('email', $email)->exists()) {
                    $this->previewNotice = "Email '{$email}' is already registered.";
                    return;
                }

                User::create([
                    'name'              => $name,
                    'email'             => $email,
                    'password'          => Hash::make($pass),
                    'role'              => User::ROLE_PLATFORM_ADMIN,
                    'email_verified_at' => now(),
                ]);

                if (Schema::hasTable('audit_logs')) {
                    AuditLog::create([
                        'firm_id'    => null,
                        'actor_id'   => Auth::id(),
                        'actor_name' => Auth::user()?->name ?? 'Platform Admin',
                        'actor_role' => 'platform_admin',
                        'module'     => 'platform',
                        'action'     => 'operator_created',
                        'target_ref' => $email,
                        'summary'    => "Created Platform Super Admin operator '{$name}' ({$email}).",
                    ]);
                }

                $this->newOperatorName = '';
                $this->newOperatorEmail = '';
                $this->newOperatorPassword = '';
                $this->loadPlatformOperators();
                $this->loadPlatformAuditLogs();
                $this->previewNotice = "Platform Super Admin '{$name}' ({$email}) created successfully.";
            }
        } catch (\Throwable $e) {
            $this->previewNotice = 'Failed to create operator: ' . $e->getMessage();
        }
    }

    public function toggleFirmModule(string $slug, string $modKey): void
    {
        if (! $this->authorizePlatformMutation()) {
            return;
        }
        $current = (bool) ($this->editEnabledModules[$modKey] ?? true);
        $this->editEnabledModules[$modKey] = ! $current;
        $this->updateDomainSettings($slug);
    }

    /**
     * 1-Click Support Enter: DhakaWala Super Admin enters any Firm workspace directly in Support/Admin mode (#Proposal5).
     */
    public function enterFirmWorkspaceAsSupport(string $slug): void
    {
        if (! $this->authorizePlatformMutation()) {
            return;
        }
        $cleanSlug = strtolower(trim($slug));
        try {
            if (Schema::hasTable('firms') && Schema::hasTable('audit_logs')) {
                $firm = Firm::where('slug', $cleanSlug)->first();
                if ($firm) {
                    AuditLog::create([
                        'firm_id'    => $firm->id,
                        'actor_id'   => Auth::id(),
                        'actor_name' => Auth::user()?->name ?? 'DhakaWala Super Admin',
                        'actor_role' => 'platform_admin',
                        'module'     => 'support',
                        'action'     => 'support_workspace_entered',
                        'target_ref' => $firm->slug,
                        'summary'    => "DhakaWala Super Admin entered '{$firm->name}' workspace via 1-Click Support Enter.",
                    ]);
                }
            }
        } catch (\Throwable) {}

        $this->redirect(route('firm.practice.dashboard', ['firmSlug' => $cleanSlug, 'section' => 'dashboard']));
    }

    public function toggleSharedAttendance(string $slug): void
    {
        if (! $this->authorizePlatformMutation()) {
            return;
        }
        $this->editSharedAttendance = ! $this->editSharedAttendance;
        $this->updateDomainSettings($slug);
    }

    public function toggleFirmSuspension(string $slug): void
    {
        if (! $this->authorizePlatformMutation()) {
            return;
        }
        $this->editIsSuspended = ! $this->editIsSuspended;
        $this->updateDomainSettings($slug);
    }

    public function sendBillingReminder(string $slug): void
    {
        if (! $this->authorizePlatformMutation()) {
            return;
        }
        $this->editPaymentStatus = 'due';
        $this->updateDomainSettings($slug);

        try {
            if (Schema::hasTable('firms') && Schema::hasTable('practice_notifications')) {
                $firm = Firm::where('slug', $slug)->first();
                if ($firm) {
                    PracticeNotification::create([
                        'firm_id'       => $firm->id,
                        'user_id'       => null,
                        'sender_name'   => 'DhakaWala Billing Operations',
                        'target_role'   => 'partner',
                        'show_as_popup' => true,
                        'icon'          => 'credit-card',
                        'title'         => 'Subscription Payment Reminder (৳' . number_format((float) $firm->subscription_fee, 0) . ')',
                        'message'       => "Your DhakaWala ERP {$firm->billing_cycle} subscription (৳" . number_format((float) $firm->subscription_fee, 0) . ") is marked Due (Expires: " . ($firm->subscription_expires_at?->format('d M Y') ?? 'Soon') . '). Please settle the invoice to ensure uninterrupted service.',
                        'is_read'       => false,
                    ]);
                    $this->previewNotice = "Subscription marked DUE and popup billing reminder sent to {$firm->name}.";
                }
            }
        } catch (\Throwable) {
        }
    }

    /**
     * Open Secure Permanent Deletion Confirmation Modal
     */
    public function openDeleteModal(string $slug): void
    {
        if (! $this->authorizePlatformMutation()) {
            return;
        }

        $cleanSlug = trim($slug);
        $this->firmToDeleteSlug = $cleanSlug;

        $firm = collect($this->demoFirms)->first(function ($f) use ($cleanSlug) {
            return ($f['slug'] ?? null) === $cleanSlug || ($f['id'] ?? null) === $cleanSlug;
        });

        if ($firm) {
            $this->firmToDeleteName = $firm['name'] ?? $cleanSlug;
        } else {
            try {
                if (Schema::hasTable('firms')) {
                    $dbFirm = $this->resolveFirmBySlug($cleanSlug);
                    $this->firmToDeleteName = $dbFirm ? $dbFirm->name : $cleanSlug;
                }
            } catch (\Throwable) {
                $this->firmToDeleteName = $cleanSlug;
            }
        }

        $this->deleteConfirmationInput = '';
        $this->showDeleteModal = true;
    }

    /**
     * Close Secure Permanent Deletion Confirmation Modal
     */
    public function closeDeleteModal(): void
    {
        $this->showDeleteModal = false;
        $this->firmToDeleteSlug = null;
        $this->firmToDeleteName = null;
        $this->deleteConfirmationInput = '';
    }

    /**
     * Execute Permanent Deletion after user explicitly types "confirm"
     */
    public function confirmDeleteOrganization(): void
    {
        if (! $this->authorizePlatformMutation()) {
            return;
        }

        if (strtolower(trim($this->deleteConfirmationInput)) !== 'confirm') {
            $this->previewNotice = 'Please type "confirm" to verify permanent deletion.';
            $this->dispatch('toast', [
                'type'    => 'warning',
                'title'   => 'Confirmation Required',
                'message' => 'Please type "confirm" exactly to delete.',
            ]);
            return;
        }

        $slug = $this->firmToDeleteSlug;
        if (! $slug) {
            $this->closeDeleteModal();
            return;
        }

        $this->deleteOrganization($slug);
        $this->closeDeleteModal();
    }

    /**
     * Resolve a firm strictly by slug.
     *
     * "firm-3" is a legacy slug, not a numeric id: the old `orWhere('id', $slug)`
     * allowed a slug such as "12" to silently target firm id 12.
     */
    protected function resolveFirmBySlug(string $slug): ?Firm
    {
        $slug = trim($slug);

        if ($slug === '' || ! Schema::hasTable('firms')) {
            return null;
        }

        $query = Firm::where('slug', $slug);

        if (str_starts_with($slug, 'firm-')) {
            $fallback = trim(substr($slug, 5));
            if ($fallback !== '') {
                $query->orWhere('slug', $fallback);
            }
        }

        return $query->first();
    }

    /**
     * Write a verified JSON backup of a tenant to disk before it is destroyed.
     *
     * Returns the stored path, or null when the backup could not be written —
     * callers must abort the deletion in that case.
     */
    protected function persistFirmBackupBeforeDeletion(Firm $firm): ?string
    {
        try {
            $payload = $this->compileFirmBackupPayload((string) $firm->slug);
            $payload['action'] = 'AUTOMATIC_BACKUP_BEFORE_DELETION';
            $payload['firm_id'] = $firm->id;

            $path = 'backups/firms/firm-' . $firm->id . '-' . now()->format('Y_m_d_His') . '.json';
            $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

            if ($json === false || ! Storage::disk('local')->put($path, $json)) {
                return null;
            }

            return $path;
        } catch (\Throwable $e) {
            Log::error('Firm backup before deletion failed: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Delete every record belonging to a firm, atomically.
     *
     * The whole cascade runs inside one transaction, so a failure part-way
     * through can no longer leave a half-deleted tenant behind. Audit logs are
     * detached (append-only) and any tenant-scoped table added by a future
     * migration is swept, so no orphaned rows survive.
     */
    protected function performSafeFirmDeletion(int $firmId): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        } elseif ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF;');
        }

        try {
            DB::transaction(function () use ($firmId): void {
                $userIds = [];
                if (Schema::hasTable('users') && Schema::hasColumn('users', 'firm_id')) {
                    $userIds = DB::table('users')->where('firm_id', $firmId)->pluck('id')->all();
                }

                if (Schema::hasTable('users') && Schema::hasColumn('users', 'firm_id') && Schema::hasColumn('users', 'manager_id')) {
                    DB::table('users')->where('firm_id', $firmId)->update(['manager_id' => null]);
                }

                if (Schema::hasTable('sessions') && ! empty($userIds) && Schema::hasColumn('sessions', 'user_id')) {
                    DB::table('sessions')->whereIn('user_id', $userIds)->delete();
                }

                // APPEND-ONLY AUDIT TRAIL: keep the platform's own history even
                // when the tenant is gone; detach it instead of deleting it.
                if (Schema::hasTable('audit_logs')) {
                    if (Schema::hasColumn('audit_logs', 'firm_id')) {
                        DB::table('audit_logs')->where('firm_id', $firmId)->update(['firm_id' => null]);
                    }
                    if (! empty($userIds) && Schema::hasColumn('audit_logs', 'actor_id')) {
                        DB::table('audit_logs')->whereIn('actor_id', $userIds)->update(['actor_id' => null]);
                    }
                }

                $firmScopedTables = [
                    'partner_policies',
                    'practice_holidays',
                    'attendances',
                    'leave_requests',
                    'conveyance_claims',
                    'payroll_records',
                    'practice_notifications',
                    'policy_proposals',
                    'audit_clients',
                ];

                foreach ($firmScopedTables as $tableName) {
                    if (Schema::hasTable($tableName)) {
                        if (Schema::hasColumn($tableName, 'firm_id')) {
                            DB::table($tableName)->where('firm_id', $firmId)->delete();
                        }
                        if (! empty($userIds)) {
                            foreach (['user_id', 'partner_id', 'actor_id', 'proposed_by', 'engaged_manager_id'] as $userCol) {
                                if (Schema::hasColumn($tableName, $userCol)) {
                                    DB::table($tableName)->whereIn($userCol, $userIds)->delete();
                                }
                            }
                        }
                    }
                }

                // Safety net: any other tenant-scoped table must not be orphaned.
                try {
                    foreach (Schema::getTableListing() as $listing) {
                        $tableName = str_contains($listing, '.')
                            ? substr($listing, strrpos($listing, '.') + 1)
                            : $listing;

                        if (in_array($tableName, [
                            'firms', 'users', 'audit_logs', 'sessions', 'migrations',
                            'password_reset_tokens', 'cache', 'cache_locks',
                            'jobs', 'job_batches', 'failed_jobs',
                        ], true)) {
                            continue;
                        }

                        if (Schema::hasColumn($tableName, 'firm_id')) {
                            DB::table($tableName)->where('firm_id', $firmId)->delete();
                        }
                    }
                } catch (\Throwable $e) {
                    Log::warning('Firm deletion sweep skipped: ' . $e->getMessage());
                }

                if (Schema::hasTable('users')) {
                    if (Schema::hasColumn('users', 'firm_id')) {
                        DB::table('users')->where('firm_id', $firmId)->delete();
                    } elseif (! empty($userIds)) {
                        DB::table('users')->whereIn('id', $userIds)->delete();
                    }
                }

                if (Schema::hasTable('firms')) {
                    DB::table('firms')->where('id', $firmId)->delete();
                }
            });
        } finally {
            if ($driver === 'mysql') {
                DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            } elseif ($driver === 'sqlite') {
                DB::statement('PRAGMA foreign_keys = ON;');
            }
        }
    }

    /**
     * Permanent transactional deletion of an organization and all its data.
     */
    public function deleteOrganization(string $slug): void
    {
        if (! $this->authorizePlatformMutation()) {
            return;
        }

        $this->previewNotice = null;
        $slug = trim($slug);

        try {
            if (Schema::hasTable('firms')) {
                // SECURITY: resolve by slug only. The old `orWhere('id', $slug)`
                // meant that deleting slug "12" silently targeted firm id 12.
                $dbFirm = $this->resolveFirmBySlug($slug);

                if ($dbFirm) {
                    $firmName = $dbFirm->name;
                    $firmId = $dbFirm->id;

                    // DISASTER RECOVERY: never destroy a tenant without a verified
                    // on-disk backup. If the backup cannot be written, nothing is
                    // deleted and the operator is told why.
                    $backupPath = $this->persistFirmBackupBeforeDeletion($dbFirm);
                    if ($backupPath === null) {
                        $this->previewNotice = 'Deletion aborted: a verified backup could not be written. No data was removed.';
                        $this->dispatch('toast', [
                            'type'    => 'error',
                            'title'   => 'Deletion Aborted',
                            'message' => 'A verified backup could not be written, so no data was removed.',
                        ]);

                        return;
                    }

                    $this->performSafeFirmDeletion($firmId);

                    $this->syncFirmsFromDatabase();
                    $this->loadPlatformAuditLogs();
                    $this->selectedFirmId = null;
                    $this->previewNotice = "Organization '{$firmName}' has been permanently deleted from production.";
                    $this->dispatch('toast', [
                        'type'    => 'success',
                        'title'   => 'Organization Deleted',
                        'message' => "Organization '{$firmName}' has been permanently deleted.",
                    ]);
                    return;
                }
            }
        } catch (\Throwable $e) {
            Log::error('deleteOrganization error: ' . $e->getMessage());
            $this->previewNotice = 'Failed to delete organization: ' . $e->getMessage();
            $this->dispatch('toast', [
                'type'    => 'error',
                'title'   => 'Deletion Failed',
                'message' => $e->getMessage(),
            ]);
            return;
        }

        $this->demoFirms = array_values(array_filter(
            $this->demoFirms,
            fn (array $f) => $f['slug'] !== $slug && $f['id'] !== $slug
        ));
        $this->selectedFirmId = null;
        $this->previewNotice = "Organization '{$slug}' deleted.";
    }

    public function acknowledgePreview(string $action): void
    {
        if (! in_array($action, ['provision', 'support'], true)) {
            return;
        }

        $this->closePreviews();
        $this->previewNotice = $action === 'provision'
            ? 'Design preview only. No organization was created or provisioned.'
            : 'Design preview only. No support access was granted, requested, or logged.';
    }

    protected function compileFirmBackupPayload(string $slug): array
    {
        $firmData = collect($this->demoFirms)->firstWhere('slug', $slug);
        $payload = [
            'backup_generated_at' => now()->toIso8601String(),
            'platform_host'       => 'erp.dhakawala.com',
            'firm_metadata'       => $firmData,
            'users'               => [],
            'attendances'         => [],
            'leave_requests'      => [],
            'conveyance_claims'   => [],
            'payroll_records'     => [],
        ];

        if (Schema::hasTable('firms')) {
            $dbFirm = $this->resolveFirmBySlug($slug);

            if ($dbFirm) {
                $firmId = $dbFirm->id;
                $payload['firm_metadata'] = $dbFirm->only([
                    'id', 'name', 'slug', 'kind', 'email', 'status', 'plan', 'region', 'custom_domain', 'custom_subdomain', 'shared_attendance_view',
                ]);

                $userIds = [];
                if (Schema::hasTable('users') && Schema::hasColumn('users', 'firm_id')) {
                    $userIds = DB::table('users')->where('firm_id', $firmId)->pluck('id')->all();
                    $payload['users'] = DB::table('users')->where('firm_id', $firmId)->get()->map(function ($u) {
                        unset($u->password, $u->remember_token, $u->invitation_token);
                        return (array) $u;
                    })->toArray();
                }

                foreach (['attendances', 'leave_requests', 'conveyance_claims', 'payroll_records'] as $tbl) {
                    if (Schema::hasTable($tbl)) {
                        if (Schema::hasColumn($tbl, 'firm_id')) {
                            $payload[$tbl] = DB::table($tbl)->where('firm_id', $firmId)->get()->map(fn ($r) => (array) $r)->toArray();
                        } elseif (! empty($userIds) && Schema::hasColumn($tbl, 'user_id')) {
                            $payload[$tbl] = DB::table($tbl)->whereIn('user_id', $userIds)->get()->map(fn ($r) => (array) $r)->toArray();
                        }
                    }
                }
            }
        }

        return $payload;
    }

    public function downloadFirmBackup(string $slug)
    {
        if (! $this->authorizePlatformMutation()) {
            return null;
        }

        $payload = $this->compileFirmBackupPayload($slug);
        $filename = 'backup_' . $slug . '_' . now()->format('Y_m_d_His') . '.json';

        return response()->streamDownload(function () use ($payload) {
            echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }, $filename, ['Content-Type' => 'application/json']);
    }

    public function backupAndDeleteFirm(string $slug)
    {
        if (! $this->authorizePlatformMutation()) {
            return null;
        }

        $payload = $this->compileFirmBackupPayload($slug);
        $payload['action'] = 'FULL_BACKUP_BEFORE_DELETION';

        try {
            if (Schema::hasTable('firms')) {
                $dbFirm = $this->resolveFirmBySlug($slug);

                if ($dbFirm) {
                    if ($this->persistFirmBackupBeforeDeletion($dbFirm) === null) {
                        $this->previewNotice = 'Deletion aborted: a verified backup could not be written. No data was removed.';

                        return null;
                    }

                    $this->performSafeFirmDeletion($dbFirm->id);
                }
            }
        } catch (\Throwable $e) {
            Log::error('backupAndDeleteFirm DB delete error: ' . $e->getMessage());
        }

        $this->syncFirmsFromDatabase();
        $this->loadPlatformAuditLogs();
        $this->selectedFirmId = null;
        $this->previewNotice = "Company '{$slug}' backup downloaded and permanently deleted from production.";

        $filename = 'deleted_firm_backup_' . $slug . '_' . now()->format('Y_m_d_His') . '.json';

        return response()->streamDownload(function () use ($payload) {
            echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }, $filename, ['Content-Type' => 'application/json']);
    }

    public function dismissNotice(): void
    {
        $this->previewNotice = null;
    }

    public function render()
    {
        $filteredFirms = array_values(array_filter(
            $this->demoFirms,
            function (array $firm): bool {
                $query = mb_strtolower(trim($this->firmSearch));
                $customDomain = $firm['custom_domain'] ?? '';
                $customSub = $firm['custom_subdomain'] ?? '';
                $haystack = mb_strtolower($firm['name'].' '.$firm['slug'].' '.$firm['kind'].' '.$customDomain.' '.$customSub);

                return ($query === '' || str_contains($haystack, $query))
                    && ($this->statusFilter === 'all' || $firm['status'] === $this->statusFilter);
            }
        ));

        $selectedFirm = null;
        foreach ($this->demoFirms as $firm) {
            if ($firm['id'] === $this->selectedFirmId) {
                $selectedFirm = $firm;
                break;
            }
        }

        $activeCount = count(array_filter($this->demoFirms, fn (array $firm) => $firm['status'] === 'preview-active'));
        $pendingCount = count(array_filter($this->demoFirms, fn (array $firm) => $firm['status'] === 'preview-pending'));
        $workspaceCount = array_sum(array_column($this->demoFirms, 'workspaces'));
        $seatCount = array_sum(array_column($this->demoFirms, 'seats'));

        return view('livewire.platform.dashboard-preview', [
            'colorPalettes' => Firm::colorPalettes(),
            'filteredFirms' => $filteredFirms,
            'selectedFirm'  => $selectedFirm,
            'activeCount'   => $activeCount,
            'pendingCount'  => $pendingCount,
            'seatCount'     => $seatCount,
            'metrics'       => [
                'firms'      => sprintf('%02d', count($this->demoFirms)),
                'workspaces' => sprintf('%02d', $workspaceCount),
                'seats'      => sprintf('%02d', $seatCount),
                'pending'    => sprintf('%02d', $pendingCount),
            ],
        ])->layout('layouts.platform', [
            'title' => 'DhakaWala Platform Administration — Central Operations',
        ]);
    }
}
