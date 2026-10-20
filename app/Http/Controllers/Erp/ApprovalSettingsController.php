<?php

namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use App\Support\ApprovalFlow;
use App\Support\TenantOps;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class ApprovalSettingsController extends Controller
{
    public function index(Request $request, string $firmSlug)
    {
        [$company, $workspace, $actor] = TenantOps::context($request);
        TenantOps::requireRole($actor, ['partner']);
        $policies = DB::table('workspace_approval_policies')
            ->where('company_id', $company->id)
            ->where('workspace_id', $workspace->id)
            ->get()->keyBy('module');
        $events = DB::table('workspace_approval_events as e')
            ->join('memberships as m', function ($join) {
                $join->on('m.id', '=', 'e.actor_membership_id')
                    ->on('m.company_id', '=', 'e.company_id')
                    ->on('m.workspace_id', '=', 'e.workspace_id');
            })
            ->join('users as u', 'u.id', '=', 'm.user_id')
            ->where('e.company_id', $company->id)
            ->where('e.workspace_id', $workspace->id)
            ->select('e.*', 'u.name as actor_name')
            ->orderByDesc('e.id')->limit(100)->get();
        $modes = ApprovalFlow::MODES;
        $modules = ['leave'=>'Leave Applications','conveyance'=>'Conveyance Claims'];
        return response()->view('erp.approval-settings', compact(
            'company','workspace','actor','firmSlug','policies','events','modes','modules'
        ))->header('Cache-Control', 'private, no-store');
    }

    public function update(Request $request, string $firmSlug)
    {
        [$company, $workspace, $actor] = TenantOps::context($request);
        TenantOps::requireRole($actor, ['partner']);
        $data = $request->validate([
            'module' => ['required', Rule::in(ApprovalFlow::MODULES)],
            'mode' => ['required', Rule::in(array_keys(ApprovalFlow::MODES))],
            'reason' => ['required','string','min:12','max:1000'],
        ]);

        DB::transaction(function () use ($company, $workspace, $actor, $data) {
            DB::table('workspaces')->where('id', $workspace->id)
                ->where('company_id', $company->id)->lockForUpdate()->first();
            $current = DB::table('memberships')->where('id', $actor->id)
                ->where('company_id', $company->id)->where('workspace_id', $workspace->id)
                ->where('role', 'partner')->where('active', true)
                ->whereNull('platform_suspended_at')->first();
            abort_unless($current, 403);
            $previous = DB::table('workspace_approval_policies')
                ->where('company_id', $company->id)
                ->where('workspace_id', $workspace->id)
                ->where('module', $data['module'])->first();
            DB::table('workspace_approval_policies')->updateOrInsert([
                'company_id' => $company->id,
                'workspace_id' => $workspace->id,
                'module' => $data['module'],
            ], [
                'mode' => $data['mode'], 'active' => true,
                'reason' => trim($data['reason']),
                'changed_by' => $actor->id,
                'created_at' => $previous?->created_at ?? now(),
                'updated_at' => now(),
            ]);
            TenantOps::audit($actor, 'approval_policy.updated', 'workspace_approval_policy',
                (int) ($previous?->id ?? 0), [
                    'module' => $data['module'], 'previous_mode' => $previous?->mode
                        ?? 'manager_partner', 'new_mode' => $data['mode'],
                    'reason' => trim($data['reason']),
                    'applies_to' => 'new_requests_only',
                ]);
        }, 3);
        return back()->with('success',
            'Approval workflow updated. Existing applications retain their original review sequence.');
    }
}
