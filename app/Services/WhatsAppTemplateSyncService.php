<?php

namespace App\Services;

use App\Models\WhatsAppTemplate;
use App\Support\WhatsAppTemplateScope;
use Illuminate\Support\Facades\Log;

/**
 * "Sync from Fast2SMS" on Message Templates -> WhatsApp: a manual, read-only pull of the account's WhatsApp templates
 * (GET /dev/dlt_manager/whatsapp?type=template, via Fast2SmsAccount - nothing is sent to anyone).
 *
 * It only ever adds and refreshes; it never deletes and never overwrites what is already saved:
 *  - a template the provider lists that SocietyFlow has not seen is added: inactive, no notification type, `synced`;
 *  - a template already saved (same provider id, or - for a hand-typed one - same name and language) keeps its id, label,
 *    notification type, active switch, variable choices, Message ID, language and text. Only the provider status and
 *    category are refreshed, and fields that are still empty (text, Message ID, variable count) are filled in;
 *  - a saved template the provider no longer lists is kept and counted in the answer.
 *
 * Fast2SMS documents no webhook for template approval or status changes (its webhooks report message delivery only), so
 * there is no automatic background sync: a template approved later appears after the next press of the button.
 */
class WhatsAppTemplateSyncService
{
    /** @return array{success: bool, message: string, skipped?: bool, detail?: ?string, added?: int, updated?: int, unchanged?: int, missing?: int, warnings?: list<string>} */
    public static function sync(?int $societyId): array
    {
        $apiKey = WhatsAppService::apiKey($societyId);
        if ($apiKey === '') {
            return ['success' => false, 'message' => 'Save your Fast2SMS API key first (WhatsApp or SMS settings), then sync.'];
        }

        $phoneNumberId = (string) (\App\Models\SystemSetting::get('whatsapp', 'whatsapp_phone_number_id', null, $societyId)
            ?: \App\Models\SystemSetting::get('whatsapp', 'whatsapp_phone_number_id', null, null));

        $fetched = Fast2SmsAccount::whatsappTemplates($apiKey, $phoneNumberId !== '' ? $phoneNumberId : null);
        if (!$fetched['ok']) {
            Log::warning('WhatsApp template sync failed', ['society_id' => $societyId, 'skipped' => !empty($fetched['skipped'])]);

            return ['success' => false, 'skipped' => !empty($fetched['skipped']), 'message' => $fetched['message'], 'detail' => $fetched['detail'] ?? null];
        }

        $added = $updated = $unchanged = 0;
        $warnings = [];
        $seen = [];
        $now = now();

        foreach ($fetched['templates'] as $remote) {
            if ($remote['external_id'] === '' || $remote['name'] === '') {
                continue;
            }
            $seen[] = $remote['external_id'];

            $local = WhatsAppTemplate::where('society_id', $societyId)->where('external_id', $remote['external_id'])->first()
                ?? WhatsAppTemplate::where('society_id', $societyId)->where('source', WhatsAppTemplate::SOURCE_MANUAL)
                    ->where('name', $remote['name'])->where(fn ($q) => $q->where('language', $remote['language'] ?: 'en')->orWhereNull('language'))->first();

            $status = strtoupper($remote['status'] ?: 'UNKNOWN');

            if (!$local) {
                WhatsAppTemplate::create([
                    'society_id' => $societyId, 'external_id' => $remote['external_id'], 'message_id' => $remote['message_id'] ?: null,
                    'phone_number_id' => $remote['phone_number_id'] ?: null, 'name' => $remote['name'], 'status' => $status,
                    'language' => $remote['language'] ?: null, 'category' => $remote['category'] ?: null, 'var_count' => $remote['var_count'],
                    'body_text' => $remote['body_text'] ?: null, 'is_active' => false, 'source' => WhatsAppTemplate::SOURCE_SYNCED, 'synced_at' => $now,
                ]);
                $added++;

                continue;
            }

            $changes = [];
            if ($local->status !== $status) {
                $changes['status'] = $status;
                if ($local->is_active && $status !== 'APPROVED') {
                    $warnings[] = "\"{$local->displayName()}\" is switched on but is now {$status} at the provider; it will not be sent until approved.";
                }
            }
            if ($remote['category'] !== '' && $local->category !== $remote['category']) {
                $changes['category'] = $remote['category'];
            }
            if (trim((string) $local->body_text) === '' && $remote['body_text'] !== '') {
                $changes['body_text'] = $remote['body_text'];
                $changes['var_count'] = $remote['var_count'];
            } elseif ($remote['body_text'] !== '' && trim((string) $local->body_text) !== trim($remote['body_text'])) {
                $warnings[] = "The saved wording of \"{$local->displayName()}\" differs from the provider's; yours was kept.";
            }
            if (!$local->message_id && $remote['message_id'] !== '') {
                $changes['message_id'] = $remote['message_id'];
            }
            if (!$local->phone_number_id && $remote['phone_number_id'] !== '') {
                $changes['phone_number_id'] = $remote['phone_number_id'];
            }
            // A hand-typed template now found at the provider: remember which one it is (status is the provider's).
            if ($local->external_id !== $remote['external_id']) {
                $changes['external_id'] = $remote['external_id'];
            }

            if ($changes) {
                $local->forceFill($changes + ['synced_at' => $now])->save();
                $updated++;
            } else {
                $local->forceFill(['synced_at' => $now])->save();
                $unchanged++;
            }
        }

        $missing = WhatsAppTemplate::where('society_id', $societyId)->where('source', WhatsAppTemplate::SOURCE_SYNCED)
            ->whereNotIn('external_id', $seen ?: ['-'])->count();

        $message = "Sync finished: {$added} new, {$updated} updated, {$unchanged} unchanged."
            . ($missing ? " {$missing} saved template(s) are no longer listed by Fast2SMS; they were kept." : '')
            . ($added ? ' New templates are inactive - choose what each is for, then switch it on.' : '');

        return compact('added', 'updated', 'unchanged', 'missing', 'warnings') + ['success' => true, 'message' => $message];
    }
}
