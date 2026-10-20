<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ProtectErpResponses
{
    public function handle(Request $request,Closure $next):Response
    {
        $response=$next($request);
        // Security headers apply on every ERP/account/MFA/API HTTP response,
        // including unauthorized and failed requests. They never change data.
        if(!$request->is('c/*','platform/*','account/*','security/mfa/*','api/v1/companies/*')){
            return $response;
        }
        $response->headers->set('X-Content-Type-Options','nosniff');
        $response->headers->set('X-Frame-Options','DENY');
        $response->headers->set('Referrer-Policy','no-referrer');
        $response->headers->set('Permissions-Policy',
            'camera=(), microphone=(), geolocation=(self)');
        if(!$response->headers->has('Content-Security-Policy')){
            // Restrict framing without blocking existing self-hosted scripts,
            // Blade inline scripts or intentionally authorized map UI.
            $response->headers->set('Content-Security-Policy',"frame-ancestors 'none'");
        }
        if(!$response->headers->has('Cache-Control') || !$response->headers->contains('Cache-Control','no-store')){
            $response->headers->set('Cache-Control','private, no-store, max-age=0');
        }
        $response->headers->set('Pragma','no-cache');
        return $response;
    }
}
