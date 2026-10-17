<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantHost
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = strtolower(rtrim($request->getHost(), '.'));
        $canonical = strtolower((string) parse_url(config('app.url'), PHP_URL_HOST));

        if ($host !== $canonical) {
            $slug = $request->route('firmSlug');
            $authorized = is_string($slug) && DB::table('company_custom_domains as d')
                ->join('companies as c', 'c.id', '=', 'd.company_id')
                ->where('d.hostname', $host)->where('d.active', true)
                ->where('c.active', true)->whereNull('c.archived_at')
                ->where('c.slug', $slug)->exists();

            abort_unless($authorized, 404);
        }

        return $next($request);
    }
}
