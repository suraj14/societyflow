<?php

namespace App\Console\Commands;

use App\Models\Society;
use App\Models\SmsTemplate;
use App\Models\SystemSetting;
use App\Services\WhatsAppService;
use App\Support\DltTemplates;
use App\Support\ExternalMessaging;
use App\Support\WhatsAppPurposes;
use Illuminate\Console\Command;

/**
 * READ-ONLY. Explains why external messaging is on or off for this installation, and what each society has configured, without
 * printing a single secret (keys, passwords and tokens are only reported as set / not set) and without contacting any provider.
 *
 * "Configured" is not "verified": nothing here says a sender, a DLT template or a WhatsApp template is approved at the provider.
 * That needs a real, approved test (docs/LOCAL_MESSAGING_TESTING.md).
 */
class MessagingStatus extends Command
{
    protected $signature = 'messaging:status {--society= : Only this society id}';

    protected $description = 'Read-only: why messaging is on or off, the test allow-list, and each society\'s provider/template configuration (no secrets)';

    public function handle(): int
    {
        $this->global();

        $societies = Society::query()->when($this->option('society'), fn ($q, $id) => $q->whereKey($id))->orderBy('id')->get(['id', 'name']);
        foreach ($societies as $society) {
            if (!$this->hasAnyProviderSettings($society->id) && !$this->option('society')) {
                continue;
            }
            $this->society($society);
        }

        $this->newLine();
        $this->line('Nothing was sent and no provider was contacted. "Configured" is not "verified": see docs/LOCAL_MESSAGING_TESTING.md for the real, approved test.');

        return self::SUCCESS;
    }

    // ── this installation ───────────────────────────────────────────────────

    private function global(): void
    {
        $effective = ExternalMessaging::enabled();
        $envFile = $this->envFileValue('EXTERNAL_MESSAGING_ENABLED');
        $process = getenv('EXTERNAL_MESSAGING_ENABLED');
        $process = $process === false ? null : $process;

        $this->info('== This installation');
        $this->line('  External messaging (effective): ' . ($effective ? 'ON  - real delivery is possible' : 'OFF - nothing leaves this server'));

        if ($envFile === null && $process === null) {
            $source = 'not set anywhere, so the safe default (OFF) applies';
        } elseif ($envFile !== null && ($process === null || $this->truthy($process) === $this->truthy($envFile))) {
            $source = '.env says ' . $envFile;
        } else {
            $source = 'the PROCESS ENVIRONMENT says "' . $process . '" and overrides .env' . ($envFile !== null ? ' (which says ' . $envFile . ')' : '')
                . ' - a variable exported in the shell that started this process wins over .env';
        }
        $this->line('  Where that comes from: ' . $source);
        $this->line('  Config cache: ' . ($this->laravel->configurationIsCached() ? 'CACHED - .env edits are ignored until "php artisan config:clear"' : 'not cached (.env is read directly)'));
        $this->line('  A server that is already running keeps the value it started with: restart it after changing .env, and do not start it with the variable exported.');

        $problem = ExternalMessaging::allowlistProblem();
        if (!ExternalMessaging::restricted()) {
            $this->line('  Test allow-list: none (no restriction) ' . ($effective ? '- with delivery ON this reaches EVERYONE; use an allow-list on any machine holding real residents' : '- irrelevant while delivery is OFF'));
        } elseif ($problem) {
            $this->warn('  Test allow-list: INVALID - ' . $problem);
        } else {
            $n = count(ExternalMessaging::parseAllowlist(is_array(config('messaging.allowlist')) ? implode(',', config('messaging.allowlist')) : (string) config('messaging.allowlist'))['entries']);
            $this->line('  Test allow-list: ' . ($n === 0 ? 'present but EMPTY - nobody can receive anything' : "valid, $n recipient(s) (contents not shown)"));
        }

        $mail = config('mail.default');
        $this->line('  Mail: default mailer "' . $mail . '" (transport ' . config('mail.mailers.' . $mail . '.transport') . ')' . ($effective ? '' : ' - forced to the log file while delivery is OFF'));
        $this->line('  Queue: "' . config('queue.default') . '"' . (config('queue.default') === 'sync' ? ' - jobs and retries run inside the request (no worker needed, and none retries later)' : ' - needs a running queue worker'));
    }

    // ── one society ─────────────────────────────────────────────────────────

    private function society(Society $society): void
    {
        $id = $society->id;
        $this->newLine();
        $this->info("== Society {$id}: {$society->name}");

        // e-mail
        $host = $this->get('email', 'mail_host', $id);
        $port = $this->get('email', 'mail_port', $id);
        $enc = $this->get('email', 'mail_encryption', $id);
        $from = $this->get('email', 'mail_from_address', $id);
        $this->line('  E-mail: ' . ($host ? "{$host}:{$port} ({$enc}), from {$from}, login " . ($this->get('email', 'mail_username', $id) ? 'set' : 'NOT set') . ', password ' . ($this->get('email', 'mail_password', $id) ? 'set' : 'NOT set') : 'no society SMTP settings (falls back to the server-wide mailer)'));
        if ($host && (int) $port === 465 && strtolower((string) $enc) !== 'ssl' && strtolower((string) $enc) !== 'tls') {
            $this->warn('    !! port 465 needs implicit TLS ("ssl" or "tls"); encryption is "' . $enc . '"');
        }
        if ($from) {
            $this->line('    sender "' . $from . '" - whether the mail provider has VERIFIED this sender cannot be seen from here; check it in the provider\'s console');
        }

        // what the delivery log says about the last REAL attempts (a "sent" row from before 2026-10-17 may only have been written to the log file)
        $authFail = \App\Models\EmailLog::where('society_id', $id)->where(fn ($q) => $q->whereNull('channel')->orWhere('channel', 'email'))->where('status', 'failed')
            ->where(fn ($q) => $q->where('error_message', 'like', '%authenticat%')->orWhere('error_message', 'like', '%530%'))->latest('id')->first();
        $changed = SystemSetting::query()->where('society_id', $id)->where('group', 'email')->max('updated_at');
        if ($authFail) {
            $after = $changed && strtotime((string) $changed) > strtotime((string) $authFail->created_at);
            $this->line('    last real SMTP failure: login rejected on ' . $authFail->created_at . '; e-mail settings last saved ' . ($changed ?: 'never')
                . ($after ? ' - AFTER that failure, so the login has not been tried again yet (a real test will show)' : ' - NOT changed since, so the same login is still expected to fail'));
        } else {
            $this->line('    no SMTP login failure in the delivery log');
        }
        $realSent = \App\Models\EmailLog::where('society_id', $id)->where(fn ($q) => $q->whereNull('channel')->orWhere('channel', 'email'))->where('status', 'sent')->count();
        $this->line("    e-mails logged as \"sent\": {$realSent} (those from before 2026-10-17 may only have been written to the log file; the log now says Blocked / Simulated / Sent honestly)");

        // SMS
        $this->line('  SMS: ' . ($this->get('sms', 'sms_enabled', $id) ? 'enabled' : 'off') . ', provider ' . ($this->get('sms', 'sms_provider', $id) ?: 'fast2sms')
            . ', sender "' . ($this->get('sms', 'fast2sms_sender_id', $id) ?: '-') . '", route ' . ($this->get('sms', 'fast2sms_route', $id) ?: '-')
            . ', API key ' . ($this->get('sms', 'fast2sms_api_key', $id) ? 'set' : 'NOT set') . ', notifications ' . ($this->get('sms', 'notification_sms_enabled', $id) ? 'on' : 'off'));
        foreach (SmsTemplate::where('society_id', $id)->orderBy('purpose')->get() as $t) {
            $issues = [];
            if (!$t->is_active) {
                $issues[] = 'inactive';
            }
            if (trim((string) $t->dlt_message_id) === '') {
                $issues[] = 'no Fast2SMS Message ID (cannot go on the DLT route)';
            }
            if ($c = DltTemplates::purposeConflict($t->purpose, (string) $t->body)) {
                $issues[] = 'WRONG WORDING for this type: ' . $c;
            } elseif ($t->dlt_message_id && ($p = DltTemplates::bodyProblems($t->purpose, (string) $t->body))) {
                $issues[] = 'differs from the registered text (advisory)';
            }
            $this->line(sprintf('    SMS template #%d "%s" -> %s%s%s', $t->id, $t->name, $t->purposeLabel(), $t->dlt_message_id ? ", Message ID {$t->dlt_message_id}" : '', $issues ? ' | ' . implode('; ', $issues) : ' | ok'));
        }
        foreach (['complaint', 'bill'] as $purpose) {
            if (!SmsTemplate::forPurpose($id, $purpose)) {
                $this->line("    SMS \"{$purpose}\": no active template, so no SMS is sent for it");
            }
        }

        // WhatsApp
        $this->line('  WhatsApp: ' . ($this->get('whatsapp', 'whatsapp_enabled', $id) ? 'enabled' : 'off') . ', phone number id ' . ($this->get('whatsapp', 'whatsapp_phone_number_id', $id) ?: 'NOT set')
            . ', business account id ' . ($this->get('whatsapp', 'whatsapp_waba_id', $id) ?: '-') . ', API key ' . ($this->get('whatsapp', 'whatsapp_api_key', $id) ? 'set' : 'NOT set (falls back to the SMS key)'));
        $this->line('    (the phone number id and account id are what is SAVED; that they belong to the SocietyFlow WhatsApp account is verified only by the provider)');
        foreach (WhatsAppPurposes::selectable() as $purpose => $meta) {
            $r = WhatsAppService::resolveTemplate($purpose, $id, false);
            $name = $r['template']?->displayName() ?? $r['name'] ?? '-';
            $verdict = match ($r['check']) {
                'ok' => 'ok - would send with "' . $name . '"',
                'no_template' => 'no template: no WhatsApp is sent',
                default => 'BLOCKED - ' . ($r['outcome']['message'] ?? $r['check']),
            };
            $this->line(sprintf('    WhatsApp %-16s %s', $purpose, $verdict));
        }
    }

    // ── helpers ─────────────────────────────────────────────────────────────

    private function hasAnyProviderSettings(int $societyId): bool
    {
        return SystemSetting::query()->where('society_id', $societyId)->whereIn('group', ['email', 'sms', 'whatsapp'])->exists();
    }

    /** A saved setting for a society (never the global one when the society has none), as plain text; secrets are only tested for being set. */
    private function get(string $group, string $key, int $societyId)
    {
        $row = SystemSetting::query()->where('group', $group)->where('key', $key)->where('society_id', $societyId)->first();
        if (!$row || $row->value === null || $row->value === '') {
            return null;
        }

        return preg_match('/password|api_key|secret|token/i', $key) ? true : SystemSetting::get($group, $key, null, $societyId);
    }

    private function envFileValue(string $key): ?string
    {
        $path = base_path('.env');
        if (!is_file($path)) {
            return null;
        }
        foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            if (preg_match('/^\s*' . preg_quote($key, '/') . '\s*=\s*(.*)$/', $line, $m)) {
                return trim($m[1], " \t\"'");
            }
        }

        return null;
    }

    private function truthy(?string $v): bool
    {
        return in_array(strtolower((string) $v), ['1', 'true', '(true)', 'on', 'yes'], true);
    }
}
