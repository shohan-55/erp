<?php

namespace App\Livewire\Auth;

use App\Services\FirmRegistry;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;
use Livewire\Component;

class ResetPassword extends Component
{
    // Route parameter {firmSlug}
    public ?string $firmSlug = null;
    public ?array $firm = null;
    public bool $isPlatform = false;

    // Token and email from the reset URL
    #[Url]
    public string $token = '';

    #[Url]
    public string $email = '';

    // Form inputs
    public string $password = '';
    public string $passwordConfirmation = '';
    public bool $showPassword = false;
    public bool $showConfirmation = false;

    // UI state: 'form' | 'completed' | 'invalid'
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

        $this->previewState = 'form';
    }

    public function setPreviewState(string $state): void
    {
        $this->previewState = $state;

        if ($state === 'mismatch') {
            $this->validationError = 'The password confirmation does not match.';
        } elseif ($state === 'expired' || $state === 'invalid') {
            $this->validationError = 'This password reset link is invalid or has expired. Please request a new one.';
        } else {
            $this->validationError = null;
        }
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

        // ─── Use Laravel's built-in password reset broker ─────────────────
        $status = Password::reset(
            [
                'email'                 => strtolower(trim($this->email)),
                'password'              => $this->password,
                'password_confirmation' => $this->passwordConfirmation,
                'token'                 => $this->token,
            ],
            function ($user, $password) {
                $user->forceFill([
                    'password'       => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            $this->previewState = 'completed';
            $this->dispatch('toast', [
                'title'   => 'Password Reset Successfully',
                'message' => 'Your password has been updated. Please log in with your new password.',
                'type'    => 'success',
            ]);
        } else {
            $this->previewState = 'invalid';
            $this->validationError = match($status) {
                Password::INVALID_TOKEN => 'This password reset link is invalid or has expired. Please request a new one.',
                Password::INVALID_USER  => 'We could not find an account with that email address.',
                default                 => 'An error occurred. Please try again.',
            };
        }
    }

    // ─── Dynamic requirement checks for live checklist ────────────────────────
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
                'title' => "Set New Password — {$firmName} | DhakaWala ERP"
            ]);
    }
}
