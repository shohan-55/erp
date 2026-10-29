<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnerPolicy extends Model
{
    protected $fillable = [
        'firm_id',
        'partner_id',
        'partner_unit',
        'office_start',
        'office_end',
        'grace_minutes',
        'manager_conveyance_limit',
        'conveyance_late_days',
        'late_penalty_enabled',
        'late_days_per_deduction',
        'deduction_days_count',
        'leave_quotas',
        'student_grids',
        'manager_permissions',
    ];

    protected $casts = [
        'grace_minutes'            => 'integer',
        'manager_conveyance_limit' => 'decimal:2',
        'conveyance_late_days'     => 'integer',
        'late_penalty_enabled'     => 'boolean',
        'late_days_per_deduction'  => 'integer',
        'deduction_days_count'     => 'decimal:2',
        'leave_quotas'             => 'array',
        'student_grids'            => 'array',
        'manager_permissions'      => 'array',
    ];

    public static function defaultStudentGrids(): array
    {
        return [
            'attendance_clock'   => true,
            'live_gps_status'    => true,
            'leave_balance'      => true,
            'conveyance_tracker' => true,
            'payroll_allowance'  => true,
            'client_site_duty'   => true,
            'firm_notices'       => true,
        ];
    }

    public static function defaultManagerPermissions(): array
    {
        return [
            'proxy_attendance'    => true,
            'edit_attendance'     => true,
            'review_leave'        => true,
            'review_claims'       => true,
            'view_team_reports'   => true,
        ];
    }

    public static function defaultLeaveQuotas(): array
    {
        return [
            ['key' => 'annual', 'label' => 'Annual / Casual Leave', 'days' => 14],
            ['key' => 'sick', 'label' => 'Medical / Sick Leave', 'days' => 14],
            ['key' => 'emergency', 'label' => 'Emergency Family Leave', 'days' => 7],
            ['key' => 'icab_exam', 'label' => 'ICAB Exam Preparation', 'days' => 30],
            ['key' => 'icab_study', 'label' => 'ICAB Study Leave', 'days' => 30],
        ];
    }

    public function firm(): BelongsTo
    {
        return $this->belongsTo(Firm::class);
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'partner_id');
    }
}
