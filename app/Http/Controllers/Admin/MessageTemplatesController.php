<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EmailEvent;
use App\Http\Controllers\Concerns\AuthorizesSocietyAccess;
use App\Http\Controllers\Controller;
use App\Models\EmailTemplate;
use App\Models\SmsTemplate;
use App\Models\WhatsAppTemplate;
use App\Services\SmsService;
use App\Support\TemplateVariables;
use App\Support\WhatsAppPurposes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Message Templates: SMS | WhatsApp | Email, each loading and managing its own real records.
 *
 *  - SMS templates are created and edited here (name, what it is for, who gets it, the wording, the Fast2SMS Message ID,
 *    active or not). The variables available come from App\Support\TemplateVariables and are validated on save.
 *  - WhatsApp templates are pasted in by hand, like SMS: the admin copies an approved template from the provider portal
 *    and saves its name / Fast2SMS Message ID, language, text and which value fills each {{n}}. Nothing is fetched or
 *    synced; a saved template is "not verified" because only the provider can approve it.
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
            'whatsapp' => $this->whatsappTab($request, $societyId),
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

    private function whatsappTab(Request $request, ?int $societyId): array
    {
        $labels = collect(WhatsAppPurposes::selectable())->map(fn ($p) => $p['label'])->all();
        $q = trim((string) $request->query('q', ''));
        $purpose = array_key_exists((string) $request->query('purpose'), $labels) ? (string) $request->query('purpose') : 'all';
        $status = in_array($request->query('status'), ['active', 'inactive'], true) ? (string) $request->query('status') : 'all';

        $all = WhatsAppTemplate::bySociety($societyId)->orderBy('purpose')->orderByDesc('is_active')->orderBy('friendly_name')->orderBy('name')->get();
        $templates = $all
            ->when($purpose !== 'all', fn ($c) => $c->where('purpose', $purpose))
            ->when($status !== 'all', fn ($c) => $c->where('is_active', $status === 'active'))
            ->when($q !== '', fn ($c) => $c->filter(fn ($t) => stripos($t->displayName() . ' ' . $t->name . ' ' . $t->body_text, $q) !== false))
            ->values();

        $active = $all->where('is_active', true)->pluck('purpose')->all();

        return [
            'templates' => $templates, 'purposeLabels' => $labels, 'filters' => ['q' => $q, 'purpose' => $purpose, 'status' => $status],
            'unmappedWhatsApp' => collect($labels)->reject(fn ($label, $key) => in_array($key, $active, true))->values()->all(),
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

        [$smsTemplate, $replaced] = DB::transaction(function () use ($data, $smsTemplate) {
            // Someone else saved it while this form was open: do not overwrite their change silently.
            $current = SmsTemplate::lockForUpdate()->findOrFail($smsTemplate->id);
            if (isset($data['loaded']) && $data['loaded'] !== (string) $current->updated_at?->timestamp) {
                throw ValidationException::withMessages(['name' => 'This template was changed since you opened it. Open it again to see the latest version.']);
            }
            $current->fill($this->attributes($data))->save();
            $replaced = null;
            if ($data['status'] === 'active') {
                $replaced = $this->activate($current);
            } else {
                $current->forceFill(['is_active' => false])->save();
            }

            return [$current, $replaced];
        });

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

    // ── WhatsApp templates: create / edit / on-off / delete (typed in by hand) ─────────

    public function createWhatsApp()
    {
        return view('admin.message-templates.whatsapp-form', $this->whatsappFormData(new WhatsAppTemplate(['purpose' => 'notice', 'language' => 'en', 'is_active' => true, 'source' => WhatsAppTemplate::SOURCE_MANUAL, 'var_count' => 0])));
    }

    public function storeWhatsApp(Request $request): RedirectResponse
    {
        $data = $this->whatsappValidated($request);
        $societyId = $this->societyId();

        [$template, $replaced] = DB::transaction(function () use ($data, $societyId) {
            $t = WhatsAppTemplate::create($this->whatsappAttributes($data) + [
                'society_id' => $societyId, 'external_id' => 'manual-' . Str::uuid(), 'source' => WhatsAppTemplate::SOURCE_MANUAL,
                'status' => 'LOCAL', 'is_active' => false,
            ]);
            $replaced = $data['status'] === 'active' ? $this->activateWhatsApp($t) : null;

            return [$t, $replaced];
        });

        return $this->savedWhatsApp($template, $replaced);
    }

    public function editWhatsApp(WhatsAppTemplate $whatsappTemplate)
    {
        $this->authorizeSocietyAccess($whatsappTemplate);

        return view('admin.message-templates.whatsapp-form', $this->whatsappFormData($whatsappTemplate));
    }

    public function updateWhatsApp(Request $request, WhatsAppTemplate $whatsappTemplate): RedirectResponse
    {
        $this->authorizeSocietyAccess($whatsappTemplate);
        $data = $this->whatsappValidated($request);

        [$template, $replaced] = DB::transaction(function () use ($data, $whatsappTemplate) {
            // Someone else saved it while this form was open: do not overwrite their change silently.
            $current = WhatsAppTemplate::lockForUpdate()->findOrFail($whatsappTemplate->id);
            if (isset($data['loaded']) && $data['loaded'] !== (string) $current->updated_at?->timestamp) {
                throw ValidationException::withMessages(['name' => 'This template was changed since you opened it. Open it again to see the latest version.']);
            }
            $current->fill($this->whatsappAttributes($data))->save();
            $replaced = null;
            if ($data['status'] === 'active') {
                $replaced = $this->activateWhatsApp($current);
            } else {
                $current->forceFill(['is_active' => false])->save();
            }

            return [$current, $replaced];
        });

        return $this->savedWhatsApp($template, $replaced);
    }

    public function toggleWhatsApp(WhatsAppTemplate $whatsappTemplate): RedirectResponse
    {
        $this->authorizeSocietyAccess($whatsappTemplate);

        if ($whatsappTemplate->is_active) {
            $whatsappTemplate->forceFill(['is_active' => false])->save();
            $message = "\"{$whatsappTemplate->displayName()}\" is now inactive. {$whatsappTemplate->purposeLabel()} is not sent on WhatsApp.";
        } else {
            if (!$whatsappTemplate->purpose) {
                return redirect()->route('admin.message-templates.index', ['channel' => 'whatsapp'])->with('error', 'Choose what this template is for before switching it on - open it with Edit.');
            }
            $replaced = $this->activateWhatsApp($whatsappTemplate);
            $message = "\"{$whatsappTemplate->displayName()}\" is now the active template for {$whatsappTemplate->purposeLabel()}." . ($replaced ? " \"{$replaced}\" was switched off." : '');
        }

        return redirect()->route('admin.message-templates.index', ['channel' => 'whatsapp'])->with('success', $message);
    }

    public function destroyWhatsApp(WhatsAppTemplate $whatsappTemplate): RedirectResponse
    {
        $this->authorizeSocietyAccess($whatsappTemplate);

        // An active template is the one a notification is using right now: switch it off first, so deleting never silently
        // stops a notification that was working.
        if ($whatsappTemplate->is_active) {
            return redirect()->route('admin.message-templates.index', ['channel' => 'whatsapp'])
                ->with('error', "\"{$whatsappTemplate->displayName()}\" is in use for {$whatsappTemplate->purposeLabel()}. Switch it off first, then delete it.");
        }

        $name = $whatsappTemplate->displayName();
        $whatsappTemplate->delete();

        return redirect()->route('admin.message-templates.index', ['channel' => 'whatsapp'])->with('success', "\"{$name}\" was deleted.");
    }

    private function activateWhatsApp(WhatsAppTemplate $template): ?string
    {
        $previous = WhatsAppTemplate::where('society_id', $template->society_id)->where('purpose', $template->purpose)
            ->where('id', '!=', $template->id)->where('is_active', true)->get()->map->displayName()->first();
        $template->makeTheActiveOne();

        return $previous;
    }

    private function savedWhatsApp(WhatsAppTemplate $template, ?string $replaced): RedirectResponse
    {
        $message = "\"{$template->displayName()}\" saved." . ($replaced ? " \"{$replaced}\" was switched off - one template at a time is used for {$template->purposeLabel()}." : '');

        return redirect()->route('admin.message-templates.index', ['channel' => 'whatsapp'])->with('success', $message);
    }

    private function whatsappAttributes(array $data): array
    {
        $numbers = WhatsAppTemplate::placeholderNumbers((string) $data['body_text']);

        return [
            'friendly_name' => trim($data['name']), 'purpose' => $data['purpose'], 'name' => trim($data['template_name']),
            'message_id' => ($data['message_id'] ?? '') !== '' ? $data['message_id'] : null,
            'language' => ($data['language'] ?? '') !== '' ? $data['language'] : 'en',
            'body_text' => $data['body_text'], 'var_count' => count($numbers), 'variable_map' => array_values($data['variables'] ?? []),
        ];
    }

    private function whatsappValidated(Request $request): array
    {
        $purposes = WhatsAppPurposes::selectable();
        $data = $request->validate([
            'name'          => ['required', 'string', 'max:120'],
            'purpose'       => ['required', Rule::in(array_keys($purposes))],
            'template_name' => ['required', 'string', 'max:200', 'regex:/^[A-Za-z0-9_\-]+$/'],
            // Fast2SMS's own integer message id (Fast2SMS -> WhatsApp -> templates), the id its send API takes.
            'message_id'    => ['nullable', 'regex:/^[0-9]{1,15}$/'],
            'language'      => ['nullable', 'regex:/^[a-z]{2,3}(_[A-Z]{2})?$/'],
            'status'        => ['required', Rule::in(['active', 'inactive'])],
            'body_text'     => ['required', 'string', 'max:1024'],
            'variables'     => ['nullable', 'array'],
            'variables.*'   => ['string'],
            'loaded'        => ['nullable', 'string'],
        ], [
            'template_name.regex' => "The template name is the provider's identifier: letters, numbers, underscores and hyphens only, no spaces.",
            'message_id.regex'    => 'Message ID is the number Fast2SMS shows for the WhatsApp template, for example 8123.',
            'language.regex'      => 'Language is a code such as en or en_US.',
            'body_text.max'       => 'WhatsApp message text can be at most 1024 characters.',
        ]);

        // {{1}}, {{2}} ... must run 1..n with no gaps (the provider's own rule), and each needs a value chosen for it.
        $numbers = WhatsAppTemplate::placeholderNumbers((string) $data['body_text']);
        sort($numbers);
        if (preg_match('/\{\{(?!\s*\d+\s*\}\})/', (string) $data['body_text'])) {
            throw ValidationException::withMessages(['body_text' => 'A WhatsApp variable is a number in double braces, like {{1}}. Check for a half-written or named placeholder.']);
        }
        if ($numbers !== [] && $numbers !== range(1, count($numbers))) {
            throw ValidationException::withMessages(['body_text' => 'The variables must be numbered {{1}}, {{2}}, {{3}} ... in order, with none missing.']);
        }
        $fields = $purposes[$data['purpose']]['fields'];
        if (count($numbers) > count($fields)) {
            throw ValidationException::withMessages(['body_text' => 'This notification can fill at most ' . count($fields) . ' values, but the text has ' . count($numbers) . '. Use a simpler template.']);
        }
        // Nothing chosen (the basic paste-and-save flow): {{1}}, {{2}} ... take the notification's own values in their
        // documented order at send time. Only an explicit choice in "Change what fills each variable" is checked.
        $vars = array_values($data['variables'] ?? []);
        if ($vars === []) {
            $vars = array_slice(array_keys($fields), 0, count($numbers));
            $data['variables'] = $vars;
        }
        if (count($vars) !== count($numbers)) {
            throw ValidationException::withMessages(['variables' => 'Choose a value for each of the ' . count($numbers) . ' variable(s) in the text.']);
        }
        foreach ($vars as $v) {
            if (!array_key_exists($v, $fields)) {
                throw ValidationException::withMessages(['variables' => 'One of the chosen values is not available for this notification.']);
            }
        }

        return $data;
    }

    private function whatsappFormData(WhatsAppTemplate $template): array
    {
        return [
            'template' => $template,
            'purposes' => collect(WhatsAppPurposes::selectable())->map(fn ($p, $key) => [
                'key' => $key, 'label' => $p['label'], 'help' => $p['help'],
                'fields' => collect($p['fields'])->map(fn ($label, $field) => ['key' => $field, 'label' => $label, 'sample' => WhatsAppPurposes::sample($field)])->values()->all(),
            ])->values()->all(),
        ];
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
            'loaded'         => ['nullable', 'string'],
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
