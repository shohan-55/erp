<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantHost
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = strtolower(rtrim($request->getHost(), '.'));
        $canonical = strtolower((string) parse_url(config('app.url'), PHP_URL_HOST));

        // Local browser QA may use 127.0.0.1 while APP_URL points to localhost.
        // Never relax the canonical host allowlist in production.
        if (app()->environment(['local', 'testing'])
            && in_array($host, ['localhost', '127.0.0.1', '::1'], true)
            && in_array($canonical, ['localhost', '127.0.0.1', '::1'], true)) {
            return $next($request);
        }

        if ($host !== $canonical) {
            $slug = $request->route('firmSlug');
            $authorized = is_string($slug) && DB::table('company_custom_domains as d')
                ->join('companies as c', 'c.id', '=', 'd.company_id')
                ->where('d.hostname', $host)->where('d.active', true)
                ->whereNotNull('d.verified_at')->whereNotNull('d.tls_approved_at')
                ->where('c.active', true)->whereNull('c.archived_at')
                ->where('c.slug', $slug)->exists();

            abort_unless($authorized, 404);

            // Override the application's trusted canonical URL only for a verified,
            // active domain already mapped to this exact tenant. Password/invitation
            // bearer-token URLs deliberately remain on the platform-owned host.
            $tokenLinkActions = ['erp.password.email', 'erp.team.invite', 'erp.team.resend'];
            $root = in_array($request->route()?->getName(), $tokenLinkActions, true)
                ? rtrim((string) config('app.url'), '/')
                : 'https://'.$host;
            URL::forceRootUrl($root);
            URL::forceScheme('https');
        } elseif ($host === $canonical) {
            // Also reset shared URL generators when multiple test requests reuse the app.
            URL::forceRootUrl(rtrim((string) config('app.url'), '/'));
        }

        return $next($request);
    }
}
