<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds the baseline set of browser security headers.
 *
 * The audit (P1-3.9) found the app sent none of these, which leaves every
 * page open to clickjacking (no frame policy), MIME sniffing and referrer
 * leakage of tenant URLs.
 *
 * The Content-Security-Policy is deliberately pragmatic: Livewire and Alpine
 * rely on inline bootstrapping and the design system ships an inline style
 * block, so 'unsafe-inline' stays for script/style. Everything else is locked
 * down, which is what actually stops this page being framed or used as a
 * script/object host. Tightening further needs a nonce pipeline — tracked as a
 * follow-up, not faked here.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = [
            'X-Content-Type-Options'  => 'nosniff',
            'X-Frame-Options'         => 'SAMEORIGIN',
            'Referrer-Policy'         => 'strict-origin-when-cross-origin',
            // Attendance capture needs geolocation on our own origin only.
            'Permissions-Policy'      => 'geolocation=(self), camera=(), microphone=(), payment=()',
            'Cross-Origin-Opener-Policy' => 'same-origin',
        ];

        foreach ($headers as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        if (! $response->headers->has('Content-Security-Policy')) {
            $response->headers->set('Content-Security-Policy', implode('; ', [
                "default-src 'self'",
                "base-uri 'self'",
                "object-src 'none'",
                "frame-ancestors 'self'",
                "img-src 'self' data: blob:",
                "font-src 'self' data: https://fonts.gstatic.com",
                "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
                "script-src 'self' 'unsafe-inline' 'unsafe-eval'",
                "connect-src 'self'",
                "form-action 'self'",
            ]));
        }

        // Only meaningful over TLS, and only then do browsers honour it.
        if ($request->isSecure() && ! $response->headers->has('Strict-Transport-Security')) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // Private tenant documents must never be cached by a shared proxy.
        if ($request->is('c/*', 'platform/*') && ! $response->headers->has('Cache-Control')) {
            $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
        }

        return $response;
    }
}
