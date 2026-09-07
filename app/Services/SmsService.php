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
    public static function sendOtp(string $phone, string $otp, ?int $societyId = null): array
    {
        $enabled  = self::getSetting('sms_enabled', false, $societyId);
        $provider = self::getSetting('sms_provider', 'fast2sms', $societyId);

        if (!$enabled) {
            // Check if Fast2SMS is configured via .env — if so, treat as enabled
            $envKey = env('FAST2SMS_API_KEY');
            if (!empty($envKey)) {
                $provider = 'fast2sms';
                // proceed — don't return dev_mode
            } else {
                Log::info("SMS disabled (dev mode) — OTP for {$phone}: {$otp}");
                return ['success' => true, 'message' => 'dev_mode', 'otp' => $otp];
            }
        }

        return match ($provider) {
            'bulksmsplans' => self::sendViaBulkSmsPlans($phone, $otp, $societyId),
            'custom'       => self::sendViaCustom($phone, $otp, $societyId),
            default        => self::sendViaFast2Sms($phone, $otp, $societyId),
        };
    }

    // ── Fast2SMS ──────────────────────────────────────────────────────────────

    private static function sendViaFast2Sms(string $phone, string $otp, ?int $societyId): array
    {
        $apiKey     = self::getSetting('fast2sms_api_key', null, $societyId);
        $route      = self::getSetting('fast2sms_route', 'otp', $societyId);
        $senderId   = self::getSetting('fast2sms_sender_id', null, $societyId);
        $templateId = self::getSetting('fast2sms_template_id', null, $societyId);

        if (empty($apiKey)) {
            Log::info("Fast2SMS key not set — OTP for {$phone}: {$otp}");
            return ['success' => true, 'message' => 'dev_mode', 'otp' => $otp];
        }

        // Build request based on route type
        $payload = [
            'route'   => $route,
            'flash'   => 0,
            'numbers' => $phone,
        ];

        if ($route === 'dlt') {
            // DLT route requires sender_id and template_id
            if (empty($senderId) || empty($templateId)) {
                Log::error("Fast2SMS DLT route requires sender_id and template_id");
                return ['success' => false, 'message' => 'Fast2SMS DLT not configured properly'];
            }
            $payload['sender_id']   = $senderId;
            $payload['message']     = $templateId;
            $payload['variables_values'] = $otp;
        } else {
            // Dev OTP route (no DLT)
            $payload['variables_values'] = $otp;
        }

        return self::curlPost('https://www.fast2sms.com/dev/bulkV2', $payload,
            ['authorization: ' . $apiKey, 'Content-Type: application/json'],
            function ($body, $httpCode) use ($phone) {
                if ($httpCode === 200 && ($body['return'] ?? false) === true) {
                    return ['success' => true, 'message' => 'OTP sent via Fast2SMS'];
                }
                $error = is_array($body['message'] ?? null)
                    ? implode(', ', $body['message'])
                    : ($body['message'] ?? "HTTP {$httpCode}");
                Log::error("Fast2SMS error for {$phone}: " . json_encode($body));
                return ['success' => false, 'message' => 'Fast2SMS: ' . $error];
            }
        );
    }

    // ── BulkSMSPlans ─────────────────────────────────────────────────────────

    private static function sendViaBulkSmsPlans(string $phone, string $otp, ?int $societyId): array
    {
        $apiId       = self::getSetting('bulksmsplans_api_id', null, $societyId);
        $apiPassword = self::getSetting('bulksmsplans_api_password', null, $societyId);
        $senderId    = self::getSetting('bulksmsplans_sender_id', 'DEMOSM', $societyId);
        $templateId  = self::getSetting('bulksmsplans_template_id', '0', $societyId);

        if (empty($apiId) || empty($apiPassword)) {
            Log::info("BulkSMSPlans not configured — OTP for {$phone}: {$otp}");
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
            Log::error("BulkSMSPlans error for {$phone}: " . json_encode($body));
            return ['success' => false, 'message' => 'BulkSMSPlans: ' . $error];
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

    private static function curlPost(string $url, array $data, array $headers, callable $handler): array
    {
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
                Log::error("SMS cURL error ({$url}): {$curlErr}");
                return ['success' => false, 'message' => 'SMS service unavailable: ' . $curlErr];
            }

            $body = json_decode($result, true) ?? ['raw' => $result];
            return $handler($body, $httpCode);

        } catch (\Throwable $e) {
            Log::error("SMS exception: " . $e->getMessage());
            return ['success' => false, 'message' => 'SMS service error: ' . $e->getMessage()];
        }
    }

    private static function curlGet(string $url, callable $handler): array
    {
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
