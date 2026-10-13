<?php

namespace App\Livewire;

use Livewire\Component;

class AppShell extends Component
{
    // Active Role Preview (Super Admin, Partner, Manager, Student)
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

    // Role definitions
    public array $roles = [
        'partner' => [
            'name' => 'A.K.M. Mohitul Haq, FCA',
            'role_title' => 'Senior Partner / Principal',
            'partner_group' => 'Partner Unit 01 (MH)',
            'badge' => 'Partner / Principal',
            'initials' => 'MH',
            'email' => 'mohitul.haq@rahmananis.com',
            'access_level' => 'Tier 2 Firm Principal'
        ],
        'manager' => [
            'name' => 'Khandaker Tanvir Ahmed, ACA',
            'role_title' => 'Audit & Assurance Manager',
            'partner_group' => 'Partner Unit 01 (MH)',
            'badge' => 'Manager',
            'initials' => 'TA',
            'email' => 'tanvir.ahmed@rahmananis.com',
            'access_level' => 'Tier 3 Team Manager'
        ],
        'student' => [
            'name' => 'Zubair Hossain Siddique',
            'role_title' => 'Articled Student (Reg: 4128)',
            'partner_group' => 'Partner Unit 01 (MH)',
            'badge' => 'Student',
            'initials' => 'ZS',
            'email' => 'zubair.student@rahmananis.com',
            'access_level' => 'Tier 4 Articled Student'
        ],
        'admin' => [
            'name' => 'DhakaWeb Systems Administrator',
            'role_title' => 'Platform Super Admin',
            'partner_group' => 'All CA Firms & Tenants',
            'badge' => 'Super Admin',
            'initials' => 'SA',
            'email' => 'sysadmin@dhakawala.com',
            'access_level' => 'Tier 1 Central Platform'
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
                'title' => 'Role Context Switched',
                'message' => 'Now previewing application shell as ' . $this->roles[$role]['badge'] . ' (' . $this->roles[$role]['name'] . ')',
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
            'success' => ['Approval Logged', 'Partner sign-off successfully recorded in audit trail.'],
            'warning' => ['Policy Review Required', 'Weekly articled student log threshold reached for Partner Unit 01.'],
            'danger' => ['Verification Alert', 'Invalid conveyance voucher code entered.'],
            'info' => ['Fiscal Calendar Update', 'Q3 Statutory Audit cycle has officially commenced.']
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
