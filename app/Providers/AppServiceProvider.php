<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Never derive bearer-token activation or password recovery links
        // from an untrusted incoming Host header in production.
        if (app()->environment('production')) {
            $root = rtrim((string) config('app.url'), '/');
            if (str_starts_with($root, 'https://')) {
                URL::forceRootUrl($root);
                URL::forceScheme('https');
            }
        }
    }
}
