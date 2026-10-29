<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConveyanceClaim extends Model
{
    protected $fillable = [
        'firm_id',
        'user_id',
        'claim_date',
        'amount',
        'route',
        'purpose',
        'status',
        'reviewed_by',
        'client_name',
        'original_amount',
        'adjustment_remark',
        'is_late',
        'partner_id',
        'manager_remarks',
        'partner_remarks',
        'attachment_name',
        'transport_items',
        'other_expense_description',
        'other_expense_amount',
    ];

    protected $casts = [
        'claim_date'           => 'date',
        'amount'               => 'decimal:2',
        'original_amount'      => 'decimal:2',
        'other_expense_amount' => 'decimal:2',
        'is_late'              => 'boolean',
        'transport_items'      => 'array',
    ];

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

    public function partner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'partner_id');
    }
}
