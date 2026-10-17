<?php

namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\TenantOps;
use App\Support\OutboundMailGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TeamController extends Controller
{
    public function index(Request $request, string $firmSlug)
    {
        [$company, $workspace, $actor] = TenantOps::context($request);
        TenantOps::requireRole($actor, ['partner', 'manager']);

        $members = DB::table('memberships as m')
            ->join('users as u', 'u.id', '=', 'm.user_id')
            ->where('m.company_id', $company->id)
            ->where('m.workspace_id', $workspace->id)
            ->when($actor->role === 'manager', fn ($query) => $query->where('m.role', 'student')
                ->where('m.manager_membership_id', $actor->id))
            ->select('m.*', 'u.name as member_name', 'u.email')
            ->orderByRaw("CASE m.role WHEN 'partner' THEN 0 WHEN 'manager' THEN 1 ELSE 2 END")
            ->orderBy('u.name')->get();

        $managers = $members->filter(fn ($m) => $m->role === 'manager' && $m->active);

        return view('erp.team', compact('company', 'workspace', 'actor', 'firmSlug', 'members', 'managers'));
    }

    public function invite(Request $request, string $firmSlug)
    {
        [$company, $workspace, $actor] = TenantOps::context($request);
        TenantOps::requireRole($actor, ['partner']);

        $input = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'email' => ['required', 'email', 'max:254'],
            'role' => ['required', 'in:manager,student'],
            'manager_membership_id' => ['nullable', 'integer'],
            'monthly_allowance' => ['nullable', 'required_if:role,student', 'numeric', 'min:0', 'max:500000', 'decimal:0,2'],
        ]);

        OutboundMailGuard::ensureConfigured();
        $managerId = $this->validateManager($actor, $input['manager_membership_id'] ?? null);

        $email = strtolower(trim($input['email']));
        $existingUser = User::where('email', $email)->first();
        if ($existingUser && DB::table('memberships')
            ->where('company_id', $company->id)
            ->where('user_id', $existingUser->id)->exists()) {
            throw ValidationException::withMessages(['email' => 'This account already has a membership in the firm.']);
        }

        [$user, $membershipId] = DB::transaction(function () use ($actor, $company, $workspace, $input, $email, $existingUser, $managerId) {
            $user = $existingUser ?: User::create([
                'name' => $input['name'], 'email' => $email,
                'password' => Hash::make(Str::random(64)),
            ]);

            $id = DB::table('memberships')->insertGetId([
                'company_id' => $company->id, 'workspace_id' => $workspace->id,
                'user_id' => $user->id,
                'role' => $input['role'],
                'manager_membership_id' => $input['role'] === 'student' ? $managerId : null,
                'monthly_allowance' => $input['role'] === 'student' ? ($input['monthly_allowance'] ?? '0') : '0.00',
                'active' => false,
                'invited_at' => now(),
                'created_at' => now(), 'updated_at' => now(),
            ]);

            TenantOps::audit($actor, 'team.invite', 'membership', $id, ['role' => $input['role']]);
            return [$user, $id];
        }, 3);

        $this->sendActivation($user, $company->name, $firmSlug);
        return back()->with('success', 'Invitation created. Email delivery depends on your configured SMTP provider.');
    }

    public function resend(Request $request, string $firmSlug, int $id)
    {
        [$company, , $actor] = TenantOps::context($request);
        TenantOps::requireRole($actor, ['partner']);
        $member = DB::table('memberships')->where('id', $id)
            ->where('company_id', $actor->company_id)
            ->where('workspace_id', $actor->workspace_id)->where('active', false)
            ->whereNull('platform_suspended_at')->first();
        abort_unless($member, 404);

        OutboundMailGuard::ensureConfigured();
        $user = User::findOrFail($member->user_id);
        $this->sendActivation($user, $company->name, $firmSlug);
        TenantOps::audit($actor, 'team.resend_invitation', 'membership', $id);
        return back()->with('success', 'Invitation email re-requested.');
    }

    public function updateStudent(Request $request, string $firmSlug, int $id)
    {
        [, , $actor] = TenantOps::context($request);
        TenantOps::requireRole($actor, ['partner']);

        $data = $request->validate([
            'manager_membership_id' => ['nullable', 'integer'],
            'monthly_allowance' => ['required', 'numeric', 'min:0', 'max:500000', 'decimal:0,2'],
        ]);
        $managerId = $this->validateManager($actor, $data['manager_membership_id'] ?? null);

        DB::transaction(function () use ($actor, $id, $data, $managerId) {
            $member = DB::table('memberships')->where('id', $id)
                ->where('company_id', $actor->company_id)
                ->where('workspace_id', $actor->workspace_id)
                ->where('role', 'student')->lockForUpdate()->first();
            abort_unless($member, 404);

            DB::table('memberships')->where('id', $id)->update([
                'manager_membership_id' => $managerId,
                'monthly_allowance' => $data['monthly_allowance'],
                'updated_at' => now(),
            ]);
            TenantOps::audit($actor, 'team.update_student', 'membership', $id, [
                'previous_manager' => $member->manager_membership_id,
                'new_manager' => $managerId,
                'prior_allowance' => $member->monthly_allowance,
                'new_allowance' => $data['monthly_allowance'],
            ]);
        }, 3);

        return back()->with('success', 'Student settings saved. Past payroll snapshots are unchanged.');
    }

    private function validateManager(object $actor, mixed $managerId): ?int
    {
        if ($managerId === null || $managerId === '') {
            return null;
        }

        $valid = DB::table('memberships')->where('id', (int) $managerId)
            ->where('company_id', $actor->company_id)
            ->where('workspace_id', $actor->workspace_id)
            ->where('role', 'manager')->where('active', true)->exists();

        if (! $valid) {
            throw ValidationException::withMessages(['manager_membership_id' => 'Manager must be active in your own Partner workspace.']);
        }
        return (int) $managerId;
    }

    private function sendActivation(User $user, string $companyName, string $firmSlug): void
    {
        $token = Password::broker()->createToken($user);
        $url = route('erp.activate.form', [
            'firmSlug' => $firmSlug, 'token' => $token, 'email' => $user->email,
        ]);

        Mail::raw(
            "You have been invited to join ".$companyName." ERP.\n\n".
            "Set your password and accept the membership using this one-time link:\n".
            $url."\n\nIf you were not expecting this invitation, do not open the link.",
            fn ($message) => $message->to($user->email)->subject('Your '.$companyName.' ERP invitation')
        );
    }
}