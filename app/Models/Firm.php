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
        'organization_type',
        'email',
        'status',
        'workspaces',
        'seats',
        'plan',
        'region',
        'initials',
        'custom_domain',
        'custom_subdomain',
        'shared_attendance_view',
        'is_suspended',
        'subscription_expires_at',
        'subscription_fee',
        'billing_cycle',
        'payment_status',
        'enabled_modules',
    ];

    protected $casts = [
        'shared_attendance_view'  => 'boolean',
        'is_suspended'            => 'boolean',
        'subscription_expires_at' => 'date',
        'subscription_fee'        => 'decimal:2',
        'enabled_modules'         => 'array',
    ];

    public static function findByHost(string $host): ?self
    {
        $cleanHost = strtolower(trim(preg_replace('/:\d+$/', '', $host)));

        $firm = static::where('custom_domain', $cleanHost)->first();
        if ($firm) {
            return $firm;
        }

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

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function conveyanceClaims(): HasMany
    {
        return $this->hasMany(ConveyanceClaim::class);
    }

    public function holidays(): HasMany
    {
        return $this->hasMany(PracticeHoliday::class);
    }

    public static function findBySlug(string $slug): ?self
    {
        return static::where('slug', strtolower(trim($slug)))->first();
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
