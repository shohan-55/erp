<?php

namespace App\Livewire\Auth;

use App\Services\FirmRegistry;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;
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

        // Send the password reset link via Laravel's built-in broker.
        // Privacy-first: We always show "submitted" regardless of whether the
        // email exists, to prevent user enumeration attacks.
        try {
            if (Schema::hasTable('users') && Schema::hasTable('password_reset_tokens')) {
                Password::sendResetLink(['email' => strtolower(trim($this->email))]);
            }
        } catch (\Throwable) {
            // Safe silent fallback for privacy and unmigrated testing environments
        }

        $this->submitted = true;

        $this->dispatch('toast', [
            'title'   => 'Recovery Email Sent',
            'message' => 'If an account with that email exists, a password reset link has been sent. Please check your inbox.',
            'type'    => 'success',
        ]);
    }

    public function resetForm(): void
    {
        $this->submitted = false;
        $this->validationError = null;
        $this->email = '';
    }

    public function render()
    {
        return view('livewire.auth.forgot-password')
            ->layout('layouts.auth', [
                'title' => ($this->firm ? $this->firm['name'] : 'Platform') . ' — Account Recovery | RA&C ERP'
            ]);
    }
}
