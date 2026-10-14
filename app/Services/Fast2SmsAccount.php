<?php

namespace App\Services;

use App\Support\WhatsAppProviderError;
use Illuminate\Support\Facades\Log;

/**
 * Read-only Fast2SMS WhatsApp account lookups: the approved-template list (Message Templates -> WhatsApp refresh) and
 * the business profile check that runs when WhatsApp settings are saved. Nothing here sends a message.
 *
 *   GET /dev/dlt_manager/whatsapp?type=template[&phone_number_id=]
 *        -> {success, data: [{waba_id, phone_number_id, ..., templates: [{message_id (int), template_id,
 *           template_name, phone_number_id, category, status, language, var_count, components: [{type, text}]}]}]}
 *        (docs.fast2sms.com/reference/get-waba-template-details; confirmed against a live account on 2026-10-08)
 *   GET /dev/whatsapp/{version}/{phone_number_id}/whatsapp_business_profile?fields=...
 *        -> {data: [{about, description, vertical, ...}]}   (docs.fast2sms.com/reference/get-whatsapp-business-profile)
 *
 * The API key travels only in the Authorization header and is scrubbed from anything returned or logged.
 */
class Fast2SmsAccount
{
    private const BASE = 'https://www.fast2sms.com/dev';

    /** Tests swap this in: fn (string $method, string $url) => [array $body, int $httpCode]. */
    public static ?\Closure $transportOverride = null;

    /**
     * Read-only profile of one WhatsApp number (docs.fast2sms.com/reference/get-whatsapp-business-profile). It succeeds
     * only when the API key and the Phone Number ID belong together, which is exactly what Settings needs to confirm.
     * Says nothing about whether any message was or will be delivered.
     *
     * @return array{ok: bool, message: string, profile?: array, detail?: ?string, skipped?: bool}
     */
    public static function whatsappProfile(string $apiKey, string $phoneNumberId, string $version = 'v26.0'): array
    {
        [$body, $code] = self::request('GET', Fast2SmsService::whatsappProfileUrl($version, $phoneNumberId), $apiKey);

        if ($code === 200 && is_array($body['data'] ?? null)) {
            return ['ok' => true, 'message' => 'Fast2SMS accepted the API key and Phone Number ID.', 'profile' => (array) ($body['data'][0] ?? [])];
        }

        return self::failure($body, $code, $apiKey);
    }

    /**
     * @return array{ok: bool, message: string, templates?: list<array{external_id: string, message_id: string, name: string, language: string, status: string, category: string, var_count: int, body_text: string, phone_number_id: string}>, detail?: ?string}
     */
    public static function whatsappTemplates(string $apiKey, ?string $phoneNumberId = null): array
    {
        $r = self::whatsapp($apiKey, 'template', $phoneNumberId);
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
                    // var_count is documented; if it is missing, count the {{n}} placeholders in the body instead of showing 0.
                    'var_count'       => isset($t['var_count']) && is_numeric($t['var_count'])
                        ? (int) $t['var_count']
                        : (int) preg_match_all('/\{\{\d+\}\}/', (string) ($body['text'] ?? '')),
                    'body_text'       => (string) ($body['text'] ?? ''),
                    'phone_number_id' => $pnid,
                ];
            }
        }

        return ['ok' => true, 'message' => count($templates) . ' WhatsApp template(s) found.', 'templates' => $templates];
    }

    /**
     * The WhatsApp numbers on the account as the provider reports them (same read-only template lookup, no filter).
     * Lets Settings tell a correct Phone Number ID from the Business Account ID or any other ID.
     *
     * @return array{ok: bool, message: string, numbers?: list<array{phone_number_id: string, waba_id: string, number_tail: string, verified_name: string, connection_status: string, quality_rating: string}>, detail?: ?string, skipped?: bool}
     */
    public static function whatsappNumbers(string $apiKey): array
    {
        // GET /dev/dlt_manager/whatsapp?type=number - the documented list of the account's WhatsApp numbers
        // (docs.fast2sms.com/reference/get-waba-template-details). Authoritative for "which IDs belong to this key".
        $r = self::whatsapp($apiKey, 'number', null);
        if (!$r['ok']) {
            return $r;
        }

        $numbers = [];
        foreach ($r['data'] as $row) {
            $pnid = (string) ($row['phone_number_id'] ?? '');
            if ($pnid !== '') {
                $numbers[$pnid] = [
                    'phone_number_id'   => $pnid,
                    'waba_id'           => (string) ($row['waba_id'] ?? ''),
                    'number_tail'       => substr(preg_replace('/\D+/', '', (string) ($row['number'] ?? '')) ?? '', -4),
                    'verified_name'     => mb_substr((string) ($row['verified_name'] ?? ''), 0, 60),
                    'connection_status' => (string) ($row['connection_status'] ?? ''),
                    'quality_rating'    => (string) ($row['quality_rating'] ?? ''),
                ];
            }
        }

        return ['ok' => true, 'message' => count($numbers) . ' WhatsApp number(s) on the account.', 'numbers' => array_values($numbers)];
    }

    /** @return array{ok: bool, message: string, data?: array, detail?: ?string} */
    private static function whatsapp(string $apiKey, string $type, ?string $phoneNumberId = null): array
    {
        // phone_number_id is the documented filter: only this number's templates come back.
        $query = http_build_query(array_filter(['type' => $type, 'phone_number_id' => $phoneNumberId], fn ($v) => $v !== null && $v !== ''));
        [$body, $code] = self::request('GET', self::BASE . '/dlt_manager/whatsapp?' . $query, $apiKey);

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

        // Meta's errors often arrive inside an HTTP 200 (`{"error": {"message": "(#100) ...", "code": 100}}`): read them, and
        // say what to do, instead of the generic line.
        $advice = WhatsAppProviderError::explain($detail, $body['error']['code'] ?? null);

        $message = match (true) {
            $code === 0                                         => $transport,
            $code === 401 || (int) ($body['status_code'] ?? 0) === 412 => 'Fast2SMS did not accept this API key. Copy it again from Fast2SMS -> Dev API.',
            $advice !== null                                    => $advice,
            WhatsAppProviderError::forHttp($code) !== null      => WhatsAppProviderError::forHttp($code),
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

        // The kill switch, timeouts and masked logging live in Fast2SmsService; when messaging is off no request is
        // made at all, and that is not a connection problem and must not be reported as one.
        return Fast2SmsService::send($method, $url, $apiKey);
    }
}
