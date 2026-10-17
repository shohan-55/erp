<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

class EnsureMfaHost
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = strtolower(rtrim($request->getHost(), '.'));
        $canonical = strtolower((string) parse_url(config('app.url'), PHP_URL_HOST));

        if ($host === $canonical) {
            URL::forceRootUrl(rtrim((string) config('app.url'), '/'));
            return $next($request);
        }

        if (app()->environment(['local', 'testing'])
            && in_array($host, ['localhost', '127.0.0.1', '::1'], true)
            && in_array($canonical, ['localhost', '127.0.0.1', '::1'], true)) {
            return $next($request);
        }

        // First-factor authentication must already have established a tenant
        // context. Never allow a platform-admin MFA flow on a client hostname.
        $slug = $request->session()->get('erp_mfa_firm_slug');
        abort_unless($request->session()->get('erp_mfa_context') === 'firm'
            && is_string($slug) && $slug !== '', 404);

        $authorized = DB::table('company_custom_domains as d')
            ->join('companies as c', 'c.id', '=', 'd.company_id')
            ->where('d.hostname', $host)->where('d.active', true)
            ->whereNotNull('d.verified_at')->whereNotNull('d.tls_approved_at')
            ->where('c.slug', $slug)->where('c.active', true)
            ->whereNull('c.archived_at')->exists();
        abort_unless($authorized, 404);

        URL::forceRootUrl('https://'.$host);
        URL::forceScheme('https');

        return $next($request);
    }
}
