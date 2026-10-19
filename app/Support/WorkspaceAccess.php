<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Tenant-and-Partner scoped delegated privileges. Never an ERP platform admin.
 * The immutable base membership role still controls data visibility.
 */
final class WorkspaceAccess
{
    public const PERMISSIONS = [
        'attendance.propose' => 'Propose verified missed/incorrect attendance for assigned students',
    ];

    public static function grant(object $actor): ?object
    {
        if (!in_array($actor->role,['partner','manager'],true) || !$actor->active) return null;
        return DB::table('workspace_access_grants')
            ->where('company_id',$actor->company_id)
            ->where('workspace_id',$actor->workspace_id)
            ->where('membership_id',$actor->id)->whereNull('revoked_at')->first();
    }

    public static function canManage(object $actor): bool
    {
        if ($actor->role === 'partner') return true;
        $grant=self::grant($actor);
        return $grant && $grant->access_role === 'company_admin';
    }

    public static function allows(object $actor,string $permission): bool
    {
        if (!array_key_exists($permission,self::PERMISSIONS)) return false;
        if ($actor->role === 'partner') return true;
        $grant=self::grant($actor);
        return $grant && in_array($permission,json_decode($grant->permissions,true) ?: [],true);
    }
}
