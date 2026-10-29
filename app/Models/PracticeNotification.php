<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PracticeNotification extends Model
{
    protected $fillable = [
        'firm_id',
        'user_id',
        'sender_id',
        'sender_name',
        'target_role',
        'recipient_ids',
        'show_as_popup',
        'icon',
        'title',
        'message',
        'is_read',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'is_read'       => 'boolean',
            'show_as_popup' => 'boolean',
            'recipient_ids' => 'array',
            'read_at'       => 'datetime',
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

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
