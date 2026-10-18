<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantMember
{
    public function handle(Request $request, Closure $next): Response
    {
        $firmSlug = (string) $request->route('firmSlug');
        $company = DB::table('companies')->where('slug', $firmSlug)
            ->where('active', true)->whereNull('archived_at')->first();

        if (! $company || ! $request->user()) {
            abort(403);
        }

        // Never resolve one of several memberships arbitrarily: the selected
        // identity determines the privacy boundary for salary and attendance.
        $memberships = DB::table('memberships as m')
            ->join('workspaces as w', function ($join) {
                $join->on('w.id', '=', 'm.workspace_id')
                    ->on('w.company_id', '=', 'm.company_id');
            })
            ->where('m.company_id', $company->id)
            ->where('m.user_id', $request->user()->id)
            ->where('m.active', true)->whereNull('m.platform_suspended_at')
            ->where('w.active', true)->select('m.*')->orderBy('m.id')->get();

        if ($memberships->isEmpty()) {
            abort(403, 'You do not have an active Partner workspace in this organization.');
        }

        $selection = $request->session()->get('erp.active_membership');
        if ($memberships->count() === 1) {
            // Backwards-compatible single-partner flow: no chooser required.
            $membership = $memberships->first();
            if (is_array($selection)
                && ($selection['company_id'] ?? null) === (int) $company->id
                && ($selection['user_id'] ?? null) === (int) $request->user()->id
                && ($selection['membership_id'] ?? null) !== (int) $membership->id) {
                // Previously selected workspace was suspended or revoked.
                // Fail closed instead of changing the identity silently.
                $request->session()->forget('erp.active_membership');
                return redirect()->route('erp.workspaces.index', ['firmSlug' => $firmSlug]);
            }
        } else {
            $membership = null;
            if (is_array($selection)
                && ($selection['company_id'] ?? null) === (int) $company->id
                && ($selection['user_id'] ?? null) === (int) $request->user()->id) {
                $membership = $memberships->firstWhere('id', $selection['membership_id'] ?? 0);
            }
            if (! $membership) {
                $request->session()->forget('erp.active_membership');
                return redirect()->route('erp.workspaces.index', ['firmSlug' => $firmSlug]);
            }
        }

        $workspace = DB::table('workspaces')
            ->where('company_id', $company->id)
            ->where('id', $membership->workspace_id)
            ->where('active', true)->first();
        abort_unless($workspace, 403, 'Your practice workspace is unavailable.');

        $request->attributes->set('tenant.company', $company);
        $request->attributes->set('tenant.membership', $membership);
        $request->attributes->set('tenant.workspace', $workspace);
        $request->attributes->set('tenant.workspace_count', $memberships->count());

        return $next($request);
    }
}
