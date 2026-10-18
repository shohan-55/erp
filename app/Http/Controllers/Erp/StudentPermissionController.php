<?php

namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use App\Support\StudentFeatureAccess;
use App\Support\TenantOps;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StudentPermissionController extends Controller
{
    public function index(Request $request, string $firmSlug)
    {
        [$company, $workspace, $actor] = TenantOps::context($request);
        $this->requireEditor($actor);

        $students = DB::table('memberships as m')
            ->join('users as u', 'u.id', '=', 'm.user_id')
            ->where('m.company_id', $company->id)
            ->where('m.workspace_id', $workspace->id)
            ->where('m.role', 'student')->where('m.active', true)
            ->when($actor->role === 'manager',
                fn ($q) => $q->where('m.manager_membership_id', $actor->id))
            ->select('m.id', 'm.manager_membership_id', 'u.name', 'u.email')
            ->orderBy('u.name')->get();

        $managers = $actor->role === 'partner'
            ? DB::table('memberships as m')
                ->join('users as u', 'u.id', '=', 'm.user_id')
                ->where('m.company_id', $company->id)
                ->where('m.workspace_id', $workspace->id)
                ->where('m.role', 'manager')->where('m.active', true)
                ->select('m.id', 'm.can_manage_student_features', 'u.name')
                ->orderBy('u.name')->get()
            : collect();

        $controls = DB::table('student_feature_controls')
            ->where('company_id', $company->id)->where('workspace_id', $workspace->id)
            ->whereIn('membership_id', $students->pluck('id'))
            ->get()->groupBy('membership_id');
        $features = StudentFeatureAccess::FEATURES;

        return view('erp.student-permissions',
            compact('company', 'workspace', 'actor', 'firmSlug', 'students', 'managers', 'controls', 'features'));
    }

    public function updateStudent(Request $request, string $firmSlug, int $memberId)
    {
        [, , $actor] = TenantOps::context($request);
        $this->requireEditor($actor);

        $input = $request->validate([
            'feature_key' => ['required', Rule::in(array_keys(StudentFeatureAccess::FEATURES))],
            'enabled' => ['required', 'boolean'],
        ]);

        DB::transaction(function () use ($actor, $memberId, $input) {
            $member = DB::table('memberships')->where('id', $memberId)
                ->where('company_id', $actor->company_id)
                ->where('workspace_id', $actor->workspace_id)
                ->where('role', 'student')->where('active', true)
                ->when($actor->role === 'manager',
                    fn ($q) => $q->where('manager_membership_id', $actor->id))
                ->lockForUpdate()->first();
            abort_unless($member, 404, 'Student is not in your authorized team.');

            // Managers cannot assign features forbidden by the Partner.
            $key = $input['feature_key'];
            $row = DB::table('student_feature_controls')
                ->where('company_id', $actor->company_id)
                ->where('workspace_id', $actor->workspace_id)
                ->where('membership_id', $memberId)
                ->where('feature_key', $key)->first();

            $enabled = (bool) $input['enabled'];
            if ($actor->role === 'manager' && $enabled && $row && ! $row->partner_enabled) {
                throw ValidationException::withMessages(['enabled' =>
                    'Only the Partner can restore a feature the Partner disabled.']);
            }

            $column = $actor->role === 'partner' ? 'partner_enabled' : 'manager_enabled';
            $actorColumn = $actor->role === 'partner' ? 'partner_updated_by' : 'manager_updated_by';
            $values = [
                $column => $enabled, $actorColumn => $actor->id, 'updated_at' => now(),
            ];
            if ($row) {
                DB::table('student_feature_controls')->where('id', $row->id)->update($values);
            } else {
                DB::table('student_feature_controls')->insert([
                    'company_id' => $actor->company_id,
                    'workspace_id' => $actor->workspace_id,
                    'membership_id' => $memberId,
                    'feature_key' => $key, 'partner_enabled' => true,
                    'manager_enabled' => true, ...$values, 'created_at' => now(),
                ]);
            }

            TenantOps::audit($actor, 'student.feature.updated', 'membership', $memberId, [
                'feature_key' => $key, 'enabled' => $enabled,
                'controller' => $actor->role,
                'partner_limit_preserved' => $actor->role === 'manager',
            ]);
        }, 3);

        return back()->with('success', 'Student feature permission saved.');
    }

    public function delegateManager(Request $request, string $firmSlug, int $memberId)
    {
        [, , $actor] = TenantOps::context($request);
        TenantOps::requireRole($actor, ['partner']);
        $input = $request->validate(['enabled' => ['required', 'boolean']]);

        DB::transaction(function () use ($actor, $memberId, $input) {
            $member = DB::table('memberships')->where('id', $memberId)
                ->where('company_id', $actor->company_id)
                ->where('workspace_id', $actor->workspace_id)
                ->where('role', 'manager')->where('active', true)
                ->lockForUpdate()->first();
            abort_unless($member, 404, 'Manager is outside your Partner workspace.');

            DB::table('memberships')->where('id', $memberId)->update([
                'can_manage_student_features' => (bool) $input['enabled'],
                'updated_at' => now(),
            ]);
            TenantOps::audit($actor, 'manager.permission_delegation.updated', 'membership', $memberId, [
                'can_manage_student_features' => (bool) $input['enabled'],
            ]);
        }, 3);

        return back()->with('success', 'Manager delegation updated.');
    }

    private function requireEditor(object $actor): void
    {
        abort_unless($actor->role === 'partner'
            || ($actor->role === 'manager' && (bool) $actor->can_manage_student_features),
            403, 'Partner authorization is required to edit student features.');
    }
}
