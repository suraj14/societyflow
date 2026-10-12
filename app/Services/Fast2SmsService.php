<?php

namespace App\Services;

use App\Support\ExternalMessaging;
use Illuminate\Support\Facades\Log;

/**
 * Every Fast2SMS HTTP call in one place: the transport (kill switch, timeouts, masked logging) and the URL builders
 * for the documented requests. SmsService, WhatsAppService, Fast2SmsAccount and WhatsAppTemplateSyncService keep their
 * public methods and call in here, so no other class touches cURL for Fast2SMS.
 *
 * Documented requests (docs.fast2sms.com), all with the header `Authorization: <API key>`:
 *   DLT SMS    GET /dev/bulkV2?route=dlt&sender_id=&message=<Message ID>&variables_values=a|b&numbers=
 *   Quick SMS  GET /dev/bulkV2?route=q&message=<text>&numbers=
 *   WhatsApp   GET /dev/whatsapp?message_id=<int>&phone_number_id=&numbers=&variables_values=a|b
 *   Number     GET /dev/dlt_manager/whatsapp?type=number
 *   Templates  GET /dev/dlt_manager/whatsapp?type=template[&phone_number_id=]
 */
class Fast2SmsService
{
    public const BASE = 'https://www.fast2sms.com/dev';

    /** Query parameters that carry message content or recipients: never written to the log. */
    private const PRIVATE_PARAMS = ['variables_values', 'message', 'numbers', 'mobile', 'otp'];

    /**
     * One request. Returns [decoded body, HTTP status]; status 0 means nothing came back (a 'transport_error' body, and
     * 'skipped' => true when the messaging kill switch stopped the call before any network use).
     *
     * @return array{0: array, 1: int}
     */
    public static function send(string $method, string $url, string $apiKey, ?array $json = null, int $timeout = 20): array
    {
        if (!ExternalMessaging::enabled()) {
            return [['transport_error' => ExternalMessaging::SUPPRESSED_MESSAGE, 'skipped' => true], 0];
        }

        ExternalMessaging::beforeOutbound('Fast2SMS', 'www.fast2sms.com');

        try {
            $ch = curl_init($url);
            $options = [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => $timeout,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_HTTPHEADER     => array_merge(['authorization: ' . $apiKey, 'Accept: application/json'], $json !== null ? ['Content-Type: application/json'] : []),
            ];
            if ($method === 'POST') {
                $options[CURLOPT_POST] = true;
                $options[CURLOPT_POSTFIELDS] = $json !== null ? json_encode($json) : '';
            }
            curl_setopt_array($ch, $options);
            $result  = curl_exec($ch);
            $curlErr = curl_error($ch);
            $code    = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($curlErr) {
                self::log($method, $url, 0, ['transport_error' => mb_substr($curlErr, 0, 120)], $apiKey);

                return [['transport_error' => mb_substr($curlErr, 0, 120)], 0];
            }

            $body = json_decode((string) $result, true);
            $body = is_array($body) ? $body : ['message' => 'Unreadable response', 'raw' => mb_substr((string) $result, 0, 200)];
            self::log($method, $url, $code, $body, $apiKey);

            return [$body, $code];
        } catch (\Throwable $e) {
            Log::warning('Fast2SMS request failed', ['class' => get_class($e)]);

            return [['transport_error' => 'request failed'], 0];
        }
    }

    /** @return array{0: array, 1: int} */
    public static function get(string $url, string $apiKey): array
    {
        return self::send('GET', $url, $apiKey);
    }

    // ── URL builders ────────────────────────────────────────────────────────

    /** @param list<string> $variables */
    public static function dltUrl(string $senderId, string $messageId, array $variables, string $phone): string
    {
        $q = ['route' => 'dlt', 'sender_id' => $senderId, 'message' => $messageId, 'numbers' => $phone];
        if ($variables) {
            $q['variables_values'] = self::pipe($variables);
        }

        return self::BASE . '/bulkV2?' . http_build_query($q, '', '&', PHP_QUERY_RFC3986);
    }

    public static function quickUrl(string $text, string $phone): string
    {
        return self::BASE . '/bulkV2?' . http_build_query(['route' => 'q', 'message' => $text, 'numbers' => $phone], '', '&', PHP_QUERY_RFC3986);
    }

    /** @param list<string> $variables */
    public static function whatsappUrl(int $messageId, string $phoneNumberId, string $number, array $variables): string
    {
        $q = ['message_id' => $messageId, 'phone_number_id' => $phoneNumberId, 'numbers' => $number];
        if ($variables) {
            $q['variables_values'] = self::pipe($variables);
        }

        return self::BASE . '/whatsapp?' . http_build_query($q, '', '&', PHP_QUERY_RFC3986);
    }

    /** Read-only profile of a WhatsApp number: the documented way to confirm a key + Phone Number ID belong together. */
    public static function whatsappProfileUrl(string $version, string $phoneNumberId): string
    {
        return self::BASE . '/whatsapp/' . rawurlencode($version) . '/' . rawurlencode($phoneNumberId) . '/whatsapp_business_profile?'
            . http_build_query(['fields' => 'about,address,description,email,profile_picture_url,websites,vertical'], '', '&', PHP_QUERY_RFC3986);
    }

    /** Values joined by the documented `|` separator; a pipe inside a value would split it, so it becomes `/`. */
    public static function pipe(array $values): string
    {
        return implode('|', array_map(fn ($v) => str_replace('|', '/', trim((string) $v)), $values));
    }

    /**
     * Fill a template's {{name}} / {{1}} placeholders, in the order they appear, from a named or listed set of values.
     * Returns the rendered text, the ordered variable list (what goes into variables_values) and the names that had no
     * value - a message with a missing value must never be sent with the placeholder (or a blank) in its place.
     *
     * @param array<string|int,string> $values
     * @return array{text: string, variables: list<string>, missing: list<string>}
     */
    public static function render(string $body, array $values): array
    {
        $variables = [];
        $missing = [];
        $position = 0;
        $text = preg_replace_callback('/\{\{\s*([A-Za-z0-9_]+)\s*\}\}/', function ($m) use ($values, &$variables, &$missing, &$position) {
            $name = $m[1];
            // {{1}}, {{2}}: position in a list of values; {{name}}: the named value, else the next listed one.
            $value = ctype_digit($name)
                ? ($values[(int) $name - 1] ?? '')
                : ($values[$name] ?? ($values[$position] ?? ''));
            $value = trim((string) $value);
            if ($value === '') {
                $missing[] = $name;
            }
            $position++;
            $variables[] = $value;

            return $value;
        }, $body) ?? $body;

        return ['text' => $text, 'variables' => $variables, 'missing' => array_values(array_unique($missing))];
    }

    // ── Logging (key, content and recipients never reach the log) ───────────

    private static function log(string $method, string $url, int $code, array $body, string $apiKey): void
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $safeQuery = array_diff_key($query, array_flip(self::PRIVATE_PARAMS));

        Log::info('Fast2SMS request', [
            'method'   => $method,
            'path'     => parse_url($url, PHP_URL_PATH),
            'query'    => $safeQuery,
            'http'     => $code,
            'response' => SmsService::rawResponse($body, $apiKey),
        ]);
    }
}
