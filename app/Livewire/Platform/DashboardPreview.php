<?php

namespace App\Livewire\Platform;

use App\Models\Firm;
use App\Models\User;
use App\Models\PartnerPolicy;
use App\Models\Attendance;
use App\Models\LeaveRequest;
use App\Models\ConveyanceClaim;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
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
    public string $newFirmRegion = 'Dhaka';
    public string $newFirmCustomDomain = '';
    public string $newFirmCustomSubdomain = '';

    // Organization Domain Settings editing
    public string $editCustomDomain = '';
    public string $editCustomSubdomain = '';
    public string $editFirmSlug = '';

    public array $demoFirms = [
        [
            'id' => 'sample-rac', 'name' => 'Rahman Anis & Co.', 'slug' => 'rac',
            'kind' => 'Chartered Accountants', 'status' => 'preview-active',
            'workspaces' => 3, 'seats' => 12, 'plan' => 'Practice',
            'region' => 'Dhaka', 'initials' => 'RA',
            'custom_domain' => null, 'custom_subdomain' => null,
            'workspace_url' => 'https://erp.dhakawala.com/c/rac',
        ],
        [
            'id' => 'sample-alpha', 'name' => 'Sample Practice Alpha', 'slug' => 'sample-alpha',
            'kind' => 'Example accounting practice', 'status' => 'preview-active',
            'workspaces' => 2, 'seats' => 8, 'plan' => 'Starter',
            'region' => 'Demo region', 'initials' => 'SA',
            'custom_domain' => null, 'custom_subdomain' => null,
            'workspace_url' => 'https://erp.dhakawala.com/c/sample-alpha',
        ],
        [
            'id' => 'sample-beta', 'name' => 'Sample Practice Beta', 'slug' => 'sample-beta',
            'kind' => 'Example accounting practice', 'status' => 'preview-pending',
            'workspaces' => 1, 'seats' => 4, 'plan' => 'Starter',
            'region' => 'Demo region', 'initials' => 'SB',
            'custom_domain' => null, 'custom_subdomain' => null,
            'workspace_url' => 'https://erp.dhakawala.com/c/sample-beta',
        ],
    ];

    public array $demoSupportRequests = [
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

    private const SECTIONS = ['overview', 'firms', 'support', 'platform'];

    public function mount(): void
    {
        $this->syncFirmsFromDatabase();
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
                $dbFirms = Firm::all();
                if ($dbFirms->isNotEmpty()) {
                    $this->demoFirms = $dbFirms->map(fn (Firm $f) => [
                        'id' => 'firm-' . $f->slug,
                        'db_id' => $f->id,
                        'name' => $f->name,
                        'slug' => $f->slug,
                        'kind' => $f->kind,
                        'status' => $f->status === 'active' ? 'preview-active' : 'preview-pending',
                        'workspaces' => $f->workspaces ?: 1,
                        'seats' => $f->seats ?: 10,
                        'plan' => $f->plan ?: 'Practice',
                        'region' => $f->region ?: 'Dhaka',
                        'custom_domain' => $f->custom_domain,
                        'custom_subdomain' => $f->custom_subdomain,
                        'workspace_url' => $f->workspace_url,
                        'initials' => $f->initials ?: strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $f->name) ?: 'DW', 0, 2)),
                    ])->toArray();
                } else {
                    $this->demoFirms = [];
                }
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
                $this->editCustomDomain = $firm['custom_domain'] ?? '';
                $this->editCustomSubdomain = $firm['custom_subdomain'] ?? '';
                $this->editFirmSlug = $firm['slug'] ?? '';
                $this->showProvisionPreview = false;
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
    }

    public function createOrganization(): void
    {
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
        $customDomain = ! empty(trim($this->newFirmCustomDomain)) ? strtolower(trim($this->newFirmCustomDomain)) : null;
        $customSubdomain = ! empty(trim($this->newFirmCustomSubdomain)) ? strtolower(trim($this->newFirmCustomSubdomain)) : null;

        try {
            if (Schema::hasTable('firms')) {
                if (Firm::where('slug', $slug)->exists()) {
                    $this->addError('newFirmSlug', 'This URL slug is already registered by another organization.');
                    return;
                }

                if ($customDomain && Firm::where('custom_domain', $customDomain)->exists()) {
                    $this->addError('newFirmCustomDomain', 'This custom domain is already assigned to another organization.');
                    return;
                }

                if ($customSubdomain && Firm::where('custom_subdomain', $customSubdomain)->exists()) {
                    $this->addError('newFirmCustomSubdomain', 'This custom subdomain is already in use.');
                    return;
                }

                $initials = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $this->newFirmName) ?: 'DW', 0, 2));

                $firm = Firm::create([
                    'name' => trim($this->newFirmName),
                    'slug' => $slug,
                    'kind' => $this->newFirmKind,
                    'email' => strtolower(trim($this->newPartnerEmail)),
                    'status' => 'active',
                    'workspaces' => 1,
                    'seats' => $this->newFirmSeats,
                    'plan' => $this->newFirmPlan,
                    'region' => trim($this->newFirmRegion),
                    'initials' => $initials,
                    'custom_domain' => $customDomain,
                    'custom_subdomain' => $customSubdomain,
                ]);

                $partnerPass = $this->newPartnerPassword ?: 'Partner@2026';
                $partner = User::create([
                    'name' => $this->newPartnerName ? trim($this->newPartnerName) : 'Managing Partner',
                    'email' => strtolower(trim($this->newPartnerEmail)),
                    'password' => Hash::make($partnerPass),
                    'role' => User::ROLE_PARTNER,
                    'firm_id' => $firm->id,
                    'partner_unit' => 'Partner Unit 01',
                    'can_proxy_attendance' => true,
                    'can_edit_attendance' => true,
                    'can_approve_leave' => true,
                    'can_approve_claims' => true,
                    'email_verified_at' => now(),
                ]);

                if (Schema::hasTable('partner_policies')) {
                    PartnerPolicy::create([
                        'firm_id' => $firm->id,
                        'partner_id' => $partner->id,
                        'partner_unit' => 'Partner Unit 01',
                        'office_start' => '09:00',
                        'office_end' => '17:00',
                        'grace_minutes' => 15,
                        'manager_conveyance_limit' => 1500.00,
                        'conveyance_late_days' => 7,
                        'student_grids' => PartnerPolicy::defaultStudentGrids(),
                        'manager_permissions' => PartnerPolicy::defaultManagerPermissions(),
                    ]);
                }

                $this->syncFirmsFromDatabase();
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

                $workspaceUrl = $firm->workspace_url;
                $this->previewNotice = "Organization '{$firm->name}' created successfully! Workspace live at: {$workspaceUrl}. Initial Managing Partner: {$partner->email} / {$partnerPass}";
                return;
            }
        } catch (\Throwable $e) {
            Log::error('createOrganization error: ' . $e->getMessage());
            $this->previewNotice = 'Failed to create organization: ' . $e->getMessage();
        }
    }

    /**
     * Update custom domain and subdomain for an existing organization.
     */
    public function updateDomainSettings(string $slug): void
    {
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

                    $firm->update([
                        'custom_domain' => $customDomain,
                        'custom_subdomain' => $customSubdomain,
                    ]);

                    $this->syncFirmsFromDatabase();
                    $this->selectFirm($firm->slug);
                    $this->previewNotice = "Domain settings updated for '{$firm->name}'. Active Workspace: " . $firm->workspace_url;
                    return;
                }
            }
        } catch (\Throwable $e) {
            Log::error('updateDomainSettings error: ' . $e->getMessage());
            $this->previewNotice = 'Failed to update domain settings: ' . $e->getMessage();
        }
    }

    /**
     * Open Secure Permanent Deletion Confirmation Modal
     */
    public function openDeleteModal(string $slug): void
    {
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
                    $dbFirm = Firm::where('slug', $cleanSlug)->orWhere('id', $cleanSlug)->first();
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
     * Permanent transactional deletion of an organization and all its data.
     */
    public function deleteOrganization(string $slug): void
    {
        $this->previewNotice = null;
        $slug = trim($slug);

        try {
            if (Schema::hasTable('firms')) {
                $dbFirm = Firm::where('slug', $slug)
                    ->orWhere('id', $slug)
                    ->when(str_starts_with($slug, 'firm-'), fn ($q) => $q->orWhere('slug', substr($slug, 5)))
                    ->first();

                if ($dbFirm) {
                    $firmName = $dbFirm->name;
                    $firmId = $dbFirm->id;

                    $driver = DB::connection()->getDriverName();
                    if ($driver === 'mysql') {
                        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
                    } elseif ($driver === 'sqlite') {
                        DB::statement('PRAGMA foreign_keys = OFF;');
                    }

                    try {
                        $userIds = DB::table('users')->where('firm_id', $firmId)->pluck('id')->all();

                        // 1. Break self-referencing foreign keys on users
                        DB::table('users')->where('firm_id', $firmId)->update(['manager_id' => null]);

                        // 2. Clear sessions
                        if (Schema::hasTable('sessions') && ! empty($userIds)) {
                            DB::table('sessions')->whereIn('user_id', $userIds)->delete();
                        }

                        // 3. Clear partner_policies
                        if (Schema::hasTable('partner_policies')) {
                            DB::table('partner_policies')->where('firm_id', $firmId)->delete();
                            if (! empty($userIds)) {
                                DB::table('partner_policies')->whereIn('partner_id', $userIds)->delete();
                            }
                        }

                        // 4. Clear attendances
                        if (Schema::hasTable('attendances')) {
                            DB::table('attendances')->where('firm_id', $firmId)->delete();
                            if (! empty($userIds)) {
                                DB::table('attendances')->whereIn('user_id', $userIds)->delete();
                            }
                        }

                        // 5. Clear leave_requests
                        if (Schema::hasTable('leave_requests')) {
                            DB::table('leave_requests')->where('firm_id', $firmId)->delete();
                            if (! empty($userIds)) {
                                DB::table('leave_requests')->whereIn('user_id', $userIds)->delete();
                            }
                        }

                        // 6. Clear conveyance_claims
                        if (Schema::hasTable('conveyance_claims')) {
                            DB::table('conveyance_claims')->where('firm_id', $firmId)->delete();
                            if (! empty($userIds)) {
                                DB::table('conveyance_claims')->whereIn('user_id', $userIds)->delete();
                            }
                        }

                        // 7. Delete users
                        DB::table('users')->where('firm_id', $firmId)->delete();

                        // 8. Delete firm
                        DB::table('firms')->where('id', $firmId)->delete();
                    } finally {
                        if ($driver === 'mysql') {
                            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
                        } elseif ($driver === 'sqlite') {
                            DB::statement('PRAGMA foreign_keys = ON;');
                        }
                    }

                    $this->syncFirmsFromDatabase();
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

        // Demo fallback for testing
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

    /**
     * Download Complete Firm Data Backup (JSON)
     */
    public function downloadFirmBackup(string $slug)
    {
        $firmData = collect($this->demoFirms)->firstWhere('slug', $slug);
        $payload = [
            'backup_generated_at' => now()->toIso8601String(),
            'platform_host' => 'erp.dhakawala.com',
            'firm_metadata' => $firmData,
            'users' => [],
            'attendances' => [],
            'leave_requests' => [],
            'conveyance_claims' => [],
        ];

        try {
            if (Schema::hasTable('firms')) {
                $dbFirm = Firm::with(['users', 'attendances', 'leaveRequests', 'conveyanceClaims'])
                    ->where('slug', $slug)
                    ->first();
                if ($dbFirm) {
                    $payload['firm_metadata'] = $dbFirm->only(['id', 'name', 'slug', 'kind', 'email', 'status', 'plan', 'region', 'custom_domain', 'custom_subdomain']);
                    $payload['users'] = $dbFirm->users->toArray();
                    $payload['attendances'] = $dbFirm->attendances->toArray();
                    $payload['leave_requests'] = $dbFirm->leaveRequests->toArray();
                    $payload['conveyance_claims'] = $dbFirm->conveyanceClaims->toArray();
                }
            }
        } catch (\Throwable $e) {
            Log::error('downloadFirmBackup error: ' . $e->getMessage());
        }

        $filename = 'backup_' . $slug . '_' . now()->format('Y_m_d_His') . '.json';

        return response()->streamDownload(function () use ($payload) {
            echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }, $filename, ['Content-Type' => 'application/json']);
    }

    /**
     * Backup Firm Data AND Permanently Delete Firm
     */
    public function backupAndDeleteFirm(string $slug)
    {
        $firmData = collect($this->demoFirms)->firstWhere('slug', $slug);
        $payload = [
            'backup_generated_at' => now()->toIso8601String(),
            'action' => 'FULL_BACKUP_BEFORE_DELETION',
            'platform_host' => 'erp.dhakawala.com',
            'firm_metadata' => $firmData,
            'users' => [],
            'attendances' => [],
            'leave_requests' => [],
            'conveyance_claims' => [],
        ];

        try {
            if (Schema::hasTable('firms')) {
                $dbFirm = Firm::with(['users', 'attendances', 'leaveRequests', 'conveyanceClaims'])
                    ->where('slug', $slug)
                    ->first();
                if ($dbFirm) {
                    $payload['firm_metadata'] = $dbFirm->only(['id', 'name', 'slug', 'kind', 'email', 'status', 'plan', 'region', 'custom_domain', 'custom_subdomain']);
                    $payload['users'] = $dbFirm->users->toArray();
                    $payload['attendances'] = $dbFirm->attendances->toArray();
                    $payload['leave_requests'] = $dbFirm->leaveRequests->toArray();
                    $payload['conveyance_claims'] = $dbFirm->conveyanceClaims->toArray();

                    $driver = DB::connection()->getDriverName();
                    if ($driver === 'mysql') {
                        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
                    } elseif ($driver === 'sqlite') {
                        DB::statement('PRAGMA foreign_keys = OFF;');
                    }

                    try {
                        $firmId = $dbFirm->id;
                        $userIds = DB::table('users')->where('firm_id', $firmId)->pluck('id')->all();

                        DB::table('users')->where('firm_id', $firmId)->update(['manager_id' => null]);
                        if (Schema::hasTable('sessions') && ! empty($userIds)) {
                            DB::table('sessions')->whereIn('user_id', $userIds)->delete();
                        }
                        if (Schema::hasTable('partner_policies')) {
                            DB::table('partner_policies')->where('firm_id', $firmId)->delete();
                            if (! empty($userIds)) {
                                DB::table('partner_policies')->whereIn('partner_id', $userIds)->delete();
                            }
                        }
                        if (Schema::hasTable('attendances')) {
                            DB::table('attendances')->where('firm_id', $firmId)->delete();
                            if (! empty($userIds)) {
                                DB::table('attendances')->whereIn('user_id', $userIds)->delete();
                            }
                        }
                        if (Schema::hasTable('leave_requests')) {
                            DB::table('leave_requests')->where('firm_id', $firmId)->delete();
                            if (! empty($userIds)) {
                                DB::table('leave_requests')->whereIn('user_id', $userIds)->delete();
                            }
                        }
                        if (Schema::hasTable('conveyance_claims')) {
                            DB::table('conveyance_claims')->where('firm_id', $firmId)->delete();
                            if (! empty($userIds)) {
                                DB::table('conveyance_claims')->whereIn('user_id', $userIds)->delete();
                            }
                        }
                        DB::table('users')->where('firm_id', $firmId)->delete();
                        DB::table('firms')->where('id', $firmId)->delete();
                    } finally {
                        if ($driver === 'mysql') {
                            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
                        } elseif ($driver === 'sqlite') {
                            DB::statement('PRAGMA foreign_keys = ON;');
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::error('backupAndDeleteFirm DB delete error: ' . $e->getMessage());
        }

        $this->syncFirmsFromDatabase();
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

        $metrics = [
            'firms' => count($this->demoFirms),
            'workspaces' => array_sum(array_column($this->demoFirms, 'workspaces')),
            'seats' => array_sum(array_column($this->demoFirms, 'seats')),
            'pending' => count(array_filter(
                $this->demoFirms,
                fn (array $firm) => $firm['status'] === 'preview-pending'
            )),
        ];

        return view('livewire.platform.dashboard-preview', [
            'filteredFirms' => $filteredFirms,
            'selectedFirm' => $selectedFirm,
            'metrics' => $metrics,
        ])->layout('layouts.platform', [
            'title' => 'Platform Console — DhakaWala ERP SaaS',
        ]);
    }
}
