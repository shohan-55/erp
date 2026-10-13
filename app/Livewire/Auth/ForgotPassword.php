<?php

namespace App\Livewire\Auth;

use App\Services\FirmRegistry;
use Livewire\Component;

class ForgotPassword extends Component
{
    // Route parameter {firmSlug}
    public ?string $firmSlug = null;
    public ?array $firm = null;
    public bool $isPlatform = false;

    // Form input
    public string $email = '';
    public bool $submitted = false;
    public ?string $validationError = null;

    public function mount(?string $firmSlug = null): void
    {
        if ($firmSlug) {
            $this->firmSlug = strtolower(trim($firmSlug));
            $this->firm = FirmRegistry::get($this->firmSlug);
            $this->isPlatform = false;
        } else {
            $this->isPlatform = true;
        }
    }

    public function submitRecovery(): void
    {
        $this->validationError = null;

        if (empty(trim($this->email)) || !filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            $this->validationError = 'Please provide a valid registered practice or platform email address.';
            return;
        }

        // Privacy-first response: Always show success state without revealing existence
        $this->submitted = true;

        $this->dispatch('toast', [
            'title' => '[Preview] Recovery Request Simulated',
            'message' => 'Privacy-first recovery request simulated for ' . $this->email . '. No actual email was sent.',
            'type' => 'success'
        ]);
    }

    public function resetForm(): void
    {
        $this->submitted = false;
        $this->validationError = null;
    }

    public function fillDemoEmail(): void
    {
        $this->email = $this->isPlatform ? 'admin@dhakawala.com' : 'mohitul.haq@rahmananis.com';
        $this->validationError = null;
    }

    public function render()
    {
        $firmName = $this->firm ? $this->firm['name'] : ($this->isPlatform ? 'DhakaWala Platform' : 'Practice');
        return view('livewire.auth.forgot-password')
            ->layout('layouts.auth', [
                'title' => "Password Recovery — {$firmName} | RA&C ERP SaaS"
            ]);
    }
}
