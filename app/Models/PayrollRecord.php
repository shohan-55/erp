<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollRecord extends Model
{
    protected $fillable = [
        'firm_id',
        'user_id',
        'partner_unit',
        'period',
        'base_allowance',
        'approved_conveyance',
        'attendance_adjustment',
        'holiday_absent_days',
        'holiday_absent_deduction',
        'net_payable',
        'present_days',
        'late_days',
        'leave_days',
        'payment_method',
        'voucher_no',
        'status',
        'reviewed_by',
        'approved_by',
        'paid_at',
        'locked_at',
        'policy_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'base_allowance'           => 'decimal:2',
            'approved_conveyance'      => 'decimal:2',
            'attendance_adjustment'    => 'decimal:2',
            'holiday_absent_days'      => 'integer',
            'holiday_absent_deduction' => 'decimal:2',
            'net_payable'              => 'decimal:2',
            'present_days'             => 'integer',
            'late_days'                => 'integer',
            'leave_days'               => 'integer',
            'paid_at'                  => 'datetime',
            'locked_at'                => 'datetime',
            'policy_snapshot'          => 'array',
        ];
    }

    public function firm(): BelongsTo
    {
        return $this->belongsTo(Firm::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isLocked(): bool
    {
        return $this->status === 'locked' || $this->locked_at !== null;
    }
}
