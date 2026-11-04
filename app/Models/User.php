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
     * Self-healing runtime schema check for local/testing environments.
     */
    protected static bool $userSchemaChecked = false;

    public static function ensureRuntimeSchemaColumns(): void
    {
        if (self::$userSchemaChecked && ! app()->runningUnitTests()) {
            return;
        }
        self::$userSchemaChecked = true;

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
     * Build Year-Wise ICAB Articleship (3-Year = 180d [60d/yr × 3] / 4-Year = 240d [60d/yr × 4])
     * or Bangladesh Corporate Staff (Jan–Dec yearly reset + Earned Leave carry-forward) structure.
     *
     * For Articled Students:
     *   - Equal annual cap: Total Cap / Years (e.g. 180 / 3 = 60 days/year, 240 / 4 = 60 days/year)
     *   - Per-year regular leaves: Casual (default 14d/yr), Sick (default 14d/yr), Emergency (default 7d/yr)
     *   - Multi-year regular totals:
     *       Casual Total    = Casual/Yr × Years (e.g. 14 × 3 = 42d, 14 × 4 = 56d)
     *       Sick Total      = Sick/Yr × Years   (e.g. 14 × 3 = 42d, 14 × 4 = 56d)
     *       Emergency Total = Emergency/Yr × Years (e.g. 7 × 3 = 21d, 7 × 4 = 28d)
     *   - ICAB Study & Exam Prep Leave:
     *       Study Total     = Total Cap - (Casual Total + Sick Total + Emergency Total)
     *                         (e.g. 180 - 105 = 75d [25d/yr] for 3-Yr; 240 - 140 = 100d [25d/yr] for 4-Yr)
     *
     * For Managers / Regular Corporate Staff:
     *   - Resets every Calendar Year (01 Jan – 31 Dec)
     *   - Unused regular leaves from the previous year carry forward into Earned Leave.
     */
    public static function buildLeaveEntitlement(string $role, int $articleshipYears = 3, ?int $customCap = null, array $customDays = []): array
    {
        $policyMode = (($customDays['leave_policy_mode'] ?? 'icab') === 'custom') ? 'custom' : 'icab';

        if ($role === self::ROLE_STUDENT) {
            $years = $policyMode === 'custom'
                ? max(1, min(6, (int) ($articleshipYears ?: 3)))
                : (($articleshipYears === 4) ? 4 : 3);
            $defaultCap = ($years === 4) ? 240 : ($years * 60);
            $cap = ($customCap !== null && $customCap >= 0) ? (int) $customCap : $defaultCap;

            // Resolve per-year regular quotas (supporting both explicit *_per_year keys and legacy keys)
            $casualPerYear = isset($customDays['annual_per_year'])
                ? max(0, (int) $customDays['annual_per_year'])
                : (isset($customDays['annual'])
                    ? ((int) $customDays['annual'] > 30 ? (int) round((int) $customDays['annual'] / $years) : max(0, (int) $customDays['annual']))
                    : 14);

            $sickPerYear = isset($customDays['sick_per_year'])
                ? max(0, (int) $customDays['sick_per_year'])
                : (isset($customDays['sick'])
                    ? ((int) $customDays['sick'] > 30 ? (int) round((int) $customDays['sick'] / $years) : max(0, (int) $customDays['sick']))
                    : 14);

            $emergencyPerYear = isset($customDays['emergency_per_year'])
                ? max(0, (int) $customDays['emergency_per_year'])
                : (isset($customDays['emergency'])
                    ? ((int) $customDays['emergency'] > 20 ? (int) round((int) $customDays['emergency'] / $years) : max(0, (int) $customDays['emergency']))
                    : 7);

            $casualTotal    = $casualPerYear * $years;
            $sickTotal      = $sickPerYear * $years;
            $emergencyTotal = $emergencyPerYear * $years;
            $regularTotal   = $casualTotal + $sickTotal + $emergencyTotal;

            if ($policyMode === 'custom' && isset($customDays['icab_study'])) {
                $studyTotal   = max(0, (int) $customDays['icab_study']);
                $effectiveCap = ($customCap !== null && $customCap > 0) ? (int) $customCap : ($regularTotal + $studyTotal);
            } else {
                // Remaining days out of 180 (3-Yr) or 240 (4-Yr) become ICAB Study / Exam Preparation Leave
                $studyTotal   = max(0, $cap - $regularTotal);
                $effectiveCap = $regularTotal + $studyTotal;
            }

            $studyPerYear = (int) round($studyTotal / max(1, $years));
            $perYearCap   = (int) round($effectiveCap / max(1, $years));
            $regimeLabel  = $policyMode === 'custom'
                ? "Custom Leave ({$years}-Yr · {$effectiveCap} Days)"
                : "{$years}-Year ICAB Articleship ({$perYearCap}d/Yr × {$years} = {$effectiveCap} Days)";

            return [
                'regime'                     => 'icab_articleship',
                'leave_policy_mode'          => $policyMode,
                'regime_label'               => $regimeLabel,
                'articleship_duration_years' => $years,
                'per_year_cap'               => $perYearCap,
                'total_leave_cap'            => $effectiveCap,
                'per_year_allocations'       => [
                    'annual'     => $casualPerYear,
                    'sick'       => $sickPerYear,
                    'emergency'  => $emergencyPerYear,
                    'icab_study' => $studyPerYear,
                ],
                'allocations'                => [
                    'leave_policy_mode'  => $policyMode,
                    'annual_per_year'    => $casualPerYear,
                    'sick_per_year'      => $sickPerYear,
                    'emergency_per_year' => $emergencyPerYear,
                    'study_per_year'     => $studyPerYear,
                    'annual'             => $casualTotal,
                    'sick'               => $sickTotal,
                    'emergency'          => $emergencyTotal,
                    'icab_study'         => $studyTotal,
                ],
                'categories'                 => [
                    [
                        'key'            => 'annual',
                        'label'          => 'Casual / Annual Leave',
                        'per_year_days'  => $casualPerYear,
                        'days'           => $casualTotal,
                        'formula_label'  => "{$casualPerYear}d/yr × {$years} yrs = {$casualTotal}d",
                        'is_study'       => false,
                    ],
                    [
                        'key'            => 'sick',
                        'label'          => 'Medical / Sick Leave',
                        'per_year_days'  => $sickPerYear,
                        'days'           => $sickTotal,
                        'formula_label'  => "{$sickPerYear}d/yr × {$years} yrs = {$sickTotal}d",
                        'is_study'       => false,
                    ],
                    [
                        'key'            => 'emergency',
                        'label'          => 'Emergency Family Leave',
                        'per_year_days'  => $emergencyPerYear,
                        'days'           => $emergencyTotal,
                        'formula_label'  => "{$emergencyPerYear}d/yr × {$years} yrs = {$emergencyTotal}d",
                        'is_study'       => false,
                    ],
                    [
                        'key'            => 'icab_study',
                        'label'          => 'ICAB Study & Exam Prep Leave',
                        'per_year_days'  => $studyPerYear,
                        'days'           => $studyTotal,
                        'formula_label'  => "{$studyPerYear}d/yr × {$years} yrs = {$studyTotal}d (Pool)",
                        'is_study'       => true,
                    ],
                ],
            ];
        }

        // Manager / Regular Corporate Staff (Jan–Dec Calendar Year Reset + Earned Leave Carry-Forward)
        $casual       = isset($customDays['annual']) ? max(0, (int) $customDays['annual']) : 10;
        $sick         = isset($customDays['sick']) ? max(0, (int) $customDays['sick']) : 14;
        $baseEarned   = isset($customDays['earned']) ? max(0, (int) $customDays['earned']) : 15;
        $carryForward = isset($customDays['carry_forward_earned']) ? max(0, (int) $customDays['carry_forward_earned']) : 0;
        $emergency    = isset($customDays['emergency']) ? max(0, (int) $customDays['emergency']) : 5;
        $totalEarned  = $baseEarned + $carryForward;
        $totalCorp    = $casual + $sick + $totalEarned + $emergency;

        $mgrRegimeLabel = $policyMode === 'custom'
            ? "Custom Leave (Jan–Dec Reset · {$totalCorp} Days)"
            : "As per ICAB / Standard Leave (Jan–Dec Reset · {$totalCorp} Days)";

        return [
            'regime'                     => 'bd_corporate',
            'leave_policy_mode'          => $policyMode,
            'regime_label'               => $mgrRegimeLabel,
            'articleship_duration_years' => null,
            'per_year_cap'               => $totalCorp,
            'total_leave_cap'            => $totalCorp,
            'base_earned_days'           => $baseEarned,
            'carry_forward_earned'       => $carryForward,
            'per_year_allocations'       => [
                'annual'    => $casual,
                'sick'      => $sick,
                'earned'    => $totalEarned,
                'emergency' => $emergency,
            ],
            'allocations'                => [
                'leave_policy_mode'    => $policyMode,
                'annual'               => $casual,
                'sick'                 => $sick,
                'earned'               => $baseEarned,
                'carry_forward_earned' => $carryForward,
                'emergency'            => $emergency,
            ],
            'categories'                 => [
                [
                    'key'            => 'annual',
                    'label'          => 'Casual Leave (Jan–Dec Reset)',
                    'per_year_days'  => $casual,
                    'days'           => $casual,
                    'formula_label'  => "{$casual}d / Calendar Year",
                    'is_study'       => false,
                ],
                [
                    'key'            => 'sick',
                    'label'          => 'Medical / Sick Leave (Jan–Dec Reset)',
                    'per_year_days'  => $sick,
                    'days'           => $sick,
                    'formula_label'  => "{$sick}d / Calendar Year",
                    'is_study'       => false,
                ],
                [
                    'key'            => 'earned',
                    'label'          => 'Earned / Privilege Leave (Carry-Forward)',
                    'per_year_days'  => $totalEarned,
                    'days'           => $totalEarned,
                    'formula_label'  => $carryForward > 0 ? "{$baseEarned}d Base + {$carryForward}d Carry-Forward" : "{$baseEarned}d Base + Unused Carry-Forward",
                    'is_study'       => false,
                ],
                [
                    'key'            => 'emergency',
                    'label'          => 'Emergency / Festival Leave',
                    'per_year_days'  => $emergency,
                    'days'           => $emergency,
                    'formula_label'  => "{$emergency}d / Calendar Year",
                    'is_study'       => false,
                ],
            ],
        ];
    }

    /**
     * Resolve this user's configured leave entitlement.
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

    /**
     * Compute the 12-month Anniversary Year window (Year 1, Year 2, Year 3, [Year 4])
     * from the student's `articleship_start_date` (e.g. 2026-11-20 ->
     *   Year 1: 2026-11-20 to 2027-11-19
     *   Year 2: 2027-11-20 to 2028-11-19
     *   Year 3: 2028-11-20 to 2029-11-19
     * ).
     */
    public function articleshipYearWindowForDate(?string $targetDateYmd = null): array
    {
        $ent = $this->resolvedLeaveEntitlement();
        $totalYears = (int) ($ent['articleship_duration_years'] ?: 3);

        $joinStr = $this->articleship_start_date
            ? $this->articleship_start_date->format('Y-m-d')
            : (now()->timezone('Asia/Dhaka')->format('Y') . '-01-01');

        $joinDt = \DateTimeImmutable::createFromFormat('!Y-m-d', $joinStr)
            ?: new \DateTimeImmutable(now()->timezone('Asia/Dhaka')->format('Y-01-01'));

        $refStr = ($targetDateYmd && preg_match('/^\d{4}-\d{2}-\d{2}$/', $targetDateYmd))
            ? $targetDateYmd
            : now()->timezone('Asia/Dhaka')->toDateString();
        $refDt = \DateTimeImmutable::createFromFormat('!Y-m-d', $refStr) ?: $joinDt;

        $allWindows = [];
        $activeWindow = null;

        for ($y = 1; $y <= $totalYears; $y++) {
            $wStart = $joinDt->modify('+' . ($y - 1) . ' years');
            $wEnd   = $joinDt->modify('+' . $y . ' years -1 day');
            $win = [
                'year_number'  => $y,
                'total_years'  => $totalYears,
                'start_date'   => $wStart->format('Y-m-d'),
                'end_date'     => $wEnd->format('Y-m-d'),
                'period_label' => $wStart->format('d M Y') . ' – ' . $wEnd->format('d M Y'),
                'short_label'  => "Year {$y} of {$totalYears} (" . $wStart->format('d M Y') . ' – ' . $wEnd->format('d M Y') . ')',
            ];
            $allWindows[] = $win;

            if ($activeWindow === null && ($refDt <= $wEnd || $y === $totalYears)) {
                $activeWindow = $win;
            }
        }

        $activeWindow['all_windows'] = $allWindows;
        $activeWindow['joining_date'] = $joinDt->format('Y-m-d');

        return $activeWindow;
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

    public function hasTwoFactorEnabled(): bool
    {
        return ! empty($this->two_factor_secret) && $this->two_factor_confirmed_at !== null;
    }

    public function isActive(): bool
    {
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
