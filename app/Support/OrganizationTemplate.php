<?php

namespace App\Support;

/**
 * Business-type presets are configuration, not forks in authorization.
 * Every profile uses the same tenant/workspace/member security boundary.
 */
final class OrganizationTemplate
{
    public const TYPES = [
        'ca_firm' => 'Chartered Accountancy / Audit Firm',
        'corporate' => 'Corporate Company',
        'ngo' => 'NGO / Nonprofit',
        'consultancy' => 'Consultancy / Professional Services',
        'other' => 'Other Organization',
    ];

    public const UNIT_KINDS = [
        'office' => 'Office / Location',
        'branch' => 'Branch',
        'division' => 'Division',
        'department' => 'Department',
        'practice' => 'Practice Group',
        'program' => 'Program / Project Unit',
        'team' => 'Team',
    ];

    private const PRESETS = [
        'ca_firm' => ['workspace' => 'Partner Unit 01', 'recommended' => ['office', 'practice', 'team']],
        'corporate' => ['workspace' => 'Head Office', 'recommended' => ['branch', 'department', 'team']],
        'ngo' => ['workspace' => 'Main Program', 'recommended' => ['program', 'office', 'team']],
        'consultancy' => ['workspace' => 'Primary Practice', 'recommended' => ['practice', 'department', 'team']],
        'other' => ['workspace' => 'Main Workspace', 'recommended' => ['branch', 'department', 'team']],
    ];

    public static function type(?string $type): string
    {
        return array_key_exists((string) $type, self::TYPES) ? (string) $type : 'ca_firm';
    }

    public static function workspaceName(string $type): string
    {
        return self::PRESETS[self::type($type)]['workspace'];
    }

    public static function recommendedKinds(string $type): array
    {
        return self::PRESETS[self::type($type)]['recommended'];
    }

    /**
     * Presets suggest starting units, but never restrict future organization
     * structure. Different industries may use any supported typed unit.
     */
    public static function unitKinds(): array
    {
        return self::UNIT_KINDS;
    }
}
