<?php

namespace App\Http\Controllers;

use App\Models\WhatsAppTemplate;
use App\Services\SmsService;
use App\Services\WhatsAppService;
use App\Support\ChannelStatus;
use App\Support\ExternalMessaging;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The optional "Test WhatsApp" behind Settings -> WhatsApp: send one saved template to a number the manager types.
 * Templates themselves are entered by hand (MessageTemplatesController) - there is no sync or refresh. Credentials are
 * saved by SettingsController::updateWhatsApp(); everything here reads the SAVED values, so nothing a manager sees is
 * taken on trust from the browser. Every answer is {success, message, details?, raw?}: a plain message for the
 * manager, the provider's own words as details / raw response.
 *
 * Routes sit in the Settings group (Admin / Super Admin, restrict.staff).
 */
class CommunicationSettingsController extends Controller
{
    private function societyId(): ?int
    {
        $user = auth()->user();

        return $user->hasRole('Super Admin') ? null : $user->society_id;
    }

    /** `$skipped`: nothing was attempted (messaging switched off) - reported as "not sent/checked", never as a failure. */
    private function fail(string $message, ?string $details = null, bool $skipped = false, ?string $raw = null): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message, 'details' => $details, 'raw' => $raw] + ($skipped ? ['skipped' => true, 'status' => 'not_sent'] : []));
    }

    // ── Test WhatsApp ───────────────────────────────────────────────────────

    /**
     * Sends one approved template to a number the manager types, filled with the values they typed (separated by |) or
     * sample values. WhatsApp cannot send free text to someone outside the 24-hour customer window, so a test is always
     * a template. The answer says what Fast2SMS replied - "accepted" is not "delivered".
     */
    public function whatsappTest(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone'       => 'required|string|min:10|max:15',
            'template_id' => 'required|integer',
            'variables'   => 'nullable|string|max:500',
        ]);
        $societyId = $this->societyId();

        if (!WhatsAppService::isEnabled($societyId)) {
            return $this->fail('WhatsApp is turned off. Switch on "Enabled", save, then test again.');
        }

        $template = WhatsAppTemplate::bySociety($societyId)->find($data['template_id']);
        if (!$template) {
            return $this->fail('Choose a saved template to test with. Add one in Message Templates > WhatsApp.');
        }

        // No values typed: the service fills sample values ("Test 1", "Test 2", ...).
        $values = null;
        if (trim((string) ($data['variables'] ?? '')) !== '') {
            $values = array_map('trim', explode('|', (string) $data['variables']));
            if (count($values) !== (int) $template->var_count) {
                return $this->fail("This template needs {$template->var_count} value(s) separated by |, but " . count($values) . ' were entered.');
            }
            if (in_array('', $values, true)) {
                return $this->fail('One of the values is empty. WhatsApp rejects empty values.');
            }
        }

        $result = WhatsAppService::sendTestTemplate($template, $data['phone'], $societyId, $values);
        $raw = $result['raw'] ?? null;

        if (!empty($result['skipped'])) {
            return $this->fail('Not sent: real messaging is switched off on this server, so nothing was sent.', null, true);
        }
        if (($result['message'] ?? null) === 'dev_mode') {
            return $this->fail('WhatsApp is not fully set up yet. Save the API key and the Phone Number ID first.');
        }
        if (!$result['success']) {
            ChannelStatus::record('whatsapp', $societyId, false, 'Test WhatsApp failed', $result['message']);

            return $this->fail('Test WhatsApp failed. ' . $this->whatsappReason($result['message']), $result['message'], false, $raw);
        }

        ChannelStatus::record('whatsapp', $societyId, true, 'Fast2SMS accepted a test message for ' . SmsService::maskPhone($data['phone']));

        return response()->json([
            'success' => true,
            'message' => 'Fast2SMS accepted the test message for ' . SmsService::maskPhone($data['phone']) . '. That means it was handed over for sending - it is not a delivery confirmation.',
            'raw'     => $raw,
        ]);
    }

    private function whatsappReason(string $provider): string
    {
        $p = strtolower($provider);

        return match (true) {
            str_contains($p, ExternalMessaging::SUPPRESSED_MESSAGE) => 'Real messaging is switched off on this server.',
            str_contains($p, 'does not exist')            => 'WhatsApp does not recognise that template on this number. Refresh the templates and try again.',
            str_contains($p, 'parameter')                 => 'The template expects a different number of values.',
            str_contains($p, 'authentication') || str_contains($p, 'authoriz') || str_contains($p, 'invalid api') => 'Fast2SMS did not accept the API key.',
            str_contains($p, 'unavailable')               => 'Could not reach Fast2SMS. Check the internet connection.',
            default                                       => 'Check the number and try again.',
        };
    }
}
