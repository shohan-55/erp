<?php

namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomDomainEntryController extends Controller
{
    public function index(Request $request)
    {
        $canonical = strtolower((string) parse_url(config('app.url'), PHP_URL_HOST));
        if (strtolower(rtrim($request->getHost(), '.')) === $canonical) {
            return view('erp.welcome');
        }

        return $this->login($request);
    }

    public function login(Request $request)
    {
        $host = strtolower(rtrim($request->getHost(), '.'));
        $canonical = strtolower((string) parse_url(config('app.url'), PHP_URL_HOST));
        abort_if($host === $canonical, 404);

        // Host is untrusted input: no redirects or tenant access unless its
        // exact hostname is registered, verified, approved and active.
        $company = DB::table('company_custom_domains as d')
            ->join('companies as c', 'c.id', '=', 'd.company_id')
            ->where('d.hostname', $host)->where('d.active', true)
            ->where('c.active', true)->whereNull('c.archived_at')
            ->whereNotNull('d.verified_at')->whereNotNull('d.tls_approved_at')
            ->select('c.*')->first();
        abort_unless($company, 404);

        $firmSlug = $company->slug;
        return app(AuthController::class)->show($request, $firmSlug);
    }
}
