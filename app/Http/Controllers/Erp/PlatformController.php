<?php

namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
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
        if (! in_array($filter['status'], ['all', 'active', 'suspended', 'archived', 'deleted'], true)) {
            $filter['status'] = 'all';
        }

        $companies = DB::table('companies')
            ->when($filter['status'] === 'deleted', fn ($q) => $q->whereNotNull('deleted_at'))
            ->when($filter['status'] !== 'deleted', fn ($q) => $q->whereNull('deleted_at'))
            ->when($filter['q'] !== '', fn ($q) => $q->where(function ($sub) use ($filter) {
                $sub->where('name', 'like', '%'.$filter['q'].'%')
                    ->orWhere('slug', 'like', '%'.$filter['q'].'%');
            }))
            ->when($filter['status'] === 'active', fn ($q) => $q->where('active', true)->whereNull('archived_at'))
            ->when($filter['status'] === 'suspended', fn ($q) => $q->where('active', false)->whereNull('archived_at'))
            ->when($filter['status'] === 'archived', fn ($q) => $q->whereNotNull('archived_at'))
            ->orderByDesc('created_at')->get();
        $stats = [
            'companies' => DB::table('companies')->whereNull('deleted_at')->count(),
            'removed_companies' => DB::table('companies')->whereNotNull('deleted_at')->count(),
            'workspaces' => DB::table('workspaces as w')->join('companies as c','c.id','=','w.company_id')->whereNull('c.deleted_at')->count(),
            'active_memberships' => DB::table('memberships as m')->join('companies as c','c.id','=','m.company_id')->where('m.active', true)->whereNull('c.deleted_at')->count(),
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
            'partner_name' => ['required', 'string', 'min:3', 'max:255'],
            'partner_email' => ['required', 'email', 'max:254'],
        ]);
        OutboundMailGuard::ensureConfigured();
        $email = strtolower(trim($data['partner_email']));

        if (DB::table('companies')->where('slug', $data['company_slug'])->exists()) {
            throw ValidationException::withMessages(['company_slug' => 'Company slug already exists.']);
        }

        [$user, $companyName, $slug] = DB::transaction(function () use ($data, $email) {
            $user = $this->invitedUser($email, $data['partner_name']);
            $companyId = DB::table('companies')->insertGetId([
                'slug' => $data['company_slug'], 'name' => $data['company_name'],
                'timezone' => 'Asia/Dhaka', 'office_start' => '10:00:00',
                'office_end' => '18:00:00', 'grace_minutes' => 10,
                'weekly_holidays' => '[5,6]', 'active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $workspaceId = DB::table('workspaces')->insertGetId([
                'company_id' => $companyId, 'slug' => 'partner-01',
                'name' => 'Partner Unit 01', 'active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->insertPartnerInvitation($companyId, $workspaceId, $user->id);
            return [$user, $data['company_name'], $data['company_slug']];
        }, 3);

        $this->emailInvite($user, $companyName, $slug);
        return back()->with('success', 'Company created with an inactive Partner invitation. Verify email delivery.');
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

        $user = DB::transaction(function () use ($data, $email, $companyId) {
            $user = $this->invitedUser($email, $data['partner_name']);

            if (DB::table('memberships')->where('company_id', $companyId)
                ->where('user_id', $user->id)->exists()) {
                throw ValidationException::withMessages(['partner_email' => 'That person already belongs to this firm.']);
            }

            $workspaceId = DB::table('workspaces')->insertGetId([
                'company_id' => $companyId, 'slug' => $data['workspace_slug'],
                'name' => $data['workspace_name'], 'active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->insertPartnerInvitation($companyId, $workspaceId, $user->id);
            return $user;
        }, 3);

        $this->emailInvite($user, $company->name, $company->slug);
        return back()->with('success', 'Partner workspace created and invitation email requested.');
    }

    private function invitedUser(string $email, string $name): User
    {
        return User::where('email', $email)->first()
            ?? User::create(['name' => $name, 'email' => $email, 'password' => Hash::make(Str::random(64))]);
    }

    private function insertPartnerInvitation(int $companyId, int $workspaceId, int $userId): void
    {
        DB::table('memberships')->insert([
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