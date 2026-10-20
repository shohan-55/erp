<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Typed ERP module registry and DENY-OVER-ALLOW feature gates.
 * Default enabled for migrated tenants; disabling ANY parent layer
 * restricts all descendants, including backend write/download routes.
 * Never gates login, security, approvals governance, SaaS billing,
 * feature settings themselves or historical data integrity.
 */
final class ErpFeatures
{
    public const MODULES = [
        'attendance' => 'Attendance & Check-in/out',
        'attendance_report' => 'Attendance Reports',
        'leave' => 'Leave Requests & Approvals',
        'conveyance' => 'Conveyance Claims & Approvals',
        'payslip' => 'Payroll & Payslips',
        'timesheets' => 'Timesheets',
        'engagements' => 'Client Engagements',
        'notifications' => 'Notifications & Notices',
        'documents' => 'Document Center',
        'staff' => 'Staff Records & Staff Lifecycle',
        'organization' => 'Organization Structure',
        'calendar' => 'Workforce Calendar',
        'reports' => 'Practice Reports',
    ];

    public const BASE_ROLES = ['partner', 'manager', 'student'];

    /**
     * Routes with operational data/actions only. Settings, governance,
     * billing, security, auth, audit, and subscription remain accessible
     * so operators can recover from toggles without bypass URLs.
     */
    public static function forRoute(?string $name): ?string
    {
        if (!$name) return null;
        if (in_array($name, ['erp.attendance.report', 'erp.attendance.reconcile'], true)) {
            return 'attendance_report';
        }
        if (str_starts_with($name, 'erp.attendance.')) return 'attendance';
        if ($name === 'erp.attendance') return 'attendance';
        if (str_starts_with($name, 'erp.leaves') || str_starts_with($name, 'erp.official.leave.')) return 'leave';
        if (str_starts_with($name, 'erp.claims')) return 'conveyance';
        if (str_starts_with($name, 'erp.payroll.')) return 'payslip';
        if ($name === 'erp.payroll') return 'payslip';
        if (str_starts_with($name, 'erp.engagements')) return 'engagements';
        if (str_starts_with($name, 'erp.timesheets')) return 'timesheets';
        if (str_starts_with($name, 'erp.notifications') || str_starts_with($name, 'erp.notices')) return 'notifications';
        if (str_starts_with($name, 'erp.documents')) return 'documents';
        if (str_starts_with($name, 'erp.staff.') || $name === 'erp.staff') return 'staff';
        if (str_starts_with($name, 'erp.organization.')) return 'organization';
        if (str_starts_with($name, 'erp.calendar.')) return 'calendar';
        if (str_starts_with($name, 'erp.reports.practice')) return 'reports';
        return null;
    }

    public static function companyKey(): string { return 'company'; }
    public static function workspaceKey(int $workspaceId): string { return 'workspace:'.$workspaceId; }
    public static function roleKey(int $workspaceId, string $role): string
    {
        return 'role:'.$workspaceId.':'.$role;
    }
    public static function userKey(int $membershipId): string { return 'user:'.$membershipId; }

    /** A single bounded lookup per screen and no cached permissions on write routes. */
    public static function enabledMap(object $actor): array
    {
        $keys = [
            self::companyKey(),
            self::workspaceKey((int) $actor->workspace_id),
            self::roleKey((int) $actor->workspace_id, (string) $actor->role),
            self::userKey((int) $actor->id),
        ];
        $rows = DB::table('erp_feature_controls')
            ->where('company_id', $actor->company_id)
            ->whereIn('scope_key', $keys)->get();

        $timezone = DB::table('companies')->where('id', $actor->company_id)->value('timezone')
            ?: 'Asia/Dhaka';
        $ratified = FeatureGovernance::companyDecisions((int) $actor->company_id, $timezone);
        $map = array_fill_keys(array_keys(self::MODULES), true);
        foreach ($rows as $row) {
            // After its effective date, a ratified immutable company version
            // supersedes a legacy immediate company toggle for this feature.
            // Workspace, role and user DENY rules still win as before.
            if ($row->scope_key === self::companyKey()
                && isset($ratified[$row->feature_key])) {
                continue;
            }
            if (array_key_exists($row->feature_key, $map) && !(bool) $row->enabled) {
                $map[$row->feature_key] = false;
            }
        }
        foreach ($ratified as $key => $version) {
            if ($version->operation === 'disable') $map[$key] = false;
            // enable / reset merely remove the company restriction.
        }

        // Historical student Partner/Manager controls remain restrictive.
        if ($actor->role === 'student') {
            $student = DB::table('student_feature_controls')
                ->where('company_id', $actor->company_id)
                ->where('workspace_id', $actor->workspace_id)
                ->where('membership_id', $actor->id)
                ->get();
            foreach ($student as $row) {
                if (array_key_exists($row->feature_key, $map)
                    && (!(bool) $row->partner_enabled || !(bool) $row->manager_enabled)) {
                    $map[$row->feature_key] = false;
                    // Legacy student Timesheet control historically covered
                    // BOTH client engagements and timesheets.
                    if ($row->feature_key === 'timesheets') $map['engagements'] = false;
                }
            }
        }
        // Reporting is intentionally independent from attendance check-in:
        // existing students may view historic reports while punch access is
        // disabled. Timesheets still require engagement visibility.
        if (!$map['engagements']) $map['timesheets'] = false;

        return $map;
    }

    public static function enabled(object $actor, string $feature): bool
    {
        return array_key_exists($feature, self::MODULES)
            && (self::enabledMap($actor)[$feature] ?? false);
    }
}
