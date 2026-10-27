<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Firm extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'kind',
        'email',
        'status',
        'workspaces',
        'seats',
        'plan',
        'region',
        'initials',
    ];

    /**
     * Get all users belonging to this firm.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get all attendance records for this firm.
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    /**
     * Get all leave requests for this firm.
     */
    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    /**
     * Get all conveyance claims for this firm.
     */
    public function conveyanceClaims(): HasMany
    {
        return $this->hasMany(ConveyanceClaim::class);
    }

    /**
     * Find a firm by its slug (case-insensitive).
     */
    public static function findBySlug(string $slug): ?self
    {
        return static::where('slug', strtolower(trim($slug)))->first();
    }

    /**
     * Scope to only active firms.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
