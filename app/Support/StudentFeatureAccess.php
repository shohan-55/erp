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
        'notifications' => 'Notices & Notifications',
    ];

    public static function featureForRoute(?string $name): ?string
    {
        if (! $name) {
            return null;
        }
        if ($name === 'erp.attendance.report') {
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
        if (! array_key_exists($feature, self::FEATURES)) {
            return false;
        }
        if ($actor->role !== 'student') {
            return true; // Role restrictions on manager/partner still apply.
        }

        $record = DB::table('student_feature_controls')
            ->where('company_id', $actor->company_id)
            ->where('workspace_id', $actor->workspace_id)
            ->where('membership_id', $actor->id)
            ->where('feature_key', $feature)
            ->first();

        // Backwards compatible: previous active students retain their existing
        // default access until their Partner explicitly narrows it.
        return ! $record || (bool) $record->partner_enabled && (bool) $record->manager_enabled;
    }

    public static function enabledMap(object $actor): array
    {
        if ($actor->role !== 'student') {
            return array_fill_keys(array_keys(self::FEATURES), true);
        }

        $records = DB::table('student_feature_controls')
            ->where('company_id', $actor->company_id)
            ->where('workspace_id', $actor->workspace_id)
            ->where('membership_id', $actor->id)
            ->get()->keyBy('feature_key');

        $map = [];
        foreach (self::FEATURES as $key => $label) {
            $row = $records->get($key);
            $map[$key] = ! $row || ((bool) $row->partner_enabled && (bool) $row->manager_enabled);
        }
        return $map;
    }
}
