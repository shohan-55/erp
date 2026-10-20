<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Privacy-scoped, read-only Staff Exit clearance.
 *
 * Must be evaluated AGAIN inside the company/workspace transaction during
 * exit. No financial records, attendance, documents or histories are removed.
 */
final class StaffExitClearance
{
    public static function issues(object $actor, object $member): array
    {
        $company=(int)$actor->company_id;
        $workspace=(int)$actor->workspace_id;
        $memberId=(int)$member->id;
        $day=now('Asia/Dhaka')->toDateString();
        $month=now('Asia/Dhaka')->startOfMonth()->toDateString();
        $scope=static fn($q)=>$q->where('company_id',$company)
            ->where('workspace_id',$workspace)
            ->where('membership_id',$memberId);
        $issues=[];

        if ($member->role==='manager') {
            if(DB::table('memberships')->where('company_id',$company)
                ->where('workspace_id',$workspace)
                ->where('role','student')->where('manager_membership_id',$memberId)
                ->where('active',true)->exists()) {
                $issues['assigned_students']='Reassign active students before this Manager exits.';
            }
            if(DB::table('leave_requests')->where('company_id',$company)
                ->where('workspace_id',$workspace)->where('initiated_by',$memberId)
                ->whereIn('status',['pending','manager_reviewed'])->exists()
                || DB::table('conveyance_claims')->where('company_id',$company)
                    ->where('workspace_id',$workspace)->where('initiated_by',$memberId)
                    ->whereIn('status',['pending','manager_reviewed'])->exists()) {
                $issues['delegated_requests']='Complete or withdraw Manager-initiated pending leave/claims before exit.';
            }
            if(DB::table('attendance_sessions')->where('company_id',$company)
                ->where('workspace_id',$workspace)->where('proxy_status','pending')
                ->where(fn($q)=>$q->where('proxy_started_by',$memberId)
                    ->orWhere('proxy_ended_by',$memberId))->exists()) {
                $issues['proxy_verification']='Partner must verify/reject pending proxy attendance before Manager exit.';
            }
            if(DB::table('attendance_review_requests')->where('company_id',$company)
                ->where('workspace_id',$workspace)->where('initiated_by',$memberId)
                ->whereIn('status',['pending','manager_reviewed'])->exists()) {
                $issues['delegated_attendance']='Resolve Manager-initiated pending attendance corrections.';
            }
        }
        if($scope(DB::table('attendance_sessions'))->whereNull('ended_at')->exists()){
            $issues['open_attendance']='Close active attendance before staff exit.';
        }
        if($scope(DB::table('attendance_review_requests'))
            ->whereIn('status',['pending','manager_reviewed'])->exists()){
            $issues['attendance_review']='Complete outstanding staff attendance reviews.';
        }
        if($scope(DB::table('conveyance_claims'))
            ->whereIn('status',['pending','manager_reviewed','approved'])->exists()){
            $issues['reimbursements']='Resolve pending/approved unpaid staff reimbursement before exit.';
        }
        if($scope(DB::table('leave_requests'))->where(function($q)use($day){
            $q->whereIn('status',['pending','manager_reviewed'])
                ->orWhere(fn($sub)=>$sub->where('status','approved')
                    ->where('date_to','>=',$day));
        })->exists()){
            $issues['leave']='Resolve pending and future approved leave before exit.';
        }
        if(DB::table('payroll_runs')->where('company_id',$company)
            ->where('workspace_id',$workspace)->where('period_month',$month)->exists()){
            $issues['payroll']='Close out or reconcile current-month payroll before exit.';
        }
        if($member->role==='student' && DB::table('staff_compensation_changes')
            ->where('company_id',$company)->where('workspace_id',$workspace)
            ->where('membership_id',$memberId)->where('effective_month','>',$month)->exists()){
            $issues['future_salary']='Resolve future salary decisions before staff exit.';
        }
        if(DB::table('staff_transfer_requests')->where('company_id',$company)
            ->where('source_membership_id',$memberId)->where('status','pending')->exists()){
            $issues['pending_transfer']='Decide or reject the pending cross-Partner transfer first.';
        }
        return $issues;
    }

    /**
     * Transfer creates a new identity in the receiving Partner workspace;
     * historic source membership must never rejoin by the ordinary exit form.
     */
    public static function wasTransferred(object $actor, object $member): bool
    {
        return DB::table('staff_transfer_requests')
            ->where('company_id',$actor->company_id)
            ->where('source_membership_id',$member->id)
            ->where('source_workspace_id',$actor->workspace_id)
            ->where('status','approved')->exists();
    }
}
