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
            $this->password = '';
            $this->validationError = null;
            $this->simulatedSuccessMessage = null;

            $this->dispatch('toast', [
                'title' => '[Preview] Identity Selected',
                'message' => "Selected preview identity for {$account['label']} ({$account['email']}). Enter any password to test design preview submission.",
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

        // Match against explicit preview identities
        $matchedRole = null;
        foreach ($this->demoAccounts as $key => $demo) {
            if (strtolower(trim($this->email)) === strtolower($demo['email'])) {
                $matchedRole = $key;
                break;
            }
        }

        if ($matchedRole !== null) {
            $account = $this->demoAccounts[$matchedRole];
            $this->simulatedSuccessMessage = "Design Preview Only: Simulated role-based routing for {$account['label']} ({$account['role_title']}). In production (Phase 03+), database credentials will be authenticated and redirected to your authorized workspace.";
            $this->simulatedRedirectUrl = route('firm.practice.preview', ['firmSlug' => $this->firmSlug, 'previewRole' => $matchedRole]);

            $this->dispatch('toast', [
                'title' => '[Preview] Role Routing Simulated',
                'message' => "Previewing destination for {$account['label']}. Real authentication will be added in Phase 03+.",
                'type' => 'info'
            ]);
        } else {
            // Do not show 'Authenticated' or successful login for arbitrary credentials
            $this->validationError = 'Design Preview Notice: Real backend authentication is not implemented in Phase 02. Arbitrary credentials cannot be verified or authenticated. To test role routing simulation, please choose one of the designated preview identities below, or click "Simulate Credential Error".';

            $this->dispatch('toast', [
                'title' => '[Preview] Real Auth Not Implemented',
                'message' => 'Arbitrary credentials cannot be authenticated in Phase 02 design preview.',
                'type' => 'warning'
            ]);
        }
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
