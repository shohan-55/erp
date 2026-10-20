<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ApplyErpLocale
{
    public function handle(Request $request,Closure $next): Response
    {
        $locale=$request->session()->get('erp.ui_locale','en');
        app()->setLocale(in_array($locale,['en','bn'],true)?$locale:'en');
        return $next($request);
    }
}
