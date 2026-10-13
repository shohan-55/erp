<?php

namespace App\Livewire;

use Livewire\Component;

class AppShell extends Component
{
    // Active Role Preview (Super Admin, Partner, Manager, Student) - Design Preview Only
    public string $currentRole = 'partner';

    // Active View Tab for Phase 01 Prototype
    public string $activeTab = 'dashboard';

    // Sidebar state
    public bool $sidebarCollapsed = false;
    public bool $mobileMenuOpen = false;

    // Interactive Demo Modals
    public bool $isModalOpen = false;
    public bool $isConfirmModalOpen = false;

    // Interactive Demo Controls
    public bool $showSkeletons = false;
    public string $searchQuery = '';
    public string $filterStatus = 'all';

    // Current Organization Branch Context
    public string $currentBranch = 'EDB Trade Center Branch, Karwan Bazar';

    // Role definitions (Fictional sample profiles for Phase 01 design demonstration)
    public array $roles = [
        'partner' => [
            'name' => 'A.K.M. Mohitul Haq, FCA',
            'role_title' => 'Senior Partner / Principal',
            'partner_group' => 'Partner Unit 01 (MH)',
            'badge' => 'Partner / Principal',
            'initials' => 'MH',
            'email' => 'mohitul.haq@rahmananis.com',
            'access_level' => 'Tier 2 Firm Principal (Design Preview)'
        ],
        'manager' => [
            'name' => 'Khandaker Tanvir Ahmed, ACA',
            'role_title' => 'Audit & Assurance Manager',
            'partner_group' => 'Partner Unit 01 (MH)',
            'badge' => 'Manager',
            'initials' => 'TA',
            'email' => 'tanvir.ahmed@rahmananis.com',
            'access_level' => 'Tier 3 Team Manager (Design Preview)'
        ],
        'student' => [
            'name' => 'Zubair Hossain Siddique',
            'role_title' => 'Articled Student (Reg: 4128)',
            'partner_group' => 'Partner Unit 01 (MH)',
            'badge' => 'Student',
            'initials' => 'ZS',
            'email' => 'zubair.student@rahmananis.com',
            'access_level' => 'Tier 4 Articled Student (Design Preview)'
        ],
        'admin' => [
            'name' => 'DhakaWeb Systems Administrator',
            'role_title' => 'Platform Super Admin',
            'partner_group' => 'All CA Firms & Tenants',
            'badge' => 'Super Admin',
            'initials' => 'SA',
            'email' => 'sysadmin@dhakawala.com',
            'access_level' => 'Tier 1 Central Platform (Design Preview)'
        ]
    ];

    public function toggleSidebar(): void
    {
        $this->sidebarCollapsed = !$this->sidebarCollapsed;
    }

    public function toggleMobileMenu(): void
    {
        $this->mobileMenuOpen = !$this->mobileMenuOpen;
    }

    public function closeMobileMenu(): void
    {
        $this->mobileMenuOpen = false;
    }

    public function setRole(string $role): void
    {
        if (array_key_exists($role, $this->roles)) {
            $this->currentRole = $role;
            $this->dispatch('toast', [
                'title' => '[Preview] Role Context Switched',
                'message' => 'Now previewing application shell as ' . $this->roles[$role]['badge'] . ' (' . $this->roles[$role]['name'] . '). UI simulation only.',
                'type' => 'info'
            ]);
        }
    }

    public function setActiveTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    public function openModal(): void
    {
        $this->isModalOpen = true;
    }

    public function closeModal(): void
    {
        $this->isModalOpen = false;
    }

    public function openConfirmModal(): void
    {
        $this->isConfirmModalOpen = true;
    }

    public function closeConfirmModal(): void
    {
        $this->isConfirmModalOpen = false;
    }

    public function toggleSkeletons(): void
    {
        $this->showSkeletons = !$this->showSkeletons;
    }

    public function triggerToast(string $type = 'success'): void
    {
        $messages = [
            'success' => [
                '[Preview] Sign-off Simulated',
                'Partner approval simulated for design demonstration. No live financial ledger or database was altered.'
            ],
            'warning' => [
                '[Preview] Policy Review Simulated',
                'Sample threshold notification demo for Partner Unit 01. UI simulation only.'
            ],
            'danger' => [
                '[Preview] Validation Alert Simulated',
                'Sample verification warning rendered to demonstrate danger status state.'
            ],
            'info' => [
                '[Preview] Fiscal Period Note',
                'Statutory audit calendar milestone demo. System is currently in Phase 01 UI prototype mode.'
            ]
        ];

        $payload = $messages[$type] ?? $messages['success'];
        $this->dispatch('toast', [
            'title' => $payload[0],
            'message' => $payload[1],
            'type' => $type
        ]);
    }

    public function render()
    {
        $userData = $this->roles[$this->currentRole] ?? $this->roles['partner'];

        return view('livewire.app-shell', [
            'user' => $userData
        ]);
    }
}
