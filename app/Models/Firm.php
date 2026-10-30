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
        'weekly_off_days',
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
        'theme_palette',
        'primary_color',
        'subscription_type',
        'per_seat_rate',
        'trial_days',
    ];

    protected $casts = [
        'shared_attendance_view'  => 'boolean',
        'is_suspended'            => 'boolean',
        'subscription_expires_at' => 'date',
        'subscription_fee'        => 'decimal:2',
        'enabled_modules'         => 'array',
        'weekly_off_days'         => 'array',
    ];

    /**
     * Friday + Saturday: the default weekend for a Dhaka CA practice. A firm
     * that works Saturday and closes Sunday overrides this in Workspace
     * Settings; NULL in the database means "use this default".
     */
    public const DEFAULT_WEEKLY_OFF_DAYS = [5, 6];

    /** 0 = Sunday … 6 = Saturday, matching DateTime::format('w'). */
    public const WEEKDAY_LABELS = [
        0 => 'Sunday',
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
    ];

    public const WEEKDAY_SHORT = [
        0 => 'Sun',
        1 => 'Mon',
        2 => 'Tue',
        3 => 'Wed',
        4 => 'Thu',
        5 => 'Fri',
        6 => 'Sat',
    ];

    /**
     * Turn whatever is stored (JSON string from MySQL, array from the cast,
     * comma list from a form, null for "not configured") into a clean, sorted
     * list of weekday integers.
     *
     * null  → the platform default (Friday + Saturday)
     * []    → a firm that genuinely works all seven days
     */
    public static function normaliseWeeklyOffDays(mixed $value): array
    {
        if ($value === null) {
            return self::DEFAULT_WEEKLY_OFF_DAYS;
        }

        if (is_string($value)) {
            $value = trim($value);
            if ($value === '') {
                return [];
            }
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : explode(',', $value);
        }

        if (! is_array($value)) {
            return self::DEFAULT_WEEKLY_OFF_DAYS;
        }

        $days = [];
        foreach ($value as $day) {
            if (! is_numeric($day)) {
                continue;
            }
            $index = (int) $day;
            if ($index >= 0 && $index <= 6) {
                $days[$index] = $index;
            }
        }

        ksort($days);

        return array_values($days);
    }

    /** The weekday numbers this firm closes on. */
    public function weeklyOffDayIndexes(): array
    {
        return self::normaliseWeeklyOffDays($this->weekly_off_days);
    }

    /** "Friday & Saturday" / "Sunday" / "Friday, Saturday & Sunday". */
    public static function describeWeeklyOffDays(array $days): string
    {
        $days = self::normaliseWeeklyOffDays($days);

        if ($days === []) {
            return 'No weekly off — open all 7 days';
        }

        $labels = array_map(fn (int $d) => self::WEEKDAY_LABELS[$d] ?? (string) $d, $days);

        if (count($labels) === 1) {
            return $labels[0];
        }

        $last = array_pop($labels);

        return implode(', ', $labels) . ' & ' . $last;
    }

    /** Short chip form: "Fri & Sat". */
    public static function shortWeeklyOffDays(array $days): string
    {
        $days = self::normaliseWeeklyOffDays($days);

        if ($days === []) {
            return 'All 7 days open';
        }

        return implode(' & ', array_map(fn (int $d) => self::WEEKDAY_SHORT[$d] ?? (string) $d, $days));
    }

    /**
     * Curated enterprise color palettes for CA firms & corporate organizations.
     */

    /**
     * Self-healing runtime schema guard: ensures theme_palette, primary_color, and subscription columns exist
     * even if migrations were already run prior to this update.
     */
    public static function ensureRuntimeSchemaColumns(): void
    {
        // The columns this method patches are now created by migration
        // 2026_10_30_000001. Running DDL inside a web request is unsafe (audit
        // P1-3.6): it serialises table locks, can race two concurrent requests
        // and hides migration drift. It is therefore limited to local/testing,
        // where it still helps a half-migrated developer database.
        if (! app()->environment('local', 'testing') && ! app()->runningUnitTests()) {
            return;
        }

        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('firms') && ! \Illuminate\Support\Facades\Schema::hasColumn('firms', 'theme_palette')) {
                \Illuminate\Support\Facades\Schema::table('firms', function (\Illuminate\Database\Schema\Blueprint $table) {
                    if (! \Illuminate\Support\Facades\Schema::hasColumn('firms', 'theme_palette')) {
                        $table->string('theme_palette', 40)->default('sea_green');
                    }
                    if (! \Illuminate\Support\Facades\Schema::hasColumn('firms', 'primary_color')) {
                        $table->string('primary_color', 20)->default('#0D9488');
                    }
                    if (! \Illuminate\Support\Facades\Schema::hasColumn('firms', 'subscription_type')) {
                        $table->string('subscription_type', 20)->default('paid');
                    }
                    if (! \Illuminate\Support\Facades\Schema::hasColumn('firms', 'per_seat_rate')) {
                        $table->decimal('per_seat_rate', 10, 2)->default(500.00);
                    }
                    if (! \Illuminate\Support\Facades\Schema::hasColumn('firms', 'trial_days')) {
                        $table->integer('trial_days')->nullable()->default(14);
                    }
                });
            } elseif (\Illuminate\Support\Facades\Schema::hasTable('firms') && ! \Illuminate\Support\Facades\Schema::hasColumn('firms', 'subscription_type')) {
                \Illuminate\Support\Facades\Schema::table('firms', function (\Illuminate\Database\Schema\Blueprint $table) {
                    if (! \Illuminate\Support\Facades\Schema::hasColumn('firms', 'subscription_type')) {
                        $table->string('subscription_type', 20)->default('paid');
                    }
                    if (! \Illuminate\Support\Facades\Schema::hasColumn('firms', 'per_seat_rate')) {
                        $table->decimal('per_seat_rate', 10, 2)->default(500.00);
                    }
                    if (! \Illuminate\Support\Facades\Schema::hasColumn('firms', 'trial_days')) {
                        $table->integer('trial_days')->nullable()->default(14);
                    }
                });
            }
        } catch (\Throwable) {}
    }

    public static function colorPalettes(): array
    {
        return [
            'sea_green' => [
                'key'          => 'sea_green',
                'light_accent' => '#2DD4BF',
                'label'       => 'Sea Green (সি গ্রিন)',
                'primary'     => '#0D9488',
                'hover'       => '#0F766E',
                'accent'      => '#14B8A6',
                'soft_bg'     => '#F0FDFA',
                'soft_border' => '#99F6E4',
                'dark_text'   => '#115E59',
                'on_primary'  => '#FFFFFF',
            ],
            'emerald_forest' => [
                'key'          => 'emerald_forest',
                'light_accent' => '#34D399',
                'label'       => 'Emerald Forest (এমেরাল্ড গ্রিন)',
                'primary'     => '#059669',
                'hover'       => '#047857',
                'accent'      => '#10B981',
                'soft_bg'     => '#ECFDF5',
                'soft_border' => '#A7F3D0',
                'dark_text'   => '#065F46',
                'on_primary'  => '#FFFFFF',
            ],
            'royal_blue' => [
                'key'          => 'royal_blue',
                'light_accent' => '#60A5FA',
                'label'       => 'Royal Blue (রয়্যাল ব্লু)',
                'primary'     => '#2563EB',
                'hover'       => '#1D4ED8',
                'accent'      => '#3B82F6',
                'soft_bg'     => '#EFF6FF',
                'soft_border' => '#BFDBFE',
                'dark_text'   => '#1E40AF',
                'on_primary'  => '#FFFFFF',
            ],
            'ocean_cyan' => [
                'key'          => 'ocean_cyan',
                'light_accent' => '#22D3EE',
                'label'       => 'Ocean Cyan (ওশান সায়ান)',
                'primary'     => '#0891B2',
                'hover'       => '#0E7490',
                'accent'      => '#06B6D4',
                'soft_bg'     => '#ECFEFF',
                'soft_border' => '#A5F3FC',
                'dark_text'   => '#155E75',
                'on_primary'  => '#FFFFFF',
            ],
            'indigo_violet' => [
                'key'          => 'indigo_violet',
                'light_accent' => '#818CF8',
                'label'       => 'Executive Indigo (ইন্ডিগো ভায়োলেট)',
                'primary'     => '#4F46E5',
                'hover'       => '#4338CA',
                'accent'      => '#6366F1',
                'soft_bg'     => '#EEF2FF',
                'soft_border' => '#C7D2FE',
                'dark_text'   => '#3730A3',
                'on_primary'  => '#FFFFFF',
            ],
            'purple_plum' => [
                'key'          => 'purple_plum',
                'light_accent' => '#C084FC',
                'label'       => 'Imperial Purple (রয়্যাল পার্পল)',
                'primary'     => '#7C3AED',
                'hover'       => '#6D28D9',
                'accent'      => '#8B5CF6',
                'soft_bg'     => '#F5F3FF',
                'soft_border' => '#DDD6FE',
                'dark_text'   => '#5B21B6',
                'on_primary'  => '#FFFFFF',
            ],
            'crimson_rose' => [
                'key'          => 'crimson_rose',
                'light_accent' => '#FB7185',
                'label'       => 'Corporate Crimson (ক্রিমসন রোজ)',
                'primary'     => '#E11D48',
                'hover'       => '#BE123C',
                'accent'      => '#F43F5E',
                'soft_bg'     => '#FFF1F2',
                'soft_border' => '#FECDD3',
                'dark_text'   => '#9F1239',
                'on_primary'  => '#FFFFFF',
            ],
            'amber_gold' => [
                'key'          => 'amber_gold',
                'light_accent' => '#FCDF02',
                'label'       => 'Classic Gold (ক্লাসিক গোল্ড)',
                'primary'     => '#FCDF02',
                'hover'       => '#E5C700',
                'accent'      => '#F5AD16',
                'soft_bg'     => '#FFFBEB',
                'soft_border' => '#FDE68A',
                'dark_text'   => '#92400E',
                'on_primary'  => '#16181D',
            ],
        ];
    }

    /**
     * Resolve palette tokens from a palette key or custom hex color.
     */
    public static function resolvePalette(?string $paletteKey, ?string $customHex = null): array
    {
        $palettes = static::colorPalettes();
        $key = strtolower(trim($paletteKey ?: 'sea_green'));

        if ($key === 'custom' && $customHex && preg_match('/^#[0-9A-Fa-f]{6}$/', trim($customHex))) {
            $hex = strtoupper(trim($customHex));
            $r = hexdec(substr($hex, 1, 2));
            $g = hexdec(substr($hex, 3, 2));
            $b = hexdec(substr($hex, 5, 2));
            $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;

            $hoverR = max(0, (int) round($r * 0.85));
            $hoverG = max(0, (int) round($g * 0.85));
            $hoverB = max(0, (int) round($b * 0.85));
            $hoverHex = sprintf('#%02X%02X%02X', $hoverR, $hoverG, $hoverB);

            $softR = min(255, (int) round($r + (255 - $r) * 0.92));
            $softG = min(255, (int) round($g + (255 - $g) * 0.92));
            $softB = min(255, (int) round($b + (255 - $b) * 0.92));
            $softBg = sprintf('#%02X%02X%02X', $softR, $softG, $softB);

            $bdrR = min(255, (int) round($r + (255 - $r) * 0.72));
            $bdrG = min(255, (int) round($g + (255 - $g) * 0.72));
            $bdrB = min(255, (int) round($b + (255 - $b) * 0.72));
            $softBorder = sprintf('#%02X%02X%02X', $bdrR, $bdrG, $bdrB);

            $darkR = max(0, (int) round($r * 0.55));
            $darkG = max(0, (int) round($g * 0.55));
            $darkB = max(0, (int) round($b * 0.55));
            $darkText = sprintf('#%02X%02X%02X', $darkR, $darkG, $darkB);

            return [
                'key'          => 'custom',
                'light_accent' => sprintf('#%02X%02X%02X', min(255, (int) round($r + (255 - $r) * 0.45)), min(255, (int) round($g + (255 - $g) * 0.45)), min(255, (int) round($b + (255 - $b) * 0.45))),
                'label'       => 'Custom Brand Color (' . $hex . ')',
                'primary'     => $hex,
                'hover'       => $hoverHex,
                'accent'      => $hex,
                'soft_bg'     => $softBg,
                'soft_border' => $softBorder,
                'dark_text'   => $darkText,
                'on_primary'  => $luminance > 0.65 ? '#16181D' : '#FFFFFF',
            ];
        }

        return $palettes[$key] ?? $palettes['sea_green'];
    }

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

    /**
     * Has the paid subscription lapsed?
     *
     * Audit P1-3.4: `subscription_expires_at` was only ever rendered as a
     * "due soon" banner — nothing was enforced, so a firm could keep full
     * write access for years after its last payment.
     *
     * A firm with no expiry date on record is treated as active, because
     * legacy/trial tenants were created before the column existed.
     */
    public function isSubscriptionExpired(): bool
    {
        $expiresAt = $this->subscription_expires_at;

        if (! $expiresAt) {
            return false;
        }

        // The column is cast to a date; a subscription expires at the end of
        // its final day, not at midnight on that day.
        return $expiresAt->copy()->endOfDay()->isPast();
    }

    /**
     * May this firm's members use the workspace right now?
     */
    public function isWorkspaceOperational(): bool
    {
        return ! $this->is_suspended && ! $this->isSubscriptionExpired();
    }
}
