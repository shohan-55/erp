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
            'primary_color' => '#FCDF02',
            'accent_color'  => '#F5AD16',
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
            'primary_color' => '#FCDF02',
            'accent_color'  => '#F5AD16',
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
     * Retrieve firm by slug from the database (with fallback).
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
                if ($firm && $firm->status !== 'active') {
                    return null;
                }
            }
        } catch (\Throwable) {
            // Fall back if database is not migrated or available
        }

        return self::$fallbackFirms[$normalized] ?? null;
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
            // Fall back
        }

        return isset(self::$fallbackFirms[$normalized]);
    }

    /**
     * List all active firms as associative arrays.
     */
    public static function all(): array
    {
        try {
            if (Schema::hasTable('firms')) {
                $firms = Firm::where('status', 'active')
                    ->get()
                    ->keyBy('slug')
                    ->map(fn (Firm $firm) => self::toArray($firm))
                    ->all();

                if (! empty($firms)) {
                    return $firms;
                }
            }
        } catch (\Throwable) {
            // Fall back
        }

        return self::$fallbackFirms;
    }

    /**
     * Convert a Firm model to the array shape used by views.
     */
    public static function toArray(Firm $firm): array
    {
        return [
            'slug'          => $firm->slug,
            'name'          => $firm->name,
            'designation'   => $firm->kind,
            'short_code'    => strtoupper($firm->initials) . '&C',
            'legal_name'    => $firm->name . ', ' . $firm->kind,
            'logo_url'      => 'images/' . $firm->slug . '_logo.svg',
            'tagline'       => 'Your Practice. One Connected Workspace.',
            'description'   => 'Manage attendance, leave, conveyance and payroll through a secure, organized workspace designed for professional accounting firms.',
            'office'        => 'Dhaka, Bangladesh',
            'support_email' => $firm->email ?? 'support@dhakawala.com',
            'primary_color' => '#FCDF02',
            'accent_color'  => '#F5AD16',
            'charcoal_color'=> '#16181D',
            'tenant_node'   => 'erp.dhakawala.com',
            'firm_status'   => $firm->status,
            'plan'          => $firm->plan,
            'seats'         => $firm->seats,
            'region'        => $firm->region,
            'initials'      => $firm->initials,
            'icab_reg'      => 'ICAB REG # ' . strtoupper($firm->slug) . '-2024',
            'features'      => [
                'Centralized ICAB Articled Student Roster & Compliance',
                'Client Audit Engagement Attendance & Geo Check-in',
                'Conveyance Claim Approvals with Document Proofs',
                'Integrated Monthly Stipend Calculation Engine',
            ],
        ];
    }
}
