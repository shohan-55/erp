<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Move receipts and avatars uploaded before the private-disk change.
 *
 * On a cPanel host with `php artisan storage:link`, anything still sitting in
 * storage/app/public is reachable at /storage/... with no authentication. This
 * command moves those files into storage/app/private (where only the
 * authorised routes can serve them) and then removes the now-empty public
 * folders. The download routes fall back to the legacy location, so running
 * this is safe at any time and can be re-run.
 */
class PrivatiseUploads extends Command
{
    protected $signature = 'erp:privatise-uploads {--dry-run : Show what would move without touching anything}';

    protected $description = 'Move existing vouchers and avatars off the public disk into private storage';

    /** @var array<int, string> */
    protected array $folders = ['conveyance-receipts', 'avatars'];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $moved = 0;
        $skipped = 0;

        foreach ($this->folders as $folder) {
            $source = storage_path("app/public/{$folder}");

            if (! is_dir($source)) {
                continue;
            }

            foreach (File::allFiles($source) as $file) {
                $relative = ltrim(str_replace($source, '', $file->getPathname()), DIRECTORY_SEPARATOR);
                $target = storage_path("app/private/{$folder}/{$relative}");

                if (is_file($target)) {
                    // Already migrated on an earlier run.
                    $skipped++;
                    continue;
                }

                if ($dryRun) {
                    $this->line("would move: {$folder}/{$relative}");
                    $moved++;
                    continue;
                }

                File::ensureDirectoryExists(dirname($target));

                if (File::move($file->getPathname(), $target)) {
                    $moved++;
                } else {
                    $this->warn("could not move: {$folder}/{$relative}");
                }
            }

            if (! $dryRun && File::allFiles($source) === []) {
                File::deleteDirectory($source);
            }
        }

        $this->info(($dryRun ? '[dry run] ' : '') . "{$moved} file(s) " . ($dryRun ? 'would move' : 'moved') . ", {$skipped} already private.");

        if (! $dryRun && $moved > 0) {
            $this->newLine();
            $this->comment('Existing links keep working: the download routes check the private disk first,');
            $this->comment('then fall back to the old public location.');
        }

        return self::SUCCESS;
    }
}
