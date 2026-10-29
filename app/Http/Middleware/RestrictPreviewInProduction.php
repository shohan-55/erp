<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RestrictPreviewInProduction
{
    /**
     * In production environments, disallow unauthenticated access to design preview routes.
     * Authenticated users are redirected or permitted only within their authorized scope.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment('production') && ! config('app.preview_enabled', false)) {
            if (! Auth::check()) {
                $firmSlug = $request->route('firmSlug');
                if ($firmSlug) {
                    return redirect()->route('firm.login', ['firmSlug' => $firmSlug]);
                }

                return redirect()->route('platform.login');
            }

            /** @var \App\Models\User $user */
            $user = Auth::user();
            $firmSlug = $request->route('firmSlug');

            if ($firmSlug && ! $user->isPlatformAdmin() && ! $user->belongsToFirm((string) $firmSlug)) {
                abort(403, 'You do not have access to this practice workspace.');
            }

            if (! $firmSlug && ! $user->isPlatformAdmin()) {
                abort(403, 'Platform administrator access required.');
            }
        }

        return $next($request);
    }
}
