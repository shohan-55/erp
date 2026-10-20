<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Workspace delegation is an additional, revocable grant, not a replacement
 * for identity/membership, and NEVER creates platform or cross-partner access.
 */
final class WorkspaceAccess
{
    // Only actions enforced by existing server-side controllers.
    public const OPERATIONS = [
        'attendance.propose' => 'Attendance: propose corrections for assigned students (Partner final approval)',
        'organization.view' => 'Organization: view workspace structure',
        'organization.manage' => 'Organization: create units and assign local workspace members',
    ];

    public const PERMISSIONS = [
        ...self::OPERATIONS,
        'access.delegate' => 'Delegation: limited Moderator grants within own authorized scope',
        // Requires explicit checkbox: legacy full grants do not silently gain proxy rights.
        'attendance.proxy' => 'Proxy attendance: manager may punch for assigned students, pending Partner verification',
    ];

    private static function live(object $grant): bool
    {
        return $grant->revoked_at === null
            && ($grant->starts_at === null || $grant->starts_at <= now()->toDateTimeString())
            && ($grant->expires_at === null || $grant->expires_at > now()->toDateTimeString());
    }

    private static function operation(object $grant, string $permission): bool
    {
        if (!array_key_exists($permission, self::PERMISSIONS)) return false;
        $raw = json_decode($grant->permissions, true);
        $explicit = is_array($raw) ? $raw : [];
        if ($permission === 'access.delegate') {
            return $grant->access_role === 'company_admin'
                && in_array('access.delegate', $explicit, true);
        }
        if ($permission === 'organization.view' && in_array('organization.manage', $explicit, true)) {
            return true;
        }
        return ($grant->delegation_mode === 'full'
                && array_key_exists($permission, self::OPERATIONS))
            || in_array($permission, $explicit, true);
    }

    /** Active grant with its parent authority still active; never trusts stale session flags. */
    public static function grant(object $actor): ?object
    {
        if ($actor->role !== 'manager' || !($actor->active ?? false)
            || ($actor->platform_suspended_at ?? null) !== null) return null;

        $grant = DB::table('workspace_access_grants')
            ->where('company_id', $actor->company_id)
            ->where('workspace_id', $actor->workspace_id)
            ->where('membership_id', $actor->id)->whereNull('revoked_at')->first();

        if (!$grant || !self::live($grant)) return null;
        if ($grant->parent_grant_id !== null) {
            $parent = DB::table('workspace_access_grants as g')
                ->join('memberships as m', function ($join) {
                    $join->on('m.id', '=', 'g.membership_id')
                        ->on('m.company_id', '=', 'g.company_id')
                        ->on('m.workspace_id', '=', 'g.workspace_id');
                })
                ->where('g.id', $grant->parent_grant_id)
                ->where('g.company_id', $grant->company_id)
                ->where('g.workspace_id', $grant->workspace_id)
                ->where('m.role', 'manager')->where('m.active', true)
                ->whereNull('m.platform_suspended_at')
                ->select('g.*')->first();
            if (!$parent || $parent->parent_grant_id !== null
                || !self::live($parent)
                || !self::operation($parent, 'access.delegate')) return null;
            // Subdelegations cannot survive beyond their parent's time window.
            if (($parent->starts_at !== null && ($grant->starts_at === null || $grant->starts_at < $parent->starts_at))
                || ($parent->expires_at !== null && ($grant->expires_at === null || $grant->expires_at > $parent->expires_at))) {
                return null;
            }
        }

        return $grant;
    }

    public static function canManage(object $actor): bool
    {
        if ($actor->role === 'partner') {
            return (bool) ($actor->active ?? false)
                && ($actor->platform_suspended_at ?? null) === null;
        }
        $grant = self::grant($actor);
        return $grant !== null && self::operation($grant, 'access.delegate');
    }

    /** Base Partner, explicit delegations and custom roles are separate rights. */
    public static function allows(object $actor, string $permission): bool
    {
        if (!array_key_exists($permission, self::PERMISSIONS)) return false;
        if (!($actor->active ?? false)
            || ($actor->platform_suspended_at ?? null) !== null) return false;
        if ($actor->role === 'partner') return true;
        if ($actor->role !== 'manager') return false;

        $grant = self::grant($actor);
        if ($grant && self::operation($grant, $permission)) {
            if ($grant->parent_grant_id !== null) {
                // Runtime check: if the grantor lost the operation, child loses it too.
                $parent = DB::table('workspace_access_grants')
                    ->where('id', $grant->parent_grant_id)
                    ->where('company_id', $actor->company_id)
                    ->where('workspace_id', $actor->workspace_id)->first();
                if (!$parent || !self::operation($parent, $permission)) return false;
            }
            return true;
        }
        return $permission !== 'access.delegate'
            && WorkspaceRoles::allows($actor, $permission);
    }
}
