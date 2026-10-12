<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use App\Models\WhatsAppTemplate;
use App\Services\SmsService;
use App\Services\WhatsAppService;
use App\Services\WhatsAppTemplateSyncService;
use App\Support\ChannelStatus;
use App\Support\ExternalMessaging;
use App\Support\WhatsAppPurposes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The WhatsApp template actions behind Message Templates -> WhatsApp and Settings -> WhatsApp: refresh the approved
 * templates from Fast2SMS, choose which template a notification uses, and send a test. Credentials themselves are
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

    // ── WhatsApp templates ──────────────────────────────────────────────────

    /** Refresh the approved templates (with their Fast2SMS message_id, variable count and body) from Fast2SMS. */
    public function whatsappSync(): JsonResponse
    {
        $result = WhatsAppTemplateSyncService::sync($this->societyId());

        return response()->json(['success' => $result['success'], 'message' => $result['message'], 'details' => $result['detail'] ?? null, 'reload' => $result['success']]
            + (!empty($result['skipped']) ? ['skipped' => true, 'status' => 'not_sent'] : []));
    }

    /** Attach an approved template to one notification type, with its {{n}} mapping. */
    public function whatsappAssign(Request $request): JsonResponse
    {
        $data = $request->validate([
            'purpose'     => ['required', Rule::in(array_keys(WhatsAppPurposes::all()))],
            'template_id' => 'required|integer',
            'variables'   => 'nullable|array',
            'variables.*' => 'string',
        ]);
        $societyId = $this->societyId();

        $template = WhatsAppTemplate::bySociety($societyId)->find($data['template_id']);
        if (!$template) {
            return $this->fail('Template not found. Refresh the templates and try again.');
        }
        if (!$template->isApproved()) {
            return $this->fail('Only approved templates can be used.');
        }

        $fields = WhatsAppPurposes::all()[$data['purpose']]['fields'];
        $variables = array_values($data['variables'] ?? []);

        if ($template->var_count > count($fields)) {
            return $this->fail("This template needs {$template->var_count} values, but this notification can fill at most " . count($fields) . '. Choose a simpler template.');
        }
        if (count($variables) !== $template->var_count) {
            return $this->fail("This template has {$template->var_count} variable(s); choose a value for each one.");
        }
        foreach ($variables as $v) {
            if (!array_key_exists($v, $fields)) {
                return $this->fail('One of the chosen values is not available for this notification.');
            }
        }

        SystemSetting::set('whatsapp', "whatsapp_{$data['purpose']}_template_name", $template->name, 'string', $societyId);
        SystemSetting::set('whatsapp', "whatsapp_{$data['purpose']}_variables", $variables, 'json', $societyId);

        return response()->json([
            'success' => true,
            'message' => '"' . WhatsAppPurposes::all()[$data['purpose']]['label'] . "\" will now be sent on WhatsApp using \"{$template->displayName()}\".",
            'reload'  => true,
        ]);
    }

    public function whatsappUnassign(Request $request): JsonResponse
    {
        $data = $request->validate(['purpose' => ['required', Rule::in(array_keys(WhatsAppPurposes::all()))]]);
        $societyId = $this->societyId();

        SystemSetting::set('whatsapp', "whatsapp_{$data['purpose']}_template_name", '', 'string', $societyId);
        SystemSetting::set('whatsapp', "whatsapp_{$data['purpose']}_variables", [], 'json', $societyId);

        return response()->json(['success' => true, 'message' => 'WhatsApp turned off for this notification.', 'reload' => true]);
    }

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

        $template = WhatsAppTemplate::bySociety($societyId)->approved()->find($data['template_id']);
        if (!$template) {
            return $this->fail('Choose an approved template to test with. Templates come from Message Templates > WhatsApp > Refresh.');
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
