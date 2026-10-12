<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Read-only Fast2SMS account lookups used by Settings to verify and
 * auto-fill configuration, so a manager never has to copy technical IDs by
 * hand. Nothing here sends a message or costs credit.
 *
 * Endpoints and response shapes were confirmed against a live Fast2SMS
 * account on 2026-10-08 (not only the docs, which omit some fields):
 *
 *   POST /dev/wallet                              -> {return: true, wallet: "123.45", sms_count: 1234}
 *        bad key: HTTP 401 {return: false, status_code: 412, message: "Invalid Authentication, ..."}
 *   GET  /dev/dlt_manager?type=template           -> {success, data: [{sender_id, entity_id, entity_name,
 *                                                     entity_status, templates: [{message_id (int), message (text),
 *                                                     status, var_count}]}]}
 *        - note: NO telecom DLT Template ID is returned, only Fast2SMS's own message_id,
 *          which is what /dev/bulkV2 needs in its `message` field.
 *   GET  /dev/dlt_manager/whatsapp?type=number    -> {success, data: [{waba_id, phone_number_id, number,
 *                                                     verified_name, connection_status, ...}]}
 *   GET  /dev/dlt_manager/whatsapp?type=template  -> same, plus templates: [{message_id, template_id,
 *                                                     template_name, phone_number_id, category, status,
 *                                                     language, var_count, components: [{type, text}]}]}
 *
 * The API key travels only in the Authorization header and is scrubbed
 * from anything returned or logged.
 */
class Fast2SmsAccount
{
    private const BASE = 'https://www.fast2sms.com/dev';

    /** Tests swap this in: fn (string $method, string $url) => [array $body, int $httpCode]. */
    public static ?\Closure $transportOverride = null;

    /** @return array{ok: bool, message: string, balance?: string, detail?: ?string} */
    public static function wallet(string $apiKey): array
    {
        [$body, $code] = self::request('POST', self::BASE . '/wallet', $apiKey);

        if ($code === 200 && filter_var($body['return'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return ['ok' => true, 'message' => 'Fast2SMS accepted the API key.', 'balance' => (string) ($body['wallet'] ?? '')];
        }

        return self::failure($body, $code, $apiKey);
    }

    /**
     * Approved DLT SMS templates, flattened across entities/senders.
     *
     * @return array{ok: bool, message: string, templates?: list<array{message_id: string, text: string, var_count: int, status: string, sender_id: string, entity_name: string}>, detail?: ?string}
     */
    public static function smsTemplates(string $apiKey): array
    {
        [$body, $code] = self::request('GET', self::BASE . '/dlt_manager?type=template', $apiKey);

        if ($code !== 200 || !filter_var($body['success'] ?? false, FILTER_VALIDATE_BOOLEAN) || !is_array($body['data'] ?? null)) {
            return self::failure($body, $code, $apiKey);
        }

        $templates = [];
        foreach ($body['data'] as $entity) {
            foreach (($entity['templates'] ?? []) as $t) {
                if (!isset($t['message_id'])) {
                    continue;
                }
                $templates[] = [
                    'message_id'  => (string) $t['message_id'],
                    'text'        => (string) ($t['message'] ?? ''),
                    'var_count'   => (int) ($t['var_count'] ?? 0),
                    'status'      => (string) ($t['status'] ?? ''),
                    'sender_id'   => (string) ($entity['sender_id'] ?? ''),
                    'entity_name' => (string) ($entity['entity_name'] ?? ''),
                ];
            }
        }

        return ['ok' => true, 'message' => count($templates) . ' SMS template(s) found.', 'templates' => $templates];
    }

    /** @return array{ok: bool, message: string, numbers?: list<array{waba_id: string, phone_number_id: string, number: string, name: string, connected: bool}>, detail?: ?string} */
    public static function whatsappNumbers(string $apiKey): array
    {
        $r = self::whatsapp($apiKey, 'number');
        if (!$r['ok']) {
            return $r;
        }

        $numbers = array_map(fn ($n) => [
            'waba_id'         => (string) ($n['waba_id'] ?? ''),
            'phone_number_id' => (string) ($n['phone_number_id'] ?? ''),
            'number'          => (string) ($n['number'] ?? ''),
            'name'            => (string) ($n['verified_name'] ?? ''),
            'connected'       => strtoupper((string) ($n['connection_status'] ?? '')) === 'CONNECTED',
        ], $r['data']);

        return ['ok' => true, 'message' => count($numbers) . ' WhatsApp number(s) found.', 'numbers' => $numbers];
    }

    /**
     * @return array{ok: bool, message: string, templates?: list<array{external_id: string, message_id: string, name: string, language: string, status: string, category: string, var_count: int, body_text: string, phone_number_id: string}>, detail?: ?string}
     */
    public static function whatsappTemplates(string $apiKey, ?string $phoneNumberId = null): array
    {
        $r = self::whatsapp($apiKey, 'template');
        if (!$r['ok']) {
            return $r;
        }

        $templates = [];
        foreach ($r['data'] as $number) {
            foreach (($number['templates'] ?? []) as $t) {
                $pnid = (string) ($t['phone_number_id'] ?? $number['phone_number_id'] ?? '');
                if ($phoneNumberId && $pnid !== '' && $pnid !== (string) $phoneNumberId) {
                    continue; // another number's template - cannot be sent from ours
                }
                $body = collect($t['components'] ?? [])->first(fn ($c) => strtoupper($c['type'] ?? '') === 'BODY');
                $templates[] = [
                    'external_id'     => (string) ($t['template_id'] ?? ''),
                    'message_id'      => (string) ($t['message_id'] ?? ''),
                    'name'            => (string) ($t['template_name'] ?? ''),
                    'language'        => (string) ($t['language'] ?? ''),
                    'status'          => (string) ($t['status'] ?? ''),
                    'category'        => (string) ($t['category'] ?? ''),
                    'var_count'       => (int) ($t['var_count'] ?? 0),
                    'body_text'       => (string) ($body['text'] ?? ''),
                    'phone_number_id' => $pnid,
                ];
            }
        }

        return ['ok' => true, 'message' => count($templates) . ' WhatsApp template(s) found.', 'templates' => $templates];
    }

    /** @return array{ok: bool, message: string, data?: array, detail?: ?string} */
    private static function whatsapp(string $apiKey, string $type): array
    {
        [$body, $code] = self::request('GET', self::BASE . '/dlt_manager/whatsapp?type=' . $type, $apiKey);

        if ($code !== 200 || !filter_var($body['success'] ?? false, FILTER_VALIDATE_BOOLEAN) || !is_array($body['data'] ?? null)) {
            return self::failure($body, $code, $apiKey);
        }

        return ['ok' => true, 'message' => '', 'data' => $body['data']];
    }

    /** A plain-language reason for the manager, and the provider's own words for "technical details". */
    private static function failure(array $body, int $code, string $apiKey): array
    {
        $raw = $body['message'] ?? ($body['error']['message'] ?? ($body['transport_error'] ?? "HTTP {$code}"));
        $detail = SmsService::safeProviderMessage(is_array($raw) ? implode(', ', $raw) : (string) $raw, $apiKey);

        // Messaging switched off on this server (EXTERNAL_MESSAGING_ENABLED=false): nothing was sent anywhere, so
        // this is "not checked", never "could not reach" (which used to send people hunting for an internet fault).
        if (!empty($body['skipped'])) {
            return ['ok' => false, 'skipped' => true, 'message' => 'Not checked - real messaging is switched off on this server, so Fast2SMS was not contacted. Your settings are saved.', 'detail' => null];
        }

        // A real transport failure (code 0): say which kind. The cURL reason carries no secret.
        $reason = strtolower((string) ($body['transport_error'] ?? ''));
        $transport = match (true) {
            str_contains($reason, 'resolve')                                   => 'This computer cannot find fast2sms.com (DNS or no internet).',
            str_contains($reason, 'timed out') || str_contains($reason, 'timeout') => 'Fast2SMS did not answer in time. Try again.',
            str_contains($reason, 'ssl') || str_contains($reason, 'certificate') => 'This server could not verify Fast2SMS\'s secure certificate - a PHP/cURL certificate setup problem on this machine, not your API key.',
            default                                                            => 'Could not reach Fast2SMS. Check the internet connection and try again.',
        };

        $message = match (true) {
            $code === 0                                         => $transport,
            $code === 401 || (int) ($body['status_code'] ?? 0) === 412 => 'Fast2SMS did not accept this API key. Copy it again from Fast2SMS -> Dev API.',
            default                                             => 'Fast2SMS could not complete the request.',
        };

        return ['ok' => false, 'message' => $message, 'detail' => "HTTP {$code}: {$detail}"];
    }

    /** @return array{0: array, 1: int} */
    private static function request(string $method, string $url, string $apiKey): array
    {
        if (self::$transportOverride !== null) {
            return (self::$transportOverride)($method, $url);
        }

        if (!\App\Support\ExternalMessaging::enabled()) {
            // No request is made at all - this is not a connection problem and must not be reported as one.
            return [['transport_error' => \App\Support\ExternalMessaging::SUPPRESSED_MESSAGE, 'skipped' => true], 0];
        }

        try {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 20,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_HTTPHEADER     => ['authorization: ' . $apiKey, 'Accept: application/json'],
            ]);
            if ($method === 'POST') {
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, '');
            }
            $result  = curl_exec($ch);
            $curlErr = curl_error($ch);
            $code    = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($curlErr) {
                Log::warning('Fast2SMS account lookup transport error', ['path' => parse_url($url, PHP_URL_PATH)]);
                return [['transport_error' => mb_substr($curlErr, 0, 120)], 0];
            }

            return [json_decode((string) $result, true) ?? ['message' => 'Unreadable response'], $code];
        } catch (\Throwable $e) {
            Log::warning('Fast2SMS account lookup failed', ['class' => get_class($e)]);
            return [['transport_error' => 'request failed'], 0];
        }
    }
}
