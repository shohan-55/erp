<?php

namespace App\Livewire\Auth;

use App\Livewire\Concerns\ConfirmsTwoFactor;
use App\Models\User;
use App\Services\FirmRegistry;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Attributes\Validate;
use Livewire\Component;

class FirmLogin extends Component
{
    use ConfirmsTwoFactor;

    // Bound to {firmSlug} route parameter
    public string $firmSlug = 'rac';
    public ?array $firm = null;

    // Form inputs
    #[Validate('required|email')]
    public string $email = '';

    #[Validate('required|string|min:8')]
    public string $password = '';

    public bool $remember = false;
    public bool $showPassword = false;

    // UI state
    public ?string $validationError = null;
    public ?string $simulatedSuccessMessage = null;
    public ?string $simulatedRedirectUrl = null;

    public function mount(string $firmSlug = 'rac'): void
    {
        // Redirect already-authenticated firm members away from login
        if (Auth::check() && Auth::user()->isFirmMember()) {
            $this->redirect(route('home'));
            return;
        }

        $this->firmSlug = strtolower(trim($firmSlug));
        $this->firm = FirmRegistry::get($this->firmSlug);
    }

    public function togglePassword(): void
    {
        $this->showPassword = !$this->showPassword;
    }

    public function fillDemoCredentials(string $role): void
    {
        $targetEmail = null;

        if (Schema::hasTable('users') && Schema::hasTable('firms')) {
            try {
                $firmModel = \App\Models\Firm::where('slug', $this->firmSlug)->first();
                if ($firmModel) {
                    $user = \App\Models\User::where('firm_id', $firmModel->id)->where('role', $role)->first();
                    if ($user) {
                        $targetEmail = $user->email;
                    }
                }
            } catch (\Throwable) {}
        }

        if (! $targetEmail) {
            if ($this->firmSlug === 'rac') {
                $targetEmail = match ($role) {
                    'partner' => 'mohitul.haq@rahmananis.com',
                    'manager' => 'tanvir.ahmed@rahmananis.com',
                    'student' => 'zubair.student@rahmananis.com',
                    default   => 'mohitul.haq@rahmananis.com',
                };
            } else {
                $domain = $this->firm['custom_domain'] ?? ($this->firmSlug . '.dhakawala.com');
                $targetEmail = $role . '@' . $domain;
            }
        }

        $this->email = $targetEmail;
        $this->password = '';
        $this->validationError = null;
        $this->dispatch('toast', [
            'type'    => 'info',
            'title'   => 'Credentials Filled',
            'message' => 'Credentials filled for role: ' . ucfirst($role),
        ]);
    }

    public function triggerErrorSimulation(): void
    {
        $this->validationError = 'Arbitrary credentials cannot be verified or authenticated.';
    }

    public function submitLogin(): void
    {
        $this->validationError = null;
        $this->simulatedSuccessMessage = null;

        // Manual empty check (gives user-facing messages consistent with old behavior)
        if (empty(trim($this->email))) {
            $this->validationError = 'Please enter your registered practice email or User ID.';
            return;
        }

        if (empty(trim($this->password))) {
            $this->validationError = 'Please enter your password.';
            return;
        }

        // ─── Rate Limiting ─────────────────────────────────────────────────
        $throttleKey = Str::lower($this->email) . '|' . request()->ip();

        if (RateLimiter::tooManyAttempts('firm-login:' . $throttleKey, 5)) {
            $seconds = RateLimiter::availableIn('firm-login:' . $throttleKey);
            $this->validationError = "Too many login attempts. Please try again in {$seconds} seconds.";
            return;
        }

        // ─── Verify firm exists ────────────────────────────────────────────
        if (! $this->firm) {
            $this->validationError = 'This firm workspace is not registered on this platform.';
            return;
        }

        // ─── Attempt Authentication ────────────────────────────────────────
        $credentials = [
            'email'    => strtolower(trim($this->email)),
            'password' => $this->password,
        ];

        $authenticated = false;
        $hasUsersTable = false;

        try {
            $hasUsersTable = Schema::hasTable('users');
        } catch (\Throwable) {
            $hasUsersTable = false;
        }

        if ($hasUsersTable) {
            try {
                $authenticated = Auth::attempt($credentials, $this->remember);
            } catch (\Throwable) {
                $authenticated = false;
            }
        }

        // Demo personas for the design previews. Gated on an explicit flag so a
        // stray APP_ENV=testing can never open the sign-in form; see config/erp.php.
        if (! $authenticated && config('erp.allow_test_logins')) {
            $testUsers = [
                'partner@dhakawala.com' => ['name' => 'A.K.M. Mohitul Haq, FCA', 'role' => 'partner'],
                'manager@dhakawala.com' => ['name' => 'Khandaker Tanvir Ahmed, ACA', 'role' => 'manager'],
                'student@dhakawala.com' => ['name' => 'Zubair Hossain Siddique', 'role' => 'student'],
                'mohitul.haq@rahmananis.com' => ['name' => 'A.K.M. Mohitul Haq, FCA', 'role' => 'partner'],
                'tanvir.ahmed@rahmananis.com' => ['name' => 'Khandaker Tanvir Ahmed, ACA', 'role' => 'manager'],
                'farhana.manager@rahmananis.com' => ['name' => 'Farhana Rahman, ACA', 'role' => 'manager'],
                'zubair.student@rahmananis.com' => ['name' => 'Zubair Hossain Siddique', 'role' => 'student'],
            ];
            $email = strtolower(trim($this->email));
            if (isset($testUsers[$email])) {
                $authenticated = true;
                $this->simulatedSuccessMessage = "Authenticated as {$testUsers[$email]['name']} ({$testUsers[$email]['role']})";
                $this->simulatedRedirectUrl = route('dashboard');
                $this->dispatch('toast', [
                    'type'    => 'success',
                    'title'   => 'Practice Sign-in Successful',
                    'message' => "Welcome back, {$testUsers[$email]['name']}.",
                ]);
                return;
            }
        }

        if (! $authenticated) {
            RateLimiter::hit('firm-login:' . $throttleKey, 60);
            $this->validationError = 'Arbitrary credentials cannot be verified or authenticated. The provided credentials do not match our practice records.';
            $this->password = '';
            return;
        }

        /** @var User $user */
        $user = Auth::user();

        // ─── Deactivated staff accounts (retained for payroll history) ────
        if ($user && Schema::hasTable('users') && Schema::hasColumn('users', 'is_active') && ! $user->isActive()) {
            Auth::logout();
            $this->validationError = 'This account has been deactivated. Please contact your practice administrator.';
            $this->password = '';
            return;
        }

        // ─── Verify user belongs to this firm ─────────────────────────────
        if ($user && ! $user->belongsToFirm($this->firmSlug)) {
            Auth::logout();
            $this->validationError = 'Your account is not registered with this practice workspace. Please use the correct firm URL.';
            $this->password = '';
            return;
        }

        // ─── Verify user is a firm member (not a platform admin) ──────────
        if ($user && ! $user->isFirmMember()) {
            Auth::logout();
            $this->validationError = 'Platform administrators must log in via the platform portal.';
            $this->password = '';
            return;
        }

        // ─── Optional second factor (P1-3.2) ──────────────────────────────
        // Inert unless this member has enrolled a TOTP secret.
        if ($user && ! $this->twoFactorGate($user)) {
            // Keep the password: the follow-up submit re-runs Auth::attempt
            // together with the code, and forcing a retype here only trains
            // people to paste passwords around.
            $this->validationError = $this->twoFactorError;
            return;
        }

        // ─── Success ───────────────────────────────────────────────────────
        RateLimiter::clear('firm-login:' . $throttleKey);
        session()->regenerate();

        $userName = $user ? $user->name : 'Practice Member';
        $userRole = $user ? $user->role : 'member';

        $this->simulatedSuccessMessage = "Authenticated as {$userName} ({$userRole})";
        $this->simulatedRedirectUrl = route('dashboard');
        $this->dispatch('toast', [
            'type'    => 'success',
            'title'   => 'Practice Sign-in Successful',
            'message' => "Welcome back, {$userName}.",
        ]);

        if (! app()->runningUnitTests() && ! app()->environment('testing')) {
            $this->redirect(route('dashboard'), navigate: true);
        }
    }

    public function render()
    {
        return view('livewire.auth.firm-login')
            ->layout('layouts.auth', [
                'title' => ($this->firm ? $this->firm['name'] : 'Practice Workspace') . ' — Sign In | DhakaWala ERP'
            ]);
    }
}
