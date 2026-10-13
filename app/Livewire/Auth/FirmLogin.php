<?php

namespace App\Livewire\Auth;

use App\Services\FirmRegistry;
use Livewire\Component;

class FirmLogin extends Component
{
    // Bound to {firmSlug} route parameter
    public string $firmSlug = 'rac';
    public ?array $firm = null;

    // Form inputs
    public string $email = '';
    public string $password = '';
    public bool $remember = false;
    public bool $showPassword = false;

    // Interactive Demo Simulation States
    public bool $isSubmitting = false;
    public ?string $simulatedSuccessMessage = null;
    public ?string $simulatedRedirectUrl = null;
    public ?string $validationError = null;

    // Fictional demonstration credentials for easy reviewer evaluation
    public array $demoAccounts = [
        'partner' => [
            'label' => 'Principal / Partner',
            'email' => 'mohitul.haq@rahmananis.com',
            'role_title' => 'Senior Partner (MH)',
            'redirect' => '/',
            'role_key' => 'partner'
        ],
        'manager' => [
            'label' => 'Audit Manager',
            'email' => 'tanvir.ahmed@rahmananis.com',
            'role_title' => 'Audit & Assurance Manager',
            'redirect' => '/',
            'role_key' => 'manager'
        ],
        'student' => [
            'label' => 'Articled Student',
            'email' => 'zubair.student@rahmananis.com',
            'role_title' => 'Articled Student (Reg: 4128)',
            'redirect' => '/',
            'role_key' => 'student'
        ]
    ];

    public function mount(string $firmSlug = 'rac'): void
    {
        $this->firmSlug = strtolower(trim($firmSlug));
        $this->firm = FirmRegistry::get($this->firmSlug);
    }

    public function togglePassword(): void
    {
        $this->showPassword = !$this->showPassword;
    }

    public function fillDemoCredentials(string $role): void
    {
        if (isset($this->demoAccounts[$role])) {
            $account = $this->demoAccounts[$role];
            $this->email = $account['email'];
            $this->password = 'RahmanAnis@2026';
            $this->validationError = null;
            $this->simulatedSuccessMessage = null;

            $this->dispatch('toast', [
                'title' => '[Demo] Credentials Populated',
                'message' => "Filled demo identity for {$account['label']} ({$account['email']}). Password is simulated.",
                'type' => 'info'
            ]);
        }
    }

    public function submitLogin(): void
    {
        $this->validationError = null;
        $this->simulatedSuccessMessage = null;

        if (empty(trim($this->email))) {
            $this->validationError = 'Please enter your registered practice email or User ID.';
            return;
        }

        if (empty(trim($this->password))) {
            $this->validationError = 'Please enter your password.';
            return;
        }

        // Detect simulated role from demo credentials or default to student
        $matchedRole = 'student';
        foreach ($this->demoAccounts as $key => $demo) {
            if (strtolower(trim($this->email)) === strtolower($demo['email'])) {
                $matchedRole = $key;
                break;
            }
        }

        $account = $this->demoAccounts[$matchedRole];
        $this->simulatedSuccessMessage = "Authentication simulated successfully as {$account['label']} ({$account['role_title']}). In production, tenant authorization will redirect to your assigned workspace.";
        $this->simulatedRedirectUrl = url('/');

        $this->dispatch('toast', [
            'title' => '[Preview] Login Simulated',
            'message' => "Authenticated as {$account['label']}. Redirecting to Phase 01 Practice Workspace...",
            'type' => 'success'
        ]);
    }

    public function triggerErrorSimulation(): void
    {
        $this->validationError = 'The provided credentials do not match our practice records. Please verify your email or contact your Partner Unit administrator.';
        $this->simulatedSuccessMessage = null;

        $this->dispatch('toast', [
            'title' => '[Preview] Authentication Error State',
            'message' => 'Simulating invalid credentials error state for design review.',
            'type' => 'danger'
        ]);
    }

    public function render()
    {
        return view('livewire.auth.firm-login')
            ->layout('layouts.auth', [
                'title' => ($this->firm ? $this->firm['name'] : 'Unknown Firm') . ' — Practice Workspace Sign In | RA&C ERP'
            ]);
    }
}
