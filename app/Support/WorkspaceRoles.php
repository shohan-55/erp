<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Phase 05 executable RBAC catalogue.
 *
 * Only operations with a corresponding server-side enforcement check belong
 * here. Future payroll, leave, claims and financial rights are deliberately
 * NOT in the list until their controllers support scoped authorization.
 */
final class WorkspaceRoles
{
    public const PERMISSIONS = [
        'attendance.propose' => 'Attendance — propose a correction for assigned students (Partner final decision still required)',
        'organization.view' => 'Organization — view offices, teams, hierarchy and workspace assignments',
        'organization.manage' => 'Organization — create, rename, activate and assign people within this workspace',
    ];

    public static function allows(object $actor, string $permission): bool
    {
        if (!array_key_exists($permission, self::PERMISSIONS)) {
            return false;
        }
        if (!($actor->active ?? false) || ($actor->platform_suspended_at ?? null) !== null) {
            return false;
        }
        // Base Partner rights stay intact and cannot be revoked by custom
        // role assignment; students are NOT promoted by a manager role label.
        if ($actor->role === 'partner') {
            return true;
        }
        if ($actor->role !== 'manager') {
            return false;
        }

        $row = DB::table('workspace_role_assignments as a')
            ->join('workspace_custom_roles as r', function ($join) {
                $join->on('r.id', '=', 'a.role_id')
                    ->on('r.company_id', '=', 'a.company_id')
                    ->on('r.workspace_id', '=', 'a.workspace_id');
            })
            ->join('memberships as m', function ($join) {
                $join->on('m.id', '=', 'a.membership_id')
                    ->on('m.company_id', '=', 'a.company_id')
                    ->on('m.workspace_id', '=', 'a.workspace_id');
            })
            ->where('a.company_id', $actor->company_id)
            ->where('a.workspace_id', $actor->workspace_id)
            ->where('a.membership_id', $actor->id)
            ->where('m.role', 'manager')
            ->where('m.active', true)
            ->whereNull('m.platform_suspended_at')
            ->where('r.active', true)
            ->select('r.permissions')->first();

        if (!$row) {
            return false;
        }
        $permissions = json_decode($row->permissions, true);
        if (!is_array($permissions)) {
            return false;
        }
        // Managing organization structure includes viewing it, but never
        // includes company ownership or permissions management.
        return in_array($permission, $permissions, true)
            || ($permission === 'organization.view'
                && in_array('organization.manage', $permissions, true));
    }
}
