<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Read-only, tightly scoped operational pulse. Always counts live records,
 * never asserts attendance absence or creates financial/leave transitions.
 */
final class WorkspacePulse
{
    public static function forActor(object $actor, array $queues, ?object $activeAttendance): array
    {
        $role = $actor->role;
        $scope = fn (string $table) => DB::table($table.' as r')
            ->where('r.company_id', $actor->company_id)
            ->where('r.workspace_id', $actor->workspace_id);
        $mine = fn (string $table) => $scope($table)->where('r.membership_id', $actor->id);
        $team = fn (string $table) => $scope($table)
            ->join('memberships as m', 'm.id', '=', 'r.membership_id')
            ->where('m.company_id', $actor->company_id)
            ->where('m.workspace_id', $actor->workspace_id)
            ->where('m.role', 'student')
            ->where('m.active', true)
            ->when($role === 'manager', fn ($q) => $q->where('m.manager_membership_id', $actor->id));

        $items = [];
        $add = static function (string $title, string $description, string $route, string $icon,
            int $count = 0, string $tone = 'neutral') use (&$items): void {
            $items[] = compact('title','description','route','icon','count','tone');
        };

        $features = $role === 'student' ? StudentFeatureAccess::enabledMap($actor) : [];
        $allowed = static fn (string $key) => $role !== 'student' || ($features[$key] ?? false);

        if ($role === 'student') {
            if ($allowed('attendance')) {
                $add($activeAttendance ? 'Session in progress' : 'My attendance',
                    $activeAttendance ? 'Your check-out is still pending.' : 'Use the confirmed check-in when your shift begins.',
                    'erp.attendance','clock-3', $activeAttendance ? 1 : 0,
                    $activeAttendance ? 'live' : 'neutral');
            }
            if ($allowed('leave')) {
                $n = $mine('leave_requests')->whereIn('r.status',['pending','manager_reviewed'])->count();
                $add('My leave requests','Review current application and approval progress.',
                    'erp.leaves','calendar-days',$n,$n ? 'attention':'neutral');
            }
            if ($allowed('conveyance')) {
                $n = $mine('conveyance_claims')->whereIn('r.status',['pending','manager_reviewed'])->count();
                $add('My conveyance claims','Receipts, decision status and reimbursement tracking.',
                    'erp.claims','receipt',$n,$n ? 'attention':'neutral');
            }
            if ($allowed('attendance')) {
                $n=$mine('attendance_review_requests')
                    ->whereIn('r.status',['pending','manager_reviewed'])->count();
                $add('Attendance corrections','Missing punch and client-duty requests awaiting review.',
                    'erp.attendance.reviews','clipboard-check',$n,$n ? 'attention':'neutral');
            }
        } else {
            $add('Leave approvals','Requests requiring your authorized decision.',
                'erp.leaves','calendar-days', (int)$queues['leave'],
                $queues['leave'] ? 'attention':'neutral');
            $add('Conveyance reviews','Review evidence and approved reimbursement amounts.',
                'erp.claims','receipt',(int)$queues['claims'],
                $queues['claims'] ? 'attention':'neutral');

            $attendance = $team('attendance_review_requests')
                ->whereIn('r.status',$role === 'manager' ? ['pending'] : ['pending','manager_reviewed'])->count();
            $add('Attendance corrections','Validate missed punches and approved client duty.',
                'erp.attendance.reviews','clipboard-check',$attendance,
                $attendance ? 'attention':'neutral');

            if ($role === 'partner') {
                $hr= $scope('workspace_hr_policy_revisions')->where('r.status','pending')->count();
                $annual=$scope('annual_holiday_plans')->where('r.status','pending')->count();
                $add('HR policy approvals','Manager-proposed schedules and sensitive policy rules.',
                    'erp.hr_policies.index','shield-check',$hr,$hr?'attention':'neutral');
                $add('Annual holiday approvals','Review future calendar plans before publication.',
                    'erp.annual_holidays.index','calendar-range',$annual,$annual?'attention':'neutral');
                $add('Payroll workflow','Review, approve, record payment and lock each period.',
                    'erp.payroll','wallet-cards',(int)$queues['payroll'],
                    $queues['payroll'] ? 'attention':'neutral');
            } else {
                $add('My team calendar','Plan office holidays and special audit duties.',
                    'erp.annual_holidays.index','calendar-range');
            }
        }
        if ($allowed('notifications')) {
            $unread = $scope('tenant_notifications')
                ->where('r.recipient_membership_id',$actor->id)
                ->whereNull('r.read_at')->count();
            $add('Unread updates','Private notices and request decisions.',
                'erp.notifications','bell',$unread,$unread?'attention':'neutral');
        }

        return [
            'items' => $items,
            'attention_count' => collect($items)
                ->filter(fn ($item)=>$item['tone']==='attention')
                ->sum('count'),
        ];
    }
}
