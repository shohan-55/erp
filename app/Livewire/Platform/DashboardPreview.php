<?php

namespace App\Livewire\Platform;

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

    /**
     * Presentation-only organization metadata. These are NOT tenant records,
     * and figures below must never be treated as real operational statistics.
     */
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

    public function setSection(string $section): void
    {
        if (! in_array($section, self::SECTIONS, true)) {
            return;
        }

        $this->section = $section;
        $this->selectedFirmId = null;
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

        $this->closePreviews();
        $this->previewNotice = $action === 'provision'
            ? 'Design preview only. No organization was created or provisioned.'
            : 'Design preview only. No support access was granted, requested, or logged.';
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
            'title' => 'Platform Dashboard Preview — DhakaWala ERP SaaS',
        ]);
    }
}
