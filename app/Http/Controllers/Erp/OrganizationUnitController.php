<?php

namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use App\Support\OrganizationTemplate;
use App\Support\TenantOps;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Organization structure belongs to the selected Partner workspace.
 * An industry template supplies labels, never authorization or a cross-partner
 * relationship. Platform admins cannot access this tenant-private controller.
 */
final class OrganizationUnitController extends Controller
{
    private function authorize(Request $request): array
    {
        [$company, $workspace, $actor] = TenantOps::context($request);
        TenantOps::requireRole($actor, ['partner']);

        return [$company, $workspace, $actor];
    }

    public function index(Request $request, string $firmSlug)
    {
        [$company, $workspace, $actor] = $this->authorize($request);

        $units = DB::table('organization_units')
            ->where('company_id', $company->id)->where('workspace_id', $workspace->id)
            ->orderBy('id')->limit(500)->get();

        $members = DB::table('memberships as m')
            ->join('users as u', 'u.id', '=', 'm.user_id')
            ->where('m.company_id', $company->id)->where('m.workspace_id', $workspace->id)
            ->where('m.active', true)->whereNull('m.platform_suspended_at')
            ->select('m.id', 'm.role', 'u.name')->orderBy('u.name')->limit(500)->get();

        $assignments = DB::table('organization_unit_assignments as a')
            ->join('memberships as m', function ($join) {
                $join->on('m.id', '=', 'a.membership_id')
                    ->on('m.company_id', '=', 'a.company_id')
                    ->on('m.workspace_id', '=', 'a.workspace_id');
            })
            ->join('users as u', 'u.id', '=', 'm.user_id')
            ->where('a.company_id', $company->id)->where('a.workspace_id', $workspace->id)
            ->whereIn('a.organization_unit_id', $units->pluck('id')->all())
            ->select('a.organization_unit_id', 'a.membership_id', 'u.name')
            ->orderBy('u.name')->get()->groupBy('organization_unit_id');

        $kinds = OrganizationTemplate::unitKinds();
        $recommended = OrganizationTemplate::recommendedKinds($company->organization_type);

        return view('erp.organization-units', compact(
            'company', 'workspace', 'actor', 'firmSlug', 'units', 'members',
            'assignments', 'kinds', 'recommended'
        ));
    }

    public function store(Request $request, string $firmSlug)
    {
        [$company, $workspace, $actor] = $this->authorize($request);
        $data = $request->validate([
            'slug' => ['required', 'regex:/^[a-z0-9][a-z0-9-]{0,78}[a-z0-9]$/', 'max:80'],
            'name' => ['required', 'string', 'min:2', 'max:160'],
            'kind' => ['required', Rule::in(array_keys(OrganizationTemplate::unitKinds()))],
            'parent_id' => ['nullable', 'integer', 'min:1'],
        ]);

        DB::transaction(function () use ($actor, $company, $workspace, $data) {
            DB::table('workspaces')->where('id', $workspace->id)
                ->where('company_id', $company->id)->lockForUpdate()->first();

            if (DB::table('organization_units')->where('company_id', $company->id)
                ->where('workspace_id', $workspace->id)->count() >= 500) {
                throw ValidationException::withMessages([
                    'slug' => 'This workspace has reached its current 500-unit operational limit.',
                ]);
            }

            if (DB::table('organization_units')->where('company_id', $company->id)
                ->where('workspace_id', $workspace->id)->where('slug', $data['slug'])->exists()) {
                throw ValidationException::withMessages(['slug' => 'This organizational unit slug is already in use.']);
            }

            if (!empty($data['parent_id'])) {
                $parent = DB::table('organization_units')
                    ->where('company_id', $company->id)
                    ->where('workspace_id', $workspace->id)
                    ->where('id', $data['parent_id'])->where('active', true)->first();
                abort_unless($parent, 404);

                // Parent links are immutable; depth guard prevents pathological
                // trees and recursive traversal loops.
                $depth = 1;
                $seen = [];
                while ($parent) {
                    abort_if(isset($seen[$parent->id]), 409);
                    $seen[$parent->id] = true;
                    $depth++;
                    if ($depth > 5) {
                        throw ValidationException::withMessages([
                            'parent_id' => 'Maximum nesting is five organizational levels.',
                        ]);
                    }
                    $parent = $parent->parent_id
                        ? DB::table('organization_units')
                            ->where('company_id', $company->id)->where('workspace_id', $workspace->id)
                            ->where('id', $parent->parent_id)->where('active', true)->first()
                        : null;
                    // Fail closed for inconsistent parent ancestry.
                    if ($parent === null && $depth > 1) {
                        $node = DB::table('organization_units')->where('id', array_key_last($seen))->first();
                        if ($node && $node->parent_id) abort(409);
                    }
                }
            }

            $id = DB::table('organization_units')->insertGetId([
                'company_id' => $company->id, 'workspace_id' => $workspace->id,
                'parent_id' => $data['parent_id'] ?? null, 'slug' => $data['slug'],
                'name' => trim($data['name']), 'kind' => $data['kind'],
                'active' => true, 'created_by' => $actor->id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            TenantOps::audit($actor, 'organization.unit.created', 'organization_unit', $id, [
                'kind' => $data['kind'], 'parent_id' => $data['parent_id'] ?? null,
            ]);
        }, 3);

        return back()->with('success', 'Organizational unit created within your workspace.');
    }

    public function rename(Request $request, string $firmSlug, int $unitId)
    {
        [$company, $workspace, $actor] = $this->authorize($request);
        $data = $request->validate(['name' => ['required', 'string', 'min:2', 'max:160']]);
        DB::transaction(function () use ($company, $workspace, $actor, $unitId, $data) {
            $unit = $this->unit($company, $workspace, $unitId, true);
            abort_unless($unit->active, 422);
            DB::table('organization_units')->where('id', $unitId)->update([
                'name' => trim($data['name']), 'updated_at' => now(),
            ]);
            TenantOps::audit($actor, 'organization.unit.renamed', 'organization_unit', $unitId, [
                'previous_name' => $unit->name, 'new_name' => trim($data['name']),
            ]);
        }, 3);

        return back()->with('success', 'Unit name updated; previous records are preserved.');
    }

    public function toggle(Request $request, string $firmSlug, int $unitId)
    {
        [$company, $workspace, $actor] = $this->authorize($request);
        $data = $request->validate([
            'action' => ['required', Rule::in(['deactivate', 'reactivate'])],
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ]);
        DB::transaction(function () use ($company, $workspace, $actor, $unitId, $data) {
            DB::table('workspaces')->where('id', $workspace->id)
                ->where('company_id', $company->id)->lockForUpdate()->first();
            $unit = $this->unit($company, $workspace, $unitId, true);
            $activate = $data['action'] === 'reactivate';
            if ($activate) {
                abort_if($unit->active, 422);
                if ($unit->parent_id) {
                    $parent = $this->unit($company, $workspace, (int) $unit->parent_id);
                    abort_unless($parent->active, 422, 'Reactivate the parent unit first.');
                }
            } else {
                abort_unless($unit->active, 422);
                if (DB::table('organization_units')->where('company_id', $company->id)
                    ->where('workspace_id', $workspace->id)->where('parent_id', $unitId)
                    ->where('active', true)->exists()
                    || DB::table('organization_unit_assignments')->where('company_id', $company->id)
                    ->where('workspace_id', $workspace->id)->where('organization_unit_id', $unitId)->exists()) {
                    throw ValidationException::withMessages([
                        'action' => 'Remove assigned people and deactivate child units before deactivating this unit.',
                    ]);
                }
            }
            DB::table('organization_units')->where('id', $unitId)->update([
                'active' => $activate, 'updated_at' => now(),
            ]);
            TenantOps::audit($actor, 'organization.unit.'.$data['action'],
                'organization_unit', $unitId, ['reason' => trim($data['reason'])]);
        }, 3);

        return back()->with('success', 'Organizational unit status saved.');
    }

    public function assign(Request $request, string $firmSlug, int $unitId)
    {
        [$company, $workspace, $actor] = $this->authorize($request);
        $input = $request->validate(['membership_id' => ['required', 'integer', 'min:1']]);
        DB::transaction(function () use ($company, $workspace, $actor, $unitId, $input) {
            DB::table('workspaces')->where('id', $workspace->id)
                ->where('company_id', $company->id)->lockForUpdate()->first();
            $unit = $this->unit($company, $workspace, $unitId);
            abort_unless($unit->active, 422);
            $member = DB::table('memberships')->where('company_id', $company->id)
                ->where('workspace_id', $workspace->id)->where('id', $input['membership_id'])
                ->where('active', true)->whereNull('platform_suspended_at')->first();
            abort_unless($member, 404);

            if (!DB::table('organization_unit_assignments')->where('organization_unit_id', $unitId)
                ->where('membership_id', $member->id)->exists()) {
                $id = DB::table('organization_unit_assignments')->insertGetId([
                    'company_id' => $company->id, 'workspace_id' => $workspace->id,
                    'organization_unit_id' => $unitId, 'membership_id' => $member->id,
                    'assigned_by' => $actor->id, 'created_at' => now(), 'updated_at' => now(),
                ]);
                TenantOps::audit($actor, 'organization.assignment.created',
                    'organization_unit_assignment', $id, [
                        'unit_id' => $unitId, 'membership_id' => $member->id,
                    ]);
            }
        }, 3);

        return back()->with('success', 'Member assigned to organizational unit.');
    }

    public function unassign(Request $request, string $firmSlug, int $unitId, int $membershipId)
    {
        [$company, $workspace, $actor] = $this->authorize($request);
        DB::transaction(function () use ($company, $workspace, $actor, $unitId, $membershipId) {
            DB::table('workspaces')->where('id', $workspace->id)
                ->where('company_id', $company->id)->lockForUpdate()->first();
            $this->unit($company, $workspace, $unitId);
            $assignment = DB::table('organization_unit_assignments')
                ->where('company_id', $company->id)->where('workspace_id', $workspace->id)
                ->where('organization_unit_id', $unitId)->where('membership_id', $membershipId)->first();
            abort_unless($assignment, 404);
            DB::table('organization_unit_assignments')->where('id', $assignment->id)->delete();
            TenantOps::audit($actor, 'organization.assignment.removed',
                'organization_unit_assignment', $assignment->id, [
                    'unit_id' => $unitId, 'membership_id' => $membershipId,
                ]);
        }, 3);

        return back()->with('success', 'Member removed from organizational unit.');
    }

    private function unit(object $company, object $workspace, int $id, bool $lock = false): object
    {
        $query = DB::table('organization_units')->where('company_id', $company->id)
            ->where('workspace_id', $workspace->id)->where('id', $id);
        $unit = ($lock ? $query->lockForUpdate() : $query)->first();
        abort_unless($unit, 404);

        return $unit;
    }
}
