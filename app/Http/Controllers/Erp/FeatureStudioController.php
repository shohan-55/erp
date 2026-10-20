<?php

namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use App\Support\ErpFeatures;
use App\Support\TenantOps;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class FeatureStudioController extends Controller
{
    private function context(Request $request): array
    {
        [$company, $workspace, $actor] = TenantOps::context($request);
        // Explicit owner authority. Manager and Moderator role labels cannot
        // silently rewrite company-wide or workspace module availability.
        TenantOps::requireRole($actor, ['partner']);
        return [$company, $workspace, $actor];
    }

    private function companyWideEligible(int $companyId): bool
    {
        // Phase 08 will add the approval/voting workflow for multiple Partners.
        // Until then, reject unilateral company-wide changes, including from
        // a tenant with multiple workspaces but only one nominated owner.
        return DB::table('workspaces')->where('company_id', $companyId)
            ->where('active', true)->count() === 1
            && DB::table('memberships')->where('company_id', $companyId)
                ->where('role', 'partner')->where('active', true)
                ->whereNull('platform_suspended_at')->count() === 1;
    }

    public function index(Request $request, string $firmSlug)
    {
        [$company, $workspace, $actor] = $this->context($request);
        $modules = ErpFeatures::MODULES;
        $members = DB::table('memberships as m')
            ->join('users as u', 'u.id', '=', 'm.user_id')
            ->where('m.company_id', $company->id)
            ->where('m.workspace_id', $workspace->id)
            ->where('m.active', true)->whereNull('m.platform_suspended_at')
            ->select('m.id', 'm.role', 'u.name')->orderBy('u.name')->limit(300)->get();
        $keys = [ErpFeatures::companyKey(), ErpFeatures::workspaceKey($workspace->id)];
        foreach (ErpFeatures::BASE_ROLES as $role) {
            $keys[] = ErpFeatures::roleKey($workspace->id, $role);
        }
        foreach ($members as $member) $keys[] = ErpFeatures::userKey($member->id);
        $controls = DB::table('erp_feature_controls')
            ->where('company_id', $company->id)
            ->whereIn('scope_key', $keys)
            ->orderBy('feature_key')->orderBy('scope_key')->limit(1200)->get();
        $allowedCompany = $this->companyWideEligible($company->id);
        $effective = ErpFeatures::enabledMap($actor);
        return response()->view('erp.feature-studio', compact(
            'company', 'workspace', 'actor', 'firmSlug', 'modules',
            'members', 'controls', 'allowedCompany', 'effective'
        ))->header('Cache-Control', 'private, no-store');
    }

    public function update(Request $request, string $firmSlug)
    {
        [$company, $workspace, $actor] = $this->context($request);
        $input = $request->validate([
            'feature_key' => ['required', Rule::in(array_keys(ErpFeatures::MODULES))],
            'scope' => ['required', Rule::in(['company', 'workspace', 'role', 'user'])],
            'role' => ['required_if:scope,role', 'nullable', Rule::in(ErpFeatures::BASE_ROLES)],
            'membership_id' => ['required_if:scope,user', 'nullable', 'integer', 'min:1'],
            'operation' => ['required', Rule::in(['enable', 'disable', 'reset'])],
            'reason' => ['required', 'string', 'min:12', 'max:500'],
        ]);

        DB::transaction(function () use ($company, $workspace, $actor, $input) {
            // Every setting update serializes at the company level so
            // scope/ownership/membership cannot race a concurrent toggle.
            DB::table('companies')->where('id', $company->id)->lockForUpdate()->first();
            DB::table('workspaces')->where('company_id', $company->id)
                ->where('id', $workspace->id)->lockForUpdate()->first();

            $key = match ($input['scope']) {
                'company' => ErpFeatures::companyKey(),
                'workspace' => ErpFeatures::workspaceKey($workspace->id),
                'role' => ErpFeatures::roleKey($workspace->id, $input['role']),
                'user' => ErpFeatures::userKey((int) $input['membership_id']),
            };

            if ($input['scope'] === 'company' && !$this->companyWideEligible($company->id)) {
                throw ValidationException::withMessages([
                    'scope' => 'This company has multiple Partners/workspaces: company-wide settings require the upcoming firm-wide approval workflow. Use Workspace settings for now.',
                ]);
            }
            if ($input['scope'] === 'user') {
                $member = DB::table('memberships')
                    ->where('id', $input['membership_id'])
                    ->where('company_id', $company->id)
                    ->where('workspace_id', $workspace->id)
                    ->where('active', true)->whereNull('platform_suspended_at')->first();
                abort_unless($member, 404);
            }

            $existing = DB::table('erp_feature_controls')
                ->where('company_id', $company->id)
                ->where('scope_key', $key)
                ->where('feature_key', $input['feature_key'])
                ->lockForUpdate()->first();

            if ($input['operation'] === 'reset') {
                DB::table('erp_feature_controls')
                    ->where('company_id', $company->id)
                    ->where('scope_key', $key)
                    ->where('feature_key', $input['feature_key'])->delete();
            } else {
                DB::table('erp_feature_controls')->updateOrInsert([
                    'company_id' => $company->id,
                    'scope_key' => $key,
                    'feature_key' => $input['feature_key'],
                ], [
                    'enabled' => $input['operation'] === 'enable',
                    'reason' => trim($input['reason']),
                    'updated_by' => $actor->id,
                    'created_at' => $existing?->created_at ?? now(),
                    'updated_at' => now(),
                ]);
            }

            TenantOps::audit($actor, 'feature.setting.changed', 'feature_control',
                $existing?->id ?? 0, [
                    'feature_key' => $input['feature_key'],
                    'scope' => $input['scope'],
                    'scope_key' => $key,
                    'operation' => $input['operation'],
                    'previous' => $existing ? (bool) $existing->enabled : null,
                    'reason' => trim($input['reason']),
                ]);
        }, 3);
        return back()->with('success', 'Module settings saved. Permissions are enforced on the next request.');
    }
}
