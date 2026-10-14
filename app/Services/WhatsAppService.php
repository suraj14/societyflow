<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Log;

/**
 * WhatsApp notifications via Fast2SMS's WhatsApp Business API (WABA).
 *
 * Fast2SMS's WhatsApp product is a thin pass-through of Meta's WhatsApp
 * Cloud API shape - confirmed from Fast2SMS's own published reference docs
 * (docs.fast2sms.com/reference/send{authentication,text}template,
 * sendsessionmessage), not guessed. Two distinct endpoints:
 *
 *   Template message (works outside the 24h session window, the only way
 *   to reach someone who has not messaged first):
 *     POST https://www.fast2sms.com/dev/whatsapp/{version}/{phone_number_id}/messages
 *     Authorization: <api key>
 *     { messaging_product: "whatsapp", recipient_type: "individual", to,
 *       type: "template", template: { name, language: { code }, components } }
 *
 *   Session message (free text; Meta only allows this within 24h of the
 *   customer's last message - "where provider/account allows", per the
 *   product brief this class was written against):
 *     POST https://www.fast2sms.com/dev/whatsapp-session?phone_number_id=..&to=..
 *     Authorization: <api key>
 *     { type: "text", text }
 *
 * Every send needs a template *name* already approved in the account's
 * WhatsApp Business Manager - this class cannot create one, and a purpose
 * with no template name configured in Settings fails honestly rather than
 * guessing at a name that does not exist.
 */
class WhatsAppService
{
    /** Swapped in by tests; bypasses the real HTTP call. Production never sets it. */
    public static $transportOverride = null;

    /**
     * The OTP/authentication template. Meta's AUTHENTICATION category
     * templates take the code once in the body and once in the
     * "Copy code" button - see sendauthenticationtemplate's own example,
     * which this mirrors exactly.
     */
    public static function sendOtp(string $phone, string $otp, ?int $societyId = null): array
    {
        $templateName = self::getSetting('whatsapp_otp_template_name', null, $societyId);

        return self::sendTemplateMessage($phone, $templateName, [
            [
                'type'       => 'body',
                'parameters' => [['type' => 'text', 'text' => $otp]],
            ],
            [
                'type'       => 'button',
                'sub_type'   => 'url',
                'index'      => '0',
                'parameters' => [['type' => 'text', 'text' => $otp]],
            ],
        ], $societyId, logContext: ['purpose' => 'otp']);
    }

    /**
     * A notification for one business purpose (see App\Support\WhatsAppPurposes),
     * sent with the approved template the manager attached to it in
     * Settings -> WhatsApp (`whatsapp_{purpose}_template_name`).
     *
     * $values is either a list (filled into {{1}}..{{n}} in order) or a
     * map of named values (`['amount' => '1,200', 'resident_name' => ...]`)
     * placed by the saved variable mapping. When the template is known from
     * the last sync, its own language is used and a value-count mismatch is
     * refused here with a clear reason - Meta would reject it anyway, with a
     * far less readable error.
     */
    public static function sendNotification(string $purpose, string $phone, array $values, ?int $societyId = null): array
    {
        // The template the admin made active for this notification (Message Templates -> WhatsApp). The earlier settings key
        // is only a fallback for a society whose choice was saved before templates were managed as records.
        $template = \App\Models\WhatsAppTemplate::forPurpose($societyId, $purpose);
        $templateName = $template?->name ?: self::getSetting("whatsapp_{$purpose}_template_name", null, $societyId);

        if (empty($templateName)) {
            // No real attempt was possible - same as SmsService::sendDltNotification's
            // own "not configured" early return, this does not write a
            // Delivery Log row either; an admin who has not set up WhatsApp
            // at all should not see a growing list of "failed" rows for it.
            return ['success' => false, 'status' => 'skipped_not_configured', 'message' => "No active WhatsApp template configured for '{$purpose}'. Add one in Message Templates \u{2192} WhatsApp."];
        }

        $template ??= \App\Models\WhatsAppTemplate::where('society_id', $societyId)->where('name', $templateName)->first();

        // Pending / rejected / paused templates and another product's templates are never sent, whatever is switched on.
        if ($template && ($blocked = $template->sendBlockReason())) {
            Log::warning('WhatsApp template not sent', ['purpose' => $purpose, 'template_id' => $template->id, 'status' => $template->status]);

            return ['success' => false, 'status' => 'blocked_template', 'message' => $blocked];
        }

        // An older settings value naming a BookMizo template (it matches no saved row, e.g. a display name) is not sent either.
        if (!$template && \App\Support\WhatsAppTemplateScope::nameLooksBookMizo((string) $templateName)) {
            Log::warning('WhatsApp template not sent', ['purpose' => $purpose, 'reason' => 'legacy name is a BookMizo template']);

            return ['success' => false, 'status' => 'blocked_template', 'message' => "\"{$templateName}\" is a BookMizo template, not a SocietyFlow one, so it is not sent to residents. Switch on a SocietyFlow template for this notification in Message Templates."];
        }

        // The saved text and the saved variable count must agree: {{1}}..{{n}} with none missing, n = var_count.
        if ($template && trim((string) $template->body_text) !== '') {
            $numbers = \App\Models\WhatsAppTemplate::placeholderNumbers((string) $template->body_text);
            sort($numbers);
            if ($numbers !== range(1, (int) $template->var_count) && !($numbers === [] && (int) $template->var_count === 0)) {
                return ['success' => false, 'status' => 'failed', 'message' => "Not sent: the saved text of \"{$template->displayName()}\" has variables " . ($numbers ? '{{' . implode('}}, {{', $numbers) . '}}' : '(none)') . " but the template is recorded with {$template->var_count}. Open it and check the text."];
            }
        }

        if (!array_is_list($values)) {
            $fields = $template?->variableFields() ?: \App\Support\WhatsAppPurposes::variableMap($purpose, (int) ($template?->var_count ?? count($values)), $societyId);
            // A value the event did not supply is a failure to send, never a placeholder or a guess.
            $missing = array_values(array_filter($fields, fn ($key) => !array_key_exists($key, $values)));
            if ($missing) {
                return ['success' => false, 'status' => 'failed', 'message' => "Not sent: this notification did not supply " . implode(', ', $missing) . ' for the template.'];
            }
        }

        $params = array_is_list($values)
            ? $values
            : array_map(
                fn ($key) => $values[$key] ?? '',
                $template?->variableFields() ?: \App\Support\WhatsAppPurposes::variableMap($purpose, (int) ($template?->var_count ?? count($values)), $societyId)
            );
        // Meta rejects an empty parameter outright.
        $params = array_map(fn ($p) => trim((string) $p) === '' ? '-' : (string) $p, $params);

        if ($template && $template->var_count !== count($params)) {
            $result = ['success' => false, 'message' => "The WhatsApp template for this notification needs {$template->var_count} value(s) but {$purpose} provides " . count($params) . ". Choose a matching template in Settings \u{2192} WhatsApp."];
            if (self::isEnabled($societyId)) {
                self::recordDelivery($purpose, $phone, $societyId, $result);
            }

            return $result;
        }

        $components = empty($params) ? [] : [[
            'type'       => 'body',
            'parameters' => array_map(fn ($p) => ['type' => 'text', 'text' => $p], $params),
        ]];

        // The same notification to the same number goes out once: an event that fires twice, or a queue retry after a send
        // that did succeed, must not message a resident twice. A failed attempt is not remembered, so it can be retried.
        $once = \App\Support\SendOnce::begin('whatsapp', $societyId, $phone, $purpose, (string) json_encode($params));
        if ($once === null) {
            Log::info('Duplicate WhatsApp message suppressed', ['purpose' => $purpose, 'phone' => SmsService::maskPhone($phone)]);

            return ['success' => false, 'skipped' => true, 'status' => 'skipped_duplicate', 'message' => 'Not sent: the same message was already sent to this number a moment ago.'];
        }

        // A synced template has Fast2SMS's integer message_id: send by that. Without one, fall back to name + language.
        $result = $template && $template->message_id
            ? self::sendByMessageId($phone, $template, $params, $societyId, ['purpose' => $purpose])
            : self::sendTemplateMessage($phone, $templateName, $components, $societyId, logContext: ['purpose' => $purpose], language: $template?->language ?: null, templatePhoneNumberId: $template?->phone_number_id);

        \App\Support\SendOnce::finish($once, !empty($result['success']) && ($result['message'] ?? null) !== 'dev_mode' && empty($result['skipped']));

        // sendTemplateMessage() itself still short-circuits to a dev_mode
        // success when WhatsApp is disabled or the API key/phone number id
        // are missing - that is also not a real attempt, so it is excluded
        // here the same way, not logged as either a send or a failure.
        if (($result['message'] ?? null) !== 'dev_mode') {
            self::recordDelivery($purpose, $phone, $societyId, $result);
        }

        return $result;
    }

    /**
     * Settings -> WhatsApp "Send Test": one synced, approved template filled with the values the manager typed (or sample
     * values), so a real message can arrive. Returns dev_mode when WhatsApp is off or incomplete. The caller checks that
     * the number of values matches the template's variable count.
     *
     * @param list<string>|null $values
     */
    public static function sendTestTemplate(\App\Models\WhatsAppTemplate $template, string $phone, ?int $societyId, ?array $values = null): array
    {
        if ($blocked = $template->sendBlockReason()) {
            return ['success' => false, 'status' => 'blocked_template', 'message' => $blocked];
        }

        $params = $values !== null ? array_values($values) : [];
        if ($values === null) {
            for ($i = 1; $i <= $template->var_count; $i++) {
                $params[] = "Test {$i}";
            }
        }
        $components = $params ? [[
            'type'       => 'body',
            'parameters' => array_map(fn ($p) => ['type' => 'text', 'text' => $p], $params),
        ]] : [];

        $result = $template->message_id
            ? self::sendByMessageId($phone, $template, $params, $societyId, ['purpose' => 'test'])
            : self::sendTemplateMessage($phone, $template->name, $components, $societyId, logContext: ['purpose' => 'test'], language: $template->language ?: null, templatePhoneNumberId: $template->phone_number_id);
        if (($result['message'] ?? null) !== 'dev_mode' && empty($result['skipped'])) {
            self::recordDelivery('test', $phone, $societyId, $result);
        }

        return $result;
    }

    /**
     * Same Delivery Log that SmsService::sendDltNotification() writes to
     * (channel='sms') and the email pipeline writes to - this is what makes
     * a WhatsApp notification show up on `/admin/email-logs` at all,
     * instead of only in the application log. Deliberately not called from
     * `sendOtp()`: the OTP SMS route does not log to this table either (high
     * volume, security-sensitive, not a "business notification" an admin
     * reviews), so WhatsApp stays consistent with that existing boundary.
     */
    private static function recordDelivery(string $purpose, string $phone, ?int $societyId, array $result): void
    {
        if ($societyId === null) {
            return;
        }

        try {
            \App\Models\EmailLog::create([
                'society_id'      => $societyId,
                'channel'         => 'whatsapp',
                'recipient_phone' => SmsService::normalizePhone($phone),
                'trigger_action'  => $purpose,
                'status'          => $result['success'] ? 'sent' : 'failed',
                'error_message'   => $result['success'] ? null : ($result['message'] ?? null),
                'sent_at'         => $result['success'] ? now() : null,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to record WhatsApp delivery log', ['purpose' => $purpose, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Free-text session message. Meta rejects this outside the 24h window
     * with its own error, which is passed through rather than hidden -
     * this method does not try to track the window itself.
     */
    public static function sendSessionMessage(string $phone, string $text, ?int $societyId = null): array
    {
        if (!self::isEnabled($societyId)) {
            return ['success' => true, 'message' => 'dev_mode'];
        }

        $apiKey = self::apiKey($societyId);
        $phoneNumberId = self::getSetting('whatsapp_phone_number_id', null, $societyId);

        if (empty($apiKey) || empty($phoneNumberId)) {
            Log::info('WhatsApp not configured - session message not sent', ['phone' => SmsService::maskPhone($phone)]);
            return ['success' => true, 'message' => 'dev_mode'];
        }

        $to = self::toE164($phone, $societyId);
        $url = 'https://www.fast2sms.com/dev/whatsapp-session?' . http_build_query([
            'phone_number_id' => $phoneNumberId,
            'to'              => $to,
        ]);

        return self::curlPost($url, ['type' => 'text', 'text' => $text],
            ['authorization: ' . $apiKey, 'Content-Type: application/json'],
            self::sessionResponseHandler($phone, $apiKey)
        );
    }

    /**
     * The session endpoint's success shape is `{"status": "success"}`, not
     * the template endpoint's `{"messages": [...]}` - two different Fast2SMS
     * products, confirmed from their own separate reference pages. Reusing
     * the template handler here would have silently reported every session
     * send as a failure.
     */
    private static function sessionResponseHandler(string $phone, string $apiKey): callable
    {
        return function ($body, $httpCode) use ($phone, $apiKey) {
            if ($httpCode === 200 && ($body['status'] ?? null) === 'success') {
                return ['success' => true, 'message' => 'WhatsApp message sent'];
            }

            $error = $body['error']['message']
                ?? (is_array($body['message'] ?? null) ? implode(', ', $body['message']) : ($body['message'] ?? "HTTP {$httpCode}"));

            Log::warning('WhatsApp session send rejected', [
                'provider'     => 'fast2sms_whatsapp',
                'purpose'      => 'session',
                'http_status'  => $httpCode,
                'phone'        => SmsService::maskPhone($phone),
                'provider_msg' => SmsService::safeProviderMessage((string) $error, $apiKey),
            ]);

            return ['success' => false, 'message' => \App\Support\WhatsAppProviderError::describe(SmsService::safeProviderMessage((string) $error, $apiKey), (int) $httpCode, $body['error']['code'] ?? null)];
        };
    }

    // ── Shared send path ────────────────────────────────────────────────────

    /**
     * Fast2SMS "Send Template Message (Simple)":
     *   GET /dev/whatsapp?message_id=<int>&phone_number_id=..&numbers=..&variables_values=a|b
     * (docs.fast2sms.com/reference/sendwhatsappmessage). It takes the integer Fast2SMS message_id from the
     * template sync - never the long Meta template_id. Success is {status: true, request_id}.
     *
     * @param list<string> $params
     */
    private static function sendByMessageId(string $phone, \App\Models\WhatsAppTemplate $template, array $params, ?int $societyId, array $logContext): array
    {
        if (!self::isEnabled($societyId)) {
            return ['success' => true, 'message' => 'dev_mode'];
        }

        $apiKey = self::apiKey($societyId);
        // A Fast2SMS template (Message ID) belongs to ONE of the account's WhatsApp numbers; the provider reports which. That
        // number is the one to send from - it is what the Message ID is valid for. The saved Phone Number ID is the fallback
        // for a template typed in by hand, which has no number of its own.
        $phoneNumberId = $template->phone_number_id ?: self::getSetting('whatsapp_phone_number_id', null, $societyId);
        if (empty($apiKey) || empty($phoneNumberId)) {
            Log::info('WhatsApp not configured - message not sent', array_merge($logContext, ['phone' => SmsService::maskPhone($phone)]));

            return ['success' => true, 'message' => 'dev_mode'];
        }

        $url = Fast2SmsService::whatsappUrl((int) $template->message_id, (string) $phoneNumberId, self::toE164($phone, $societyId), $params);

        $handler = function ($body, $httpCode) use ($phone, $apiKey, $logContext) {
            if ($httpCode === 200 && filter_var($body['status'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                return ['success' => true, 'message' => 'WhatsApp message sent', 'request_id' => $body['request_id'] ?? null, 'raw' => SmsService::rawResponse($body, $apiKey)];
            }

            $error = $body['error']['message']
                ?? (is_array($body['message'] ?? null) ? implode(', ', $body['message']) : ($body['message'] ?? "HTTP {$httpCode}"));
            Log::warning('WhatsApp send rejected', array_merge($logContext, [
                'provider' => 'fast2sms_whatsapp', 'http_status' => $httpCode,
                'phone' => SmsService::maskPhone($phone), 'provider_msg' => SmsService::safeProviderMessage((string) $error, $apiKey),
            ]));

            return ['success' => false, 'message' => \App\Support\WhatsAppProviderError::describe(SmsService::safeProviderMessage((string) $error, $apiKey), (int) $httpCode, $body['error']['code'] ?? null), 'http_status' => $httpCode, 'raw' => SmsService::rawResponse($body, $apiKey)];
        };

        // The same handling for the real transport and a test's mock: status 0 means nothing came back.
        [$body, $httpCode] = self::$transportOverride !== null
            ? (self::$transportOverride)($url, [], ['authorization: ' . $apiKey])
            : Fast2SmsService::get($url, (string) $apiKey);

        if ($httpCode === 0) {
            if (!empty($body['skipped'])) {
                Log::notice('WhatsApp suppressed', ['host' => 'www.fast2sms.com']);

                return ['success' => false, 'skipped' => true, 'message' => \App\Support\ExternalMessaging::SUPPRESSED_MESSAGE];
            }

            return ['success' => false, 'message' => 'WhatsApp service unavailable: ' . \App\Support\WhatsAppProviderError::forHttp(0) . ' (' . SmsService::safeProviderMessage((string) ($body['transport_error'] ?? 'no response')) . ')'];
        }

        return $handler($body, $httpCode);
    }

    private static function sendTemplateMessage(string $phone, ?string $templateName, array $components, ?int $societyId, array $logContext, ?string $language = null, ?string $templatePhoneNumberId = null): array
    {
        if (!self::isEnabled($societyId)) {
            return ['success' => true, 'message' => 'dev_mode'];
        }

        $apiKey = self::apiKey($societyId);
        $phoneNumberId = $templatePhoneNumberId ?: self::getSetting('whatsapp_phone_number_id', null, $societyId);
        $version = self::getSetting('whatsapp_api_version', 'v26.0', $societyId);
        // A synced template's own language wins: Meta matches name AND
        // language, and an account's templates are often plain 'en' while
        // the saved default says 'en_US'.
        $language = $language ?: self::getSetting('whatsapp_template_language', 'en_US', $societyId);

        if (empty($apiKey) || empty($phoneNumberId)) {
            Log::info('WhatsApp not configured - message not sent', array_merge($logContext, ['phone' => SmsService::maskPhone($phone)]));
            return ['success' => true, 'message' => 'dev_mode'];
        }

        if (empty($templateName)) {
            return ['success' => false, 'message' => 'No WhatsApp template name configured for this notification type.'];
        }

        $to = self::toE164($phone, $societyId);
        $url = "https://www.fast2sms.com/dev/whatsapp/{$version}/{$phoneNumberId}/messages";

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $to,
            'type'              => 'template',
            'template'          => array_filter([
                'name'     => $templateName,
                'language' => ['code' => $language],
                'components' => $components ?: null,
            ], fn ($v) => $v !== null),
        ];

        return self::curlPost($url, $payload,
            ['authorization: ' . $apiKey, 'Content-Type: application/json'],
            self::responseHandler($phone, $apiKey, $logContext)
        );
    }

    /**
     * Fast2SMS's WhatsApp endpoints do not use the SMS API's `return: true`
     * shape - success is an HTTP 200 with a `messages` array carrying each
     * message's id. Anything else is read as a rejection and the provider's
     * own error text (sanitised) is surfaced, never assumed to be success.
     */
    private static function responseHandler(string $phone, string $apiKey, array $logContext): callable
    {
        return function ($body, $httpCode) use ($phone, $apiKey, $logContext) {
            if ($httpCode === 200 && !empty($body['messages'][0]['id'] ?? null)) {
                return ['success' => true, 'message' => 'WhatsApp message sent'];
            }

            $error = $body['error']['message']
                ?? (is_array($body['message'] ?? null) ? implode(', ', $body['message']) : ($body['message'] ?? "HTTP {$httpCode}"));

            Log::warning('WhatsApp send rejected', array_merge($logContext, [
                'provider'     => 'fast2sms_whatsapp',
                'http_status'  => $httpCode,
                'phone'        => SmsService::maskPhone($phone),
                'provider_msg' => SmsService::safeProviderMessage((string) $error, $apiKey),
            ]));

            return ['success' => false, 'message' => \App\Support\WhatsAppProviderError::describe(SmsService::safeProviderMessage((string) $error, $apiKey), (int) $httpCode, $body['error']['code'] ?? null)];
        };
    }

    public static function isEnabled(?int $societyId): bool
    {
        return (bool) self::getSetting('whatsapp_enabled', false, $societyId);
    }

    /**
     * The key WhatsApp sends with: its own, or - when that field was left empty - the Fast2SMS key saved in SMS settings
     * (one Fast2SMS account serves both). Never logged or returned to a page.
     */
    public static function apiKey(?int $societyId): string
    {
        $own = (string) self::getSetting('whatsapp_api_key', '', $societyId);

        return $own !== '' ? $own : SmsService::apiKey($societyId);
    }

    /** True when WhatsApp is using the SMS key because no key of its own is saved (for the settings page wording). */
    public static function usesSmsKey(?int $societyId): bool
    {
        return (string) self::getSetting('whatsapp_api_key', '', $societyId) === '' && SmsService::apiKey($societyId) !== '';
    }

    /**
     * Confirms the saved key and Phone Number ID belong together, using the documented read-only business-profile
     * request. Reports "skipped" when messaging is switched off (nothing was contacted). Proves nothing about delivery.
     *
     * @return array{ok: bool, message: string, detail?: ?string, skipped?: bool}
     */
    public static function checkConnection(?int $societyId): array
    {
        $apiKey = self::apiKey($societyId);
        $phoneNumberId = (string) self::getSetting('whatsapp_phone_number_id', '', $societyId);
        if ($apiKey === '' || $phoneNumberId === '') {
            return ['ok' => false, 'message' => 'Save the API key and the Phone Number ID first.'];
        }

        $version = (string) (self::getSetting('whatsapp_api_version', 'v26.0', $societyId) ?: 'v26.0');

        return Fast2SmsAccount::whatsappProfile($apiKey, $phoneNumberId, $version);
    }

    /**
     * "Verify configuration": three separate, read-only checks that send no message and say exactly what each proved.
     *  1. local     - what is saved is complete and shaped like the provider's identifiers (no network);
     *  2. provider  - Fast2SMS accepts the key together with the Phone Number ID (business-profile lookup);
     *  3. account   - the saved Phone Number ID is one of the WhatsApp numbers Fast2SMS reports for the account, and not
     *                 the Business Account (WABA) ID or another ID (template lookup).
     * `verified` is true only when all three passed. None of this says anything about delivery. A check that was not
     * made (messaging switched off on the server, or an earlier check failed) has `ok` = null and is never shown as passed.
     *
     * @return array{local: array, provider: array, account: array, verified: bool}
     */
    public static function verify(?int $societyId): array
    {
        $apiKey = self::apiKey($societyId);
        $pnid = trim((string) self::getSetting('whatsapp_phone_number_id', '', $societyId));
        $waba = trim((string) self::getSetting('whatsapp_waba_id', '', $societyId));

        $problems = [];
        if ($apiKey === '') {
            $problems[] = 'No Fast2SMS API key is saved (WhatsApp or SMS settings).';
        }
        if ($pnid === '') {
            $problems[] = 'No Phone Number ID is saved.';
        } elseif (!ctype_digit($pnid) || strlen($pnid) < 8) {
            $problems[] = 'The Phone Number ID must be the long numeric ID Fast2SMS shows for your WhatsApp number.';
        } elseif (preg_match('/^(91)?[6-9][0-9]{9}$/', $pnid)) {
            $problems[] = 'The Phone Number ID looks like a mobile number. It is the long numeric ID, not the number itself.';
        } elseif ($waba !== '' && $pnid === $waba) {
            $problems[] = 'The Phone Number ID is the same as the Business Account (WABA) ID. They are different IDs: use the Phone Number ID of your WhatsApp number.';
        }

        $local = ['ok' => $problems === [], 'message' => $problems === [] ? 'Saved settings are complete.' : implode(' ', $problems)];
        $notRun = ['ok' => null, 'message' => 'Not run.'];
        if (!$local['ok']) {
            return ['local' => $local, 'provider' => $notRun, 'account' => $notRun, 'numbers' => [], 'verified' => false];
        }

        $conn = self::checkConnection($societyId);
        $provider = !empty($conn['skipped'])
            ? ['ok' => null, 'message' => $conn['message']]
            : ['ok' => (bool) $conn['ok'], 'message' => $conn['message'], 'detail' => $conn['detail'] ?? null];

        $found = Fast2SmsAccount::whatsappNumbers($apiKey);
        $numbers = $found['numbers'] ?? [];
        // "Bookmizo (number ending 5118, ID 1209...)" - what the provider itself reports for each number on this key
        $describe = fn (array $n) => ($n['verified_name'] !== '' ? $n['verified_name'] . ' ' : '') . '(number ending ' . ($n['number_tail'] ?: '????') . ', Phone Number ID ' . $n['phone_number_id'] . ')';

        if (!empty($found['skipped'])) {
            $account = ['ok' => null, 'message' => $found['message']];
        } elseif (!$found['ok']) {
            $account = ['ok' => false, 'message' => $found['message'], 'detail' => $found['detail'] ?? null];
        } else {
            $ids = array_column($numbers, 'phone_number_id');
            $isWaba = in_array($pnid, array_filter(array_column($numbers, 'waba_id')), true);
            $list = implode('; ', array_map($describe, $numbers));
            $mine = collect($numbers)->firstWhere('phone_number_id', $pnid);
            $account = match (true) {
                $mine !== null              => ['ok' => true, 'message' => 'The saved Phone Number ID is the account\'s ' . $describe($mine) . '.'
                                                . (($mine['connection_status'] ?? '') !== '' && strtoupper($mine['connection_status']) !== 'CONNECTED' ? ' The provider reports it as ' . $mine['connection_status'] . '.' : '')],
                $isWaba                     => ['ok' => false, 'message' => 'The saved "Phone Number ID" is a Business Account (WABA) ID. This key\'s WhatsApp numbers: ' . $list . '.'],
                $ids === []                 => ['ok' => false, 'message' => 'Fast2SMS reports no WhatsApp number on this account.'],
                default                     => ['ok' => false, 'message' => 'The saved Phone Number ID is not one of this key\'s WhatsApp numbers - check each digit. This key\'s numbers: ' . $list . '.'],
            };
        }

        // a 404 on the profile step with the real list at hand: name the way out right there
        if ($provider['ok'] === false && $numbers && str_contains((string) ($provider['detail'] ?? ''), 'HTTP 404')) {
            $provider['message'] .= ' This key\'s WhatsApp numbers: ' . implode('; ', array_map($describe, $numbers)) . '.';
        }

        return ['local' => $local, 'provider' => $provider, 'account' => $account, 'numbers' => $numbers,
            'verified' => $provider['ok'] === true && $account['ok'] === true];
    }

    /** Meta's API wants a country-coded number with no leading '+'. India default. */
    private static function toE164(string $phone, ?int $societyId = null): string
    {
        $digits = SmsService::normalizePhone($phone);
        $code = preg_replace('/\D+/', '', (string) self::getSetting('whatsapp_country_code', '91', $societyId)) ?: '91';

        return strlen($digits) === 10 ? $code . $digits : $digits;
    }

    private static function getSetting(string $key, $default, ?int $societyId)
    {
        if ($societyId) {
            $val = SystemSetting::get('whatsapp', $key, null, $societyId);
            if ($val !== null && $val !== '') return $val;
        }

        return SystemSetting::get('whatsapp', $key, $default, null);
    }

    /** Same transport the SMS service uses: real curl, or the test override. */
    private static function curlPost(string $url, array $data, array $headers, callable $handler, bool $get = false): array
    {
        if (self::$transportOverride !== null) {
            [$body, $httpCode] = (self::$transportOverride)($url, $data, $headers);

            return $handler($body, $httpCode);
        }

        if (!\App\Support\ExternalMessaging::enabled()) {
            Log::notice('WhatsApp suppressed', ['host' => parse_url($url, PHP_URL_HOST)]);

            return ['success' => false, 'skipped' => true, 'message' => \App\Support\ExternalMessaging::SUPPRESSED_MESSAGE];
        }

        \App\Support\ExternalMessaging::beforeOutbound('WhatsApp', (string) parse_url($url, PHP_URL_HOST));

        try {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 15,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_HTTPHEADER     => $headers,
            ] + ($get ? [CURLOPT_HTTPGET => true] : [CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode($data)]));
            $result   = curl_exec($ch);
            $curlErr  = curl_error($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($curlErr) {
                Log::error('WhatsApp transport error', ['host' => parse_url($url, PHP_URL_HOST), 'error' => mb_substr($curlErr, 0, 120)]);
                return ['success' => false, 'message' => 'WhatsApp service unavailable: ' . $curlErr];
            }

            $body = json_decode($result, true) ?? ['raw' => $result];
            return $handler($body, $httpCode);
        } catch (\Throwable $e) {
            Log::error('WhatsApp exception', ['class' => get_class($e)]);
            return ['success' => false, 'message' => 'WhatsApp service error: ' . $e->getMessage()];
        }
    }
}
