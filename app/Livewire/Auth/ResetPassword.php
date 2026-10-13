<?php

namespace App\Livewire\Auth;

use App\Services\FirmRegistry;
use Livewire\Component;

class ResetPassword extends Component
{
    // Route parameter {firmSlug}
    public ?string $firmSlug = null;
    public ?array $firm = null;
    public bool $isPlatform = false;

    // Form inputs
    public string $password = '';
    public string $passwordConfirmation = '';
    public bool $showPassword = false;
    public bool $showConfirmation = false;

    // Interactive Preview State Switcher: 'form' | 'mismatch' | 'expired' | 'completed'
    public string $previewState = 'form';
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

    public function setPreviewState(string $state): void
    {
        $this->previewState = $state;
        $this->validationError = null;

        if ($state === 'mismatch') {
            $this->password = '';
            $this->passwordConfirmation = '';
            $this->validationError = 'Design Preview: Password confirmation does not match the chosen password.';
        } elseif ($state === 'form') {
            $this->password = '';
            $this->passwordConfirmation = '';
        }

        $this->dispatch('toast', [
            'title' => '[Preview] State Switched',
            'message' => "Switched Reset Password view to '{$state}' preview state.",
            'type' => 'info'
        ]);
    }

    public function togglePassword(): void
    {
        $this->showPassword = !$this->showPassword;
    }

    public function toggleConfirmation(): void
    {
        $this->showConfirmation = !$this->showConfirmation;
    }

    public function submitReset(): void
    {
        $this->validationError = null;

        if (strlen($this->password) < 8) {
            $this->validationError = 'Password must contain at least 8 characters.';
            return;
        }

        if (!preg_match('/[A-Z]/', $this->password)) {
            $this->validationError = 'Password must include at least one uppercase letter (A-Z).';
            return;
        }

        if (!preg_match('/[0-9]/', $this->password)) {
            $this->validationError = 'Password must include at least one numeric digit (0-9).';
            return;
        }

        if (!preg_match('/[^A-Za-z0-9]/', $this->password)) {
            $this->validationError = 'Password must include at least one special character.';
            return;
        }

        if ($this->password !== $this->passwordConfirmation) {
            $this->validationError = 'The password confirmation does not match.';
            return;
        }

        $this->previewState = 'completed';

        $this->dispatch('toast', [
            'title' => '[Preview] Password Reset Simulated',
            'message' => 'Password reset simulated successfully. In production, single-use token will be consumed.',
            'type' => 'success'
        ]);
    }

    // Dynamic requirement checks for live checklist
    public function getHasMinLengthProperty(): bool
    {
        return strlen($this->password) >= 8;
    }

    public function getHasUppercaseProperty(): bool
    {
        return (bool) preg_match('/[A-Z]/', $this->password);
    }

    public function getHasNumberProperty(): bool
    {
        return (bool) preg_match('/[0-9]/', $this->password);
    }

    public function getHasSpecialCharProperty(): bool
    {
        return (bool) preg_match('/[^A-Za-z0-9]/', $this->password);
    }

    public function getIsMatchProperty(): bool
    {
        return !empty($this->password) && ($this->password === $this->passwordConfirmation);
    }

    public function render()
    {
        $firmName = $this->firm ? $this->firm['name'] : ($this->isPlatform ? 'DhakaWala Platform' : 'Practice');
        return view('livewire.auth.reset-password')
            ->layout('layouts.auth', [
                'title' => "Set New Password — {$firmName} | RA&C ERP SaaS"
            ]);
    }
}
