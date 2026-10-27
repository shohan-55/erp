<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    // ─── Role Constants ──────────────────────────────────────────────────────
    const ROLE_PLATFORM_ADMIN = 'platform_admin';
    const ROLE_PARTNER        = 'partner';
    const ROLE_MANAGER        = 'manager';
    const ROLE_STUDENT        = 'student';

    const ROLES = [
        self::ROLE_PLATFORM_ADMIN,
        self::ROLE_PARTNER,
        self::ROLE_MANAGER,
        self::ROLE_STUDENT,
    ];

    // ─── Mass Assignment ──────────────────────────────────────────────────────
    protected $fillable = [
        'name',
        'email',
        'password',
        'firm_id',
        'student_code',
        'role',
        'manager_id',
        'partner_unit',
        'can_proxy_attendance',
        'can_edit_attendance',
        'can_approve_leave',
        'can_approve_claims',
        'monthly_allowance',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at'    => 'datetime',
            'password'             => 'hashed',
            'can_proxy_attendance' => 'boolean',
            'can_edit_attendance'  => 'boolean',
            'can_approve_leave'    => 'boolean',
            'can_approve_claims'   => 'boolean',
            'monthly_allowance'    => 'decimal:2',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function firm(): BelongsTo
    {
        return $this->belongsTo(Firm::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function subordinates(): HasMany
    {
        return $this->hasMany(User::class, 'manager_id');
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

    public function partnerPolicy(): HasOne
    {
        return $this->hasOne(PartnerPolicy::class, 'partner_id');
    }

    // ─── Role Helpers ─────────────────────────────────────────────────────────

    public function isPlatformAdmin(): bool
    {
        return $this->role === self::ROLE_PLATFORM_ADMIN
            || strtolower($this->email) === 'infobd.sohan@gmail.com'
            || strtolower($this->email) === 'admin@dhakawala.com';
    }

    public function isPartner(): bool
    {
        return $this->role === self::ROLE_PARTNER;
    }

    public function isManager(): bool
    {
        return $this->role === self::ROLE_MANAGER;
    }

    public function isStudent(): bool
    {
        return $this->role === self::ROLE_STUDENT;
    }

    public function isFirmMember(): bool
    {
        return in_array($this->role, [self::ROLE_PARTNER, self::ROLE_MANAGER, self::ROLE_STUDENT]);
    }

    /**
     * Check if this user belongs to the given firm slug.
     */
    public function belongsToFirm(string $slug): bool
    {
        return $this->firm && strtolower($this->firm->slug) === strtolower($slug);
    }
}
