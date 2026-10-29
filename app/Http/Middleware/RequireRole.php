<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RequireRole
{
    /**
     * Handle an incoming request.
     *
     * Usage in routes:
     *   ->middleware('role:partner,manager')
     *   ->middleware('role:platform_admin')
     *
     * @param array<string> $roles
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (! Auth::check()) {
            if (in_array(User::ROLE_PLATFORM_ADMIN, $roles, true)) {
                return redirect()->route('platform.login');
            }
            $firmSlug = $request->route('firmSlug');
            if ($firmSlug) {
                return redirect()->route('firm.login', ['firmSlug' => $firmSlug]);
            }
            return redirect()->route('home');
        }

        /** @var User $user */
        $user = Auth::user();

        if (in_array(User::ROLE_PLATFORM_ADMIN, $roles, true) && $user->isPlatformAdmin()) {
            return $next($request);
        }

        if (! in_array($user->role, $roles, true)) {
            abort(403, 'You do not have permission to access this resource.');
        }

        return $next($request);
    }
}
