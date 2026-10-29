<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    protected $fillable = [
        'firm_id',
        'user_id',
        'date',
        'work_mode',
        'client_site',
        'in_time',
        'in_lat',
        'in_lng',
        'in_location_name',
        'out_time',
        'out_lat',
        'out_lng',
        'out_location_name',
        'distance_km',
        'location_mismatch',
        'status',
        'session_state',
        'movement_logs',
        'correction_requested',
        'requested_out_time',
        'correction_reason',
        'correction_status',
        'is_proxy',
        'proxy_by',
        'edited_by',
        'edit_remarks',
    ];

    protected $casts = [
        'date'                 => 'date',
        'in_time'              => 'datetime',
        'out_time'             => 'datetime',
        'is_proxy'             => 'boolean',
        'location_mismatch'    => 'boolean',
        'correction_requested' => 'boolean',
        'distance_km'          => 'decimal:2',
        'movement_logs'        => 'array',
        'in_lat'               => 'decimal:7',
        'in_lng'               => 'decimal:7',
        'out_lat'              => 'decimal:7',
        'out_lng'              => 'decimal:7',
    ];

    public function firm(): BelongsTo
    {
        return $this->belongsTo(Firm::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function proxyUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proxy_by');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'edited_by');
    }
}
