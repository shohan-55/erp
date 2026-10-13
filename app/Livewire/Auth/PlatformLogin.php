<?php

namespace App\Livewire\Auth;

use Livewire\Component;

class PlatformLogin extends Component
{
    public string $email = '';
    public string $password = '';
    public bool $remember = false;
    public bool $showPassword = false;

    // Interactive Demo Simulation States
    public bool $isSubmitting = false;
    public ?string $simulatedSuccessMessage = null;
    public ?string $validationError = null;

    public function togglePassword(): void
    {
        $this->showPassword = !$this->showPassword;
    }

    public function fillPlatformCredentials(): void
    {
        $this->email = 'admin@dhakawala.com';
        $this->password = 'PlatformRoot#2026';
        $this->validationError = null;
        $this->simulatedSuccessMessage = null;

        $this->dispatch('toast', [
            'title' => '[Demo] Platform Credentials Populated',
            'message' => 'Filled demo Super Admin identity for DhakaWala Platform Management.',
            'type' => 'info'
        ]);
    }

    public function triggerErrorSimulation(): void
    {
        $this->validationError = 'Platform administrative credentials rejected. Unauthorized access attempts to the central cluster are logged.';
        $this->simulatedSuccessMessage = null;

        $this->dispatch('toast', [
            'title' => '[Preview] Platform Authentication Error',
            'message' => 'Simulating central cluster authentication rejection state.',
            'type' => 'danger'
        ]);
    }

    public function submitLogin(): void
    {
        $this->validationError = null;
        $this->simulatedSuccessMessage = null;

        if (empty(trim($this->email))) {
            $this->validationError = 'Please provide an authorized platform operator email address.';
            return;
        }

        if (empty(trim($this->password))) {
            $this->validationError = 'Please enter your platform root password.';
            return;
        }

        $this->simulatedSuccessMessage = 'Platform operator authentication simulated. Note: Platform Super Admins do not possess ambient access to CA Firm tenant private records under the Privacy-First SaaS policy.';

        $this->dispatch('toast', [
            'title' => '[Preview] Platform Authentication Simulated',
            'message' => 'Authenticated as Platform Operator. Multi-tenant privacy safeguards are active.',
            'type' => 'success'
        ]);
    }

    public function render()
    {
        return view('livewire.auth.platform-login')
            ->layout('layouts.auth', [
                'title' => 'Platform Super Admin Portal — DhakaWala ERP SaaS'
            ]);
    }
}
