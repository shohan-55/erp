<?php

namespace App\Providers;

use App\Http\Middleware\RequireFirmMember;
use App\Http\Middleware\RequireRole;
use App\Http\Middleware\RestrictPreviewInProduction;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->registerMiddlewareAliases();
        $this->registerGates();
    }

    /**
     * Register custom middleware aliases so routes can use:
     *   ->middleware('role:partner,manager')
     *   ->middleware('firm.member')
     *   ->middleware('production.preview')
     */
    private function registerMiddlewareAliases(): void
    {
        $this->app['router']->aliasMiddleware('role', RequireRole::class);
        $this->app['router']->aliasMiddleware('firm.member', RequireFirmMember::class);
        $this->app['router']->aliasMiddleware('production.preview', RestrictPreviewInProduction::class);
    }

    /**
     * Define authorization gates for the RBAC system.
     */
    private function registerGates(): void
    {
        // Platform-level gates
        Gate::define('manage-platform', fn (User $user) => $user->isPlatformAdmin());

        // Firm-level approval gates (derived from per-user permission flags)
        Gate::define('approve-leave', fn (User $user) => $user->isPartner() || (bool) $user->can_approve_leave);
        Gate::define('approve-claims', fn (User $user) => $user->isPartner() || (bool) $user->can_approve_claims);
        Gate::define('proxy-attendance', fn (User $user) => $user->isPartner() || (bool) $user->can_proxy_attendance);
        Gate::define('edit-attendance', fn (User $user) => $user->isPartner() || (bool) $user->can_edit_attendance);

        // Role-based gates
        Gate::define('is-partner', fn (User $user) => $user->isPartner());
        Gate::define('is-manager', fn (User $user) => $user->isManager());
        Gate::define('is-student', fn (User $user) => $user->isStudent());
        Gate::define('is-partner-or-manager', fn (User $user) => $user->isPartner() || $user->isManager());
    }
}
