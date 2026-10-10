<?php

namespace App\Console\Commands;

use App\Models\SystemSetting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Read-only report on stored provider credentials. NEVER prints a secret:
 * only the key name, society, whether it is stored encrypted, its length
 * class and a short fingerprint (first 8 hex of sha256) - enough to prove
 * after a rotation that the value really changed, useless for recovering it.
 *
 *   php artisan credentials:audit
 *
 * Exit code 1 when a secret is stored in plaintext or is shared with .env.
 */
class AuditStoredCredentials extends Command
{
    protected $signature = 'credentials:audit';

    protected $description = 'Report (never print) stored SMTP/SMS/WhatsApp/payment credentials: encryption, duplication with .env, fingerprint';

    /** Every key SettingSecrets treats as sensitive. */
    private function sensitiveKeys(): array
    {
        return collect(['sms', 'email', 'payment', 'whatsapp'])
            ->flatMap(fn ($g) => \App\Support\SettingSecrets::keysFor($g))->unique()->values()->all();
    }

    public function handle(): int
    {
        $env = (string) @file_get_contents(base_path('.env'));
        $rows = DB::table('system_settings')->whereIn('key', $this->sensitiveKeys())
            ->whereNotNull('value')->where('value', '!=', '')->orderBy('society_id')->orderBy('key')->get();

        $problems = 0;
        $table = [];

        foreach ($rows as $row) {
            $encrypted = true;
            try {
                $plain = Crypt::decryptString($row->value);
            } catch (\Throwable) {
                $encrypted = false;
                $plain = (string) $row->value;
                $problems++;
            }

            $inEnv = strlen($plain) >= 6 && str_contains($env, $plain);
            if ($inEnv) {
                $problems++;
            }

            $table[] = [
                $row->society_id ? "society {$row->society_id}" : 'global',
                $row->key,
                $encrypted ? 'encrypted' : 'PLAINTEXT',
                strlen($plain) < 12 ? 'short (weak/placeholder?)' : 'ok',
                $inEnv ? 'YES - also in .env' : 'no',
                substr(hash('sha256', $plain), 0, 8),
            ];
        }

        $this->table(['Scope', 'Key', 'Storage', 'Length', 'Duplicated in .env', 'Fingerprint'], $table);
        $this->line('Credentials in .env (names only): ' . (collect(['MAIL_PASSWORD', 'FAST2SMS_API_KEY', 'AWS_SECRET_ACCESS_KEY', 'STRIPE_SECRET', 'RAZORPAY_SECRET'])
            ->filter(fn ($k) => preg_match('/^' . $k . '=.+$/m', $env))->implode(', ') ?: 'none'));

        if ($problems === 0) {
            $this->info('All stored secrets are encrypted and not duplicated in .env.');
        } else {
            $this->warn("{$problems} finding(s). After rotating, re-run and confirm every fingerprint changed.");
        }

        return $problems === 0 ? self::SUCCESS : self::FAILURE;
    }
}
