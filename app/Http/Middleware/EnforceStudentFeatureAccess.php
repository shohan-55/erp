<?php

namespace App\Http\Middleware;

use App\Support\ErpFeatures;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceStudentFeatureAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $actor = $request->attributes->get('tenant.membership');
        abort_unless($actor, 403);

        $feature = ErpFeatures::forRoute($request->route()?->getName());
        if ($feature !== null && ! ErpFeatures::enabled($actor, $feature)) {
            abort(403, 'This module is disabled by your organization or workspace policy.');
        }

        return $next($request);
    }
}
