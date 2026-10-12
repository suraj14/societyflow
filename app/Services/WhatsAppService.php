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
        $templateName = self::getSetting("whatsapp_{$purpose}_template_name", null, $societyId);

        if (empty($templateName)) {
            // No real attempt was possible - same as SmsService::sendDltNotification's
            // own "not configured" early return, this does not write a
            // Delivery Log row either; an admin who has not set up WhatsApp
            // at all should not see a growing list of "failed" rows for it.
            return ['success' => false, 'message' => "No WhatsApp template configured for '{$purpose}'. Set one in Settings \u{2192} WhatsApp."];
        }

        $template = \App\Models\WhatsAppTemplate::where('society_id', $societyId)->where('name', $templateName)->first();

        $params = array_is_list($values)
            ? $values
            : array_map(
                fn ($key) => $values[$key] ?? '',
                \App\Support\WhatsAppPurposes::variableMap($purpose, (int) ($template?->var_count ?? count($values)), $societyId)
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

        // A synced template has Fast2SMS's integer message_id: send by that. Without one, fall back to name + language.
        $result = $template && $template->message_id
            ? self::sendByMessageId($phone, $template, $params, $societyId, ['purpose' => $purpose])
            : self::sendTemplateMessage($phone, $templateName, $components, $societyId, logContext: ['purpose' => $purpose], language: $template?->language ?: null);

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
     * Settings -> WhatsApp "Send Test WhatsApp": one synced, approved
     * template, filled with sample values, so the manager can see a real
     * message arrive. Returns dev_mode when WhatsApp is off or incomplete.
     */
    public static function sendTestTemplate(\App\Models\WhatsAppTemplate $template, string $phone, ?int $societyId): array
    {
        $params = [];
        for ($i = 1; $i <= $template->var_count; $i++) {
            $params[] = "Test {$i}";
        }
        $components = $params ? [[
            'type'       => 'body',
            'parameters' => array_map(fn ($p) => ['type' => 'text', 'text' => $p], $params),
        ]] : [];

        $result = $template->message_id
            ? self::sendByMessageId($phone, $template, $params, $societyId, ['purpose' => 'test'])
            : self::sendTemplateMessage($phone, $template->name, $components, $societyId, logContext: ['purpose' => 'test'], language: $template->language ?: null);
        if (($result['message'] ?? null) !== 'dev_mode') {
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

        $apiKey = self::getSetting('whatsapp_api_key', null, $societyId);
        $phoneNumberId = self::getSetting('whatsapp_phone_number_id', null, $societyId);

        if (empty($apiKey) || empty($phoneNumberId)) {
            Log::info('WhatsApp not configured - session message not sent', ['phone' => SmsService::maskPhone($phone)]);
            return ['success' => true, 'message' => 'dev_mode'];
        }

        $to = self::toE164($phone);
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

            return ['success' => false, 'message' => SmsService::safeProviderMessage((string) $error, $apiKey)];
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

        $apiKey = self::getSetting('whatsapp_api_key', null, $societyId);
        $phoneNumberId = self::getSetting('whatsapp_phone_number_id', null, $societyId);
        if (empty($apiKey) || empty($phoneNumberId)) {
            Log::info('WhatsApp not configured - message not sent', array_merge($logContext, ['phone' => SmsService::maskPhone($phone)]));

            return ['success' => true, 'message' => 'dev_mode'];
        }

        // pipe is the separator, so it cannot appear inside a value
        $values = implode('|', array_map(fn ($p) => str_replace('|', '/', (string) $p), $params));
        $query = ['message_id' => (int) $template->message_id, 'phone_number_id' => $phoneNumberId, 'numbers' => self::toE164($phone)];
        if ($values !== '') {
            $query['variables_values'] = $values;
        }

        $handler = function ($body, $httpCode) use ($phone, $apiKey, $logContext) {
            if ($httpCode === 200 && filter_var($body['status'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                return ['success' => true, 'message' => 'WhatsApp message sent', 'request_id' => $body['request_id'] ?? null];
            }

            $error = $body['error']['message']
                ?? (is_array($body['message'] ?? null) ? implode(', ', $body['message']) : ($body['message'] ?? "HTTP {$httpCode}"));
            Log::warning('WhatsApp send rejected', array_merge($logContext, [
                'provider' => 'fast2sms_whatsapp', 'http_status' => $httpCode,
                'phone' => SmsService::maskPhone($phone), 'provider_msg' => SmsService::safeProviderMessage((string) $error, $apiKey),
            ]));

            return ['success' => false, 'message' => SmsService::safeProviderMessage((string) $error, $apiKey), 'http_status' => $httpCode];
        };

        return self::curlPost('https://www.fast2sms.com/dev/whatsapp?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986),
            [], ['authorization: ' . $apiKey], $handler, get: true);
    }

    private static function sendTemplateMessage(string $phone, ?string $templateName, array $components, ?int $societyId, array $logContext, ?string $language = null): array
    {
        if (!self::isEnabled($societyId)) {
            return ['success' => true, 'message' => 'dev_mode'];
        }

        $apiKey = self::getSetting('whatsapp_api_key', null, $societyId);
        $phoneNumberId = self::getSetting('whatsapp_phone_number_id', null, $societyId);
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

        $to = self::toE164($phone);
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

            return ['success' => false, 'message' => SmsService::safeProviderMessage((string) $error, $apiKey)];
        };
    }

    public static function isEnabled(?int $societyId): bool
    {
        return (bool) self::getSetting('whatsapp_enabled', false, $societyId);
    }

    /** Meta's API wants a country-coded number with no leading '+'. India default. */
    private static function toE164(string $phone): string
    {
        $digits = SmsService::normalizePhone($phone);

        return strlen($digits) === 10 ? '91' . $digits : $digits;
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

            return ['success' => false, 'message' => \App\Support\ExternalMessaging::SUPPRESSED_MESSAGE];
        }

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
