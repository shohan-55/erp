<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Aggregate-only review signals. They do not establish absences, wages due,
 * automatic penalties or legal compliance; authorized humans review them.
 */
final class PayrollReview
{
    public static function overview(object $company,object $workspace,string $month): array
    {
        $end=CarbonImmutable::parse($month)->endOfMonth()->toDateString();
        $scope=fn($q)=>$q->where('company_id',$company->id)
            ->where('workspace_id',$workspace->id);
        $verifiedOrSelf = fn ($q) => $q->whereNull('proxy_status')
            ->orWhere('proxy_status','verified');
        return [
            // Proxy punches are not attendance evidence until Partner verification.
            'recorded_sessions'=>$scope(DB::table('attendance_sessions'))
                ->whereBetween('work_date',[$month,$end])
                ->where($verifiedOrSelf)->count(),
            'late_sessions'=>$scope(DB::table('attendance_sessions'))
                ->whereBetween('work_date',[$month,$end])
                ->where('late',true)->where($verifiedOrSelf)->count(),
            'open_sessions'=>$scope(DB::table('attendance_sessions'))
                ->whereBetween('work_date',[$month,$end])
                ->whereNull('ended_at')->where($verifiedOrSelf)->count(),
            'pending_proxy_sessions'=>$scope(DB::table('attendance_sessions'))
                ->whereBetween('work_date',[$month,$end])
                ->where('proxy_status','pending')->count(),
            'rejected_proxy_sessions'=>$scope(DB::table('attendance_sessions'))
                ->whereBetween('work_date',[$month,$end])
                ->where('proxy_status','rejected')->count(),
            'approved_leaves'=>$scope(DB::table('leave_requests'))
                ->where('status','approved')->where('date_from','<=',$end)
                ->where('date_to','>=',$month)->count(),
            'pending_leave_requests'=>$scope(DB::table('leave_requests'))
                ->whereIn('status',['pending','manager_reviewed'])
                ->where('date_from','<=',$end)->where('date_to','>=',$month)->count(),
            'pending_attendance_reviews'=>$scope(DB::table('attendance_review_requests'))
                ->whereBetween('work_date',[$month,$end])
                ->whereIn('status',['pending','manager_reviewed'])->count(),
            'approved_unpaid_claims'=>$scope(DB::table('conveyance_claims'))
                ->whereBetween('journey_date',[$month,$end])
                ->where('status','approved')->whereNull('paid_payroll_run_id')->count(),
        ];
    }
}
