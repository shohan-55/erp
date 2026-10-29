<?php

namespace App\Services;

use App\Models\Firm;
use Illuminate\Support\Facades\Schema;

class FirmRegistry
{
    private static array $fallbackFirms = [
        'rac' => [
            'slug'          => 'rac',
            'name'          => 'Rahman Anis & Co.',
            'designation'   => 'Chartered Accountants',
            'short_code'    => 'RA&C',
            'legal_name'    => 'Rahman Anis & Co., Chartered Accountants',
            'logo_url'      => 'images/rac_logo.svg',
            'tagline'       => 'Your Practice. One Connected Workspace.',
            'description'   => 'Manage attendance, leave, conveyance and payroll through a secure, organized workspace designed for professional accounting firms.',
            'office'        => 'Dhaka, Bangladesh',
            'support_email' => 'support@rahmananis.com',
            'theme_palette' => 'sea_green',
            'primary_color' => '#0D9488',
            'hover_color'   => '#0F766E',
            'accent_color'  => '#14B8A6',
            'light_accent'  => '#2DD4BF',
            'soft_bg'       => '#F0FDFA',
            'soft_border'   => '#99F6E4',
            'dark_text'     => '#115E59',
            'on_primary'    => '#FFFFFF',
            'charcoal_color'=> '#16181D',
            'tenant_node'   => 'erp.dhakawala.com',
            'firm_status'   => 'active',
            'plan'          => 'Practice',
            'seats'         => 12,
            'region'        => 'Dhaka',
            'initials'      => 'RA',
            'icab_reg'      => 'ICAB REG # RAC-2024',
            'features'      => [
                'Centralized ICAB Articled Student Roster & Compliance',
                'Client Audit Engagement Attendance & Geo Check-in',
                'Conveyance Claim Approvals with Document Proofs',
                'Integrated Monthly Stipend Calculation Engine',
            ],
        ],
        'sample-alpha' => [
            'slug'          => 'sample-alpha',
            'name'          => 'Sample Practice Alpha',
            'designation'   => 'Chartered Accountants',
            'short_code'    => 'SA&C',
            'legal_name'    => 'Sample Practice Alpha, Chartered Accountants',
            'logo_url'      => 'images/sample-alpha_logo.svg',
            'tagline'       => 'Your Practice. One Connected Workspace.',
            'description'   => 'Manage attendance, leave, conveyance and payroll through a secure, organized workspace.',
            'office'        => 'Dhaka, Bangladesh',
            'support_email' => 'info@samplealpha.com',
            'theme_palette' => 'royal_blue',
            'primary_color' => '#2563EB',
            'hover_color'   => '#1D4ED8',
            'accent_color'  => '#3B82F6',
            'light_accent'  => '#60A5FA',
            'soft_bg'       => '#EFF6FF',
            'soft_border'   => '#BFDBFE',
            'dark_text'     => '#1E40AF',
            'on_primary'    => '#FFFFFF',
            'charcoal_color'=> '#16181D',
            'tenant_node'   => 'erp.dhakawala.com',
            'firm_status'   => 'active',
            'plan'          => 'Starter',
            'seats'         => 8,
            'region'        => 'Dhaka',
            'initials'      => 'SA',
            'icab_reg'      => 'ICAB REG # SA-2024',
            'features'      => [
                'Centralized ICAB Articled Student Roster & Compliance',
                'Client Audit Engagement Attendance & Geo Check-in',
            ],
        ],
    ];

    /**
     * Retrieve firm by slug from the database (fallback used strictly in unit tests without migrations).
     */
    public static function get(string $slug): ?array
    {
        $normalized = strtolower(trim($slug));

        try {
            if (Schema::hasTable('firms')) {
                $firm = Firm::findBySlug($normalized);
                if ($firm && $firm->status === 'active') {
                    return self::toArray($firm);
                }
                return null;
            }
        } catch (\Throwable) {
        }

        if (app()->runningUnitTests()) {
            return self::$fallbackFirms[$normalized] ?? null;
        }

        return null;
    }

    /**
     * Check if a firm slug exists and is active.
     */
    public static function has(string $slug): bool
    {
        $normalized = strtolower(trim($slug));

        try {
            if (Schema::hasTable('firms')) {
                return Firm::where('slug', $normalized)
                    ->where('status', 'active')
                    ->exists();
            }
        } catch (\Throwable) {
        }

        if (app()->runningUnitTests()) {
            return isset(self::$fallbackFirms[$normalized]);
        }

        return false;
    }

    /**
     * List all active firms as associative arrays.
     */
    public static function all(): array
    {
        try {
            if (Schema::hasTable('firms')) {
                return Firm::where('status', 'active')
                    ->get()
                    ->keyBy('slug')
                    ->map(fn (Firm $firm) => self::toArray($firm))
                    ->all();
            }
        } catch (\Throwable) {
        }

        if (app()->runningUnitTests()) {
            return self::$fallbackFirms;
        }

        return [];
    }

    /**
     * Convert a Firm model to the array shape used by views, including resolved color palette tokens.
     */
    public static function toArray(Firm $firm): array
    {
        $palette = Firm::resolvePalette($firm->theme_palette ?? 'sea_green', $firm->primary_color ?? null);

        return [
            'slug'             => $firm->slug,
            'name'             => $firm->name,
            'designation'      => $firm->kind,
            'short_code'       => strtoupper($firm->initials) . '&C',
            'legal_name'       => $firm->name . ', ' . $firm->kind,
            'logo_url'         => 'images/' . $firm->slug . '_logo.svg',
            'tagline'          => 'Your Practice. One Connected Workspace.',
            'description'      => 'Manage attendance, leave, conveyance and payroll through a secure, organized workspace designed for professional accounting firms.',
            'office'           => ($firm->region ?: 'Dhaka') . ', Bangladesh',
            'support_email'    => $firm->email ?? 'support@dhakawala.com',
            'theme_palette'    => $firm->theme_palette ?: 'sea_green',
            'palette_label'    => $palette['label'],
            'primary_color'    => $palette['primary'],
            'hover_color'      => $palette['hover'],
            'accent_color'     => $palette['accent'],
            'light_accent'     => $palette['light_accent'] ?? $palette['accent'],
            'soft_bg'          => $palette['soft_bg'],
            'soft_border'      => $palette['soft_border'],
            'dark_text'        => $palette['dark_text'],
            'on_primary'       => $palette['on_primary'],
            'charcoal_color'   => '#16181D',
            'tenant_node'      => 'erp.dhakawala.com',
            'firm_status'      => $firm->status,
            'plan'             => $firm->plan,
            'seats'            => $firm->seats,
            'region'           => $firm->region,
            'initials'         => $firm->initials,
            'icab_reg'         => 'ICAB REGISTERED PRACTICE',
            'custom_domain'    => $firm->custom_domain,
            'custom_subdomain' => $firm->custom_subdomain,
            'workspace_url'    => $firm->workspace_url,
            'enabled_modules'  => is_array($firm->enabled_modules) ? $firm->enabled_modules : ['attendance' => true, 'leave' => true, 'conveyance' => true, 'payroll' => true, 'team' => true, 'policies' => true],
            'features'         => [
                'Centralized ICAB Articled Student Roster & Compliance',
                'Client Audit Engagement Attendance & Geo Check-in',
                'Conveyance Claim Approvals with Document Proofs',
                'Integrated Monthly Stipend Calculation Engine',
            ],
        ];
    }

    /**
     * Resolve firm by host (custom domain or custom subdomain).
     */
    public static function getByHost(string $host): ?array
    {
        try {
            if (Schema::hasTable('firms')) {
                $firm = Firm::findByHost($host);
                if ($firm && $firm->status === 'active') {
                    return self::toArray($firm);
                }
            }
        } catch (\Throwable) {
        }

        return null;
    }
}
