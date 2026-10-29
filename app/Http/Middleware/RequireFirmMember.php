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
        $firmSlug = $request->route('firmSlug');

        if (! Auth::check()) {
            if ($firmSlug) {
                return redirect()->route('firm.login', ['firmSlug' => $firmSlug]);
            }
            return redirect()->route('home');
        }

        /** @var User $user */
        $user = Auth::user();

        // Platform admins can access any firm workspace in read-only / oversight mode.
        if ($user->isPlatformAdmin()) {
            return $next($request);
        }

        if (! $user->isFirmMember() || ($firmSlug && ! $user->belongsToFirm((string) $firmSlug))) {
            abort(403, 'You do not have access to this practice workspace.');
        }

        return $next($request);
    }
}
