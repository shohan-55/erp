<?php

namespace App\Livewire\Auth;

use App\Services\FirmRegistry;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class AccountActivation extends Component
{
    // Route parameter {firmSlug}
    public string $firmSlug = 'rac';
    public ?array $firm = null;

    // Fictional invited user identity for design demonstration
    public array $invitedUser = [
        'name' => 'Farhan Tanvir',
        'email' => 'farhan.tanvir@dhakawala.com',
        'role' => 'Articled Student',
        'role_badge' => 'Audit & Assurance Unit II',
        'student_reg' => 'Reg: 4892',
        'inviter_name' => 'Senior Partner',
        'invited_at' => 'October 12, 2026',
        'expires_in' => '48 hours'
    ];

    // Form inputs
    public string $password = '';
    public string $passwordConfirmation = '';
    public bool $showPassword = false;
    public bool $showConfirmation = false;
    public bool $termsAccepted = false;

    // Interactive Preview State Switcher: 'valid' | 'expired' | 'used' | 'completed'
    public string $previewState = 'valid';
    public ?string $validationError = null;

    public function mount(string $firmSlug = 'rac'): void
    {
        // Redirect already authenticated users
        if (Auth::check()) {
            $this->redirect(route('dashboard'));
            return;
        }

        $this->firmSlug = strtolower(trim($firmSlug));
        $this->firm = FirmRegistry::get($this->firmSlug);

        if ($this->firm) {
            $domain = $this->firm['custom_domain'] ?? ($this->firmSlug . '.dhakawala.com');
            $this->invitedUser['email'] = 'farhan.tanvir@' . $domain;
            $this->invitedUser['inviter_name'] = 'Senior Partner (' . ($this->firm['name'] ?? 'Practice') . ')';
        }
    }

    public function setPreviewState(string $state): void
    {
        $this->previewState = $state;
        $this->validationError = null;

        $this->dispatch('toast', [
            'title' => '[Preview] Invitation State Switched',
            'message' => "Switched Account Activation view to '{$state}' preview state.",
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

    public function submitActivation(): void
    {
        $this->validationError = null;

        if (strlen($this->password) < 8) {
            $this->validationError = 'Password must contain at least 8 characters.';
            return;
        }

        if (!preg_match('/[A-Z]/', $this->password) || !preg_match('/[0-9]/', $this->password)) {
            $this->validationError = 'Password must meet all complexity requirements.';
            return;
        }

        if ($this->password !== $this->passwordConfirmation) {
            $this->validationError = 'Password confirmation does not match.';
            return;
        }

        if (!$this->termsAccepted) {
            $this->validationError = 'Please acknowledge the practice code of conduct and audit confidentiality policy.';
            return;
        }

        $this->previewState = 'completed';

        $this->dispatch('toast', [
            'title' => '[Preview] Account Activation Simulated',
            'message' => 'Account setup simulated for Farhan Tanvir. No database record was altered.',
            'type' => 'success'
        ]);
    }

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
        return view('livewire.auth.account-activation')
            ->layout('layouts.auth', [
                'title' => ($this->firm ? $this->firm['name'] : 'Practice') . ' — Account Invitation & Activation | DhakaWala ERP'
            ]);
    }
}
