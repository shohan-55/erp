<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PolicyProposal extends Model
{
    protected $fillable = [
        'firm_id',
        'proposed_by',
        'title',
        'description',
        'proposed_office_start',
        'proposed_office_end',
        'proposed_grace_minutes',
        'proposed_conveyance_limit',
        'effective_date',
        'required_votes',
        'approvals',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'proposed_grace_minutes'    => 'integer',
            'proposed_conveyance_limit' => 'decimal:2',
            'effective_date'            => 'date',
            'required_votes'            => 'integer',
            'approvals'                 => 'array',
        ];
    }

    public function firm(): BelongsTo
    {
        return $this->belongsTo(Firm::class);
    }

    public function proposer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proposed_by');
    }
}
