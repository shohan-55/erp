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
        'student_grids',
        'manager_permissions',
    ];

    protected $casts = [
        'grace_minutes'            => 'integer',
        'manager_conveyance_limit' => 'decimal:2',
        'conveyance_late_days'     => 'integer',
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

    public function firm(): BelongsTo
    {
        return $this->belongsTo(Firm::class);
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'partner_id');
    }
}
