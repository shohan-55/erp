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
        $this->password = '';
        $this->validationError = null;
        $this->simulatedSuccessMessage = null;

        $this->dispatch('toast', [
            'title' => '[Preview] Platform Identity Selected',
            'message' => 'Selected preview operator identity (admin@dhakawala.com). Enter any password to test preview submission.',
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
            $this->validationError = 'Please enter your platform password.';
            return;
        }

        if (strtolower(trim($this->email)) === 'admin@dhakawala.com') {
            $this->simulatedSuccessMessage = 'Design Preview Only: Platform operator console routing simulated. Note: Platform Super Admins do not possess ambient access to CA Firm tenant private records under the Privacy-First SaaS policy. Real authentication is deferred to Phase 03+.';

            $this->dispatch('toast', [
                'title' => '[Preview] Platform Navigation Simulated',
                'message' => 'Simulating platform console routing for authorized operator preview.',
                'type' => 'info'
            ]);
        } else {
            // Do not show 'Authenticated' or successful login for arbitrary credentials
            $this->validationError = 'Design Preview Notice: Real backend authentication is not implemented in Phase 02. Arbitrary credentials cannot be verified or authenticated. To test the platform operator layout, please use the designated preview identity (admin@dhakawala.com).';

            $this->dispatch('toast', [
                'title' => '[Preview] Real Auth Not Implemented',
                'message' => 'Arbitrary credentials cannot be authenticated in Phase 02 design preview.',
                'type' => 'warning'
            ]);
        }
    }

    public function render()
    {
        return view('livewire.auth.platform-login')
            ->layout('layouts.auth', [
                'title' => 'Platform Super Admin Portal — DhakaWala ERP SaaS'
            ]);
    }
}
