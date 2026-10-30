<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RestrictPreviewInProduction
{
    /**
     * Design preview routes are denied unless demo mode is explicitly enabled.
     *
     * This used to depend only on `APP_ENV === 'production'`, which meant a
     * mis-typed .env (or a staging box) exposed an anonymous, writable view of
     * every tenant's data. The check is now fail-closed: previews need
     * ERP_DEMO_MODE=true, and even then an authenticated visitor is still
     * confined to their own firm.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->demoModeIsAllowed()) {
            return $next($request);
        }

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

        return $next($request);
    }

    /**
     * Previews are open only for local development, the automated test suite,
     * or an environment that deliberately opted in through ERP_DEMO_MODE.
     */
    protected function demoModeIsAllowed(): bool
    {
        return (bool) config('app.demo_mode', false)
            || app()->runningUnitTests()
            || app()->environment('local', 'testing');
    }
}
