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
                rmdir($link);
            }
            symlink($target, $link);
            $this->line("✓ Created symlink: public/storage -> storage/app/public");
        }

        // Set permissions
        foreach ($directories as $dir) {
            $path = base_path($dir);
            chmod($path, 0755);
        }
        chmod(storage_path('app/public'), 0755);
        chmod($link, 0755);

        $this->info('✓ Storage setup completed successfully!');
    }
}
