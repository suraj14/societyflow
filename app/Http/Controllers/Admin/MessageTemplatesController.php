<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EmailEvent;
use App\Http\Controllers\Concerns\AuthorizesSocietyAccess;
use App\Http\Controllers\Controller;
use App\Models\EmailTemplate;
use App\Models\SmsTemplate;
use App\Models\SystemSetting;
use App\Models\WhatsAppTemplate;
use App\Services\SmsService;
use App\Support\SettingSecrets;
use App\Support\TemplateVariables;
use App\Support\WhatsAppPurposes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Message Templates: SMS | WhatsApp | Email, each loading and managing its own real records.
 *
 *  - SMS templates are created and edited here (name, what it is for, who gets it, the wording, the Fast2SMS Message ID,
 *    active or not). The variables available come from App\Support\TemplateVariables and are validated on save.
 *  - WhatsApp templates come from Fast2SMS (Refresh): their wording is fixed by the provider's approval, so a manager
 *    chooses which approved template each notification uses and how its {{n}} values are filled - never edits the text.
 *  - Email templates keep their existing editor; this tab lists them and links to it.
 *
 * Routes sit in the Settings group (Super Admin | Admin, restrict.staff). Everything is scoped to the signed-in
 * society; a Super Admin works with the platform-level (society-less) rows.
 */
class MessageTemplatesController extends Controller
{
    use AuthorizesSocietyAccess;

    private function societyId(): ?int
    {
        $user = auth()->user();

        return $user->hasRole('Super Admin') ? null : $user->society_id;
    }

    // ── The page ────────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $societyId = $this->societyId();
        $channel = in_array($request->query('channel'), ['sms', 'whatsapp', 'email'], true) ? $request->query('channel') : 'sms';

        $counts = [
            'sms'      => SmsTemplate::where('society_id', $societyId)->count(),
            'whatsapp' => WhatsAppTemplate::bySociety($societyId)->count(),
            'email'    => $this->emailQuery($societyId)->count(),
        ];

        return view('admin.message-templates.index', ['channel' => $channel, 'counts' => $counts] + match ($channel) {
            'sms'      => $this->smsTab($request, $societyId),
            'whatsapp' => $this->whatsappTab($societyId),
            'email'    => $this->emailTab($request, $societyId),
        });
    }

    private function smsTab(Request $request, ?int $societyId): array
    {
        $labels = TemplateVariables::smsLabels();
        $q = trim((string) $request->query('q', ''));
        $purpose = array_key_exists((string) $request->query('purpose'), $labels) ? (string) $request->query('purpose') : 'all';
        $status = in_array($request->query('status'), ['active', 'inactive'], true) ? (string) $request->query('status') : 'all';

        $all = SmsTemplate::where('society_id', $societyId)->orderBy('purpose')->orderByDesc('is_active')->orderBy('name')->get();
        $templates = $all
            ->when($purpose !== 'all', fn ($c) => $c->where('purpose', $purpose))
            ->when($status !== 'all', fn ($c) => $c->where('is_active', $status === 'active'))
            ->when($q !== '', fn ($c) => $c->filter(fn ($t) => stripos($t->name . ' ' . $t->body, $q) !== false))
            ->values();

        // purposes with no active template: said once, in one line
        $active = $all->where('is_active', true)->pluck('purpose')->all();
        $withoutTemplate = collect(TemplateVariables::sms())->reject(fn ($p, $key) => in_array($key, $active, true));

        return [
            'templates' => $templates, 'purposeLabels' => $labels, 'filters' => ['q' => $q, 'purpose' => $purpose, 'status' => $status],
            'withoutTemplate' => $withoutTemplate, 'routing' => SmsService::routing($societyId),
        ];
    }

    private function whatsappTab(?int $societyId): array
    {
        $wa = SystemSetting::getGroup('whatsapp', $societyId);

        return [
            // masked: the view never receives a decrypted key
            'settings' => ['whatsapp' => SettingSecrets::mask('whatsapp', $wa)],
            'unmappedWhatsApp' => collect(WhatsAppPurposes::all())->filter(fn ($p, $key) => ($wa["whatsapp_{$key}_template_name"] ?? '') === '')->map(fn ($p) => $p['label'])->values()->all(),
        ];
    }

    private function emailQuery(?int $societyId)
    {
        return EmailTemplate::where(fn ($q) => $q->where('society_id', $societyId)->orWhereNull('society_id'));
    }

    private function emailTab(Request $request, ?int $societyId): array
    {
        $q = trim((string) $request->query('q', ''));
        $templates = $this->emailQuery($societyId)->orderBy('name')->get()
            ->when($q !== '', fn ($c) => $c->filter(fn ($t) => stripos($t->name . ' ' . $t->subject, $q) !== false))->values();

        return ['emailTemplates' => $templates, 'eventLabels' => EmailEvent::labels(), 'filters' => ['q' => $q]];
    }

    // ── SMS templates: create / edit / on-off / delete ──────────────────────

    public function createSms()
    {
        return view('admin.message-templates.sms-form', $this->formData(new SmsTemplate(['purpose' => 'notice', 'audience' => 'resident', 'is_active' => true])));
    }

    public function storeSms(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $societyId = $this->societyId();

        $template = SmsTemplate::create($this->attributes($data) + ['society_id' => $societyId, 'is_active' => false]);
        $replaced = $data['status'] === 'active' ? $this->activate($template) : null;

        return $this->saved($template, $replaced);
    }

    public function editSms(SmsTemplate $smsTemplate)
    {
        $this->authorizeSocietyAccess($smsTemplate);

        return view('admin.message-templates.sms-form', $this->formData($smsTemplate));
    }

    public function updateSms(Request $request, SmsTemplate $smsTemplate): RedirectResponse
    {
        $this->authorizeSocietyAccess($smsTemplate);
        $data = $this->validated($request);

        $smsTemplate->fill($this->attributes($data))->save();
        $replaced = null;
        if ($data['status'] === 'active') {
            $replaced = $this->activate($smsTemplate);
        } else {
            $smsTemplate->forceFill(['is_active' => false])->save();
        }

        return $this->saved($smsTemplate, $replaced);
    }

    public function toggleSms(SmsTemplate $smsTemplate): RedirectResponse
    {
        $this->authorizeSocietyAccess($smsTemplate);

        if ($smsTemplate->is_active) {
            $smsTemplate->forceFill(['is_active' => false])->save();
            $message = "\"{$smsTemplate->name}\" is now inactive. {$smsTemplate->purposeLabel()} has no active SMS template.";
        } else {
            $replaced = $this->activate($smsTemplate);
            $message = "\"{$smsTemplate->name}\" is now the active template for {$smsTemplate->purposeLabel()}." . ($replaced ? " \"{$replaced}\" was switched off." : '');
        }

        return redirect()->route('admin.message-templates.index', ['channel' => 'sms'])->with('success', $message);
    }

    public function destroySms(SmsTemplate $smsTemplate): RedirectResponse
    {
        $this->authorizeSocietyAccess($smsTemplate);
        $smsTemplate->delete();

        return redirect()->route('admin.message-templates.index', ['channel' => 'sms'])->with('success', "\"{$smsTemplate->name}\" was deleted.");
    }

    /** Makes it the active template of its purpose; returns the name of the one it replaced, if any. */
    private function activate(SmsTemplate $template): ?string
    {
        $previous = SmsTemplate::where('society_id', $template->society_id)->where('purpose', $template->purpose)
            ->where('id', '!=', $template->id)->where('is_active', true)->value('name');
        $template->makeTheActiveOne();

        return $previous;
    }

    private function saved(SmsTemplate $template, ?string $replaced): RedirectResponse
    {
        $message = "\"{$template->name}\" saved." . ($replaced ? " \"{$replaced}\" was switched off - one template at a time is used for {$template->purposeLabel()}." : '');

        return redirect()->route('admin.message-templates.index', ['channel' => 'sms'])->with('success', $message);
    }

    private function attributes(array $data): array
    {
        return [
            'name' => trim($data['name']), 'purpose' => $data['purpose'], 'audience' => $data['audience'], 'body' => trim($data['body']),
            'dlt_message_id' => ($data['dlt_message_id'] ?? '') !== '' ? $data['dlt_message_id'] : null,
        ];
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name'           => ['required', 'string', 'max:120'],
            'purpose'        => ['required', Rule::in(array_keys(TemplateVariables::sms()))],
            'audience'       => ['required', Rule::in(array_keys(SmsTemplate::AUDIENCES))],
            'status'         => ['required', Rule::in(['active', 'inactive'])],
            'body'           => ['required', 'string', 'max:' . SmsTemplate::MAX_BODY],
            // Fast2SMS's own short Message ID (DLT Manager), not the long DLT Template ID from the telecom portal.
            'dlt_message_id' => ['nullable', 'regex:/^[0-9]{1,14}$/'],
        ], [
            'dlt_message_id.regex' => 'Message ID is the number Fast2SMS shows next to your approved template, for example 223577 - not the long DLT Template ID.',
            'body.max'             => 'The message can be at most ' . SmsTemplate::MAX_BODY . ' characters.',
        ]);

        $problems = TemplateVariables::problems($data['purpose'], (string) $data['body']);
        if ($problems) {
            throw ValidationException::withMessages(['body' => $problems]);
        }

        return $data;
    }

    private function formData(SmsTemplate $template): array
    {
        return [
            'template' => $template,
            'purposes' => collect(TemplateVariables::sms())->map(fn ($p, $key) => [
                'key' => $key, 'label' => $p['label'], 'help' => $p['help'],
                'variables' => collect($p['variables'])->map(fn ($description, $name) => ['name' => $name, 'description' => $description, 'sample' => TemplateVariables::sample($name)])->values()->all(),
            ])->values()->all(),
            'routing' => SmsService::routing($this->societyId()),
        ];
    }
}
