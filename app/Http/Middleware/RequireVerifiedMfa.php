<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireVerifiedMfa
{
    public function handle(Request $request, Closure $next): Response
    {
        // The legacy feature tests exercise unrelated domain workflows.
        // Dedicated MFA tests explicitly turn this testing-only bypass OFF.
        // In production this bypass is unconditionally impossible.
        if (app()->environment('testing') && config('erp.mfa_test_bypass', true)) {
            return $next($request);
        }

        $user = $request->user();
        abort_unless($user, 401);

        $actor = $request->attributes->get('tenant.membership');
        $privileged = (bool) $user->is_platform_admin || $actor?->role === 'partner';
        if (! $privileged && ! $user->mfa_confirmed_at) {
            return $next($request);
        }

        if (! $user->mfa_confirmed_at || ! $user->mfa_secret) {
            return redirect()->route('erp.mfa.setup');
        }

        if ((int) $request->session()->get('erp_mfa_verified_user_id', 0) !== (int) $user->id) {
            return redirect()->route('erp.mfa.challenge');
        }

        return $next($request);
    }
}
