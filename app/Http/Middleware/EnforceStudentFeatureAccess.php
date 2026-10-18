<?php

namespace App\Http\Middleware;

use App\Support\StudentFeatureAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceStudentFeatureAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $actor = $request->attributes->get('tenant.membership');
        abort_unless($actor, 403);

        if ($actor->role === 'student') {
            $feature = StudentFeatureAccess::featureForRoute($request->route()?->getName());
            if ($feature !== null && ! StudentFeatureAccess::enabled($actor, $feature)) {
                abort(403, 'Your Partner has disabled this feature for your account.');
            }
        }

        return $next($request);
    }
}
