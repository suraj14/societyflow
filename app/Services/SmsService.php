<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Log;

class SmsService
{
    /**
     * Send OTP via the configured SMS provider.
     * Supports: fast2sms, bulksmsplans, custom
     */
    public static function sendOtp(string $phone, string $otp, ?int $societyId = null, ?string $forceRoute = null): array
    {
        // Every send path (OTP login, Test SMS) goes through here, so the
        // number is normalised once, here. Fast2SMS takes a bare 10-digit
        // Indian mobile; a "+91 ..." or "91..." form is rejected.
        $phone = self::normalizePhone($phone);

        $enabled  = self::getSetting('sms_enabled', false, $societyId);
        $provider = self::getSetting('sms_provider', 'fast2sms', $societyId);

        if (!$enabled) {
            // Check if Fast2SMS is configured via .env — if so, treat as enabled
            $envKey = env('FAST2SMS_API_KEY');
            if (!empty($envKey)) {
                $provider = 'fast2sms';
                // proceed — don't return dev_mode
            } else {
                Log::info("SMS disabled - no message sent", ["phone" => self::maskPhone($phone)]);
                return ['success' => true, 'message' => 'dev_mode', 'otp' => $otp];
            }
        }

        // A forced route is for the Settings "Test OTP"/"Test DLT SMS"
        // buttons: each must exercise its own path regardless of which one is
        // currently saved, so the admin can verify both before choosing.
        if ($forceRoute !== null && $provider === 'fast2sms') {
            $apiKey = (string) self::getSetting('fast2sms_api_key', '', $societyId);
            if ($apiKey === '') {
                return ['success' => true, 'message' => 'dev_mode', 'otp' => $otp];
            }
            return $forceRoute === 'dlt'
                ? self::sendViaFast2SmsDlt($phone, $otp, $apiKey, $societyId)
                : self::sendViaFast2SmsOtp($phone, $otp, $apiKey, $societyId);
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
     */
    public static function sendDltNotification(string $purpose, string $phone, string $message, ?int $societyId = null, bool $force = false): array
    {
        $phone = self::normalizePhone($phone);

        // $force: the Settings "Send Test SMS" button, which must exercise
        // the template even before notifications are switched on.
        if (!$force && !self::getSetting("{$purpose}_sms_enabled", false, $societyId)) {
            Log::info("{$purpose} SMS disabled - no message sent", ['purpose' => $purpose, 'phone' => self::maskPhone($phone)]);
            return ['success' => true, 'message' => 'dev_mode'];
        }

        $apiKey = self::getSetting('fast2sms_api_key', null, $societyId);
        if (empty($apiKey)) {
            Log::info('Fast2SMS not configured - notification not sent', ['purpose' => $purpose, 'phone' => self::maskPhone($phone)]);
            return ['success' => true, 'message' => 'dev_mode'];
        }

        // The notification template chosen in Settings -> SMS, if any;
        // otherwise the one DLT template saved for OTP (the original
        // single-template behaviour, kept so nothing that worked stops).
        $result = self::sendViaFast2SmsDlt(
            $phone, $message, $apiKey, $societyId,
            self::getSetting('fast2sms_notification_template_id', null, $societyId),
            self::getSetting('fast2sms_notification_sender_id', null, $societyId),
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
    public static function safeProviderMessage(string $message, ?string $secret = null): string
    {
        if ($secret !== null && $secret !== '') {
            $message = str_replace($secret, '[redacted]', $message);
        }

        $message = preg_replace('/authorization\s*:\s*\S+/i', 'authorization: [redacted]', $message) ?? $message;
        $message = preg_replace('/[A-Za-z0-9_\-]{20,}/', '[redacted]', $message) ?? $message;
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
        $route  = self::getSetting('fast2sms_route', 'otp', $societyId);

        if (empty($apiKey)) {
            // Never write the OTP itself to the log (CLAUDE.md §7); dev mode
            // hands it back in the response instead.
            Log::info("Fast2SMS not configured - no message sent", ["provider" => "fast2sms", "phone" => self::maskPhone($phone)]);
            return ['success' => true, 'message' => 'dev_mode', 'otp' => $otp];
        }

        if ($route === 'dlt') {
            return self::sendViaFast2SmsDlt($phone, $otp, $apiKey, $societyId);
        }

        return self::sendViaFast2SmsOtp($phone, $otp, $apiKey, $societyId);
    }

    private static function sendViaFast2SmsOtp(string $phone, string $otp, string $apiKey, ?int $societyId): array
    {
        $otpId = self::getSetting('fast2sms_otp_id', null, $societyId);

        if (empty($otpId)) {
            Log::warning('Fast2SMS OTP ID missing', ['provider' => 'fast2sms', 'route' => 'otp']);
            return ['success' => false, 'message' => 'Fast2SMS OTP ID is not set. Enter your approved OTP ID in SMS settings.'];
        }

        $payload = [
            'mobile'     => $phone,
            'otp_id'     => (string) $otpId,
            'otp'        => $otp,
            'otp_expiry' => 10,
        ];

        return self::curlPost('https://www.fast2sms.com/dev/otp/send', $payload,
            ['authorization: ' . $apiKey, 'Content-Type: application/json', 'Accept: application/json'],
            self::fast2smsResponseHandler($phone, 'otp', $apiKey)
        );
    }

    private static function sendViaFast2SmsDlt(string $phone, string $otp, string $apiKey, ?int $societyId, ?string $templateId = null, ?string $senderId = null): array
    {
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

        $payload = [
            'route'            => 'dlt',
            'flash'            => 0,
            'numbers'          => $phone,
            'sender_id'        => $senderId,
            'message'          => $templateId,
            'variables_values' => $otp,
        ];

        return self::curlPost('https://www.fast2sms.com/dev/bulkV2', $payload,
            ['authorization: ' . $apiKey, 'Content-Type: application/json'],
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
                return ['success' => true, 'message' => 'OTP sent via Fast2SMS'];
            }
            $error = is_array($body['message'] ?? null)
                ? implode(', ', $body['message'])
                : ($body['message'] ?? "HTTP {$httpCode}");

            Log::warning('Fast2SMS send rejected', [
                'provider'     => 'fast2sms',
                'route'        => $route,
                'http_status'  => $httpCode,
                'phone'        => self::maskPhone($phone),
                'provider_msg' => self::safeProviderMessage((string) $error, $apiKey),
            ]);

            return ['success' => false, 'message' => self::safeProviderMessage((string) $error, $apiKey)];
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
            return ['success' => true, 'message' => 'dev_mode', 'otp' => $otp];
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

            return ['success' => false, 'message' => \App\Support\ExternalMessaging::SUPPRESSED_MESSAGE];
        }

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

            return ['success' => false, 'message' => \App\Support\ExternalMessaging::SUPPRESSED_MESSAGE];
        }

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

        // Fall back to .env values for Fast2SMS
        $envMap = [
            'fast2sms_api_key'    => 'FAST2SMS_API_KEY',
            'fast2sms_route'      => 'FAST2SMS_ROUTE',
            'fast2sms_sender_id'  => 'FAST2SMS_SENDER_ID',
            'fast2sms_template_id'=> 'FAST2SMS_TEMPLATE_ID',
            'fast2sms_otp_id'     => 'FAST2SMS_OTP_ID',
            'fast2sms_flash'      => 'FAST2SMS_FLASH',
            'sms_enabled'         => null, // no env fallback for enabled flag
            'sms_provider'        => 'SMS_PROVIDER',
        ];

        if (isset($envMap[$key]) && $envMap[$key] !== null) {
            $envVal = env($envMap[$key]);
            if ($envVal !== null && $envVal !== '') return $envVal;
        }

        return $default;
    }
}
