<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SetupStorageDirectories extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'storage:setup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create all required storage directories and symlink';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Setting up storage directories...');

        $directories = [
            'storage/app/public/notices',
            'storage/app/public/facilities',
            'storage/app/public/visitor-photos',
            'storage/app/public/avatars',
            'storage/app/public/society-logos',
            'storage/app/public/society-favicons',
            'storage/app/public/utility-bills',
            'storage/app/public/utility-bills/payments',
            'storage/app/public/service-providers',
            'storage/app/public/landing/features',
            'storage/app/public/landing/reviews',
            'storage/app/public/owners/documents',
            'storage/app/public/complaints',
            'storage/app/public/complaint-updates',
        ];

        foreach ($directories as $dir) {
            $path = base_path($dir);
            if (!is_dir($path)) {
                mkdir($path, 0755, true);
                $this->line("✓ Created: {$dir}");
            } else {
                $this->line("✓ Already exists: {$dir}");
            }
        }

        // Create symlink
        $link = public_path('storage');
        $target = storage_path('app/public');

        if (is_link($link)) {
            $this->line("✓ Symlink already exists");
        } else {
            if (is_dir($link)) {
                // A previous environment (or a workaround for a symlink
                // that never resolved - see docs/PRODUCTION_CHECKLIST.md
                // "Deployment") left public/storage as a REAL directory
                // with real uploaded files in it, written there directly
                // by controllers that bypassed the Storage facade
                // (public_path('storage/...') + move(), still the majority
                // pattern in this codebase). rmdir() only removes an EMPTY
                // directory and silently does nothing otherwise - this used
                // to print "Created symlink" regardless of whether that
                // actually happened, so a populated public/storage looked
                // fixed while nothing had changed and new uploads kept
                // landing on whichever side the writing code happened to
                // use. Anything uploaded through the Storage facade in the
                // meantime (confirmed: two real files under
                // storage/app/public/complaints and complaint-updates) was
                // never visible through public/storage at all.
                //
                // Fixed at the root: merge every real file found under the
                // stray public/storage into the true target first - keep
                // the version already in storage/app/public on a name
                // collision - then replace the directory with the symlink.
                // Nothing already uploaded is lost.
                $this->mergeDirectoryInto($link, $target);
                $this->removeDirectory($link);
            }

            if (!@symlink($target, $link)) {
                // A true NTFS symlink needs SeCreateSymbolicLinkPrivilege -
                // granted by running elevated, or by Developer Mode on
                // Windows 10+. Neither is guaranteed on a local dev box.
                // A directory junction needs neither privilege and Windows
                // resolves it identically for read/write/serve purposes;
                // `mklink` has no Linux/macOS equivalent, so this fallback
                // only runs when PHP's own symlink() has already failed.
                if (PHP_OS_FAMILY === 'Windows') {
                    exec('mklink /J ' . escapeshellarg($link) . ' ' . escapeshellarg($target), $output, $exitCode);
                    if ($exitCode !== 0) {
                        $this->error('Could not create public/storage as a symlink or a junction: ' . implode(' ', $output));
                        return self::FAILURE;
                    }
                    $this->line('✓ Created junction: public/storage -> storage/app/public (symlink() needs elevation or Developer Mode on this machine)');
                } else {
                    $this->error("Could not create symlink public/storage -> {$target}");
                    return self::FAILURE;
                }
            } else {
                $this->line("✓ Created symlink: public/storage -> storage/app/public");
            }
        }

        // Set permissions
        foreach ($directories as $dir) {
            $path = base_path($dir);
            chmod($path, 0755);
        }
        chmod(storage_path('app/public'), 0755);
        chmod($link, 0755);

        $this->info('✓ Storage setup completed successfully!');

        return self::SUCCESS;
    }

    /**
     * Copy every file under $from into the matching relative path under
     * $to, without disturbing anything already in $to. A name collision
     * keeps the $to version (the Storage-facade side, already the correct
     * target) and reports it rather than silently overwriting either copy.
     */
    private function mergeDirectoryInto(string $from, string $to): void
    {
        if (!is_dir($from)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($from, \FilesystemIterator::SKIP_DOTS)
        );

        $copied = 0;
        $skipped = 0;

        foreach ($files as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $relative = substr($file->getPathname(), strlen($from) + 1);
            $destination = $to . DIRECTORY_SEPARATOR . $relative;

            if (is_file($destination)) {
                $skipped++;
                continue;
            }

            @mkdir(dirname($destination), 0755, true);
            copy($file->getPathname(), $destination);
            $copied++;
        }

        if ($copied > 0) {
            $this->line("✓ Merged {$copied} file(s) from the stray public/storage into storage/app/public");
        }
        if ($skipped > 0) {
            $this->line("  ({$skipped} already existed on both sides - kept the storage/app/public copy)");
        }
    }

    /** Recursively remove a real directory (not a symlink/junction). */
    private function removeDirectory(string $path): void
    {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $file) {
            $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
        }

        @rmdir($path);
    }
}
