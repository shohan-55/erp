<?php

namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use App\Support\TenantOps;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class WorkspaceReminderRuleController extends Controller
{
    public function index(Request $request, string $firmSlug)
    {
        [$company, $workspace, $actor] = TenantOps::context($request);
        TenantOps::requireRole($actor, ['partner']);

        $rule = DB::table('workspace_reminder_rules')
            ->where('company_id', $company->id)->where('workspace_id', $workspace->id)
            ->where('rule_type', 'milestone_due')->first();

        $recent = DB::table('workspace_reminder_deliveries as d')
            ->join('workspace_reminder_rules as r', 'r.id', '=', 'd.rule_id')
            ->where('r.company_id', $company->id)->where('r.workspace_id', $workspace->id)
            ->select('d.id', 'd.milestone_id', 'd.recipient_membership_id',
                'd.due_on', 'd.kind', 'd.created_at')
            ->orderByDesc('d.id')->limit(50)->get();

        return response()->view('erp.workspace-reminders', compact(
            'company', 'workspace', 'actor', 'firmSlug', 'rule', 'recent'
        ))->header('Cache-Control', 'private, no-store, max-age=0');
    }

    public function save(Request $request, string $firmSlug)
    {
        [, , $actor] = TenantOps::context($request);
        TenantOps::requireRole($actor, ['partner']);
        $input = $request->validate([
            'days_before' => ['required', 'integer', 'between:0,30'],
            'active' => ['required', 'boolean'],
            'reason' => ['required', 'string', 'min:15', 'max:500'],
        ]);
        DB::transaction(function () use ($actor, $input) {
            DB::table('workspaces')->where('id', $actor->workspace_id)
                ->where('company_id', $actor->company_id)->lockForUpdate()->firstOrFail();

            $query = DB::table('workspace_reminder_rules')
                ->where('company_id', $actor->company_id)
                ->where('workspace_id', $actor->workspace_id)
                ->where('rule_type', 'milestone_due');
            $existing = $query->first();
            $data = [
                'days_before' => (int) $input['days_before'],
                'active' => (bool) $input['active'],
                'updated_by' => $actor->id,
                'updated_at' => now(),
            ];
            if ($existing) {
                $query->update($data);
                $id = $existing->id;
            } else {
                $id = DB::table('workspace_reminder_rules')->insertGetId(array_merge($data, [
                    'company_id' => $actor->company_id,
                    'workspace_id' => $actor->workspace_id,
                    'rule_type' => 'milestone_due',
                    'created_at' => now(),
                ]));
            }
            TenantOps::audit($actor, 'reminders.rule_updated', 'workspace_reminder_rule', $id, [
                'active' => (bool) $input['active'],
                'days_before' => (int) $input['days_before'],
                'reason' => trim($input['reason']),
                'previous_active' => (bool) ($existing->active ?? false),
                'previous_days_before' => $existing->days_before ?? null,
            ]);
        }, 3);

        return back()->with('success',
            'Workspace rule saved. Enabled rules deliver private in-app reminders on the scheduled daily check.');
    }
}
