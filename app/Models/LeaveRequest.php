<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveRequest extends Model
{
    protected $fillable = [
        'firm_id',
        'user_id',
        'engaged_manager_id',
        'leave_type',
        'start_date',
        'end_date',
        'requested_days',
        'approved_days',
        'approved_dates_summary',
        'reason',
        'status',
        'reviewed_by',
        'partner_id',
        'manager_remarks',
        'partner_remarks',
    ];

    protected $casts = [
        'start_date'     => 'date',
        'end_date'       => 'date',
        'requested_days' => 'integer',
        'approved_days'  => 'integer',
    ];

    public function firm(): BelongsTo
    {
        return $this->belongsTo(Firm::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function engagedManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'engaged_manager_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'partner_id');
    }
}
