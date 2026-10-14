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
        $firmSlug = $request->route('firmSlug');
        $company = DB::table('companies')
            ->where('slug', $firmSlug)->where('active', true)->first();

        if (! $company || ! $request->user()) {
            abort(403);
        }

        $membership = DB::table('memberships')
            ->where('company_id', $company->id)
            ->where('user_id', $request->user()->id)
            ->where('active', true)->first();

        if (! $membership) {
            abort(403, 'You do not belong to this organization.');
        }

        $workspace = DB::table('workspaces')
            ->where('company_id', $company->id)
            ->where('id', $membership->workspace_id)
            ->where('active', true)->first();

        if (! $workspace) {
            abort(403, 'Your practice workspace is unavailable.');
        }

        $request->attributes->set('tenant.company', $company);
        $request->attributes->set('tenant.membership', $membership);
        $request->attributes->set('tenant.workspace', $workspace);

        return $next($request);
    }
}