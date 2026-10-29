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
        'theme_palette',
        'primary_color',
    ];

    protected $casts = [
        'shared_attendance_view'  => 'boolean',
        'is_suspended'            => 'boolean',
        'subscription_expires_at' => 'date',
        'subscription_fee'        => 'decimal:2',
        'enabled_modules'         => 'array',
    ];

    /**
     * Curated enterprise color palettes for CA firms & corporate organizations.
     */
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
}
