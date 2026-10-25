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
                $make('Staff & Articleship', 'People records, promotions and transfers', 'erp.staff', 'briefcase-business'),
                $make('Student Permissions', 'Control student grids and access', 'erp.permissions.index', 'shield-check'),
                $make('Office Policies', 'Manage workspace attendance rules', 'erp.policies', 'settings-2'),
                $make('Smart HR Policy Center', 'Policy versions, approvals and manager permissions', 'erp.hr_policies.index', 'sliders-horizontal'),
                $make('Annual Holiday Planner', 'Review annual holidays and company watermark', 'erp.annual_holidays.index', 'calendar-range'),
                $make('Company Brand Studio', 'Official letterhead, logo, signatory and company colors', 'erp.brand.index', 'palette'),
                $make('Official Document Templates', 'Approve editable template versions and signatory text', 'erp.official_templates.index', 'file-pen-line'),
                $make('Official Certificates', 'Publish employment and locked-payroll salary letters', 'erp.staff_certificates.index', 'file-badge-2'),
                $make('Payroll Control', 'Review your Partner payroll only', 'erp.payroll', 'wallet-cards', null, $queue['payroll']),
                $make('Audit Engagements', 'Partner clients, audit teams and budgets', 'erp.engagements', 'briefcase-business'),
                $make('Timesheet Approvals', 'Approve engagement working hours', 'erp.timesheets', 'clock-3'),
                $make('Attendance Report', 'Review workspace attendance', 'erp.attendance.report', 'calendar-check-2'),
                $make('Practice Reports', 'Consolidated private monthly reporting', 'erp.reports.practice', 'chart-no-axes-combined'),
                $make('Publish Notices', 'Send messages to your own practice', 'erp.notices', 'megaphone'),
                $make('Document Center', 'Secure Partner and employee files', 'erp.documents', 'folder-lock'),
                $make('Notifications', 'Read your private messages', 'erp.notifications', 'bell'),
                $make('SaaS Subscription', 'Firm-level billing status and invoices', 'erp.subscription', 'credit-card'),
            ];
        }
        if ($actor->role === 'manager') {
            $links = [
                $make('Review Team Leave', 'Only your assigned students', 'erp.leaves', 'calendar-days', null, $queue['leave']),
                $make('Review Team Claims', 'Only your assigned students', 'erp.claims', 'receipt', null, $queue['claims']),
                $make('Team Attendance', 'View assigned student attendance', 'erp.attendance.report', 'calendar-check-2'),
                $make('Smart HR Policy Center', 'Propose future schedules and review policies', 'erp.hr_policies.index', 'sliders-horizontal'),
                $make('Annual Holiday Planner', 'Prepare annual holiday plans for Partner approval', 'erp.annual_holidays.index', 'calendar-range'),
                $make('Company Brand Studio', 'View authorized firm branding and letterhead settings', 'erp.brand.index', 'palette'),
                $make('Document Templates', 'Read active official letter wording', 'erp.official_templates.index', 'file-pen-line'),
                $make('Audit Engagements', 'Your team client assignments', 'erp.engagements', 'briefcase-business'),
                $make('Team Timesheets', 'Review assigned students and own work hours', 'erp.timesheets', 'clock-3'),
                $make('Notifications', 'Follow up on team approvals', 'erp.notifications', 'bell'),
                $make('Document Center', 'Own and assigned-team secure documents', 'erp.documents', 'folder-lock'),
                $make('My Staff Records', 'Private employment and document records', 'erp.staff.my_records', 'file-user'),
            ];
            if ((bool) $actor->can_manage_student_features) {
                $links[] = $make('Student Permissions', 'Delegated assigned-team controls', 'erp.permissions.index', 'shield-check');
            }
            return $links;
        }

        $allowed = StudentFeatureAccess::enabledMap($actor);
        $links = [
            $make('My Attendance', 'Check in/out and view history', 'erp.attendance', 'calendar-check-2', 'attendance'),
            $make('Workforce Calendar', 'Review your private workdays and approved holidays', 'erp.calendar.index', 'calendar-days'),
            $make('Apply for Leave', 'Submit or track leave requests', 'erp.leaves', 'calendar-days', 'leave'),
            $make('Conveyance Claim', 'Submit expense claims', 'erp.claims', 'receipt', 'conveyance'),
            $make('Attendance Report', 'Review recorded work sessions', 'erp.attendance.report', 'clipboard-list', 'attendance_report'),
            $make('My Payslips', 'Only finalized and locked records', 'erp.payroll', 'wallet-cards', 'payslip'),
            $make('My Engagements', 'See assigned audit clients and assignments', 'erp.engagements', 'briefcase-business', 'timesheets'),
            $make('My Timesheets', 'Submit audit work hours for review', 'erp.timesheets', 'clock-3', 'timesheets'),
            $make('Notifications', 'Read your own notices', 'erp.notifications', 'bell', 'notifications'),
            $make('My Shared Documents', 'Access private practice documents shared with you', 'erp.documents', 'folder-lock'),
            $make('My Official Certificates', 'Download published Partner-issued certificates', 'erp.staff_certificates.index', 'file-badge-2'),
            $make('Account Security', 'Manage account protection', 'erp.security', 'shield-check'),
            $make('My Staff Records', 'My articleship, employment and private documents', 'erp.staff.my_records', 'file-user'),
        ];
        return array_values(array_filter($links, static fn (array $item) =>
            $item['feature'] === null || ($allowed[$item['feature']] ?? false)));
    }
}
