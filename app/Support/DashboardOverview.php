<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

final class DashboardOverview
{
    /**
     * Private operational queue counts scoped to the CURRENT Partner unit.
     * A manager sees only assigned students; students never receive other
     * students' review counts. No salary amount is selected here.
     */
    public static function queues(object $actor): array
    {
        if (! in_array($actor->role, ['partner', 'manager'], true)) {
            return ['leave' => 0, 'claims' => 0, 'payroll' => 0];
        }

        $scoped = fn (string $table) => DB::table($table.' as r')
            ->join('memberships as m', 'm.id', '=', 'r.membership_id')
            ->where('r.company_id', $actor->company_id)
            ->where('r.workspace_id', $actor->workspace_id)
            ->where('m.company_id', $actor->company_id)
            ->where('m.workspace_id', $actor->workspace_id)
            ->where('m.role', 'student')
            ->where('m.active', true)
            ->when($actor->role === 'manager', fn ($q) =>
                $q->where('m.manager_membership_id', $actor->id));

        $statuses = $actor->role === 'manager' ? ['pending'] : ['pending', 'manager_reviewed'];

        return [
            'leave' => $scoped('leave_requests')->whereIn('r.status', $statuses)->count(),
            'claims' => $scoped('conveyance_claims')->whereIn('r.status', $statuses)
                ->whereNull('r.reserved_payroll_run_id')->whereNull('r.paid_payroll_run_id')->count(),
            // Financial-run metadata is partner-only, never sent to managers.
            'payroll' => $actor->role === 'partner'
                ? DB::table('payroll_runs')->where('company_id', $actor->company_id)
                    ->where('workspace_id', $actor->workspace_id)
                    ->whereIn('status', ['draft', 'reviewed', 'approved'])->count()
                : 0,
        ];
    }

    /**
     * These are ROUTES that already exist and are authorized for each role.
     * Student visibility reuses the Phase 03 server-enforced permission map.
     * A link never confers a new permission.
     */
    public static function actions(object $actor, array $queue): array
    {
        $make = fn (string $title, string $description, string $route, string $icon, ?string $feature = null, ?int $count = null) => [
            'title' => $title,
            'description' => $description,
            'route' => $route,
            'icon' => $icon,
            'feature' => $feature,
            'count' => $count,
        ];

        if ($actor->role === 'partner') {
            return [
                $make('Review Leave', 'Approve outstanding student leave', 'erp.leaves', 'calendar-days', null, $queue['leave']),
                $make('Review Conveyance', 'Verify submitted expense claims', 'erp.claims', 'receipt', null, $queue['claims']),
                $make('Team & Accounts', 'Assign managers and student accounts', 'erp.team', 'users-round'),
                $make('Student Permissions', 'Control student grids and access', 'erp.permissions.index', 'shield-check'),
                $make('Office Policies', 'Manage workspace attendance rules', 'erp.policies', 'settings-2'),
                $make('Payroll Control', 'Review your Partner payroll only', 'erp.payroll', 'wallet-cards', null, $queue['payroll']),
                $make('Attendance Report', 'Review workspace attendance', 'erp.attendance.report', 'calendar-check-2'),
                $make('Notifications', 'Read your private messages', 'erp.notifications', 'bell'),
            ];
        }
        if ($actor->role === 'manager') {
            $links = [
                $make('Review Team Leave', 'Only your assigned students', 'erp.leaves', 'calendar-days', null, $queue['leave']),
                $make('Review Team Claims', 'Only your assigned students', 'erp.claims', 'receipt', null, $queue['claims']),
                $make('Team Attendance', 'View assigned student attendance', 'erp.attendance.report', 'calendar-check-2'),
                $make('Notifications', 'Follow up on team approvals', 'erp.notifications', 'bell'),
            ];
            if ((bool) $actor->can_manage_student_features) {
                $links[] = $make('Student Permissions', 'Delegated assigned-team controls', 'erp.permissions.index', 'shield-check');
            }
            return $links;
        }

        $allowed = StudentFeatureAccess::enabledMap($actor);
        $links = [
            $make('My Attendance', 'Check in/out and view history', 'erp.attendance', 'calendar-check-2', 'attendance'),
            $make('Apply for Leave', 'Submit or track leave requests', 'erp.leaves', 'calendar-days', 'leave'),
            $make('Conveyance Claim', 'Submit expense claims', 'erp.claims', 'receipt', 'conveyance'),
            $make('Attendance Report', 'Review recorded work sessions', 'erp.attendance.report', 'clipboard-list', 'attendance_report'),
            $make('My Payslips', 'Only finalized and locked records', 'erp.payroll', 'wallet-cards', 'payslip'),
            $make('Notifications', 'Read your own notices', 'erp.notifications', 'bell', 'notifications'),
            $make('Account Security', 'Manage account protection', 'erp.security', 'shield-check'),
        ];
        return array_values(array_filter($links, static fn (array $item) =>
            $item['feature'] === null || ($allowed[$item['feature']] ?? false)));
    }
}
