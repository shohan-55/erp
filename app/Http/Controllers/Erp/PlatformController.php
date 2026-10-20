<?php

namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use App\Support\OrganizationTemplate;
use App\Support\IdentityLink;
use App\Models\User;
use App\Support\OutboundMailGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PlatformController extends Controller
{
    public function login()
    {
        return view('erp.platform-login');
    }

    public function signIn(Request $request)
    {
        $input = $request->validate([
            'email' => ['required', 'email', 'max:254'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($input) || ! Auth::user()->is_platform_admin) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            throw ValidationException::withMessages(['email' => 'Invalid platform administrator credentials.']);
        }

        $request->session()->regenerate();
        $request->session()->forget(['erp_mfa_verified_user_id', 'erp_mfa_pending_secret', 'erp_mfa_setup_user_id', 'erp_mfa_firm_slug']);
        $request->session()->put('erp_mfa_context', 'platform');
        return redirect()->route('erp.platform.dashboard');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('erp.platform.login');
    }

    public function dashboard(Request $request)
    {
        // Platform operators see organization-level metadata, never private tenant records.
        $filter = [
            'q' => mb_substr(trim((string) $request->query('q', '')), 0, 80),
            'status' => (string) $request->query('status', 'all'),
        ];
        if (! in_array($filter['status'], ['all', 'active', 'suspended', 'archived', 'removed'], true)) {
            $filter['status'] = 'all';
        }

        $companies = DB::table('companies')
            ->when($filter['q'] !== '', fn ($q) => $q->where(function ($sub) use ($filter) {
                $sub->where('name', 'like', '%'.$filter['q'].'%')
                    ->orWhere('slug', 'like', '%'.$filter['q'].'%');
            }))
            ->when($filter['status'] === 'all', fn ($q) => $q->whereNull('deleted_at'))
            ->when($filter['status'] === 'active', fn ($q) => $q->where('active', true)->whereNull('archived_at')->whereNull('deleted_at'))
            ->when($filter['status'] === 'suspended', fn ($q) => $q->where('active', false)->whereNull('archived_at')->whereNull('deleted_at'))
            ->when($filter['status'] === 'archived', fn ($q) => $q->whereNotNull('archived_at')->whereNull('deleted_at'))
            ->when($filter['status'] === 'removed', fn ($q) => $q->whereNotNull('deleted_at'))
            ->orderByDesc('created_at')->get();
        $stats = [
            'companies' => DB::table('companies')->count(),
            'workspaces' => DB::table('workspaces')->count(),
            'active_memberships' => DB::table('memberships')->where('active', true)->count(),
            'pending_invitations' => DB::table('memberships')->where('active', false)
                ->whereNull('platform_suspended_at')->count(),
            'active_domains' => DB::table('company_custom_domains')->where('active', true)->count(),
            'domains_awaiting_setup' => DB::table('company_custom_domains')->where('active', false)->count(),
        ];

        $workspaces = DB::table('workspaces')->select('company_id', DB::raw('COUNT(*) as total'))
            ->groupBy('company_id')->pluck('total', 'company_id');

        $domains = DB::table('company_custom_domains')->get()->keyBy('company_id');
        $recentOperations = DB::table('platform_control_events as e')
            ->join('companies as c', 'c.id', '=', 'e.company_id')
            ->select('e.action', 'e.subject_type', 'e.created_at', 'c.slug', 'c.name')
            ->orderByDesc('e.id')->limit(8)->get();

        return view('erp.platform-dashboard', compact('companies', 'stats', 'workspaces', 'domains', 'filter', 'recentOperations'));
    }

    public function createFirm(Request $request)
    {
        $data = $request->validate([
            'company_name' => ['required', 'string', 'min:3', 'max:255'],
            'company_slug' => ['required', 'regex:/^[a-z0-9][a-z0-9-]{1,68}[a-z0-9]$/'],
            'organization_type' => ['nullable', \Illuminate\Validation\Rule::in(array_keys(OrganizationTemplate::TYPES))],
            'partner_name' => ['required', 'string', 'min:3', 'max:255'],
            'partner_email' => ['required', 'email', 'max:254'],
            'timezone' => ['nullable', 'timezone'],
            'brand_name' => ['nullable', 'string', 'max:100'],
            'brand_accent_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'min_active_partners_for_company_policy' => ['nullable', 'integer', 'between:1,200'],
        ]);
        // The product is owned by DhakaWala, never by an individual tenant.
        // Industry presets share the same secure membership and billing engine.
        // Legacy onboarding without a type continues to create a CA firm.
        if (in_array($data['company_slug'], [
            'platform', 'login', 'logout', 'admin', 'api', 'c', 'dashboard',
            'security', 'storage', 'support', 'register', 'forgot-password',
        ], true)) {
            throw ValidationException::withMessages([
                'company_slug' => 'This address is reserved for the ERP platform.',
            ]);
        }
        OutboundMailGuard::ensureConfigured();
        $email = strtolower(trim($data['partner_email']));
        if (User::where('email', $email)->where('is_platform_admin', true)->exists()) {
            throw ValidationException::withMessages([
                'partner_email' => 'Platform administrator accounts cannot be invited into private customer workspaces.',
            ]);
        }

        if (DB::table('companies')->where('slug', $data['company_slug'])->exists()) {
            throw ValidationException::withMessages(['company_slug' => 'Company slug already exists.']);
        }

        [$user, $companyName, $slug, $membershipId, $existing] = DB::transaction(function () use ($data, $email) {
            $user = User::where('email', $email)->first();
            $existing = (bool) $user;
            $user ??= $this->invitedUser($email, $data['partner_name']);
            $companyId = DB::table('companies')->insertGetId([
                'slug' => $data['company_slug'], 'name' => trim($data['company_name']),
                'organization_type' => $data['organization_type'] ?? 'ca_firm',
                'brand_name' => trim($data['brand_name'] ?? '') ?: null,
                'brand_accent_color' => $data['brand_accent_color'] ?? '#FCDF02',
                'min_active_partners_for_company_policy' => (int) ($data['min_active_partners_for_company_policy'] ?? 1),
                'timezone' => $data['timezone'] ?? 'Asia/Dhaka', 'office_start' => '10:00:00',
                'office_end' => '18:00:00', 'grace_minutes' => 10,
                'weekly_holidays' => '[5,6]', 'active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $workspaceId = DB::table('workspaces')->insertGetId([
                'company_id' => $companyId, 'slug' => 'partner-01',
                'name' => OrganizationTemplate::workspaceName($data['organization_type'] ?? 'ca_firm'), 'active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $membershipId = $this->insertPartnerInvitation($companyId, $workspaceId, $user->id);
            DB::table('platform_control_events')->insert([
                'company_id' => $companyId,
                'actor_user_id' => Auth::id(),
                'action' => 'company.onboarded',
                'subject_type' => 'company',
                'subject_id' => $companyId,
                'details' => json_encode([
                    'organization_type' => $data['organization_type'] ?? 'ca_firm',
                    'initial_workspace_id' => $workspaceId,
                ], JSON_THROW_ON_ERROR),
                'created_at' => now(),
            ]);
            return [$user, $data['company_name'], $data['company_slug'], $membershipId, $existing];
        }, 3);

        if ($existing) {
            IdentityLink::issue($user, $membershipId, $companyName);
        } else {
            $this->emailInvite($user, $companyName, $slug);
        }
        return back()->with('success', 'Organization created from a business template; primary administrator must accept the invitation.');
    }

    public function addPartner(Request $request, int $companyId)
    {
        $data = $request->validate([
            'workspace_slug' => ['required', 'regex:/^[a-z0-9][a-z0-9-]{1,78}[a-z0-9]$/'],
            'workspace_name' => ['required', 'string', 'min:3', 'max:255'],
            'partner_email' => ['required', 'email', 'max:254'],
            'partner_name' => ['required', 'string', 'min:3', 'max:255'],
        ]);

        $company = DB::table('companies')->where('id', $companyId)->where('active', true)->first();
        abort_unless($company, 404);

        if (DB::table('workspaces')->where('company_id', $companyId)
            ->where('slug', $data['workspace_slug'])->exists()) {
            throw ValidationException::withMessages(['workspace_slug' => 'That workspace slug already exists.']);
        }

        OutboundMailGuard::ensureConfigured();
        $email = strtolower(trim($data['partner_email']));
        if (User::where('email', $email)->where('is_platform_admin', true)->exists()) {
            throw ValidationException::withMessages([
                'partner_email' => 'Platform administrator accounts cannot be invited into private customer workspaces.',
            ]);
        }

        [$user, $membershipId, $existing] = DB::transaction(function () use ($data, $email, $companyId) {
            $firm = DB::table('companies')->where('id', $companyId)->lockForUpdate()->first();
            abort_unless($firm && $firm->active && $firm->archived_at === null, 404);
            if (DB::table('workspaces')->where('company_id', $companyId)
                ->where('slug', $data['workspace_slug'])->exists()) {
                throw ValidationException::withMessages(['workspace_slug' => 'That workspace slug already exists.']);
            }

            \App\Support\SaasBilling::assertCapacity($companyId,1,1);
            $user = User::where('email', $email)->first();
            $existing = (bool) $user;
            $user ??= $this->invitedUser($email, $data['partner_name']);

            $workspaceId = DB::table('workspaces')->insertGetId([
                'company_id' => $companyId, 'slug' => $data['workspace_slug'],
                'name' => $data['workspace_name'], 'active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $membershipId = $this->insertPartnerInvitation($companyId, $workspaceId, $user->id);
            DB::table('platform_control_events')->insert([
                'company_id' => $companyId,
                'actor_user_id' => Auth::id(),
                'action' => 'partner.workspace.created',
                'subject_type' => 'workspace',
                'subject_id' => $workspaceId,
                'details' => json_encode(['workspace_slug' => $data['workspace_slug']], JSON_THROW_ON_ERROR),
                'created_at' => now(),
            ]);
            return [$user, $membershipId, $existing];
        }, 3);

        if ($existing) {
            IdentityLink::issue($user, $membershipId, $company->name);
        } else {
            $this->emailInvite($user, $company->name, $company->slug);
        }
        return back()->with('success', 'Partner workspace created and invitation email requested.');
    }

    private function invitedUser(string $email, string $name): User
    {
        // Never silently attach a pre-existing global identity to a new firm
        // or reset that person's password as part of onboarding.
        if (User::where('email', $email)->exists()) {
            throw ValidationException::withMessages(['partner_email' =>
                'This email already belongs to an ERP identity.']);
        }
        return User::create([
            'name' => trim($name), 'email' => $email,
            'password' => Hash::make(Str::random(64)),
        ]);
    }

    private function insertPartnerInvitation(int $companyId, int $workspaceId, int $userId): int
    {
        return DB::table('memberships')->insertGetId([
            'company_id' => $companyId, 'workspace_id' => $workspaceId,
            'user_id' => $userId, 'role' => 'partner', 'active' => false,
            'monthly_allowance' => '0.00', 'invited_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function emailInvite(User $user, string $companyName, string $slug): void
    {
        $token = Password::broker()->createToken($user);
        $url = route('erp.activate.form', [
            'firmSlug' => $slug, 'token' => $token, 'email' => $user->email,
        ]);
        Mail::raw(
            "You have been invited to manage ".$companyName." on DhakaWala ERP.\n\n".
            "Use this one-time link to accept the invitation and set your password:\n".
            $url."\n\nIgnore the invitation if you were not expecting it.",
            fn ($msg) => $msg->to($user->email)->subject('Your '.$companyName.' Partner invitation')
        );
    }
}