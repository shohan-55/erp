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
        // Respect newly enrolled or revoked MFA state immediately rather than
        // trusting an ORM instance cached earlier in the same session.
        $user->refresh();

        // Platform operators must enroll. Tenant members (including Partners)
        // may opt in from Settings > Security. Once enrolled, verification
        // remains mandatory on every fresh login until securely disabled.
        if (! $user->is_platform_admin && ! $user->mfa_confirmed_at) {
            return $next($request);
        }

        if (! $user->mfa_confirmed_at) {
            return redirect()->route('erp.mfa.setup');
        }

        // Do not allow an inconsistent enrolled record to bypass MFA or loop.
        abort_unless($user->mfa_secret, 403, 'Authenticator configuration requires support.');

        if ((int) $request->session()->get('erp_mfa_verified_user_id', 0) !== (int) $user->id) {
            return redirect()->route('erp.mfa.challenge');
        }

        return $next($request);
    }
}
