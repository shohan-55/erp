<?php

namespace App\Livewire\Platform;

use App\Models\Firm;
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

    // New Organization Provisioning fields
    public string $newFirmName = '';
    public string $newFirmSlug = '';
    public string $newFirmPlan = 'Practice';
    public string $newFirmRegion = 'Dhaka';

    public array $demoFirms = [
        [
            'id' => 'sample-rac', 'name' => 'Rahman Anis & Co.', 'slug' => 'rac',
            'kind' => 'Chartered Accountants', 'status' => 'preview-active',
            'workspaces' => 3, 'seats' => 12, 'plan' => 'Practice',
            'region' => 'Dhaka', 'initials' => 'RA',
        ],
        [
            'id' => 'sample-alpha', 'name' => 'Sample Practice Alpha', 'slug' => 'sample-alpha',
            'kind' => 'Example accounting practice', 'status' => 'preview-active',
            'workspaces' => 2, 'seats' => 8, 'plan' => 'Starter',
            'region' => 'Demo region', 'initials' => 'SA',
        ],
        [
            'id' => 'sample-beta', 'name' => 'Sample Practice Beta', 'slug' => 'sample-beta',
            'kind' => 'Example accounting practice', 'status' => 'preview-pending',
            'workspaces' => 1, 'seats' => 4, 'plan' => 'Starter',
            'region' => 'Demo region', 'initials' => 'SB',
        ],
    ];

    public array $demoSupportRequests = [
        [
            'id' => 'DEMO-101', 'firm' => 'Sample Practice Alpha',
            'subject' => 'Request to review navigation settings', 'scope' => 'Settings only',
            'approval' => 'Partner approval required', 'status' => 'Awaiting approval',
        ],
        [
            'id' => 'DEMO-102', 'firm' => 'Sample Practice Beta',
            'subject' => 'Configuration assistance request', 'scope' => 'Setup metadata only',
            'approval' => 'Partner approval required', 'status' => 'Not requested',
        ],
    ];

    private const SECTIONS = ['overview', 'firms', 'support', 'platform'];

    public function mount(): void
    {
        $this->syncFirmsFromDatabase();
    }

    protected function syncFirmsFromDatabase(): void
    {
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('firms')) {
                $dbFirms = Firm::all();
                if ($dbFirms->isNotEmpty()) {
                    $this->demoFirms = $dbFirms->map(fn (Firm $f) => [
                        'id' => $f->slug === 'rac' ? 'sample-rac' : ('sample-' . $f->slug),
                        'db_id' => $f->id,
                        'name' => $f->name,
                        'slug' => $f->slug,
                        'kind' => $f->kind,
                        'status' => $f->status === 'active' ? 'preview-active' : 'preview-pending',
                        'workspaces' => $f->workspaces,
                        'seats' => $f->seats,
                        'plan' => $f->plan,
                        'region' => $f->region,
                        'initials' => $f->initials,
                    ])->toArray();
                }
            }
        } catch (\Throwable $e) {
            // Fallback to static array if DB not migrated yet
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
            if ($firm['id'] === $id) {
                $this->selectedFirmId = $id;
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

    public function acknowledgePreview(string $action): void
    {
        if (! in_array($action, ['provision', 'support'], true)) {
            return;
        }

        if ($action === 'provision' && trim($this->newFirmName) !== '' && trim($this->newFirmSlug) !== '') {
            try {
                if (\Illuminate\Support\Facades\Schema::hasTable('firms')) {
                    Firm::updateOrCreate(
                        ['slug' => strtolower(trim($this->newFirmSlug))],
                        [
                            'name' => trim($this->newFirmName),
                            'kind' => 'Chartered Accountants',
                            'status' => 'active',
                            'workspaces' => 1,
                            'seats' => 10,
                            'plan' => $this->newFirmPlan,
                            'region' => $this->newFirmRegion,
                            'initials' => strtoupper(substr(trim($this->newFirmName), 0, 2)),
                        ]
                    );
                    $this->syncFirmsFromDatabase();
                    $this->closePreviews();
                    $this->previewNotice = "Organization '{$this->newFirmName}' provisioned successfully!";
                    return;
                }
            } catch (\Throwable $e) {
            }
        }

        $this->closePreviews();
        $this->previewNotice = $action === 'provision'
            ? 'Design preview only. No organization was created or provisioned.'
            : 'Design preview only. No support access was granted, requested, or logged.';
    }

    /**
     * Platform Admin Feature #2: Download Complete Firm Data Backup (JSON)
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
            if (\Illuminate\Support\Facades\Schema::hasTable('firms')) {
                $dbFirm = Firm::with(['users', 'attendances', 'leaveRequests', 'conveyanceClaims'])
                    ->where('slug', $slug)
                    ->first();
                if ($dbFirm) {
                    $payload['firm_metadata'] = $dbFirm->only(['id', 'name', 'slug', 'kind', 'email', 'status', 'plan', 'region']);
                    $payload['users'] = $dbFirm->users->toArray();
                    $payload['attendances'] = $dbFirm->attendances->toArray();
                    $payload['leave_requests'] = $dbFirm->leaveRequests->toArray();
                    $payload['conveyance_claims'] = $dbFirm->conveyanceClaims->toArray();
                }
            }
        } catch (\Throwable $e) {
        }

        $filename = 'backup_' . $slug . '_' . now()->format('Y_m_d_His') . '.json';

        return response()->streamDownload(function () use ($payload) {
            echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }, $filename, ['Content-Type' => 'application/json']);
    }

    /**
     * Platform Admin Feature #2: Backup Firm Data AND Delete Firm Completely
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
            if (\Illuminate\Support\Facades\Schema::hasTable('firms')) {
                $dbFirm = Firm::with(['users', 'attendances', 'leaveRequests', 'conveyanceClaims'])
                    ->where('slug', $slug)
                    ->first();
                if ($dbFirm) {
                    $payload['firm_metadata'] = $dbFirm->only(['id', 'name', 'slug', 'kind', 'email', 'status', 'plan', 'region']);
                    $payload['users'] = $dbFirm->users->toArray();
                    $payload['attendances'] = $dbFirm->attendances->toArray();
                    $payload['leave_requests'] = $dbFirm->leaveRequests->toArray();
                    $payload['conveyance_claims'] = $dbFirm->conveyanceClaims->toArray();

                    // Delete the firm and all cascading records
                    $dbFirm->delete();
                }
            }
        } catch (\Throwable $e) {
        }

        // Remove from current list & update UI state
        $this->demoFirms = array_values(array_filter(
            $this->demoFirms,
            fn (array $f) => $f['slug'] !== $slug
        ));
        $this->selectedFirmId = null;
        $this->previewNotice = "Company '{$slug}' backup downloaded and company records deleted.";

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
                $haystack = mb_strtolower($firm['name'].' '.$firm['slug'].' '.$firm['kind']);

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
