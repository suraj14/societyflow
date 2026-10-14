<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Log;

class SmsService
{
    /**
     * Result contract (backward compatible). Every send returns the historical `success` / `message` pair PLUS:
     *   status - 'sent' (the provider accepted the request), 'skipped_disabled' (SMS switched off, or the global
     *            EXTERNAL_MESSAGING_ENABLED kill switch), 'skipped_not_configured' (key/template missing), 'failed'
     *   sent   - true ONLY for 'sent'. Never treat `success` alone as "a message went out": a skip used to
     *            return success=true / message='dev_mode'. 'sent' means accepted by the provider, not delivered.
     */
    public const STATUS_SENT = 'sent';
    public const STATUS_SKIPPED_DISABLED = 'skipped_disabled';
    public const STATUS_SKIPPED_NOT_CONFIGURED = 'skipped_not_configured';
    /** The same notification already went to this number a moment ago (see App\Support\SendOnce). */
    public const STATUS_SKIPPED_DUPLICATE = 'skipped_duplicate';
    public const STATUS_FAILED = 'failed';

    /** A send that was deliberately not attempted. Keeps the legacy success=true / 'dev_mode' shape. */
    private static function skipped(string $status, array $extra = []): array
    {
        return ['success' => true, 'message' => 'dev_mode', 'status' => $status, 'sent' => false] + $extra;
    }

    /** Adds status/sent to any result that does not carry them yet (provider handlers return success/message only). */
    public static function stamp(array $result): array
    {
        if (!isset($result['status'])) {
            $result['status'] = !empty($result['success']) ? self::STATUS_SENT : self::STATUS_FAILED;
        }
        $result['sent'] = $result['status'] === self::STATUS_SENT;

        return $result;
    }

    /** True when the result is a skip (nothing was attempted), whether or not it reports success for old callers. */
    public static function wasSkipped(array $result): bool
    {
        return str_starts_with((string) ($result['status'] ?? ''), 'skipped_') || ($result['message'] ?? null) === 'dev_mode';
    }

    /**
     * Delivery Log row for an attempt that never left the building, so an admin sees "Skipped - not sent" instead of
     * nothing. email_logs.status cannot hold a new value without a table rebuild, so it uses `failed` with a
     * "Skipped:" marker and retry_count=3 (the views render the marker as "Skipped" and offer no Retry).
     */
    public static function recordSkipped(string $purpose, string $phone, ?int $societyId, string $reason): void
    {
        if ($societyId === null) {
            return;
        }
        try {
            \App\Models\EmailLog::create([
                'society_id'      => $societyId,
                'channel'         => 'sms',
                'recipient_phone' => $phone,
                'trigger_action'  => $purpose,
                'status'          => 'failed',
                'error_message'   => \App\Models\EmailLog::SKIPPED_PREFIX . $reason,
                'retry_count'     => 3,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to record skipped SMS', ['purpose' => $purpose, 'error' => $e->getMessage()]);
        }
    }
    /**
     * Send OTP via the configured SMS provider.
     * Supports: fast2sms, bulksmsplans, custom
     */
    public static function sendOtp(string $phone, string $otp, ?int $societyId = null, ?string $forceRoute = null): array
    {
        return self::stamp(self::dispatchOtp($phone, $otp, $societyId, $forceRoute));
    }

    private static function dispatchOtp(string $phone, string $otp, ?int $societyId, ?string $forceRoute): array
    {
        // Every send path (OTP login, Test SMS) goes through here, so the
        // number is normalised once, here. Fast2SMS takes a bare 10-digit
        // Indian mobile; a "+91 ..." or "91..." form is rejected.
        $phone = self::normalizePhone($phone);

        $enabled  = self::getSetting('sms_enabled', false, $societyId);
        $provider = self::getSetting('sms_provider', 'fast2sms', $societyId);

        // "Integration active" is the master switch. A key in the server environment used to turn it on again for OTP
        // (and behaved differently once the configuration was cached); it no longer can: off means nothing is sent.
        if (!$enabled) {
            Log::info("SMS disabled - no message sent", ["phone" => self::maskPhone($phone)]);
            return self::skipped(self::STATUS_SKIPPED_DISABLED, ['otp' => $otp]);
        }

        // A forced route is for the Settings "Test OTP"/"Test DLT SMS"
        // buttons: each must exercise its own path regardless of which one is
        // currently saved, so the admin can verify both before choosing.
        if ($forceRoute !== null && $provider === 'fast2sms') {
            $apiKey = (string) self::getSetting('fast2sms_api_key', '', $societyId);
            if ($apiKey === '') {
                return self::skipped(self::STATUS_SKIPPED_NOT_CONFIGURED, ['otp' => $otp]);
            }
            return match ($forceRoute) {
                'dlt'   => self::sendViaFast2SmsDlt($phone, $otp, $apiKey, $societyId),
                'quick' => self::sendViaFast2SmsQuick($phone, "Test message from SocietyFlow: {$otp}", $apiKey),
                default => self::sendViaFast2SmsOtp($phone, $otp, $apiKey, $societyId),
            };
        }

        return match ($provider) {
            'bulksmsplans' => self::sendViaBulkSmsPlans($phone, $otp, $societyId),
            'custom'       => self::sendViaCustom($phone, $otp, $societyId),
            default        => self::sendViaFast2Sms($phone, $otp, $societyId),
        };
    }

    /**
     * A DLT notification for one purpose - 'visitor', 'access_pass' or
     * 'notification' - gated by that purpose's own `{purpose}_sms_enabled`
     * Settings toggle, independent of the OTP channel above.
     *
     * This is the infrastructure those toggles exist for: built and tested,
     * but not yet called from a visitor-approval, access-pass-created or
     * other event. A caller still needs to be added at each real trigger
     * point before a toggle here causes an actual SMS to go out - flipping
     * the toggle alone sends nothing today.
     *
     * Fast2SMS's DLT template is one fixed, pre-approved piece of text with
     * a single variable slot (see sendViaFast2SmsDlt) - $message fills that
     * slot, so it must already match the wording the registered template
     * expects, not an arbitrary sentence.
     *
     * When the society has an active Message Templates -> SMS template for the notification
     * ($templatePurpose: notice | gate_pass | document), that template is rendered from $values (the named values the
     * event supplies - see App\Support\TemplateVariables) and $message is only the legacy single-slot text, used when no
     * such template exists.
     *
     * @param array<string,string> $values
     */
    public static function sendDltNotification(string $purpose, string $phone, string $message, ?int $societyId = null, bool $force = false, ?string $templatePurpose = null, array $values = []): array
    {
        $phone = self::normalizePhone($phone);

        // $force: the Settings "Send Test SMS" button, which must exercise
        // the template even before notifications are switched on.
        if (!$force && !self::getSetting("{$purpose}_sms_enabled", false, $societyId)) {
            Log::info("{$purpose} SMS disabled - no message sent", ['purpose' => $purpose, 'phone' => self::maskPhone($phone)]);
            return self::skipped(self::STATUS_SKIPPED_DISABLED);
        }

        // "Integration active" is the master switch: with it off, nothing goes out - notifications included - and every
        // saved setting stays as it is.
        if (!self::getSetting('sms_enabled', false, $societyId)) {
            Log::info("SMS integration is off - {$purpose} notification not sent", ['purpose' => $purpose, 'phone' => self::maskPhone($phone)]);

            return self::skipped(self::STATUS_SKIPPED_DISABLED);
        }

        $apiKey = self::getSetting('fast2sms_api_key', null, $societyId);
        if (empty($apiKey)) {
            Log::info('Fast2SMS not configured - notification not sent', ['purpose' => $purpose, 'phone' => self::maskPhone($phone)]);
            self::recordSkipped($purpose, $phone, $societyId, 'Fast2SMS API key is not configured');

            return self::skipped(self::STATUS_SKIPPED_NOT_CONFIGURED);
        }

        // The notification template chosen in Settings -> SMS, if any;
        // otherwise the one DLT template saved for OTP (the original
        // single-template behaviour, kept so nothing that worked stops).
        // A Message Templates -> SMS template for this purpose wins; its Message ID decides DLT or Quick SMS.
        $templatePurpose ??= self::templatePurpose($purpose);

        // The same notification to the same number is sent once: an event that fires twice, or a retry after a send that
        // did succeed, must not text a resident twice. A failed attempt is not remembered, so it can be retried.
        $once = null;
        if (!$force) {
            $once = \App\Support\SendOnce::begin('sms', $societyId, $phone, $templatePurpose, (string) json_encode($values ?: $message));
            if ($once === null) {
                Log::info('Duplicate SMS suppressed', ['purpose' => $templatePurpose, 'phone' => self::maskPhone($phone)]);

                return ['success' => false, 'message' => 'Not sent: the same message was already sent to this number a moment ago.', 'status' => self::STATUS_SKIPPED_DUPLICATE, 'sent' => false];
            }
        }

        $smsTemplate = \App\Models\SmsTemplate::forPurpose($societyId, $templatePurpose);
        $result = $smsTemplate
            ? self::sendByTemplate($templatePurpose, $phone, $values, $societyId, $message)
            : self::sendViaFast2SmsDlt(
                $phone, $message, $apiKey, $societyId,
                self::getSetting('fast2sms_notification_template_id', null, $societyId),
                self::getSetting('fast2sms_notification_sender_id', null, $societyId),
                false,
            );

        // email_logs is now the multi-channel delivery log (see the
        // 2026_10_07 migrations) - recording here is what makes an SMS
        // notification visible on the same Delivery Logs admin screen as
        // email, instead of only in the application log file. The full
        // phone number, not masked, is stored: this is an authenticated,
        // society-scoped admin record of who a message went to - the same
        // thing recipient_email already is for email rows - not the
        // application log, where CLAUDE.md's masking rule still applies
        // unchanged (the Log::info/warning calls above and inside
        // sendViaFast2SmsDlt still use maskPhone()).
        $result = self::stamp($result);
        \App\Support\SendOnce::finish($once, $result['sent']);
        if ($societyId !== null && self::wasSkipped($result)) {
            self::recordSkipped($purpose, $phone, $societyId, (string) ($result['message'] ?? 'not sent'));

            return $result;
        }

        if ($societyId !== null) {
            try {
                \App\Models\EmailLog::create([
                    'society_id'      => $societyId,
                    'channel'         => 'sms',
                    'recipient_phone' => $phone,
                    'trigger_action'  => $purpose,
                    'status'          => $result['success'] ? 'sent' : 'failed',
                    'error_message'   => $result['success'] ? null : ($result['message'] ?? null),
                    'sent_at'         => $result['success'] ? now() : null,
                ]);
            } catch (\Throwable $e) {
                // Logging the delivery is never allowed to turn a real send
                // into a reported failure.
                Log::error('Failed to record SMS delivery log', ['purpose' => $purpose, 'error' => $e->getMessage()]);
            }
        }

        return $result;
    }
    /**
     * What the SMS settings give every send (for Message Templates, to say how each template will go out). Never the key.
     *
     * @return array{has_key: bool, sender_id: string, default_message_id: string, enabled: bool}
     */
    public static function routing(?int $societyId): array
    {
        return [
            'has_key'            => self::apiKey($societyId) !== '',
            'sender_id'          => (string) self::getSetting('fast2sms_sender_id', '', $societyId),
            'default_message_id' => (string) self::getSetting('fast2sms_template_id', '', $societyId),
            'enabled'            => (bool) self::getSetting('sms_enabled', false, $societyId),
        ];
    }

    /** The saved Fast2SMS API key for a society (falling back to the platform key); '' when none. Never logged. */
    public static function apiKey(?int $societyId): string
    {
        return (string) self::getSetting('fast2sms_api_key', '', $societyId);
    }

    /** The Message Templates purpose a notification purpose reads its SMS template from. */
    private static function templatePurpose(string $purpose): string
    {
        return match ($purpose) {
            'visitor'     => 'visitor',
            'access_pass' => 'gate_pass',
            default       => 'notice',
        };
    }

    /** Bare digits, with an Indian country code stripped down to 10 digits. */
    public static function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
            return substr($digits, 2);
        }

        if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            return substr($digits, 1);
        }

        return $digits;
    }

    /**
     * A provider or transport message made safe to show an admin: any
     * authorization header text, and any long token-like run, is removed,
     * and the length is capped. The stored key itself is scrubbed too, in
     * case a provider ever echoed it back.
     */
    public static function safeProviderMessage(string $message, ?string $secret = null, bool $maskNumbers = false): string
    {
        if ($secret !== null && $secret !== '') {
            $message = str_replace($secret, '[redacted]', $message);
        }

        $message = preg_replace('/authorization\s*:\s*(?:(?:bearer|basic)\s+)?\S+/i', 'authorization: [redacted]', $message) ?? $message;
        // A long run of letters and digits looks like a key or token. A plain snake_case word is a field or endpoint name
        // (e.g. whatsapp_business_profile) and is the very thing a provider error needs to show, so it stays.
        $message = preg_replace_callback('/[A-Za-z0-9_\-]{20,}/', fn ($m) => preg_match('/^[a-z]+(_[a-z]+)+$/', $m[0]) ? $m[0] : '[redacted]', $message) ?? $message;
        // A provider that echoes the request back ("invalid OTP 4821") must not put a live one-time code, or a phone number,
        // into a log or a message: on the OTP route every run of four or more digits is hidden.
        if ($maskNumbers) {
            $message = preg_replace('/\d{4,}/', '[number]', $message) ?? $message;
        }
        $message = trim(preg_replace('/\s+/', ' ', $message) ?? '');

        return mb_substr($message !== '' ? $message : 'no response from provider', 0, 160);
    }

    /** The last four digits only - the only part of a number that may reach a log or a message. */
    public static function maskPhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        return str_repeat('*', max(0, strlen($digits) - 4)) . substr($digits, -4);
    }

    // ── Fast2SMS ──────────────────────────────────────────────────────────────

    /**
     * Fast2SMS has two products behind one provider, with separate settings:
     *  - route 'otp': the Smart OTP API (/dev/otp/send). Needs the approved
     *    OTP ID (fast2sms_otp_id). Sends no DLT fields.
     *  - route 'dlt': the DLT bulk API (/dev/bulkV2). Needs the sender ID and
     *    the DLT template ID. Never uses the OTP ID.
     */
    private static function sendViaFast2Sms(string $phone, string $otp, ?int $societyId): array
    {
        $apiKey = self::getSetting('fast2sms_api_key', null, $societyId);
        $route  = self::getSetting('fast2sms_route', 'dlt', $societyId);

        if (empty($apiKey)) {
            // Never write the OTP itself to the log (CLAUDE.md §7); dev mode
            // hands it back in the response instead.
            Log::info("Fast2SMS not configured - no message sent", ["provider" => "fast2sms", "phone" => self::maskPhone($phone)]);
            return self::skipped(self::STATUS_SKIPPED_NOT_CONFIGURED, ['otp' => $otp]);
        }

        // Advanced: the Fast2SMS OTP service (needs an OTP ID). Everything else is routed automatically.
        if ($route === 'otp') {
            return self::sendViaFast2SmsOtp($phone, $otp, $apiKey, $societyId);
        }

        return self::sendByTemplate('login_otp', $phone, ['otp' => $otp], $societyId, "Your verification code is {$otp}. Do not share it.");
    }

    /**
     * Automatic routing for one SMS purpose:
     *  - the purpose's template (or, failing that, the default Message ID) gives a Fast2SMS Message ID AND a Sender ID
     *    is set  ->  DLT route, with the template's variables piped in order;
     *  - otherwise  ->  Quick SMS with the rendered text (random sender - fine for testing, not for production).
     *
     * @param array<string|int,string> $values   named or listed variable values
     * @param string                   $fallbackText  used when the purpose has no template of its own
     */
    public static function sendByTemplate(string $purpose, string $phone, array $values, ?int $societyId, string $fallbackText): array
    {
        $apiKey = (string) self::getSetting('fast2sms_api_key', '', $societyId);
        if ($apiKey === '') {
            return self::skipped(self::STATUS_SKIPPED_NOT_CONFIGURED, ['otp' => reset($values) ?: '']);
        }
        if ($bad = self::invalidNumber($phone)) {
            return $bad;
        }

        $template = \App\Models\SmsTemplate::forPurpose($societyId, $purpose);
        // The template's own Message ID wins; the default from SMS settings is only the fallback for a template without one.
        $messageId = (string) ($template?->dlt_message_id ?: self::getSetting('fast2sms_template_id', '', $societyId));
        $senderId = (string) self::getSetting('fast2sms_sender_id', '', $societyId);

        if ($template) {
            // every template can use the society's name; the event supplies the rest
            if ($societyId && !array_key_exists('society_name', $values)) {
                $values['society_name'] = (string) \App\Models\Society::whereKey($societyId)->value('name');
            }
            $rendered = Fast2SmsService::render((string) $template->body, $values);
        } else {
            $rendered = ['text' => $fallbackText, 'variables' => array_values($values), 'missing' => []];
        }

        // A placeholder with no value is never sent as a placeholder or a blank.
        if ($rendered['missing']) {
            return self::failedSend('Not sent: there is no value for ' . implode(', ', array_map(fn ($n) => '{{' . $n . '}}', $rendered['missing'])) . '.', 'missing_variables');
        }

        if ($messageId !== '') {
            // A Message ID means this message must go out as that approved DLT text. It is never quietly downgraded to
            // Quick SMS: if the DLT settings are incomplete, say so.
            // 15+ digits is the telecom DLT Template ID, which Fast2SMS rejects here.
            if (strlen(preg_replace('/\D+/', '', $messageId)) >= 15) {
                return self::failedSend('The saved SMS template is a DLT Template ID, but Fast2SMS needs its Message ID. Choose the template again in Settings > SMS / OTP.', 'invalid_message_id');
            }
            if ($senderId === '') {
                return self::failedSend('Not sent: a Message ID is set, so this goes out as a DLT SMS, but there is no Sender ID. Add the Sender ID in SMS settings.', 'sender_missing');
            }

            return self::fast2smsGet(Fast2SmsService::dltUrl($senderId, $messageId, $rendered['variables'], $phone), $apiKey,
                self::fast2smsResponseHandler($phone, 'dlt', $apiKey));
        }

        return self::sendViaFast2SmsQuick($phone, $rendered['text'], $apiKey);
    }

    /**
     * Settings -> SMS "Send Test SMS": the typed text, routed like any other SMS. With a Sender ID and a default Message
     * ID saved it goes on the DLT route and the text (split on "|") fills the template's variables; otherwise it is
     * Quick SMS. Honours the Enabled switch and the messaging kill switch like every other send.
     */
    public static function sendTest(string $phone, string $text, ?int $societyId): array
    {
        $phone = self::normalizePhone($phone);

        if (!self::getSetting('sms_enabled', false, $societyId)) {
            return self::stamp(self::skipped(self::STATUS_SKIPPED_DISABLED));
        }

        $apiKey = (string) self::getSetting('fast2sms_api_key', '', $societyId);
        if ($apiKey === '') {
            return self::stamp(self::skipped(self::STATUS_SKIPPED_NOT_CONFIGURED));
        }
        if ($bad = self::invalidNumber($phone)) {
            return $bad;
        }

        $messageId = (string) self::getSetting('fast2sms_template_id', '', $societyId);
        $senderId = (string) self::getSetting('fast2sms_sender_id', '', $societyId);

        if ($messageId !== '') {
            // A saved Message ID means a DLT SMS; an incomplete DLT setup is reported, never sent as Quick SMS instead.
            if (strlen(preg_replace('/\D+/', '', $messageId)) >= 15) {
                return self::stamp(self::failedSend('The saved Message ID is a DLT Template ID, but Fast2SMS needs its Message ID (for example 223577).', 'invalid_message_id'));
            }
            if ($senderId === '') {
                return self::stamp(self::failedSend('Not sent: a Message ID is saved, so this goes out as a DLT SMS, but there is no Sender ID. Add the Sender ID and save.', 'sender_missing'));
            }

            return self::stamp(self::fast2smsGet(Fast2SmsService::dltUrl($senderId, $messageId, array_values(array_filter(array_map('trim', explode('|', $text)), fn ($v) => $v !== '')), $phone), $apiKey,
                self::fast2smsResponseHandler($phone, 'dlt', $apiKey)));
        }

        return self::stamp(self::sendViaFast2SmsQuick($phone, $text, $apiKey));
    }

    private static function failedSend(string $message, string $code): array
    {
        return ['success' => false, 'message' => $message, 'status' => self::STATUS_FAILED, 'sent' => false, 'error_code' => $code];
    }

    /**
     * Fast2SMS Quick SMS (route=q): free text, no DLT sender or template. For testing only - Indian carriers
     * block it for most traffic and Fast2SMS may require a minimum recharge before it works.
     */
    public static function sendViaFast2SmsQuick(string $phone, string $text, string $apiKey): array
    {
        if ($bad = self::invalidNumber($phone)) {
            return $bad;
        }

        return self::fast2smsGet(Fast2SmsService::quickUrl($text, $phone), $apiKey, self::fast2smsResponseHandler($phone, 'q', $apiKey));
    }

    /** GET to Fast2SMS through the shared transport, or the test override (same arguments curlPost would use). */
    private static function fast2smsGet(string $url, string $apiKey, callable $handler): array
    {
        // The same handling for the real transport and a test's mock: status 0 means nothing came back.
        [$body, $httpCode] = self::$transportOverride !== null
            ? (self::$transportOverride)($url, [], ['authorization: ' . $apiKey])
            : Fast2SmsService::get($url, $apiKey);

        if ($httpCode === 0) {
            if (!empty($body['skipped'])) {
                Log::notice('SMS suppressed', ['host' => 'www.fast2sms.com']);

                return ['success' => false, 'message' => \App\Support\ExternalMessaging::SUPPRESSED_MESSAGE, 'status' => self::STATUS_SKIPPED_DISABLED, 'sent' => false];
            }

            return ['success' => false, 'message' => 'SMS service unavailable: ' . ($body['transport_error'] ?? 'no response')];
        }

        return $handler($body, $httpCode);
    }

    /** A provider answer made safe to show: the API key and long token-like strings are removed. */
    public static function rawResponse($body, ?string $apiKey = null): string
    {
        $json = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR) ?: '';
        if ($apiKey !== null && $apiKey !== '') {
            $json = str_replace($apiKey, '[redacted]', $json);
        }

        return mb_substr($json, 0, 1000);
    }

    /**
     * Fast2SMS takes `mobile` as ^[0-9]{10}$ (Send OTP and DLT SMS). A malformed number is refused here with a plain
     * reason - nothing is sent and no request is made.
     */
    private static function invalidNumber(string $phone): ?array
    {
        if (preg_match('/^[0-9]{10}$/', $phone)) {
            return null;
        }

        return ['success' => false, 'message' => 'Enter a valid 10-digit Indian mobile number.', 'status' => self::STATUS_FAILED, 'sent' => false, 'error_code' => 'invalid_number'];
    }

    private static function sendViaFast2SmsOtp(string $phone, string $otp, string $apiKey, ?int $societyId): array
    {
        if ($bad = self::invalidNumber($phone)) {
            return $bad;
        }
        $otpId = self::getSetting('fast2sms_otp_id', null, $societyId);

        if (empty($otpId)) {
            Log::warning('Fast2SMS OTP ID missing', ['provider' => 'fast2sms', 'route' => 'otp']);
            return ['success' => false, 'message' => 'Fast2SMS OTP ID is not set. Enter your approved OTP ID in SMS settings.'];
        }

        $payload = [
            'mobile'     => $phone,
            'otp_id'     => (string) $otpId,
            'otp'        => $otp,
            'otp_expiry' => \App\Models\MobileOtp::TTL_MINUTES,   // minutes; documented range 1-10080
        ];

        return self::curlPost('https://www.fast2sms.com/dev/otp/send', $payload,
            ['authorization: ' . $apiKey, 'Content-Type: application/json', 'Accept: application/json'],
            self::fast2smsResponseHandler($phone, 'otp', $apiKey)
        );
    }

    private static function sendViaFast2SmsDlt(string $phone, string $otp, string $apiKey, ?int $societyId, ?string $templateId = null, ?string $senderId = null, bool $fallbackToOtpTemplate = true): array
    {
        // Notifications pass $fallbackToOtpTemplate = false: a template approved for OTPs is not approved for
        // notice text, so a missing notification template is an error, never a silent reuse of the OTP one.
        if ($bad = self::invalidNumber($phone)) {
            return $bad;
        }

        if (!$fallbackToOtpTemplate && (empty($templateId) || empty($senderId))) {
            return ['success' => false, 'message' => 'No notification SMS template is chosen. Choose one in Settings > SMS / OTP > "Use for notifications".', 'status' => self::STATUS_SKIPPED_NOT_CONFIGURED, 'sent' => false];
        }

        // A template and its sender always travel together: they belong to
        // the same DLT entity in Fast2SMS.
        if (empty($templateId) || empty($senderId)) {
            $senderId   = self::getSetting('fast2sms_sender_id', null, $societyId);
            $templateId = self::getSetting('fast2sms_template_id', null, $societyId);
        }

        // /dev/bulkV2 wants Fast2SMS's short Message ID in `message`. A
        // 15+ digit value is the telecom DLT Template ID, which Fast2SMS
        // rejects there - say so instead of sending a request that cannot work.
        if (!empty($templateId) && strlen(preg_replace('/\D+/', '', (string) $templateId)) >= 15) {
            Log::warning('Fast2SMS DLT template looks like a telecom DLT Template ID, not a Message ID', ['provider' => 'fast2sms']);
            return ['success' => false, 'message' => 'The saved SMS template is a DLT Template ID, but Fast2SMS needs its Message ID. Choose the template again in Settings > SMS / OTP.'];
        }

        if (empty($senderId) || empty($templateId)) {
            Log::error("Fast2SMS DLT route requires sender_id and template_id");
            return ['success' => false, 'message' => 'Fast2SMS DLT not configured properly'];
        }

        // GET /dev/bulkV2 (the documented method). $otp may already hold several values separated by "|".
        return self::fast2smsGet(
            Fast2SmsService::dltUrl((string) $senderId, (string) $templateId, array_values(array_filter(explode('|', $otp), fn ($v) => $v !== '')), $phone),
            $apiKey,
            self::fast2smsResponseHandler($phone, 'dlt', $apiKey)
        );
    }

    /**
     * Shared reading of a Fast2SMS answer. Both endpoints return
     * return:true on acceptance. Structured, safe fields only are logged:
     * the status and message, a masked number. Never the key, the header,
     * the OTP, or the raw body.
     */
    private static function fast2smsResponseHandler(string $phone, string $route, string $apiKey): callable
    {
        return function ($body, $httpCode) use ($phone, $route, $apiKey) {
            if ($httpCode === 200 && ($body['return'] ?? false) === true) {
                // request_id is the provider's reference for this send (not a secret): kept for support/correlation.
                return ['success' => true, 'message' => 'OTP sent via Fast2SMS', 'request_id' => isset($body['request_id']) ? (string) $body['request_id'] : null, 'raw' => self::rawResponse($body, $apiKey)];
            }
            $error = is_array($body['message'] ?? null)
                ? implode(', ', $body['message'])
                : ($body['message'] ?? "HTTP {$httpCode}");

            Log::warning('Fast2SMS send rejected', [
                'provider'     => 'fast2sms',
                'route'        => $route,
                'http_status'  => $httpCode,
                'phone'        => self::maskPhone($phone),
                'provider_msg' => self::safeProviderMessage((string) $error, $apiKey, $route === 'otp'),
            ]);

            // The docs define exactly two failures: 401 "Invalid Authorization key" and 400 (e.g. "Invalid OTP ID").
            // Anything else is reported as a plain rejection rather than guessed at.
            $code = match (true) {
                $httpCode === 401 => 'invalid_api_key',
                $httpCode === 400 && preg_match('/otp\s*id/i', (string) $error) === 1 => 'invalid_otp_id',
                $httpCode >= 500, $httpCode === 0 => 'provider_unavailable',
                default => 'rejected',
            };

            return ['success' => false, 'message' => self::safeProviderMessage((string) $error, $apiKey, $route === 'otp'), 'error_code' => $code, 'provider_status' => $httpCode, 'raw' => self::rawResponse($body, $apiKey)];
        };
    }

    // ── BulkSMSPlans ─────────────────────────────────────────────────────────

    private static function sendViaBulkSmsPlans(string $phone, string $otp, ?int $societyId): array
    {
        $apiId       = self::getSetting('bulksmsplans_api_id', null, $societyId);
        $apiPassword = self::getSetting('bulksmsplans_api_password', null, $societyId);
        $senderId    = self::getSetting('bulksmsplans_sender_id', 'DEMOSM', $societyId);
        $templateId  = self::getSetting('bulksmsplans_template_id', '0', $societyId);

        if (empty($apiId) || empty($apiPassword)) {
            Log::info("BulkSMSPlans not configured - no message sent", ["provider" => "bulksmsplans", "phone" => self::maskPhone($phone)]);
            return self::skipped(self::STATUS_SKIPPED_NOT_CONFIGURED, ['otp' => $otp]);
        }

        $message = "Your OTP is {$otp}. Valid for 5 minutes. Do not share with anyone.";

        // BulkSMSPlans API — POST to /api/send_sms
        return self::curlPost('https://bulksmsplans.com/api/send_sms', [
            'api_id'       => $apiId,
            'api_password' => $apiPassword,
            'sms_type'     => 'OTP',
            'sms_encoding' => '1',        // 1 = Text
            'sender'       => $senderId,
            'number'       => $phone,
            'message'      => $message,
            'template_id'  => $templateId ?: '0',
        ], ['Content-Type: application/json'], function ($body, $httpCode) use ($phone) {
            // Success: {"code":"SMS","message":"Message Submitted Successfully","data":{...}}
            $code = $body['code'] ?? '';
            if ($httpCode === 200 && strtoupper($code) === 'SMS') {
                return ['success' => true, 'message' => 'OTP sent via BulkSMSPlans'];
            }
            $error = $body['message'] ?? json_encode($body);
            if (is_array($error)) $error = implode(', ', $error);
            Log::warning("BulkSMSPlans send rejected", ["provider" => "bulksmsplans", "phone" => self::maskPhone($phone), "http_status" => $httpCode ?? null]);
            return ['success' => false, 'message' => 'BulkSMSPlans: ' . self::safeProviderMessage((string) $error)];
        });
    }

    // ── Custom HTTP API ───────────────────────────────────────────────────────

    private static function sendViaCustom(string $phone, string $otp, ?int $societyId): array
    {
        $apiUrl = self::getSetting('custom_sms_api_url', null, $societyId);

        if (empty($apiUrl)) {
            return ['success' => false, 'message' => 'Custom SMS API URL not configured.'];
        }

        // Replace placeholders in URL (for GET-style APIs)
        $message = urlencode("Your OTP is {$otp}. Valid for 5 minutes.");
        $url = str_replace(['{phone}', '{mobile}', '{otp}', '{message}'], [$phone, $phone, $otp, $message], $apiUrl);

        // If URL has placeholders replaced, use GET; otherwise POST with JSON
        $isGet = (str_contains($url, $phone) || str_contains($url, $otp));

        if ($isGet) {
            return self::curlGet($url, function ($body, $httpCode) use ($phone) {
                if ($httpCode >= 200 && $httpCode < 300) {
                    return ['success' => true, 'message' => 'OTP sent via custom API'];
                }
                return ['success' => false, 'message' => "Custom API failed (HTTP {$httpCode})"];
            });
        }

        return self::curlPost($url, [
            'mobile'  => $phone,
            'otp'     => $otp,
            'message' => "Your OTP is {$otp}. Valid for 5 minutes.",
        ], ['Content-Type: application/json'], function ($body, $httpCode) use ($phone) {
            if ($httpCode >= 200 && $httpCode < 300) {
                return ['success' => true, 'message' => 'OTP sent via custom API'];
            }
            return ['success' => false, 'message' => "Custom API failed (HTTP {$httpCode})"];
        });
    }

    // ── HTTP helpers ──────────────────────────────────────────────────────────

    /**
     * Test-only seam: when set, replaces the network call. Production never
     * sets it, so the real cURL path below always runs there. Receives the
     * same arguments curlPost() would send, and returns [body, httpCode].
     *
     * @var (callable(string,array,array):array{0:mixed,1:int})|null
     */
    public static ?\Closure $transportOverride = null;

    private static function curlPost(string $url, array $data, array $headers, callable $handler): array
    {
        if (self::$transportOverride !== null) {
            [$body, $httpCode] = (self::$transportOverride)($url, $data, $headers);

            return $handler($body, $httpCode);
        }

        if (!\App\Support\ExternalMessaging::enabled()) {
            Log::notice('SMS suppressed', ['host' => parse_url($url, PHP_URL_HOST)]);

            return ['success' => false, 'message' => \App\Support\ExternalMessaging::SUPPRESSED_MESSAGE, 'status' => self::STATUS_SKIPPED_DISABLED, 'sent' => false];
        }

        \App\Support\ExternalMessaging::beforeOutbound('SMS', (string) parse_url($url, PHP_URL_HOST));

        try {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 15,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => json_encode($data),
                CURLOPT_HTTPHEADER     => $headers,
            ]);
            $result   = curl_exec($ch);
            $curlErr  = curl_error($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($curlErr) {
                Log::error("SMS transport error", ["host" => parse_url($url, PHP_URL_HOST), "error" => mb_substr($curlErr, 0, 120)]);
                return ['success' => false, 'message' => 'SMS service unavailable: ' . $curlErr];
            }

            $body = json_decode($result, true) ?? ['raw' => $result];
            return $handler($body, $httpCode);

        } catch (\Throwable $e) {
            Log::error("SMS exception", ["class" => get_class($e)]);
            return ['success' => false, 'message' => 'SMS service error: ' . $e->getMessage()];
        }
    }

    private static function curlGet(string $url, callable $handler): array
    {
        if (!\App\Support\ExternalMessaging::enabled()) {
            Log::notice('SMS lookup suppressed', ['host' => parse_url($url, PHP_URL_HOST)]);

            return ['success' => false, 'message' => \App\Support\ExternalMessaging::SUPPRESSED_MESSAGE, 'status' => self::STATUS_SKIPPED_DISABLED, 'sent' => false];
        }

        \App\Support\ExternalMessaging::beforeOutbound('SMS', (string) parse_url($url, PHP_URL_HOST));

        try {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 15,
                CURLOPT_SSL_VERIFYPEER => true,
            ]);
            $result   = curl_exec($ch);
            $curlErr  = curl_error($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($curlErr) {
                return ['success' => false, 'message' => 'SMS service unavailable: ' . $curlErr];
            }

            $body = json_decode($result, true) ?? ['raw' => $result];
            return $handler($body, $httpCode);

        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'SMS service error: ' . $e->getMessage()];
        }
    }

    // ── Settings helper ───────────────────────────────────────────────────────

    private static function getSetting(string $key, $default, ?int $societyId)
    {
        if ($societyId) {
            $val = SystemSetting::get('sms', $key, null, $societyId);
            if ($val !== null && $val !== '') return $val;
        }
        $val = SystemSetting::get('sms', $key, null, null);
        if ($val !== null && $val !== '') return $val;

        // Platform-wide fallback from the environment, read through config/sms.php (env() itself returns null once the
        // configuration is cached). There is deliberately no fallback for the enabled flag.
        $configMap = [
            'fast2sms_api_key'    => 'sms.fast2sms.api_key',
            'fast2sms_route'      => 'sms.fast2sms.route',
            'fast2sms_sender_id'  => 'sms.fast2sms.sender_id',
            'fast2sms_template_id'=> 'sms.fast2sms.template_id',
            'fast2sms_otp_id'     => 'sms.fast2sms.otp_id',
            'fast2sms_flash'      => 'sms.fast2sms.flash',
            'sms_provider'        => 'sms.provider',
        ];

        if (isset($configMap[$key])) {
            $envVal = config($configMap[$key]);
            if ($envVal !== null && $envVal !== '') return $envVal;
        }

        return $default;
    }
}
