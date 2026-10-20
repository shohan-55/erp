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
            'student.features' => \App\Http\Middleware\EnforceStudentFeatureAccess::class,
            'tenant.host' => \App\Http\Middleware\EnsureTenantHost::class,
            'platform.host' => \App\Http\Middleware\EnsurePlatformHost::class,
            'mfa.host' => \App\Http\Middleware\EnsureMfaHost::class,
            'platform.admin' => \App\Http\Middleware\EnsurePlatformAdmin::class,
            'mfa.verified' => \App\Http\Middleware\RequireVerifiedMfa::class,
            'erp.api-token' => \App\Http\Middleware\AuthenticateWorkspaceApi::class,
            'erp.locale' => \App\Http\Middleware\ApplyErpLocale::class,
        ]);
        $middleware->redirectGuestsTo(function (Request $request): string {
            if ($request->is('platform/app*')) {
                return route('erp.platform.login');
            }

            $firmSlug = $request->route('firmSlug');
            if (is_string($firmSlug) && $firmSlug !== '') {
                return route('erp.login', ['firmSlug' => $firmSlug]);
            }

            // An MFA route has no tenant in its URL. Never silently assume
            // that a logged-out user belongs to a particular company.
            return route('home');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
