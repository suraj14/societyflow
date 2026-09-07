<?php

namespace App\Console\Commands;

use App\Events\UserCreated;
use App\Helpers\TemporaryPassword;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * Finds accounts still using one of the well-known default passwords that
 * OwnerController and UserController used to hand out, and rotates them.
 *
 * The code defect is fixed (see App\Helpers\TemporaryPassword), but every
 * account created before that fix still carries the old credential. This
 * command is the remediation for existing data.
 *
 * It reports by default and only writes with --force, because rotating a
 * password locks the current holder out until they read the email.
 */
class RotateWeakPasswords extends Command
{
    protected $signature = 'users:rotate-weak-passwords
                            {--force : Actually rotate. Without this the command only reports.}
                            {--no-mail : Rotate without dispatching the welcome email (prints nothing; use only if mail is misconfigured and you will reset out of band).}';

    protected $description = 'Find and rotate accounts using known default passwords';

    /**
     * Passwords historically handed out by the admin-side create screens,
     * plus the usual suspects.
     */
    private const KNOWN_WEAK = [
        'password',
        'password123',
        'Password123',
        '12345678',
        'admin123',
        'secret',
        'changeme',
    ];

    public function handle(): int
    {
        $affected = [];

        User::query()->select(['id', 'name', 'email', 'password', 'society_id'])
            ->chunkById(200, function ($users) use (&$affected) {
                foreach ($users as $user) {
                    foreach (self::KNOWN_WEAK as $weak) {
                        if (Hash::check($weak, $user->password)) {
                            $affected[] = ['user' => $user, 'weak' => $weak];
                            break;
                        }
                    }
                }
            });

        if ($affected === []) {
            $this->info('No accounts are using a known default password.');

            return self::SUCCESS;
        }

        $this->warn(sprintf('%d account(s) are using a known default password:', count($affected)));
        $this->table(
            ['ID', 'Name', 'Email', 'Password'],
            array_map(fn ($row) => [
                $row['user']->id,
                $row['user']->name,
                $row['user']->email,
                $row['weak'],
            ], $affected)
        );

        if (!$this->option('force')) {
            $this->newLine();
            $this->line('This was a report only. Re-run with --force to rotate these passwords.');
            $this->line('Each affected user is emailed a new temporary password unless --no-mail is given.');

            return self::SUCCESS;
        }

        $rotated = 0;
        $failed  = 0;

        foreach ($affected as $row) {
            /** @var User $user */
            $user        = $row['user'];
            $newPassword = TemporaryPassword::generate();

            try {
                $user->forceFill(['password' => Hash::make($newPassword)])->save();

                // Revoke any live mobile sessions issued under the old password.
                $user->tokens()->delete();

                if (!$this->option('no-mail')) {
                    UserCreated::dispatch($user, $newPassword);
                }

                $rotated++;
            } catch (\Throwable $e) {
                $failed++;
                $this->error("Failed to rotate {$user->email}: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info("Rotated {$rotated} account(s). API tokens for those accounts were revoked.");

        if ($failed > 0) {
            $this->error("{$failed} account(s) could not be rotated - see errors above.");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
