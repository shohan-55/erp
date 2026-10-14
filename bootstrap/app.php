<?php

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
            'tenant.member' => \App\Http\Middleware\EnsureTenantMember::class,
            'platform.admin' => \App\Http\Middleware\EnsurePlatformAdmin::class,
            'mfa.verified' => \App\Http\Middleware\RequireVerifiedMfa::class,
        ]);
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('platform/app*')
            ? route('erp.platform.login')
            : route('erp.login', ['firmSlug' => $request->route('firmSlug') ?? 'rac']));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
