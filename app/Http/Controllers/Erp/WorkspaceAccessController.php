<?php

namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use App\Support\TenantOps;
use App\Support\WorkspaceAccess;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class WorkspaceAccessController extends Controller
{
    public function index(Request $request, string $firmSlug)
    {
        [$company, $workspace, $actor] = TenantOps::context($request);
        abort_unless(WorkspaceAccess::canManage($actor), 403);
        $members = DB::table('memberships as m')
            ->join('users as u', 'u.id', '=', 'm.user_id')
            ->where('m.company_id', $company->id)->where('m.workspace_id', $workspace->id)
            ->where('m.role', 'manager')->where('m.active', true)
            ->whereNull('m.platform_suspended_at')
            ->select('m.id', 'u.name', 'u.email')->orderBy('u.name')->get();
        $grants = DB::table('workspace_access_grants as g')
            ->join('memberships as m', 'm.id', '=', 'g.membership_id')
            ->join('users as u', 'u.id', '=', 'm.user_id')
            ->where('g.company_id', $company->id)->where('g.workspace_id', $workspace->id)
            ->where('m.company_id', $company->id)->where('m.workspace_id', $workspace->id)
            ->where('m.role', 'manager')->whereNull('g.revoked_at')
            ->where('m.active', true)->whereNull('m.platform_suspended_at')
            ->select('g.*', 'u.name', 'u.email')->orderBy('u.name')->get();
        $permissions = WorkspaceAccess::PERMISSIONS;
        return response()->view('erp.workspace-access', compact(
            'company', 'workspace', 'actor', 'firmSlug', 'members', 'grants', 'permissions'
        ))->header('Cache-Control', 'private, no-store');
    }

    private function parseTime(?string $value): ?Carbon
    {
        return $value ? Carbon::createFromFormat('Y-m-d\\TH:i', $value, config('app.timezone'))
            ->utc() : null;
    }

    public function save(Request $request, string $firmSlug)
    {
        [$company, $workspace, $actor] = TenantOps::context($request);
        abort_unless(WorkspaceAccess::canManage($actor), 403);
        $input = $request->validate([
            'membership_id' => ['required', 'integer', 'min:1'],
            'access_role' => ['required', Rule::in(['company_admin', 'moderator'])],
            'delegation_mode' => ['nullable', Rule::in(['full', 'limited'])],
            'permissions' => ['sometimes', 'array', 'max:12'],
            'permissions.*' => ['string', Rule::in(array_keys(WorkspaceAccess::PERMISSIONS))],
            'starts_at' => ['nullable', 'date_format:Y-m-d\\TH:i'],
            'expires_at' => ['nullable', 'date_format:Y-m-d\\TH:i'],
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ]);

        $permissions = array_values(array_unique($input['permissions'] ?? []));
        $mode = $input['delegation_mode'] ?? 'limited';
        if ($input['access_role'] !== 'company_admin'
            && in_array('access.delegate', $permissions, true)) {
            throw ValidationException::withMessages([
                'permissions' => 'Only a Company Admin can receive the explicitly approved delegation-management permission.',
            ]);
        }

        $start = $this->parseTime($input['starts_at'] ?? null);
        $end = $this->parseTime($input['expires_at'] ?? null);
        if ($end && $end->lte($start ?? now()->utc())) {
            throw ValidationException::withMessages(['expires_at' => 'Expiry must be after the start time and in the future.']);
        }

        DB::transaction(function () use (
            $company, $workspace, $actor, $input, $permissions, $mode, $start, $end
        ) {
            DB::table('workspaces')->where('company_id', $company->id)
                ->where('id', $workspace->id)->lockForUpdate()->first();
            $current = DB::table('memberships')->where('id', $actor->id)
                ->where('company_id', $company->id)->where('workspace_id', $workspace->id)
                ->where('active', true)->whereNull('platform_suspended_at')->first();
            abort_unless($current && WorkspaceAccess::canManage($current), 403);

            $target = DB::table('memberships')->where('id', $input['membership_id'])
                ->where('company_id', $company->id)->where('workspace_id', $workspace->id)
                ->where('role', 'manager')->where('active', true)
                ->whereNull('platform_suspended_at')->first();
            abort_unless($target && (int) $target->id !== (int) $actor->id, 404);

            $previous = DB::table('workspace_access_grants')
                ->where('company_id', $company->id)->where('workspace_id', $workspace->id)
                ->where('membership_id', $target->id)->first();
            $parentGrantId = null;
            if ($current->role !== 'partner') {
                // A delegate can only grant restricted, temporary Moderator
                // access, and never update grants belonging to a Partner or
                // another Company Admin. Privileges are never transitive.
                $grant = WorkspaceAccess::grant($current);
                abort_unless($grant && $grant->access_role === 'company_admin'
                    && $grant->parent_grant_id === null, 403);
                if ($input['access_role'] !== 'moderator' || $mode !== 'limited') {
                    throw ValidationException::withMessages([
                        'access_role' => 'Delegates may grant limited Moderator access only.',
                    ]);
                }
                if ($previous && (int) $previous->parent_grant_id !== (int) $grant->id) {
                    abort(403, 'You may only modify delegations you originally issued.');
                }
                if ($end === null || $end->gt(now()->utc()->addDays(30))
                    || ($grant->expires_at && $end->gt(Carbon::parse($grant->expires_at, 'UTC')))
                    || ($start && $grant->starts_at && $start->lt(Carbon::parse($grant->starts_at, 'UTC')))) {
                    throw ValidationException::withMessages([
                        'expires_at' => 'A delegated Moderator must expire within 30 days and within its grantor\'s valid period.',
                    ]);
                }
                foreach ($permissions as $permission) {
                    if ($permission === 'access.delegate' || !WorkspaceAccess::allows($current, $permission)) {
                        throw ValidationException::withMessages([
                            'permissions' => 'You may delegate only operations explicitly granted to your account.',
                        ]);
                    }
                }
                $parentGrantId = $grant->id;
            }

            $values = [
                'access_role' => $input['access_role'],
                'delegation_mode' => $mode,
                'permissions' => json_encode($permissions, JSON_THROW_ON_ERROR),
                'starts_at' => $start?->toDateTimeString(),
                'expires_at' => $end?->toDateTimeString(),
                'parent_grant_id' => $parentGrantId,
                'grant_reason' => trim($input['reason']),
                'granted_by' => $actor->id, 'revoked_by' => null,
                'revoked_at' => null, 'updated_at' => now(),
            ];

            if ($previous) {
                DB::table('workspace_access_grants')->where('id', $previous->id)->update($values);
                $id = $previous->id;
            } else {
                $id = DB::table('workspace_access_grants')->insertGetId([
                    'company_id' => $company->id, 'workspace_id' => $workspace->id,
                    'membership_id' => $target->id, ...$values, 'created_at' => now(),
                ]);
            }
            TenantOps::audit($actor, 'workspace_access.granted', 'workspace_access_grant', $id, [
                'target_membership_id' => $target->id, 'role' => $input['access_role'],
                'mode' => $mode, 'permissions' => $permissions,
                'starts_at' => $values['starts_at'], 'expires_at' => $values['expires_at'],
                'parent_grant_id' => $parentGrantId, 'previous_role' => $previous?->access_role,
                'reason' => trim($input['reason']),
            ]);
            TenantOps::notify($actor, $target->id, 'Workspace delegation updated',
                'Your delegated '.$input['access_role'].' access and expiry have been updated.');
        }, 3);
        return back()->with('success', 'Workspace access saved with an audited expiry and authority chain.');
    }

    public function revoke(Request $request, string $firmSlug, int $id)
    {
        [$company, $workspace, $actor] = TenantOps::context($request);
        abort_unless(WorkspaceAccess::canManage($actor), 403);
        $input = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ]);
        DB::transaction(function () use ($company, $workspace, $actor, $id, $input) {
            DB::table('workspaces')->where('company_id', $company->id)
                ->where('id', $workspace->id)->lockForUpdate()->first();
            $current = DB::table('memberships')->where('id', $actor->id)
                ->where('company_id', $company->id)->where('workspace_id', $workspace->id)
                ->where('active', true)->whereNull('platform_suspended_at')->first();
            abort_unless($current && WorkspaceAccess::canManage($current), 403);

            $grant = DB::table('workspace_access_grants')
                ->where('company_id', $company->id)->where('workspace_id', $workspace->id)
                ->where('id', $id)->whereNull('revoked_at')->lockForUpdate()->first();
            abort_unless($grant && (int) $grant->membership_id !== (int) $actor->id, 404);
            if ($current->role !== 'partner') {
                $parent = WorkspaceAccess::grant($current);
                abort_unless($parent && (int) $grant->parent_grant_id === (int) $parent->id, 403);
            }
            DB::table('workspace_access_grants')->where('id', $id)->update([
                'revoked_at' => now(), 'revoked_by' => $actor->id, 'updated_at' => now(),
            ]);
            // Descendants are immediately invalidated by the runtime parent
            // check. Persist revocation and actor attribution for transparency.
            DB::table('workspace_access_grants')
                ->where('company_id', $company->id)->where('workspace_id', $workspace->id)
                ->where('parent_grant_id', $grant->id)->whereNull('revoked_at')
                ->update(['revoked_at' => now(), 'revoked_by' => $actor->id, 'updated_at' => now()]);
            TenantOps::audit($actor, 'workspace_access.revoked', 'workspace_access_grant', $id, [
                'target_membership_id' => $grant->membership_id, 'reason' => trim($input['reason']),
                'descendants_invalidated' => true,
            ]);
            TenantOps::notify($actor, $grant->membership_id, 'Workspace access revoked',
                'Your delegated workspace access has been revoked.');
        }, 3);
        return back()->with('success', 'Delegated access revoked with any dependent access.');
    }
}
