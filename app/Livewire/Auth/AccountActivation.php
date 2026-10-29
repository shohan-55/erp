<?php

namespace App\Livewire\Auth;

use App\Models\AuditLog;
use App\Models\Firm;
use App\Models\User;
use App\Services\FirmRegistry;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;

class AccountActivation extends Component
{
    // Route parameter {firmSlug}
    public string $firmSlug = 'rac';
    public ?array $firm = null;
    public ?string $token = null;
    public ?int $targetUserId = null;

    // Invited user identity (populated from DB token when present, or fallback preview)
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
        $this->token = request()->query('token');
        $emailParam = request()->query('email');

        if ($this->firm) {
            $domain = $this->firm['custom_domain'] ?? ($this->firmSlug . '.dhakawala.com');
            $this->invitedUser['email'] = 'farhan.tanvir@' . $domain;
            $this->invitedUser['inviter_name'] = 'Senior Partner (' . ($this->firm['name'] ?? 'Practice') . ')';

            // Resolve real invited database account if token or email is supplied
            if (($this->token || $emailParam) && Schema::hasTable('users') && Schema::hasTable('firms')) {
                try {
                    $firmModel = Firm::where('slug', $this->firmSlug)->first();
                    if ($firmModel) {
                        $query = User::where('firm_id', $firmModel->id);
                        if ($this->token && Schema::hasColumn('users', 'invitation_token')) {
                            $query->where('invitation_token', $this->token);
                        } elseif ($emailParam) {
                            $query->where('email', strtolower(trim((string) $emailParam)));
                        }
                        $dbUser = $query->first();

                        if ($dbUser) {
                            $this->targetUserId = $dbUser->id;
                            $this->invitedUser = [
                                'name'         => $dbUser->name,
                                'email'        => $dbUser->email,
                                'role'         => match ($dbUser->role) {
                                    'partner' => 'Partner / Principal',
                                    'manager' => 'Audit Manager',
                                    default   => 'Articled Student',
                                },
                                'role_badge'   => $dbUser->partner_unit ?? 'Partner Unit 01',
                                'student_reg'  => $dbUser->student_code ? ('Code: ' . $dbUser->student_code) : 'Staff Member',
                                'inviter_name' => 'Managing Partner (' . $firmModel->name . ')',
                                'invited_at'   => $dbUser->created_at?->format('F d, Y') ?? now()->format('F d, Y'),
                                'expires_in'   => '48 hours',
                            ];
                            $this->previewState = 'valid';
                        } elseif ($this->token) {
                            $this->previewState = 'expired';
                        }
                    }
                } catch (\Throwable) {
                }
            }
        }
    }

    public function setPreviewState(string $state): void
    {
        if (! in_array($state, ['valid', 'expired', 'used', 'completed'], true)) {
            return;
        }

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

        // Persist real account activation if a database user is matched
        if ($this->targetUserId && Schema::hasTable('users')) {
            try {
                $dbUser = User::find($this->targetUserId);
                if ($dbUser) {
                    $dbUser->password = Hash::make($this->password);
                    $dbUser->email_verified_at = now();
                    if (Schema::hasColumn('users', 'invitation_token')) {
                        $dbUser->invitation_token = null;
                    }
                    $dbUser->save();

                    if (Schema::hasTable('audit_logs')) {
                        AuditLog::create([
                            'firm_id'    => $dbUser->firm_id,
                            'actor_id'   => $dbUser->id,
                            'actor_name' => $dbUser->name,
                            'actor_role' => $dbUser->role,
                            'module'     => 'team',
                            'action'     => 'account_activated',
                            'target_ref' => $dbUser->email,
                            'summary'    => "Member {$dbUser->name} completed invitation account activation.",
                        ]);
                    }

                    $this->previewState = 'completed';
                    $this->dispatch('toast', [
                        'title'   => 'Account Activated',
                        'message' => "Account activated for {$dbUser->name}. You may now sign in.",
                        'type'    => 'success',
                    ]);
                    return;
                }
            } catch (\Throwable) {
            }
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
