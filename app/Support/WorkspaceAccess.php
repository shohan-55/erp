<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Explicit per-Partner RBAC: never confers platform admin or access to other
 * partners' tenants. Every permission is independently revocable.
 */
final class WorkspaceAccess
{
    public const PERMISSIONS = [
        'attendance.propose' => 'Propose historical attendance exceptions',
        'attendance.proxy' => 'Record current server-time attendance on behalf of students',
        'attendance.final' => 'Final attendance correction approval (two-person)',
        'leave.review' => 'First-level leave recommendation',
        'leave.final' => 'Second-level leave approval or rejection',
        'leave.submit_for_student' => 'Submit student leave with identified initiator',
        'claims.review' => 'First-level conveyance claim recommendation',
        'claims.final' => 'Second-level conveyance claim approval or rejection',
        'claims.submit_for_student' => 'Submit a conveyance claim for a student',
        'payroll.manage' => 'Manage Partner-workspace payroll and settlement',
        'access.manage' => 'Manage moderator grants within allowed scope',
        'access.delegate_admins' => 'Allow granting company administrator title',
        'scope.workspace' => 'Access all students within this Partner workspace',
    ];

    public static function grant(object $actor): ?object
    {
        if (!in_array($actor->role,['partner','manager'],true) || !$actor->active || $actor->platform_suspended_at) return null;
        return DB::table('workspace_access_grants')
            ->where('company_id',$actor->company_id)
            ->where('workspace_id',$actor->workspace_id)
            ->where('membership_id',$actor->id)->whereNull('revoked_at')->first();
    }

    public static function canManage(object $actor): bool
    {
        return self::allows($actor,'access.manage');
    }

    public static function allows(object $actor,string $permission): bool
    {
        if (!array_key_exists($permission,self::PERMISSIONS)) return false;
        if ($actor->role === 'partner' && $actor->active && !$actor->platform_suspended_at) return true;
        $grant=self::grant($actor);
        return $grant && in_array($permission,json_decode($grant->permissions,true) ?: [],true);
    }

    public static function subject(object $actor,int $memberId,string $permission): object
    {
        abort_unless(self::allows($actor,$permission),403);
        $query=DB::table('memberships')->where('id',$memberId)
            ->where('company_id',$actor->company_id)
            ->where('workspace_id',$actor->workspace_id)
            ->where('active',true)->whereNull('platform_suspended_at')
            ->where('role','student');
        if ($actor->role==='manager' && !self::allows($actor,'scope.workspace')) {
            $query->where('manager_membership_id',$actor->id);
        }
        $member=$query->first();
        abort_unless($member,404);
        return $member;
    }

    public static function reviewer(object $actor,object $subject,string $module): bool
    {
        if (!in_array($module,['leave','claims'],true)) return false;
        if (!self::allows($actor,$module.'.review')) return false;
        if ($subject->company_id !== $actor->company_id
            || $subject->workspace_id !== $actor->workspace_id) return false;
        if ($actor->role==='partner') return true;
        return self::allows($actor,'scope.workspace')
            || $subject->manager_membership_id===$actor->id;
    }

    public static function finalApprover(object $actor,object $subject,string $module): bool
    {
        if (!in_array($module,['leave','claims'],true)) return false;
        if (!self::allows($actor,$module.'.final')) return false;
        if ($subject->company_id!==$actor->company_id
            || $subject->workspace_id!==$actor->workspace_id) return false;
        return $actor->role==='partner'
            || self::allows($actor,'scope.workspace')
            || $subject->manager_membership_id===$actor->id;
    }
}
