<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RequireFirmMember
{
    /**
     * Ensure the authenticated user is a member of the firm in the URL.
     *
     * This middleware reads the {firmSlug} route parameter and checks that
     * the logged-in user's firm slug matches, preventing cross-firm access.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return redirect()->route('home');
        }

        /** @var User $user */
        $user = Auth::user();

        // Platform admins can access any firm workspace in read-only mode.
        if ($user->isPlatformAdmin()) {
            return $next($request);
        }

        $firmSlug = $request->route('firmSlug');

        if ($firmSlug && ! $user->belongsToFirm($firmSlug)) {
            abort(403, 'You do not have access to this practice workspace.');
        }

        return $next($request);
    }
}
