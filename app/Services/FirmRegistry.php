<?php

namespace App\Services;

class FirmRegistry
{
    /**
     * Presentation registry of supported CA firms for Phase 02 UI prototype.
     */
    protected static array $firms = [
        'rac' => [
            'slug' => 'rac',
            'name' => 'Rahman Anis & Co.',
            'designation' => 'Chartered Accountants',
            'short_code' => 'RA&C',
            'legal_name' => 'Rahman Anis & Co., Chartered Accountants',
            'logo_url' => 'images/rac_logo.svg',
            'tagline' => 'Your Practice. One Connected Workspace.',
            'description' => 'Manage attendance, leave, conveyance and payroll through a secure, organized workspace designed for professional accounting firms.',
            'office' => 'Karwan Bazar, Dhaka, Bangladesh (Sample Placeholder Address)',
            'support_email' => 'support@rahmananis.com (Sample Contact)',
            'primary_color' => '#FCDF02',
            'accent_color' => '#F5AD16',
            'charcoal_color' => '#16181D',
            'tenant_node' => 'erp.dhakawala.com',
            'firm_status' => 'Design Prototype Preview',
            'established' => 'Sample Est. (Placeholder)',
            'icab_reg' => 'ICAB Firm Reg: Sample/Placeholder',
            'partner_count' => null,
            'features' => [
                'Centralized ICAB Articled Student Roster & Compliance',
                'Client Audit Engagement Attendance & Geo Check-in',
                'Conveyance Claim Approvals with Document Proofs',
                'Integrated Monthly Stipend Calculation Engine'
            ]
        ]
    ];

    /**
     * Retrieve firm presentation metadata by slug.
     */
    public static function get(string $slug): ?array
    {
        $normalized = strtolower(trim($slug));
        return self::$firms[$normalized] ?? null;
    }

    /**
     * Check if a firm slug is registered.
     */
    public static function has(string $slug): bool
    {
        return isset(self::$firms[strtolower(trim($slug))]);
    }

    /**
     * List all registered firm slugs.
     */
    public static function all(): array
    {
        return self::$firms;
    }
}
