<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformHost
{
    public function handle(Request $request, Closure $next): Response
    {
        $canonical = strtolower((string) parse_url(config('app.url'), PHP_URL_HOST));
        $host = strtolower(rtrim($request->getHost(), '.'));

        // Local browser QA may use 127.0.0.1 while APP_URL points to localhost.
        // Never relax the canonical host allowlist in production.
        if (app()->environment(['local', 'testing'])
            && in_array($host, ['localhost', '127.0.0.1', '::1'], true)
            && in_array($canonical, ['localhost', '127.0.0.1', '::1'], true)) {
            return $next($request);
        }
        abort_unless($host === $canonical, 404);
        URL::forceRootUrl(rtrim((string) config('app.url'), '/'));
        return $next($request);
    }
}
