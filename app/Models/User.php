<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

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
        'phone',
        'avatar_url',
        'password',
        'firm_id',
        'student_code',
        'role',
        'designation',
        'manager_id',
        'partner_unit',
        'can_proxy_attendance',
        'can_edit_attendance',
        'can_approve_leave',
        'can_approve_claims',
        'can_manage_team',
        'can_manage_policies',
        'can_manage_payroll',
        'can_final_approve_leave',
        'can_final_approve_claims',
        'monthly_allowance',
        'payment_account_mode',
        'payment_account_no',
        'articleship_start_date',
        'articleship_end_date',
        'articleship_duration_years',
        'total_leave_cap',
        'leave_allocations',
        'promotion_history',
        'invitation_token',
        'invitation_sent_at',
        'email_verified_at',
        'is_active',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
    ];

    protected $hidden = [
        'two_factor_secret',
        'two_factor_recovery_codes',
        'password',
        'remember_token',
        'invitation_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at'          => 'datetime',
            'invitation_sent_at'         => 'datetime',
            'articleship_start_date'     => 'date',
            'articleship_end_date'       => 'date',
            'articleship_duration_years' => 'integer',
            'total_leave_cap'            => 'integer',
            'leave_allocations'          => 'array',
            'promotion_history'          => 'array',
            'password'                   => 'hashed',
            'can_proxy_attendance'       => 'boolean',
            'can_edit_attendance'        => 'boolean',
            'can_approve_leave'          => 'boolean',
            'can_approve_claims'         => 'boolean',
            'can_manage_team'            => 'boolean',
            'can_manage_policies'        => 'boolean',
            'can_manage_payroll'         => 'boolean',
            'can_final_approve_leave'    => 'boolean',
            'can_final_approve_claims'   => 'boolean',
            'is_active'                  => 'boolean',
            'two_factor_confirmed_at'    => 'datetime',
            'two_factor_secret'          => 'encrypted',
            'two_factor_recovery_codes'  => 'encrypted:array',
            'monthly_allowance'          => 'decimal:2',
        ];
    }

    /**
     * Self-healing runtime schema check for cPanel deployments so leave_allocations,
     * total_leave_cap, and articleship_duration_years exist even before CLI migrate.
     */
    public static function ensureRuntimeSchemaColumns(): void
    {
        if (! app()->environment('local', 'testing') && ! app()->runningUnitTests()) {
            return;
        }

        try {
            if (! Schema::hasTable('users')) {
                return;
            }
            $missingYears = ! Schema::hasColumn('users', 'articleship_duration_years');
            $missingCap   = ! Schema::hasColumn('users', 'total_leave_cap');
            $missingAlloc = ! Schema::hasColumn('users', 'leave_allocations');

            if ($missingYears || $missingCap || $missingAlloc) {
                Schema::table('users', function (Blueprint $table) use ($missingYears, $missingCap, $missingAlloc) {
                    if ($missingYears) {
                        $table->unsignedTinyInteger('articleship_duration_years')->nullable()->default(3);
                    }
                    if ($missingCap) {
                        $table->unsignedSmallInteger('total_leave_cap')->nullable()->default(180);
                    }
                    if ($missingAlloc) {
                        $table->json('leave_allocations')->nullable();
                    }
                });
            }
        } catch (\Throwable $e) {
            Log::debug('User schema check skipped: ' . $e->getMessage());
        }
    }

    /**
     * Build standard ICAB Articleship (3-Year = 180d max / 4-Year = 240d max)
     * or Bangladesh Corporate Staff leave entitlement structure.
     *
     * For Articled Students:
     *   Study Leave = Total Articleship Leave Cap (180 or 240) - (Casual + Sick + Emergency)
     * For Managers / Regular Corporate Staff:
     *   Bangladesh corporate standard: Casual (10d), Sick (14d), Earned (15d), Emergency (5d) = 44d
     */
    public static function buildLeaveEntitlement(string $role, int $articleshipYears = 3, ?int $customCap = null, array $customDays = []): array
    {
        if ($role === self::ROLE_STUDENT) {
            $years = ($articleshipYears === 4) ? 4 : 3;
            $defaultCap = ($years === 4) ? 240 : 180;
            $cap = ($customCap !== null && $customCap > 0) ? (int) $customCap : $defaultCap;

            $casual    = isset($customDays['annual']) ? max(0, (int) $customDays['annual']) : 14;
            $sick      = isset($customDays['sick']) ? max(0, (int) $customDays['sick']) : 14;
            $emergency = isset($customDays['emergency']) ? max(0, (int) $customDays['emergency']) : 7;
            $regularSum = $casual + $sick + $emergency;

            // Everything remaining out of the 180-day (3-yr) or 240-day (4-yr) cap is ICAB Study / Exam Leave
            $study = max(0, $cap - $regularSum);
            $effectiveTotal = $regularSum + $study;

            return [
                'regime'                     => 'icab_articleship',
                'regime_label'               => "{$years}-Year ICAB Articleship (Max {$effectiveTotal} Days)",
                'articleship_duration_years' => $years,
                'total_leave_cap'            => $effectiveTotal,
                'allocations'                => [
                    'annual'     => $casual,
                    'sick'       => $sick,
                    'emergency'  => $emergency,
                    'icab_study' => $study,
                ],
                'categories'                 => [
                    ['key' => 'annual',     'label' => 'Casual / Annual Leave',          'days' => $casual,    'is_study' => false],
                    ['key' => 'sick',       'label' => 'Medical / Sick Leave',           'days' => $sick,      'is_study' => false],
                    ['key' => 'emergency',  'label' => 'Emergency Family Leave',         'days' => $emergency, 'is_study' => false],
                    ['key' => 'icab_study', 'label' => 'ICAB Study & Exam Prep Leave',   'days' => $study,     'is_study' => true],
                ],
            ];
        }

        // Manager / Regular Corporate Staff (Bangladesh Corporate / Labour Law Standard)
        $casual    = isset($customDays['annual']) ? max(0, (int) $customDays['annual']) : 10;
        $sick      = isset($customDays['sick']) ? max(0, (int) $customDays['sick']) : 14;
        $earned    = isset($customDays['earned']) ? max(0, (int) $customDays['earned']) : 15;
        $emergency = isset($customDays['emergency']) ? max(0, (int) $customDays['emergency']) : 5;
        $totalCorp = $casual + $sick + $earned + $emergency;

        return [
            'regime'                     => 'bd_corporate',
            'regime_label'               => "BD Corporate Leave Policy ({$totalCorp} Days/Yr)",
            'articleship_duration_years' => null,
            'total_leave_cap'            => $totalCorp,
            'allocations'                => [
                'annual'    => $casual,
                'sick'      => $sick,
                'earned'    => $earned,
                'emergency' => $emergency,
            ],
            'categories'                 => [
                ['key' => 'annual',    'label' => 'Casual Leave (BD Corporate)',     'days' => $casual,    'is_study' => false],
                ['key' => 'sick',      'label' => 'Medical / Sick Leave',            'days' => $sick,      'is_study' => false],
                ['key' => 'earned',    'label' => 'Earned / Annual Privilege Leave', 'days' => $earned,    'is_study' => false],
                ['key' => 'emergency', 'label' => 'Emergency / Festival Leave',      'days' => $emergency, 'is_study' => false],
            ],
        ];
    }

    /**
     * Resolve this user's configured leave entitlement (falling back to role &
     * articleship duration defaults if not explicitly customized yet).
     */
    public function resolvedLeaveEntitlement(): array
    {
        $years = (int) ($this->articleship_duration_years ?: 3);
        if ($this->role === self::ROLE_STUDENT && $this->articleship_start_date && $this->articleship_end_date && ! $this->articleship_duration_years) {
            $diffDays = $this->articleship_start_date->diffInDays($this->articleship_end_date);
            if ($diffDays >= 1300) {
                $years = 4;
            }
        }
        $savedAlloc = is_array($this->leave_allocations) ? $this->leave_allocations : [];
        $cap = $this->total_leave_cap ? (int) $this->total_leave_cap : null;

        return self::buildLeaveEntitlement((string) ($this->role ?: self::ROLE_STUDENT), $years, $cap, $savedAlloc);
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

    public function payrollRecords(): HasMany
    {
        return $this->hasMany(PayrollRecord::class);
    }

    public function partnerPolicy(): HasOne
    {
        return $this->hasOne(PartnerPolicy::class, 'partner_id');
    }

    // ─── Role Helpers ─────────────────────────────────────────────────────────

    public function isPlatformAdmin(): bool
    {
        // SECURITY: platform authority is granted by the role column only.
        // Matching on hard-coded email addresses allowed anyone who could set
        // their own email to become a platform super admin.
        return $this->role === self::ROLE_PLATFORM_ADMIN;
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
        return in_array($this->role, [self::ROLE_PARTNER, self::ROLE_MANAGER, self::ROLE_STUDENT], true);
    }

    public function displayDesignation(): string
    {
        if (! empty($this->designation) && is_string($this->designation)) {
            return trim($this->designation);
        }

        return match ($this->role) {
            self::ROLE_PARTNER => 'Partner / Principal',
            self::ROLE_MANAGER => 'Audit Manager',
            default            => 'Articled Student',
        };
    }

    /**
     * Has this account completed TOTP enrolment?
     *
     * Deliberately strict: a secret without a confirmation timestamp is an
     * abandoned enrolment and must not lock the user out.
     */
    public function hasTwoFactorEnabled(): bool
    {
        return ! empty($this->two_factor_secret) && $this->two_factor_confirmed_at !== null;
    }

    public function isActive(): bool
    {
        // Rows created before the is_active column existed must keep working.
        return $this->is_active === null ? true : (bool) $this->is_active;
    }

    public function canManageTeam(): bool
    {
        return $this->isPlatformAdmin() || $this->isPartner() || (bool) $this->can_manage_team;
    }

    public function belongsToFirm(string $slug): bool
    {
        return $this->firm && strtolower($this->firm->slug) === strtolower($slug);
    }
}
