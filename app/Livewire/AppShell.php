<?php

namespace App\Livewire;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class AppShell extends Component
{
    // Active navigation tab
    public string $activeTab = 'dashboard';

    // Role preview state
    public string $currentRole = 'partner';

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

    public array $roles = [
        'partner' => [
            'name'          => 'A.K.M. Mohitul Haq, FCA',
            'role_title'    => 'Senior Partner',
            'partner_unit'  => 'Partner Unit 01',
            'partner_group' => 'Partner Unit 01 • Executive Practice',
            'badge'         => 'Principal',
            'initials'      => 'MH',
            'email'         => 'mohitul.haq@rahmananis.com',
        ],
        'manager' => [
            'name'          => 'Khandaker Tanvir Ahmed, ACA',
            'role_title'    => 'Audit Manager',
            'partner_unit'  => 'Partner Unit 01',
            'partner_group' => 'Partner Unit 01 • Audit Field Team',
            'badge'         => 'Manager',
            'initials'      => 'TA',
            'email'         => 'tanvir.ahmed@rahmananis.com',
        ],
        'student' => [
            'name'          => 'Zubair Hossain Siddique',
            'role_title'    => 'Articled Student (Level 2)',
            'partner_unit'  => 'Partner Unit 01',
            'partner_group' => 'Partner Unit 01 • Field Articled',
            'badge'         => 'Student',
            'initials'      => 'ZS',
            'email'         => 'zubair.student@rahmananis.com',
        ],
        'admin' => [
            'name'          => 'Platform Super Admin',
            'role_title'    => 'System Administrator',
            'partner_unit'  => 'IT Operations',
            'partner_group' => 'IT Operations & Infrastructure',
            'badge'         => 'Super Admin',
            'initials'      => 'SA',
            'email'         => 'admin@dhakawala.com',
        ],
    ];

    public function mount(): void
    {
        if (Auth::check()) {
            /** @var User $user */
            $user = Auth::user();
            if (in_array($user->role, ['partner', 'manager', 'student'])) {
                $this->currentRole = $user->role;
            } elseif ($user->isPlatformAdmin()) {
                $this->currentRole = 'admin';
            }
        }
    }

    public function setRole(string $role): void
    {
        if (array_key_exists($role, $this->roles)) {
            $this->currentRole = $role;
            $this->dispatch('toast', [
                'title'   => '[Preview] Role Switched',
                'message' => "Previewing workspace as {$this->roles[$role]['name']} ({$this->roles[$role]['role_title']}). No live financial ledger records affected.",
                'type'    => 'info',
            ]);
        }
    }

    public function setActiveTab(string $tab): void
    {
        $allowed = ['dashboard', 'components', 'tokens', 'architecture'];
        if (in_array($tab, $allowed, true)) {
            $this->activeTab = $tab;
        }
    }

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
            'success' => ['[Preview] Sign-off Recorded', 'Partner approval simulation recorded. No live financial ledger records affected.'],
            'warning' => ['[Preview] Policy Review Required', 'A threshold notification has been issued for your Partner Unit.'],
            'danger'  => ['[Preview] Validation Alert', 'Verification warning triggered. Please review.'],
            'info'    => ['[Preview] Fiscal Period Note', 'Statutory audit calendar milestone noted.'],
        ];

        $payload = $messages[$type] ?? $messages['success'];
        $this->dispatch('toast', [
            'title'   => $payload[0],
            'message' => $payload[1],
            'type'    => $type,
        ]);
    }

    public function logout(): void
    {
        Auth::logout();
        session()->invalidate();
        session()->regenerateToken();
        $this->redirect(route('home'), navigate: true);
    }

    public function render()
    {
        $userData = $this->roles[$this->currentRole] ?? $this->roles['partner'];

        if (Auth::check()) {
            /** @var User $user */
            $user = Auth::user();
            $userData['name'] = $user->name;
            $userData['email'] = $user->email;
            if ($user->partner_unit) {
                $userData['partner_unit'] = $user->partner_unit;
                $userData['partner_group'] = $user->partner_unit . ' • Executive Practice';
            }
        }

        return view('livewire.app-shell', [
            'user' => $userData,
            'currentRole' => $this->currentRole,
            'activeTab' => $this->activeTab,
        ]);
    }
}
