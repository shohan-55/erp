<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformHost
{
    public function handle(Request $request, Closure $next): Response
    {
        $canonical = strtolower((string) parse_url(config('app.url'), PHP_URL_HOST));
        abort_unless(strtolower(rtrim($request->getHost(), '.')) === $canonical, 404);
        return $next($request);
    }
}
