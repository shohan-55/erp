<?php

use App\Http\Middleware\RequireFirmMember;
use App\Http\Middleware\RequireRole;
use App\Http\Middleware\RestrictPreviewInProduction;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'firm.member'        => RequireFirmMember::class,
            'role'               => RequireRole::class,
            'production.preview' => RestrictPreviewInProduction::class,
        ]);

        // Baseline browser security headers on every web response.
        $middleware->web(append: [
            SecurityHeaders::class,
        ]);

        // Behind cPanel/Cloudflare the app only sees HTTP unless the proxy is
        // trusted, which silently breaks $request->isSecure(), secure cookies
        // and generated https URLs. Default to trusting the edge; override with
        // TRUSTED_PROXIES= (empty) to trust nothing, or a comma-separated list.
        $trustedProxies = env('TRUSTED_PROXIES', '*');
        if ($trustedProxies !== '' && $trustedProxies !== null) {
            $middleware->trustProxies(
                at: $trustedProxies === '*' ? '*' : array_map('trim', explode(',', (string) $trustedProxies)),
                headers: \Illuminate\Http\Request::HEADER_X_FORWARDED_FOR
                    | \Illuminate\Http\Request::HEADER_X_FORWARDED_HOST
                    | \Illuminate\Http\Request::HEADER_X_FORWARDED_PORT
                    | \Illuminate\Http\Request::HEADER_X_FORWARDED_PROTO,
            );
        }
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
