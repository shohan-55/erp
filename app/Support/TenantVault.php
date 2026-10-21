<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

final class TenantVault
{
    public const MIME = [
        'pdf' => 'application/pdf',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
    ];

    public static function disk(): string
    {
        $disk = (string) config('erp_vault.disk', 'erp_vault');
        // Never accept public, generic local, arbitrary disks or user-supplied roots.
        if (! in_array($disk, ['erp_vault', 's3'], true)) {
            throw ValidationException::withMessages(['document' => 'A private vault disk must be configured.']);
        }
        if ($disk === 's3' && ! class_exists(\League\Flysystem\AwsS3V3\AwsS3V3Adapter::class)) {
            throw ValidationException::withMessages(['document' =>
                'Cloud object storage requires a separately installed and validated S3 adapter.']);
        }
        // Throw on storage failures: silently falling back to local breaks privacy.
        if (! config('filesystems.disks.'.$disk.'.throw', false)) {
            throw ValidationException::withMessages(['document' => 'The private storage disk must fail closed on errors.']);
        }
        return $disk;
    }

    public static function quota(object $actor): object
    {
        DB::table('erp_vault_quotas')->insertOrIgnore([
            'company_id' => $actor->company_id,
            'workspace_id' => $actor->workspace_id,
            'limit_bytes' => (int) config('erp_vault.workspace_limit_bytes', 104857600),
            'used_bytes' => 0, 'reserved_bytes' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return DB::table('erp_vault_quotas')
            ->where('company_id', $actor->company_id)
            ->where('workspace_id', $actor->workspace_id)
            ->lockForUpdate()->firstOrFail();
    }

    public static function visible(object $actor, object $document): bool
    {
        if ($actor->role === 'partner') return true;
        if ($document->archived_at || $document->visibility === 'partner'
            || ! $document->subject_membership_id) return false;
        if ((int) $document->subject_membership_id === (int) $actor->id) return true;
        if ($actor->role !== 'manager' || $document->visibility !== 'team') return false;
        return DB::table('memberships')->where('id', $document->subject_membership_id)
            ->where('company_id', $actor->company_id)
            ->where('workspace_id', $actor->workspace_id)
            ->where('manager_membership_id', $actor->id)
            ->where('role', 'student')->exists();
    }

    public static function capacity(object $quota, int $incoming): void
    {
        if ($incoming <= 0 || $incoming > (int) $quota->limit_bytes
            || (int)$quota->used_bytes + (int)$quota->reserved_bytes
                > (int)$quota->limit_bytes - $incoming) {
            throw ValidationException::withMessages(['expected_bytes' =>
                'This file exceeds the remaining private workspace storage quota.']);
        }
    }
}
