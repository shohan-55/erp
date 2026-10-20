<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

final class StudentFeatureAccess
{
    // Single source of truth for student navigation, dashboard and middleware.
    public const FEATURES = [
        'attendance' => 'Attendance & Check-in/out',
        'attendance_report' => 'Attendance Reports',
        'leave' => 'Leave Application',
        'conveyance' => 'Conveyance Claims',
        'payslip' => 'Finalized Payslips',
        'timesheets' => 'Audit Engagements & Timesheets',
        'notifications' => 'Notices & Notifications',
    ];

    public static function featureForRoute(?string $name): ?string
    {
        if (! $name) {
            return null;
        }
        if (in_array($name, ['erp.attendance.report', 'erp.attendance.reconcile'], true)) {
            return 'attendance_report';
        }
        if (str_starts_with($name, 'erp.attendance')) {
            return 'attendance';
        }
        if (str_starts_with($name, 'erp.leaves')) {
            return 'leave';
        }
        if (str_starts_with($name, 'erp.claims')) {
            return 'conveyance';
        }
        if (str_starts_with($name, 'erp.engagements') || str_starts_with($name, 'erp.timesheets')) {
            return 'timesheets';
        }
        if (str_starts_with($name, 'erp.payroll')) {
            return 'payslip';
        }
        if (str_starts_with($name, 'erp.notifications')) {
            return 'notifications';
        }
        return null;
    }

    public static function enabled(object $actor, string $feature): bool
    {
        return array_key_exists($feature, self::FEATURES)
            && ErpFeatures::enabled($actor, $feature);
    }

    public static function enabledMap(object $actor): array
    {
        $resolved = ErpFeatures::enabledMap($actor);
        return array_intersect_key($resolved, self::FEATURES);
    }
}
