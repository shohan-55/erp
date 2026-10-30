<?php

namespace App\Support;

/**
 * Locates a tenant's uploaded file for the authorised download routes.
 *
 * Receipts and avatars are written to the private disk (`storage/app/private`),
 * so the only way to reach them is through a route that has already checked
 * `auth` + `firm.member`. Before this, both lived on the public disk: running
 * `php artisan storage:link` on a cPanel host published every firm's
 * conveyance receipts under `/storage/conveyance-receipts/{firm}/{file}` with
 * no authentication at all — the random filename was the only protection.
 *
 * Files uploaded before the move are still on the public disk, so the lookup
 * falls back to the legacy location instead of 404-ing on old history.
 */
class FirmFile
{
    /**
     * @return string|null absolute path, or null when the file does not exist
     */
    public static function locate(string $folder, string $firmSlug, string $filename): ?string
    {
        // Never let a crafted name walk out of the folder.
        $safeSlug = self::slug($firmSlug);
        $safeFile = basename($filename);

        if ($safeSlug === '' || $safeFile === '' || $safeFile === '.' || $safeFile === '..') {
            return null;
        }

        $candidates = [
            storage_path("app/private/{$folder}/{$safeSlug}/{$safeFile}"),
            // Legacy: uploaded before the move to the private disk.
            storage_path("app/public/{$folder}/{$safeSlug}/{$safeFile}"),
        ];

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    protected static function slug(string $slug): string
    {
        return preg_replace('/[^a-z0-9_-]/', '', strtolower(trim($slug))) ?? '';
    }
}
