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
        'custom_domain',
        'custom_subdomain',
    ];

    /**
     * Resolve a firm by host (custom domain or custom subdomain).
     */
    public static function findByHost(string $host): ?self
    {
        $cleanHost = strtolower(trim(preg_replace('/:\d+$/', '', $host)));

        // Direct custom domain match
        $firm = static::where('custom_domain', $cleanHost)->first();
        if ($firm) {
            return $firm;
        }

        // Custom subdomain match (e.g. {sub}.dhakawala.com)
        if (str_ends_with($cleanHost, '.dhakawala.com')) {
            $sub = str_replace('.dhakawala.com', '', $cleanHost);
            if ($sub !== 'erp' && $sub !== 'www' && ! empty($sub)) {
                return static::where('custom_subdomain', $sub)
                    ->orWhere('slug', $sub)
                    ->first();
            }
        }

        return null;
    }

    /**
     * Get computed full workspace URL.
     */
    public function getWorkspaceUrlAttribute(): string
    {
        if (! empty($this->custom_domain)) {
            return 'https://' . $this->custom_domain;
        }
        if (! empty($this->custom_subdomain)) {
            return 'https://' . $this->custom_subdomain . '.dhakawala.com';
        }
        return url('/c/' . $this->slug);
    }

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
