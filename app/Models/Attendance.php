<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'status',
        'is_proxy',
        'proxy_by',
        'edited_by',
        'edit_remarks',
    ];

    protected $casts = [
        'date'     => 'date',
        'in_time'  => 'datetime',
        'out_time' => 'datetime',
        'is_proxy' => 'boolean',
        'in_lat'   => 'decimal:7',
        'in_lng'   => 'decimal:7',
        'out_lat'  => 'decimal:7',
        'out_lng'  => 'decimal:7',
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
