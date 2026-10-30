<?php

namespace App\Livewire\Practice\Concerns;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Partner-unit scoping for *write* actions.
 *
 * Audit finding P1-3.3: partner-unit isolation existed only for viewing. Any
 * Manager could proxy or edit attendance, and approve or reject leave and
 * conveyance claims, for every member of the firm — including other units and
 * the Partners themselves.
 *
 * This trait centralises the rule so all workflows agree:
 *   - a Partner / platform admin may act on anyone in the firm;
 *   - a Manager may only act on their own direct reports or their own unit's
 *     Articled Students;
 *   - a Student may act on nobody.
 *
 * Unauthenticated previews keep their design-only behaviour.
 */
trait ManagesUnitScope
{
    /**
     * May the signed-in actor record / approve something for $target?
     */
    protected function actorMayManageUser(?User $target): bool
    {
        if (! $target) {
            return false;
        }

        if (! Auth::check()) {
            // Design preview: no real identity, and previews are already gated
            // behind ERP_DEMO_MODE, so don't invent a second gate here.
            return true;
        }

        /** @var User $actor */
        $actor = Auth::user();

        if ($actor->isPlatformAdmin() || $actor->isPartner()) {
            return true;
        }

        if (! $actor->isManager()) {
            return false;
        }

        // Never let a Manager touch a Partner or another Manager.
        if (! $target->isStudent()) {
            return false;
        }

        // Cross-firm is never allowed, whatever the unit says.
        if ($actor->firm_id && $target->firm_id && (int) $actor->firm_id !== (int) $target->firm_id) {
            return false;
        }

        if ((int) $target->manager_id === (int) $actor->id) {
            return true;
        }

        return $target->partner_unit !== null
            && $actor->partner_unit !== null
            && strcasecmp((string) $target->partner_unit, (string) $actor->partner_unit) === 0;
    }

    /**
     * Convenience guard used by write actions. Sets $this->error and returns
     * false when the actor may not touch the target.
     */
    protected function guardUserScope(?User $target, string $action = 'modify'): bool
    {
        if ($this->actorMayManageUser($target)) {
            return true;
        }

        $this->error = 'Access denied: you can only ' . $action . ' members of your own partner unit or your direct reports.';

        return false;
    }
}
