<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use App\Models\WhatsAppTemplate;
use App\Services\Fast2SmsAccount;
use App\Services\WhatsAppService;
use App\Services\WhatsAppTemplateSyncService;
use App\Support\ChannelStatus;
use App\Support\WhatsAppPurposes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The "verify and pick" actions behind Settings -> SMS / OTP and
 * Settings -> WhatsApp. Credentials themselves are still saved by
 * SettingsController::updateSms()/updateWhatsApp(); everything here reads
 * the SAVED key and asks Fast2SMS, so nothing a manager sees is taken on
 * trust from the browser. Every answer is {success, message, details?}:
 * a plain message for the manager, the provider's own words only as
 * technical details.
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

    private function smsKey(): string
    {
        return (string) $this->setting('sms', 'fast2sms_api_key');
    }

    private function whatsappKey(): string
    {
        return (string) $this->setting('whatsapp', 'whatsapp_api_key');
    }

    private function setting(string $group, string $key, $default = null)
    {
        $societyId = $this->societyId();
        $value = SystemSetting::get($group, $key, null, $societyId);

        return ($value === null || $value === '') && $societyId ? SystemSetting::get($group, $key, $default, null) : ($value ?? $default);
    }

    /** `$skipped`: nothing was attempted (messaging switched off) - reported as "not sent/checked", never as a failure. */
    private function fail(string $message, ?string $details = null, bool $skipped = false): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message, 'details' => $details] + ($skipped ? ['skipped' => true, 'status' => 'not_sent'] : []));
    }

    // ── SMS ─────────────────────────────────────────────────────────────────

    /** Checks the saved key and returns the approved DLT templates to choose from. */
    public function smsConnect(): JsonResponse
    {
        $key = $this->smsKey();
        if ($key === '') {
            return $this->fail('Enter your Fast2SMS API key and save first.');
        }

        $wallet = Fast2SmsAccount::wallet($key);
        if (!$wallet['ok']) {
            // A skipped check says nothing about the connection, so it must not overwrite the last real result.
            if (empty($wallet['skipped'])) {
                ChannelStatus::record('sms', $this->societyId(), false, $wallet['message'], $wallet['detail'] ?? null);
            }

            return $this->fail($wallet['message'], $wallet['detail'] ?? null, !empty($wallet['skipped']));
        }

        $list = Fast2SmsAccount::smsTemplates($key);
        $templates = $list['ok'] ? $this->approvedSms($list['templates']) : [];

        ChannelStatus::record('sms', $this->societyId(), true, 'Connected to Fast2SMS (balance ' . $wallet['balance'] . ')');

        return response()->json([
            'success'   => true,
            'message'   => 'Connected to Fast2SMS. ' . count($templates) . ' approved SMS template(s) found.',
            'templates' => $templates,
            'details'   => $list['ok'] ? null : ($list['detail'] ?? null),
        ]);
    }

    /**
     * "Paste an ID and Verify". Fast2SMS sends DLT SMS by its own short
     * Message ID; the 19-digit telecom DLT Template ID is not returned by its
     * API, so it cannot be matched - the manager is asked to pick the
     * template by its approved text instead of us guessing.
     */
    public function smsVerify(Request $request): JsonResponse
    {
        $ref = preg_replace('/\D+/', '', (string) $request->validate(['template_ref' => 'required|string|max:40'])['template_ref']);

        $list = $this->smsTemplateList();
        if ($list instanceof JsonResponse) {
            return $list;
        }

        $match = collect($list)->firstWhere('message_id', $ref);
        if ($match) {
            return response()->json(['success' => true, 'message' => 'Template found: "' . $match['text'] . '"', 'template' => $match, 'templates' => $list]);
        }

        if (strlen($ref) >= 15) {
            return response()->json([
                'success'   => false,
                'message'   => 'That looks like a DLT Template ID from the telecom DLT portal. Fast2SMS sends using its own Message ID instead, so pick the same template from the list below by its text.',
                'templates' => $list,
            ]);
        }

        return response()->json([
            'success'   => false,
            'message'   => 'No approved template with that ID was found in your Fast2SMS DLT Manager. Pick one from the list below.',
            'templates' => $list,
        ]);
    }

    /** Use an approved template for OTP or for notifications. Re-checked with Fast2SMS; nothing typed is trusted. */
    public function smsUseTemplate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'purpose'    => 'required|in:otp,notification',
            'message_id' => 'required|string|max:20',
        ]);

        $list = $this->smsTemplateList();
        if ($list instanceof JsonResponse) {
            return $list;
        }

        $template = collect($list)->firstWhere('message_id', (string) $data['message_id']);
        if (!$template) {
            return $this->fail('That template is not in your Fast2SMS account any more. Connect again to refresh the list.');
        }
        if ($template['var_count'] !== 1) {
            return $this->fail('Choose a template with exactly one {#var#}. SocietyFlow fills a single value (the OTP, or the notification text).');
        }

        $societyId = $this->societyId();
        $meta = ['message_id' => $template['message_id'], 'sender_id' => $template['sender_id'], 'text' => $template['text']];

        if ($data['purpose'] === 'otp') {
            SystemSetting::set('sms', 'fast2sms_template_id', $template['message_id'], 'string', $societyId);
            SystemSetting::set('sms', 'fast2sms_sender_id', $template['sender_id'], 'string', $societyId);
            SystemSetting::set('sms', 'fast2sms_route', 'dlt', 'string', $societyId);
            SystemSetting::set('sms', 'fast2sms_otp_template', $meta, 'json', $societyId);
        } else {
            SystemSetting::set('sms', 'fast2sms_notification_template_id', $template['message_id'], 'string', $societyId);
            SystemSetting::set('sms', 'fast2sms_notification_sender_id', $template['sender_id'], 'string', $societyId);
            SystemSetting::set('sms', 'fast2sms_notification_template', $meta, 'json', $societyId);
        }

        return response()->json([
            'success' => true,
            'message' => ($data['purpose'] === 'otp' ? 'OTP' : 'Notification') . ' template saved. Send a test SMS to confirm.',
        ]);
    }

    /** @return array|JsonResponse */
    private function smsTemplateList()
    {
        $key = $this->smsKey();
        if ($key === '') {
            return $this->fail('Enter your Fast2SMS API key and save first.');
        }

        $list = Fast2SmsAccount::smsTemplates($key);
        if (!$list['ok']) {
            return $this->fail($list['message'], $list['detail'] ?? null, !empty($list['skipped']));
        }

        return $this->approvedSms($list['templates']);
    }

    private function approvedSms(array $templates): array
    {
        return array_values(array_filter($templates, fn ($t) => strcasecmp($t['status'], 'approved') === 0));
    }

    // ── WhatsApp ────────────────────────────────────────────────────────────

    /**
     * Connect & Verify: confirms the key, fills in the sender number's
     * Phone Number ID / Business Account ID when the account has exactly
     * one (or the manager picks one), then syncs approved templates.
     */
    public function whatsappConnect(Request $request): JsonResponse
    {
        $request->validate(['phone_number_id' => 'nullable|string|max:40']);
        $societyId = $this->societyId();
        $key = $this->whatsappKey();

        if ($key === '') {
            return $this->fail('Enter your Fast2SMS API key and save first.');
        }

        $numbers = Fast2SmsAccount::whatsappNumbers($key);
        if (!$numbers['ok']) {
            if (empty($numbers['skipped'])) {
                ChannelStatus::record('whatsapp', $societyId, false, $numbers['message'], $numbers['detail'] ?? null);
            }

            return $this->fail($numbers['message'], $numbers['detail'] ?? null, !empty($numbers['skipped']));
        }

        $list = collect($numbers['numbers']);
        $chosen = $request->filled('phone_number_id')
            ? $list->firstWhere('phone_number_id', (string) $request->phone_number_id)
            : ($list->firstWhere('phone_number_id', (string) $this->setting('whatsapp', 'whatsapp_phone_number_id', '')) ?? ($list->count() === 1 ? $list->first() : null));

        if (!$chosen) {
            if ($list->isEmpty()) {
                return $this->fail('No WhatsApp number is connected to this Fast2SMS account yet. Connect one in Fast2SMS first.');
            }

            return response()->json([
                'success' => false,
                'choose'  => true,
                'message' => 'Your Fast2SMS account has more than one WhatsApp number. Choose the one SocietyFlow should send from.',
                'numbers' => $list->map(fn ($n) => ['phone_number_id' => $n['phone_number_id'], 'label' => trim($n['number'] . ' ' . $n['name'])])->values(),
            ]);
        }

        SystemSetting::set('whatsapp', 'whatsapp_phone_number_id', $chosen['phone_number_id'], 'string', $societyId);
        SystemSetting::set('whatsapp', 'whatsapp_waba_id', $chosen['waba_id'], 'string', $societyId);

        $sync = WhatsAppTemplateSyncService::sync($societyId);
        $approved = WhatsAppTemplate::bySociety($societyId)->approved()->count();
        $label = trim($chosen['number'] . ($chosen['name'] ? " ({$chosen['name']})" : ''));

        ChannelStatus::record('whatsapp', $societyId, true, "Connected to {$label} · {$approved} approved template(s)");

        return response()->json([
            'success' => true,
            'message' => "Connected to {$label}. " . ($sync['success'] ? $sync['message'] : 'Templates could not be synced: ' . $sync['message']),
            'reload'  => true,
        ]);
    }

    public function whatsappSync(): JsonResponse
    {
        $result = WhatsAppTemplateSyncService::sync($this->societyId());

        return response()->json(['success' => $result['success'], 'message' => $result['message'], 'details' => $result['detail'] ?? null, 'reload' => $result['success']]
            + (!empty($result['skipped']) ? ['skipped' => true, 'status' => 'not_sent'] : []));
    }

    /**
     * Pasted ID or name -> a synced template, matched on the template's real
     * name, its Meta template ID or its Fast2SMS message ID. Whatever was
     * pasted is never itself used as a template name.
     */
    public function whatsappVerifyTemplate(Request $request): JsonResponse
    {
        $ref = trim((string) $request->validate(['template_ref' => 'required|string|max:120'])['template_ref']);
        $societyId = $this->societyId();

        $find = fn () => WhatsAppTemplate::bySociety($societyId)->get()->first(
            fn ($t) => $t->name === $ref || $t->external_id === $ref || ($t->message_id !== null && $t->message_id === $ref)
                || strcasecmp($t->displayName(), $ref) === 0
        );

        $template = $find();
        if (!$template) {
            $sync = WhatsAppTemplateSyncService::sync($societyId);
            if (!$sync['success']) {
                return $this->fail($sync['message'], $sync['detail'] ?? null, !empty($sync['skipped']));
            }
            $template = $find();
        }

        if (!$template) {
            return $this->fail('No template matching that ID or name was found on your WhatsApp number. Check it in Fast2SMS, or pick from Approved Templates.');
        }
        if (!$template->isApproved()) {
            return $this->fail("Found \"{$template->displayName()}\", but Meta has not approved it (status: " . ucfirst(strtolower($template->status)) . ').');
        }

        return response()->json([
            'success'  => true,
            'message'  => "Verified: \"{$template->displayName()}\" ({$template->language}, {$template->var_count} variable(s)). Choose what it is used for below.",
            'template' => ['id' => $template->id],
        ]);
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
            return $this->fail('Template not found. Sync templates and try again.');
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

    /** Sends one approved template, filled with sample values, to a number the manager types. */
    public function whatsappTest(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone'       => 'required|string|min:10|max:15',
            'template_id' => 'required|integer',
        ]);
        $societyId = $this->societyId();

        if (!WhatsAppService::isEnabled($societyId)) {
            return $this->fail('WhatsApp is turned off. Switch on "Enable WhatsApp", save, then test again.');
        }

        $template = WhatsAppTemplate::bySociety($societyId)->approved()->find($data['template_id']);
        if (!$template) {
            return $this->fail('Choose an approved template to test with.');
        }

        $result = WhatsAppService::sendTestTemplate($template, $data['phone'], $societyId);

        if ($result['message'] === 'dev_mode') {
            return $this->fail('WhatsApp is not fully set up yet. Save your API key and use "Connect & Verify" first.');
        }
        if (!$result['success']) {
            ChannelStatus::record('whatsapp', $societyId, false, 'Test WhatsApp failed', $result['message']);

            return $this->fail('Test WhatsApp failed. ' . $this->whatsappReason($result['message']), $result['message']);
        }

        ChannelStatus::record('whatsapp', $societyId, true, 'Test message accepted for ' . \App\Services\SmsService::maskPhone($data['phone']));

        return response()->json(['success' => true, 'message' => 'Test WhatsApp sent. WhatsApp accepted it for delivery to ' . \App\Services\SmsService::maskPhone($data['phone']) . '.']);
    }

    private function whatsappReason(string $provider): string
    {
        $p = strtolower($provider);

        return match (true) {
            str_contains($p, 'does not exist')            => 'WhatsApp does not recognise that template on this number. Sync templates and try again.',
            str_contains($p, 'parameter')                 => 'The template expects a different number of values.',
            str_contains($p, 'authentication') || str_contains($p, 'authoriz') => 'Fast2SMS did not accept the API key.',
            str_contains($p, 'unavailable')               => 'Could not reach Fast2SMS. Check the internet connection.',
            default                                       => 'Check the number and try again.',
        };
    }
}
