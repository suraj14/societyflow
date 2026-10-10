<?php

namespace App\Services;

use App\Models\SystemSetting;
use App\Models\WhatsAppTemplate;
use App\Support\WhatsAppPurposes;
use Illuminate\Support\Facades\Log;

/**
 * Pulls a society's WhatsApp templates from Fast2SMS into a local list, so
 * Settings -> WhatsApp can offer approved templates to pick from instead
 * of asking a manager to type template names or IDs.
 *
 * Source: GET /dev/dlt_manager/whatsapp?type=template (via Fast2SmsAccount).
 * It needs only the API key - no WhatsApp Business Account ID - and returns
 * the variable count and body text the picker needs. Shape confirmed
 * against a live account on 2026-10-08. Templates of other sender numbers
 * on the same account are skipped: they cannot be sent from ours.
 *
 * Also repairs one real-world mistake found in saved data: notification
 * types whose saved "template name" was the humanised label shown on
 * screen ("Payment Completed") rather than the template's actual name
 * ("payment_completed"). Meta matches the exact name, so every such send
 * failed. A label is rewritten only when it maps to exactly one synced
 * template - never guessed.
 */
class WhatsAppTemplateSyncService
{
    /** Swapped in by tests; bypasses the real HTTP call. Production never sets it. fn () => [array $body, int $code] */
    public static $transportOverride = null;

    /** @return array{success: bool, message: string, count?: int, detail?: ?string, repaired?: int} */
    public static function sync(?int $societyId): array
    {
        $apiKey = self::getSetting('whatsapp_api_key', null, $societyId);
        $phoneNumberId = self::getSetting('whatsapp_phone_number_id', null, $societyId);

        if (empty($apiKey)) {
            return ['success' => false, 'message' => 'Save your Fast2SMS API key first, then sync templates.'];
        }

        $fetched = self::fetch((string) $apiKey, $phoneNumberId ? (string) $phoneNumberId : null);
        if (!$fetched['ok']) {
            Log::warning('WhatsApp template sync failed', ['provider' => 'fast2sms_whatsapp', 'society_id' => $societyId]);

            return ['success' => false, 'message' => 'Could not fetch templates: ' . $fetched['message'], 'detail' => $fetched['detail'] ?? null];
        }

        $now = now();
        $seen = [];

        foreach ($fetched['templates'] as $remote) {
            if ($remote['external_id'] === '' || $remote['name'] === '') {
                continue;
            }

            $existing = WhatsAppTemplate::where('society_id', $societyId)->where('external_id', $remote['external_id'])->first();

            WhatsAppTemplate::updateOrCreate(
                ['society_id' => $societyId, 'external_id' => $remote['external_id']],
                [
                    'message_id'      => $remote['message_id'] ?: null,
                    'phone_number_id' => $remote['phone_number_id'] ?: null,
                    'name'            => $remote['name'],
                    // Never overwrite an admin's own label with the synced default.
                    'friendly_name'   => $existing?->friendly_name,
                    'status'          => strtoupper($remote['status'] ?: 'UNKNOWN'),
                    'language'        => $remote['language'] ?: null,
                    'category'        => $remote['category'] ?: null,
                    'var_count'       => $remote['var_count'],
                    'body_text'       => $remote['body_text'] ?: null,
                    'synced_at'       => $now,
                ]
            );
            $seen[] = $remote['external_id'];
        }

        // A template deleted at the provider must not stay pickable.
        WhatsAppTemplate::where('society_id', $societyId)->whereNotIn('external_id', $seen ?: ['-'])->delete();

        $repaired = self::repairAssignments($societyId);
        $count = count($seen);

        return [
            'success'  => true,
            'message'  => "Synced {$count} template(s)." . ($repaired ? " Fixed {$repaired} notification(s) that were saved with a display name instead of the template name." : ''),
            'count'    => $count,
            'repaired' => $repaired,
        ];
    }

    /**
     * Saved purpose -> template values that are not real template names but
     * match exactly one synced template's on-screen label are rewritten to
     * that template's real name. Anything else is left untouched.
     */
    public static function repairAssignments(?int $societyId): int
    {
        $templates = WhatsAppTemplate::where('society_id', $societyId)->get();
        $names = $templates->pluck('name')->all();
        $fixed = 0;

        foreach (array_keys(WhatsAppPurposes::all()) as $purpose) {
            $key = "whatsapp_{$purpose}_template_name";
            $saved = trim((string) self::getSetting($key, '', $societyId, false));
            if ($saved === '' || in_array($saved, $names, true)) {
                continue;
            }

            $matches = $templates->filter(fn ($t) => strcasecmp($t->displayName(), $saved) === 0
                || strcasecmp(WhatsAppTemplate::humanize($t->name), $saved) === 0
                || $t->external_id === $saved
                || ($t->message_id !== null && $t->message_id === $saved));

            if ($matches->count() === 1) {
                SystemSetting::set('whatsapp', $key, $matches->first()->name, 'string', $societyId);
                $fixed++;
            }
        }

        return $fixed;
    }

    /** @return array{ok: bool, message: string, templates?: array, detail?: ?string} */
    private static function fetch(string $apiKey, ?string $phoneNumberId): array
    {
        if (self::$transportOverride === null) {
            return Fast2SmsAccount::whatsappTemplates($apiKey, $phoneNumberId);
        }

        [$body, $code] = (self::$transportOverride)();
        if ($code !== 200 || !is_array($body['data'] ?? null)) {
            $error = $body['error']['message'] ?? ($body['message'] ?? "HTTP {$code}");

            return ['ok' => false, 'message' => SmsService::safeProviderMessage((string) $error, $apiKey)];
        }

        // Accept both the dlt_manager shape (numbers with nested templates)
        // and Meta's flat list ({id, name, status, language, category}).
        $templates = [];
        foreach ($body['data'] as $item) {
            foreach (isset($item['templates']) ? $item['templates'] : [$item] as $t) {
                $bodyComponent = collect($t['components'] ?? [])->first(fn ($c) => strtoupper($c['type'] ?? '') === 'BODY');
                $text = (string) ($bodyComponent['text'] ?? '');
                $templates[] = [
                    'external_id'     => (string) ($t['template_id'] ?? $t['id'] ?? ''),
                    'message_id'      => (string) ($t['message_id'] ?? ''),
                    'name'            => (string) ($t['template_name'] ?? $t['name'] ?? ''),
                    'language'        => (string) ($t['language'] ?? ''),
                    'status'          => (string) ($t['status'] ?? ''),
                    'category'        => (string) ($t['category'] ?? ''),
                    'var_count'       => (int) ($t['var_count'] ?? preg_match_all('/\{\{\d+\}\}/', $text)),
                    'body_text'       => $text,
                    'phone_number_id' => (string) ($t['phone_number_id'] ?? ''),
                ];
            }
        }

        return ['ok' => true, 'message' => '', 'templates' => $templates];
    }

    private static function getSetting(string $key, $default, ?int $societyId, bool $fallBackToGlobal = true)
    {
        if ($societyId) {
            $val = SystemSetting::get('whatsapp', $key, null, $societyId);
            if ($val !== null && $val !== '') return $val;
            if (!$fallBackToGlobal) return $default;
        }

        return SystemSetting::get('whatsapp', $key, $default, null);
    }
}
