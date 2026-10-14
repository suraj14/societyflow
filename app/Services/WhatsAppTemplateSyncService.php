<?php

namespace App\Services;

use App\Models\SystemSetting;
use App\Models\WhatsAppTemplate;
use App\Support\WhatsAppTemplateMapping;
use App\Support\WhatsAppTemplateScope;
use Illuminate\Support\Facades\Log;

/**
 * "Sync from Fast2SMS" on Message Templates -> WhatsApp: a manual, read-only pull of the account's WhatsApp templates
 * (GET /dev/dlt_manager/whatsapp?type=template, via Fast2SmsAccount - nothing is sent to anyone).
 *
 * It only adds and refreshes; it never deletes and never overwrites what is already saved:
 *  - a template the provider lists that SocietyFlow has not seen is added: INACTIVE, `synced`, with the provider's own
 *    status; its notification type is filled in only when its name clearly says which one (WhatsAppTemplateMapping::
 *    suggestPurpose) and the provider has APPROVED it. Nothing is ever switched on here.
 *  - a template already saved (same provider id, or - for a hand-typed one - same name and language) keeps its id, label,
 *    notification type, active switch, variable choices, Message ID, language and text. The provider status and category
 *    are refreshed, fields still empty (text, Message ID, variable count) are filled in, and an unassigned approved
 *    template gets its notification type if its name is clear.
 *  - a saved template the provider no longer lists is kept and counted.
 * Running it again changes nothing (idempotent).
 *
 * The saved Phone Number ID is checked against the numbers the provider reports: when it is the Business Account ID or
 * not one of the account's numbers, the answer says so (sending would fail), and every template is still synced - the
 * list is a property of the account, not of the saved ID.
 *
 * Fast2SMS documents no webhook for template approval or status changes (its webhooks report message delivery only), so
 * there is no automatic background sync: a template approved later appears after the next press of the button.
 */
class WhatsAppTemplateSyncService
{
    /** @return array{success: bool, message: string, skipped?: bool, detail?: ?string, added?: int, updated?: int, unchanged?: int, skipped_count?: int, failed?: int, assigned?: int, missing?: int, warnings?: list<string>} */
    public static function sync(?int $societyId): array
    {
        $apiKey = WhatsAppService::apiKey($societyId);
        if ($apiKey === '') {
            return ['success' => false, 'message' => 'Save your Fast2SMS API key first (WhatsApp or SMS settings), then sync.'];
        }

        $saved = trim((string) (SystemSetting::get('whatsapp', 'whatsapp_phone_number_id', null, $societyId)
            ?: SystemSetting::get('whatsapp', 'whatsapp_phone_number_id', null, null)));

        // the whole account list: a wrong saved id must not hide the templates
        $fetched = Fast2SmsAccount::whatsappTemplates($apiKey, null);
        if (!$fetched['ok']) {
            Log::warning('WhatsApp template sync failed', ['society_id' => $societyId, 'skipped' => !empty($fetched['skipped'])]);

            return ['success' => false, 'skipped' => !empty($fetched['skipped']), 'message' => $fetched['message'], 'detail' => $fetched['detail'] ?? null];
        }

        $numbers = array_values(array_unique(array_filter(array_column($fetched['templates'], 'phone_number_id'))));
        $warnings = [];
        $idOk = $saved !== '' && in_array($saved, $numbers, true);
        if ($saved === '') {
            $warnings[] = 'No Phone Number ID is saved yet: templates were synced, but nothing can be sent until it is.';
        } elseif (!$idOk && $numbers) {
            $warnings[] = "The saved Phone Number ID ({$saved}) is not a WhatsApp number of this account (" . implode(', ', $numbers) . '). Fix it in WhatsApp settings and press Verify configuration; sending would fail.';
        }

        $added = $updated = $unchanged = $skipped = $failed = $assigned = 0;
        $seen = [];
        $now = now();

        foreach ($fetched['templates'] as $remote) {
            if ($remote['external_id'] === '' || $remote['name'] === '') {
                $skipped++;   // nothing to identify it by

                continue;
            }
            // With a valid saved number, another number's templates cannot be sent from it: counted, not stored.
            if ($idOk && $remote['phone_number_id'] !== '' && $remote['phone_number_id'] !== $saved) {
                $skipped++;

                continue;
            }
            $seen[] = $remote['external_id'];

            try {
                $outcome = self::store($societyId, $remote, $now, $warnings);
            } catch (\Throwable $e) {
                $failed++;
                Log::warning('WhatsApp template could not be stored', ['class' => get_class($e)]);

                continue;
            }
            ${$outcome['kind']}++;
            $assigned += $outcome['assigned'] ? 1 : 0;
        }

        $missing = WhatsAppTemplate::where('society_id', $societyId)->where('source', WhatsAppTemplate::SOURCE_SYNCED)
            ->whereNotIn('external_id', $seen ?: ['-'])->count();

        $message = "Sync finished: {$added} added, {$updated} updated, {$unchanged} unchanged"
            . ($skipped ? ", {$skipped} skipped" : '') . ($failed ? ", {$failed} failed" : '') . '.'
            . ($assigned ? " {$assigned} approved template(s) were attached to the notification their name names." : '')
            . ($missing ? " {$missing} saved template(s) are no longer listed by Fast2SMS; they were kept." : '')
            . ' New templates are inactive: switch each on yourself.';

        return ['success' => $failed === 0 || ($added + $updated + $unchanged) > 0, 'message' => $message, 'added' => $added, 'updated' => $updated, 'unchanged' => $unchanged,
            'skipped_count' => $skipped, 'failed' => $failed, 'assigned' => $assigned, 'missing' => $missing, 'warnings' => $warnings];
    }

    /** @return array{kind: string, assigned: bool} kind is the counter to bump: added | updated | unchanged */
    private static function store(?int $societyId, array $remote, $now, array &$warnings): array
    {
        $status = strtoupper($remote['status'] ?: 'UNKNOWN');

        $local = WhatsAppTemplate::where('society_id', $societyId)->where('external_id', $remote['external_id'])->first()
            ?? WhatsAppTemplate::where('society_id', $societyId)->where('source', WhatsAppTemplate::SOURCE_MANUAL)
                ->where('name', $remote['name'])->where(fn ($q) => $q->where('language', $remote['language'] ?: 'en')->orWhereNull('language'))->first();

        if (!$local) {
            $new = new WhatsAppTemplate([
                'society_id' => $societyId, 'external_id' => $remote['external_id'], 'message_id' => $remote['message_id'] ?: null,
                'phone_number_id' => $remote['phone_number_id'] ?: null, 'name' => $remote['name'], 'status' => $status,
                'language' => $remote['language'] ?: null, 'category' => $remote['category'] ?: null, 'var_count' => $remote['var_count'],
                'body_text' => $remote['body_text'] ?: null, 'is_active' => false, 'source' => WhatsAppTemplate::SOURCE_SYNCED, 'synced_at' => $now,
            ]);
            $purpose = self::purposeFor($new);
            $new->purpose = $purpose;
            $new->save();

            return ['kind' => 'added', 'assigned' => $purpose !== null];
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
        // a hand-typed template now found at the provider: remember which one it is (its status is now the provider's)
        if ($local->external_id !== $remote['external_id']) {
            $changes['external_id'] = $remote['external_id'];
        }

        // an unassigned template gets its notification only here, only when approved, only when its name is clear
        $candidate = clone $local;
        $candidate->forceFill($changes);
        $assigned = false;
        if ($local->purpose === null && ($purpose = self::purposeFor($candidate)) !== null) {
            $changes['purpose'] = $purpose;
            $assigned = true;
        }

        if ($changes) {
            $local->forceFill($changes + ['synced_at' => $now])->save();

            return ['kind' => 'updated', 'assigned' => $assigned];
        }
        $local->forceFill(['synced_at' => $now])->save();

        return ['kind' => 'unchanged', 'assigned' => false];
    }

    /** The notification a template is clearly for - only for an APPROVED, SocietyFlow (not BookMizo) template. */
    private static function purposeFor(WhatsAppTemplate $t): ?string
    {
        if (strtoupper((string) $t->status) !== 'APPROVED' || WhatsAppTemplateScope::isBookMizo($t)) {
            return null;
        }

        return WhatsAppTemplateMapping::suggestPurpose((string) $t->name, (string) $t->body_text);
    }
}
