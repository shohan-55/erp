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
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PlatformManagementController extends Controller
{
    public function showCompany(int $companyId)
    {
        $company = DB::table('companies')->find($companyId);
        abort_unless($company, 404);

        $workspaces = DB::table('workspaces')
            ->where('company_id', $companyId)->orderBy('name')->get();

        // Platform account metadata ONLY. No joins to attendance, payroll,
        // claims, leave, notifications, personal documents or tenant audit data.
        $members = DB::table('memberships as m')
            ->join('users as u', 'u.id', '=', 'm.user_id')
            ->join('workspaces as w', 'w.id', '=', 'm.workspace_id')
            ->where('m.company_id', $companyId)
            ->where('w.company_id', $companyId)
            ->select(
                'm.id', 'm.workspace_id', 'm.role', 'm.active',
                'm.invited_at', 'm.platform_suspended_at',
                'm.manager_membership_id', 'u.name', 'u.email',
                'u.is_platform_admin', 'w.name as workspace_name'
            )
            ->orderBy('w.name')->orderBy('u.name')->get();

        $events = DB::table('platform_control_events')
            ->where('company_id', $companyId)
            ->orderByDesc('id')->limit(12)
            ->get(['action', 'subject_type', 'subject_id', 'created_at']);

        return view('erp.platform-company', compact('company', 'workspaces', 'members', 'events'));
    }

    public function updateCompany(Request $request, int $companyId)
    {
        $data = $request->validate([
            'company_name' => ['required', 'string', 'min:3', 'max:255'],
        ]);

        $name = trim($data['company_name']);
        if (mb_strlen($name) < 3) {
            throw ValidationException::withMessages(['company_name' => 'Company name is too short.']);
        }

        DB::transaction(function () use ($companyId, $name) {
            $company = DB::table('companies')->where('id', $companyId)->lockForUpdate()->first();
            abort_unless($company, 404);
            $this->rejectArchived($company);

            if ($company->name !== $name) {
                DB::table('companies')->where('id', $companyId)->update([
                    'name' => $name, 'updated_at' => now(),
                ]);
                $this->audit($companyId, 'company.name.updated', 'company', $companyId, ['field' => 'name']);
            }
        }, 3);

        return back()->with('success', 'Company details updated. The login slug stays unchanged.');
    }

    public function transitionCompany(Request $request, int $companyId)
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['suspend', 'reactivate', 'archive', 'restore'])],
            'confirmation_slug' => ['required', 'string', 'max:70'],
        ]);

        DB::transaction(function () use ($data, $companyId) {
            $company = DB::table('companies')->where('id', $companyId)->lockForUpdate()->first();
            abort_unless($company, 404);

            if (! hash_equals($company->slug, $data['confirmation_slug'])) {
                throw ValidationException::withMessages([
                    'confirmation_slug' => 'Type the exact company slug to confirm this change.',
                ]);
            }

            $changes = match ($data['action']) {
                'suspend' => $company->active && $company->archived_at === null
                    ? ['active' => false] : null,
                'reactivate' => ! $company->active && $company->archived_at === null
                    ? ['active' => true] : null,
                'archive' => ! $company->active && $company->archived_at === null
                    ? ['active' => false, 'archived_at' => now()] : null,
                'restore' => ! $company->active && $company->archived_at !== null
                    ? ['active' => false, 'archived_at' => null] : null,
            };

            if ($changes === null) {
                throw ValidationException::withMessages([
                    'action' => 'Invalid state transition. Suspend before archiving; restore before reactivating.',
                ]);
            }

            DB::table('companies')->where('id', $companyId)->update([
                ...$changes, 'updated_at' => now(),
            ]);
            $this->audit($companyId, 'company.'.$data['action'], 'company', $companyId);
        }, 3);

        return back()->with('success', 'Company '.($data['action']).' operation completed.');
    }

    public function inviteMember(Request $request, int $companyId)
    {
        $data = $request->validate([
            'workspace_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'email' => ['required', 'email', 'max:254'],
            'role' => ['required', Rule::in(['manager', 'student'])],
            'manager_membership_id' => ['nullable', 'integer'],
        ]);

        OutboundMailGuard::ensureConfigured();
        $email = strtolower(trim($data['email']));

        // An existing account may belong to other companies and have MFA.
        // Reusing it through a new-password invitation would reset its shared
        // credentials; refuse and require a separately reviewed linking flow.
        if (User::where('email', $email)->exists()) {
            throw ValidationException::withMessages([
                'email' => 'This email already has an ERP account. Its shared credentials cannot be reset by invitation.',
            ]);
        }

        [$company, $user] = DB::transaction(function () use ($companyId, $data, $email) {
            $company = DB::table('companies')->where('id', $companyId)->lockForUpdate()->first();
            abort_unless($company && $company->active && $company->archived_at === null, 404);

            $workspace = DB::table('workspaces')->where('id', $data['workspace_id'])
                ->where('company_id', $companyId)->where('active', true)->first();
            abort_unless($workspace, 404);

            $manager = $this->validManager(
                $companyId, (int) $workspace->id, $data['manager_membership_id'] ?? null
            );

            if (User::where('email', $email)->exists()) {
                throw ValidationException::withMessages(['email' => 'Account already exists.']);
            }

            $user = User::create([
                'name' => trim($data['name']), 'email' => $email,
                'password' => Hash::make(Str::random(64)),
            ]);

            $id = DB::table('memberships')->insertGetId([
                'company_id' => $companyId, 'workspace_id' => $workspace->id,
                'user_id' => $user->id, 'role' => $data['role'],
                'manager_membership_id' => $data['role'] === 'student' ? $manager : null,
                'active' => false, 'monthly_allowance' => '0.00',
                'invited_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->audit($companyId, 'account.invited', 'membership', $id, [
                'role' => $data['role'], 'workspace_id' => (int) $workspace->id,
            ]);

            return [$company, $user];
        }, 3);

        $token = Password::broker()->createToken($user);
        $url = route('erp.activate.form', [
            'firmSlug' => $company->slug, 'token' => $token, 'email' => $user->email,
        ]);
        Mail::raw(
            "You have been invited to ".$company->name." on DhakaWala ERP.\n\n".
            "Use this one-time link to activate your account and set a password:\n".
            $url."\n\nIgnore this message if you did not request access.",
            fn ($message) => $message->to($user->email)->subject($company->name.' ERP account invitation')
        );

        return back()->with('success', 'Account invitation saved; email delivery requested.');
    }

    public function updateMember(Request $request, int $companyId, int $memberId)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'role' => ['required', Rule::in(['partner', 'manager', 'student'])],
            'manager_membership_id' => ['nullable', 'integer'],
        ]);
        $newName = trim($data['name']);
        if (mb_strlen($newName) < 3) {
            throw ValidationException::withMessages(['name' => 'Account name is too short.']);
        }

        DB::transaction(function () use ($data, $newName, $companyId, $memberId) {
            $company = DB::table('companies')->where('id', $companyId)->lockForUpdate()->first();
            abort_unless($company && $company->active && $company->archived_at === null, 404);

            $member = DB::table('memberships')->where('id', $memberId)
                ->where('company_id', $companyId)->lockForUpdate()->first();
            abort_unless($member, 404);

            $user = User::whereKey($member->user_id)->lockForUpdate()->firstOrFail();
            if ($user->is_platform_admin && $newName !== $user->name) {
                throw ValidationException::withMessages(['name' => 'Platform administrator identity cannot be edited here.']);
            }

            if ($user->name !== $newName) {
                // User.name is global, not a tenant-scoped field.
                if (DB::table('memberships')->where('user_id', $user->id)
                    ->where('company_id', '!=', $companyId)->exists()) {
                    throw ValidationException::withMessages([
                        'name' => 'This account belongs to multiple companies. Edit shared identity separately.',
                    ]);
                }
                $user->name = $newName;
                $user->save();
                $this->audit($companyId, 'account.name.updated', 'membership', $memberId, ['field' => 'name']);
            }

            if ($member->role !== $data['role']) {
                if ($member->role === 'partner' || $data['role'] === 'partner') {
                    throw ValidationException::withMessages([
                        'role' => 'Partner ownership cannot be changed from account editing.',
                    ]);
                }
                if ($member->role === 'manager' && DB::table('memberships')
                    ->where('company_id', $companyId)
                    ->where('workspace_id', $member->workspace_id)
                    ->where('manager_membership_id', $memberId)->exists()) {
                    throw ValidationException::withMessages([
                        'role' => 'Reassign this Manager’s students before changing their role.',
                    ]);
                }
            }

            $manager = $data['role'] === 'student'
                ? $this->validManager($companyId, (int) $member->workspace_id, $data['manager_membership_id'] ?? null)
                : null;

            $oldManager = $member->manager_membership_id !== null ? (int) $member->manager_membership_id : null;
            if ($member->role !== $data['role'] || $oldManager !== $manager) {
                DB::table('memberships')->where('id', $memberId)->update([
                    'role' => $data['role'], 'manager_membership_id' => $manager, 'updated_at' => now(),
                ]);
                $this->audit($companyId, 'account.membership.updated', 'membership', $memberId, [
                    'old_role' => $member->role, 'role' => $data['role'],
                    'manager_changed' => $oldManager !== $manager,
                ]);
            }
        }, 3);

        return back()->with('success', 'Account details updated. Email and password were not changed.');
    }

    public function transitionMember(Request $request, int $companyId, int $memberId)
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['suspend', 'reactivate'])],
            'confirmation_email' => ['required', 'email', 'max:254'],
        ]);

        DB::transaction(function () use ($companyId, $memberId, $data) {
            $company = DB::table('companies')->where('id', $companyId)->lockForUpdate()->first();
            abort_unless($company && $company->active && $company->archived_at === null, 404);

            $member = DB::table('memberships')->where('id', $memberId)
                ->where('company_id', $companyId)->lockForUpdate()->first();
            abort_unless($member, 404);

            $user = User::whereKey($member->user_id)->firstOrFail();
            if (! hash_equals($user->email, strtolower(trim($data['confirmation_email'])))) {
                throw ValidationException::withMessages([
                    'confirmation_email' => 'Enter the exact account email to confirm the change.',
                ]);
            }

            if ($data['action'] === 'suspend') {
                if (! $member->active || $member->platform_suspended_at !== null) {
                    throw ValidationException::withMessages([
                        'action' => 'Only an active account can be suspended.',
                    ]);
                }
                if ($member->role === 'partner' && ! DB::table('memberships')
                    ->where('company_id', $companyId)
                    ->where('workspace_id', $member->workspace_id)
                    ->where('id', '!=', $memberId)
                    ->where('role', 'partner')->where('active', true)->exists()) {
                    throw ValidationException::withMessages([
                        'action' => 'Cannot suspend the last active Partner. Suspend the company instead.',
                    ]);
                }
                $updates = ['active' => false, 'platform_suspended_at' => now()];
            } else {
                if ($member->active || $member->platform_suspended_at === null) {
                    throw ValidationException::withMessages([
                        'action' => 'Unaccepted invitations cannot be activated by the platform operator.',
                    ]);
                }
                $workspaceExists = DB::table('workspaces')
                    ->where('company_id', $companyId)->where('id', $member->workspace_id)
                    ->where('active', true)->exists();
                abort_unless($workspaceExists, 404);
                if ($member->role === 'partner' && DB::table('memberships')
                    ->where('company_id', $companyId)
                    ->where('workspace_id', $member->workspace_id)
                    ->where('id', '!=', $memberId)
                    ->where('role', 'partner')->where('active', true)->exists()) {
                    throw ValidationException::withMessages([
                        'action' => 'This workspace already has an active Partner.',
                    ]);
                }
                $updates = ['active' => true, 'platform_suspended_at' => null];
            }

            DB::table('memberships')->where('id', $memberId)->update([...$updates, 'updated_at' => now()]);
            $this->audit($companyId, 'account.'.$data['action'], 'membership', $memberId, [
                'workspace_id' => (int) $member->workspace_id,
            ]);
        }, 3);

        return back()->with('success', 'Account '.($data['action']).' operation completed.');
    }

    private function validManager(int $companyId, int $workspaceId, mixed $managerId): ?int
    {
        if ($managerId === null || $managerId === '') {
            return null;
        }

        $found = DB::table('memberships')
            ->where('id', (int) $managerId)
            ->where('company_id', $companyId)
            ->where('workspace_id', $workspaceId)
            ->where('role', 'manager')->where('active', true)->exists();

        if (! $found) {
            throw ValidationException::withMessages([
                'manager_membership_id' => 'Choose an active Manager in the same Partner workspace.',
            ]);
        }
        return (int) $managerId;
    }

    private function rejectArchived(object $company): void
    {
        if ($company->archived_at !== null) {
            throw ValidationException::withMessages([
                'company_name' => 'Restore the archived company before editing details.',
            ]);
        }
    }

    private function audit(int $companyId, string $action, string $type, int $id, array $details = []): void
    {
        DB::table('platform_control_events')->insert([
            'company_id' => $companyId,
            'actor_user_id' => Auth::id(),
            'action' => $action,
            'subject_type' => $type,
            'subject_id' => $id,
            'details' => json_encode($details, JSON_THROW_ON_ERROR),
            'created_at' => now(),
        ]);
    }
}
