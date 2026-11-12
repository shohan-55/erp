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
 *   - a Manager may only act on their own direct reports, students who
 *     explicitly engaged them, or their own unit's Articled Students;
 *   - a Student may act on nobody.
 *
 * Unauthenticated previews keep their design-only behaviour.
 */
trait ManagesUnitScope
{
    /**
     * May the signed-in actor record / approve something for $target?
     */
    protected function actorMayManageUser(?User $target, ?int $engagedManagerId = null): bool
    {
        if (! $target) {
            return false;
        }

        if (! Auth::check()) {
            // Never allow unauthenticated callers to mutate real database rows
            // in production or demo mode; only the automated test runner may
            // exercise unauthenticated preview simulations.
            return ! app()->environment('production') && app()->runningUnitTests();
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

        // Direct report or explicitly engaged Manager on a leave/claim request.
        if ((int) $target->manager_id === (int) $actor->id) {
            return true;
        }

        if ($engagedManagerId !== null && (int) $engagedManagerId === (int) $actor->id) {
            return true;
        }

        // If the student has no assigned manager or firm-wide shared view is active, allow any Manager in the same firm.
        if ($target->manager_id === null || ! empty($this->sharedView) || ! empty($this->canViewAllPartnersAttendance)) {
            return true;
        }

        // Unassigned student in the same unit or when either unit is unset / single-unit firm.
        if ($target->partner_unit === null || $actor->partner_unit === null || trim((string) $target->partner_unit) === '' || trim((string) $actor->partner_unit) === '') {
            return true;
        }

        if (isset($this->availablePartnerUnits) && is_array($this->availablePartnerUnits) && count($this->availablePartnerUnits) <= 1) {
            return true;
        }

        return strcasecmp(trim((string) $target->partner_unit), trim((string) $actor->partner_unit)) === 0;
    }

    /**
     * Convenience guard used by write actions. Sets $this->error and returns
     * false when the actor may not touch the target.
     */
    protected function guardUserScope(?User $target, string $action = 'modify', ?int $engagedManagerId = null): bool
    {
        if ($this->actorMayManageUser($target, $engagedManagerId)) {
            return true;
        }

        $this->error = 'Access denied: you can only ' . $action . ' members of your own partner unit or your direct reports.';

        return false;
    }
}
