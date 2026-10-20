<?php

namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use App\Support\TenantOps;
use App\Support\WorkspaceRoles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class CustomRoleController extends Controller
{
    private function context(Request $request): array
    {
        [$company, $workspace, $actor] = TenantOps::context($request);
        // Granting custom rights and changing the matrix is reserved for the
        // original Partner. A delegated company_admin cannot self-escalate.
        TenantOps::requireRole($actor, ['partner']);

        return [$company, $workspace, $actor];
    }

    private function permissions(Request $request): array
    {
        $input = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'slug' => ['required', 'regex:/^[a-z][a-z0-9-]{1,63}$/', 'max:64'],
            'description' => ['nullable', 'string', 'max:500'],
            'permissions' => ['nullable', 'array', 'max:10'],
            'permissions.*' => ['string', Rule::in(array_keys(WorkspaceRoles::PERMISSIONS))],
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ]);
        $input['permissions'] = array_values(array_unique($input['permissions'] ?? []));
        return $input;
    }

    private function scopedRole(object $company, object $workspace, int $id, bool $lock = false): object
    {
        $q = DB::table('workspace_custom_roles')
            ->where('id', $id)
            ->where('company_id', $company->id)
            ->where('workspace_id', $workspace->id);
        $role = ($lock ? $q->lockForUpdate() : $q)->first();
        abort_unless($role, 404);

        return $role;
    }

    public function index(Request $request, string $firmSlug)
    {
        [$company, $workspace, $actor] = $this->context($request);
        $roles = DB::table('workspace_custom_roles')
            ->where('company_id', $company->id)->where('workspace_id', $workspace->id)
            ->orderBy('name')->limit(100)->get();
        $members = DB::table('memberships as m')
            ->join('users as u', 'u.id', '=', 'm.user_id')
            ->where('m.company_id', $company->id)->where('m.workspace_id', $workspace->id)
            ->where('m.role', 'manager')->where('m.active', true)
            ->whereNull('m.platform_suspended_at')
            ->select('m.id', 'u.name', 'u.email')->orderBy('u.name')->limit(300)->get();
        $assignments = DB::table('workspace_role_assignments as a')
            ->join('workspace_custom_roles as r', function ($join) {
                $join->on('r.id', '=', 'a.role_id')
                    ->on('r.company_id', '=', 'a.company_id')
                    ->on('r.workspace_id', '=', 'a.workspace_id');
            })
            ->where('a.company_id', $company->id)
            ->where('a.workspace_id', $workspace->id)
            ->whereIn('a.membership_id', $members->pluck('id')->all())
            ->select('a.membership_id', 'r.id as role_id', 'r.name as role_name')
            ->get()->keyBy('membership_id');
        $permissions = WorkspaceRoles::PERMISSIONS;

        return response()->view('erp.custom-roles', compact(
            'company', 'workspace', 'actor', 'firmSlug', 'roles',
            'members', 'assignments', 'permissions'
        ))->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request, string $firmSlug)
    {
        [$company, $workspace, $actor] = $this->context($request);
        $data = $this->permissions($request);
        DB::transaction(function () use ($company, $workspace, $actor, $data) {
            DB::table('workspaces')->where('id', $workspace->id)
                ->where('company_id', $company->id)->lockForUpdate()->first();

            if (DB::table('workspace_custom_roles')
                ->where('company_id', $company->id)->where('workspace_id', $workspace->id)
                ->count() >= 100) {
                throw ValidationException::withMessages([
                    'slug' => 'Workspace role limit reached; review inactive roles first.',
                ]);
            }
            if (DB::table('workspace_custom_roles')
                ->where('workspace_id', $workspace->id)
                ->where('slug', $data['slug'])->exists()) {
                throw ValidationException::withMessages([
                    'slug' => 'Role slug already exists in this workspace.',
                ]);
            }
            $id = DB::table('workspace_custom_roles')->insertGetId([
                'company_id' => $company->id, 'workspace_id' => $workspace->id,
                'slug' => $data['slug'], 'name' => trim($data['name']),
                'description' => trim($data['description'] ?? ''),
                'permissions' => json_encode($data['permissions'], JSON_THROW_ON_ERROR),
                'active' => true, 'created_by' => $actor->id, 'updated_by' => $actor->id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            TenantOps::audit($actor, 'custom_role.created', 'workspace_custom_role', $id, [
                'slug' => $data['slug'], 'permissions' => $data['permissions'],
                'reason' => trim($data['reason']),
            ]);
        }, 3);

        return back()->with('success', 'Custom role created for this workspace.');
    }

    public function update(Request $request, string $firmSlug, int $roleId)
    {
        [$company, $workspace, $actor] = $this->context($request);
        $data = $this->permissions($request);
        DB::transaction(function () use ($company, $workspace, $actor, $roleId, $data) {
            DB::table('workspaces')->where('id', $workspace->id)
                ->where('company_id', $company->id)->lockForUpdate()->first();
            $role = $this->scopedRole($company, $workspace, $roleId, true);
            if ($role->slug !== $data['slug']) {
                throw ValidationException::withMessages([
                    'slug' => 'Role slug is stable after creation; use a new role for a different identity.',
                ]);
            }
            DB::table('workspace_custom_roles')->where('id', $roleId)->update([
                'name' => trim($data['name']),
                'description' => trim($data['description'] ?? ''),
                'permissions' => json_encode($data['permissions'], JSON_THROW_ON_ERROR),
                'updated_by' => $actor->id, 'updated_at' => now(),
            ]);
            TenantOps::audit($actor, 'custom_role.updated', 'workspace_custom_role', $roleId, [
                'old_permissions' => json_decode($role->permissions, true) ?: [],
                'new_permissions' => $data['permissions'],
                'reason' => trim($data['reason']),
            ]);
        }, 3);

        return back()->with('success', 'Role changes now apply to assigned managers.');
    }

    public function status(Request $request, string $firmSlug, int $roleId)
    {
        [$company, $workspace, $actor] = $this->context($request);
        $input = $request->validate([
            'active' => ['required', 'boolean'],
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ]);
        DB::transaction(function () use ($company, $workspace, $actor, $roleId, $input) {
            DB::table('workspaces')->where('id', $workspace->id)
                ->where('company_id', $company->id)->lockForUpdate()->first();
            $role = $this->scopedRole($company, $workspace, $roleId, true);
            DB::table('workspace_custom_roles')->where('id', $roleId)->update([
                'active' => (bool) $input['active'], 'updated_by' => $actor->id, 'updated_at' => now(),
            ]);
            TenantOps::audit($actor, 'custom_role.status', 'workspace_custom_role', $roleId, [
                'previous' => (bool) $role->active,
                'active' => (bool) $input['active'], 'reason' => trim($input['reason']),
            ]);
        }, 3);

        return back()->with('success', 'Role availability changed. Inactive roles grant no access.');
    }

    public function assign(Request $request, string $firmSlug)
    {
        [$company, $workspace, $actor] = $this->context($request);
        $input = $request->validate([
            'membership_id' => ['required', 'integer', 'min:1'],
            'role_id' => ['nullable', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ]);
        DB::transaction(function () use ($company, $workspace, $actor, $input) {
            DB::table('workspaces')->where('id', $workspace->id)
                ->where('company_id', $company->id)->lockForUpdate()->first();
            $member = DB::table('memberships')
                ->where('id', $input['membership_id'])->where('company_id', $company->id)
                ->where('workspace_id', $workspace->id)
                ->where('role', 'manager')->where('active', true)
                ->whereNull('platform_suspended_at')->first();
            abort_unless($member, 404);

            $newId = $input['role_id'] ?? null;
            if ($newId !== null) {
                $role = $this->scopedRole($company, $workspace, (int) $newId);
                abort_unless($role->active, 422, 'Cannot assign an inactive role.');
            }
            $old = DB::table('workspace_role_assignments')
                ->where('company_id', $company->id)->where('workspace_id', $workspace->id)
                ->where('membership_id', $member->id)->first();
            if ($newId === null) {
                DB::table('workspace_role_assignments')->where('company_id', $company->id)
                    ->where('workspace_id', $workspace->id)
                    ->where('membership_id', $member->id)->delete();
            } elseif ($old) {
                DB::table('workspace_role_assignments')->where('id', $old->id)->update([
                    'role_id' => $newId, 'assigned_by' => $actor->id, 'updated_at' => now(),
                ]);
            } else {
                DB::table('workspace_role_assignments')->insert([
                    'company_id' => $company->id, 'workspace_id' => $workspace->id,
                    'membership_id' => $member->id, 'role_id' => $newId,
                    'assigned_by' => $actor->id,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            TenantOps::audit($actor, 'custom_role.assignment.changed',
                'membership', $member->id, [
                    'previous_role_id' => $old?->role_id, 'new_role_id' => $newId,
                    'reason' => trim($input['reason']),
                ]);
        }, 3);

        return back()->with('success', 'Manager role assignment updated without changing base membership.');
    }
}
