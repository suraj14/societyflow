@extends('layouts.app')

@section('title', $template->exists ? 'Edit SMS Template' : 'New SMS Template')
@section('page-title', $template->exists ? 'Edit SMS Template' : 'New SMS Template')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-purple-600">Dashboard</a>
    <span class="mx-2">/</span>
    <a href="{{ route('admin.message-templates.index', ['channel' => 'sms']) }}" class="hover:text-purple-600">Message Templates</a>
    <span class="mx-2">/</span>
    <span class="text-gray-700">{{ $template->exists ? 'Edit' : 'New' }} SMS template</span>
@endsection

@section('content')
@php
    $editing = $template->exists;
    $action = $editing ? route('admin.message-templates.sms.update', $template) : route('admin.message-templates.sms.store');
    $sfInput = 'w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-purple-500';
    $status = old('status', $template->is_active ? 'active' : 'inactive');
    $config = [
        'purposes' => $purposes,
        'purpose'  => old('purpose', $template->purpose ?: 'notice'),
        'body'     => (string) old('body', (string) $template->body),
        'max'      => \App\Models\SmsTemplate::MAX_BODY,
    ];
    $bodyErrors = $errors->get('body');
@endphp

<div class="max-w-3xl space-y-6" x-data="sfSmsTemplateForm(@js($config))">
    <div class="flex items-center justify-between gap-3">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">{{ $editing ? 'Edit SMS Template' : 'New SMS Template' }}</h2>
            <p class="text-sm text-gray-500 mt-1">The wording must match your DLT-approved text, with the same variables in the same order.</p>
        </div>
        <a href="{{ route('admin.message-templates.index', ['channel' => 'sms']) }}" class="shrink-0 text-sm font-semibold text-gray-600 hover:text-gray-900"><i class="fas fa-arrow-left text-xs mr-1"></i> Back</a>
    </div>

    @if ($errors->any())
        <div class="rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-900" role="alert">
            <p class="font-semibold"><i class="fas fa-circle-xmark mr-1"></i> The template was not saved. Please fix:</p>
            <ul class="list-disc ml-5 mt-1 space-y-0.5">
                @foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ $action }}" class="bg-white rounded-2xl border border-gray-200 p-5 sm:p-6 space-y-5" x-data="{ saving: false }" @submit="if (saving) { $event.preventDefault(); } else { saving = true; }">
        @csrf
        @if ($editing)
            @method('PUT')
            <input type="hidden" name="loaded" value="{{ $template->updated_at?->timestamp }}">
        @endif

        <div>
            <label for="t-name" class="block text-sm font-medium text-gray-700 mb-1.5">Template Name <span class="text-red-500">*</span></label>
            <input id="t-name" type="text" name="name" value="{{ old('name', $template->name) }}" maxlength="120" required placeholder="e.g. Notice alert" class="{{ $sfInput }}">
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label for="t-purpose" class="block text-sm font-medium text-gray-700 mb-1.5">Type</label>
                <select id="t-purpose" name="purpose" x-model="purpose" class="{{ $sfInput }}">
                    @foreach ($purposes as $p)
                        <option value="{{ $p['key'] }}">{{ $p['label'] }}</option>
                    @endforeach
                </select>
                <p class="text-xs text-gray-500 mt-1" x-text="current().help"></p>
                <p class="text-xs text-indigo-700 mt-1" x-show="current().dlt_text" x-cloak>Registered text for this type: <span class="font-mono" x-text="current().dlt_text"></span></p>
            </div>
            <div>
                <label for="t-audience" class="block text-sm font-medium text-gray-700 mb-1.5">Recipient</label>
                <select id="t-audience" name="audience" class="{{ $sfInput }}">
                    @foreach (\App\Models\SmsTemplate::AUDIENCES as $key => $label)
                        <option value="{{ $key }}" @selected(old('audience', $template->audience ?: 'resident') === $key) title="{{ \App\Support\SmsAudience::OPTIONS[$key][1] ?? '' }}">{{ $label }}</option>
                    @endforeach
                </select>
                <p id="t-audience-preview" class="text-xs text-gray-500 mt-1" aria-live="polite"></p>
                <script>
                    (function () {
                        var sel = document.getElementById('t-audience'), out = document.getElementById('t-audience-preview');
                        function show() {
                            fetch('{{ route('admin.message-templates.sms.audience-preview') }}?audience=' + encodeURIComponent(sel.value), {headers: {'Accept': 'application/json'}})
                                .then(function (r) { return r.ok ? r.json() : null; })
                                .then(function (d) {
                                    if (!d) { out.textContent = ''; return; }
                                    out.textContent = d.can_send
                                        ? d.label + ': ' + d.count + ' eligible recipient(s)' + (d.bulk ? ' - a bulk audience, so any send is confirmed first.' : '.')
                                        : d.reason;
                                }).catch(function () { out.textContent = ''; });
                        }
                        sel.addEventListener('change', show); show();
                    })();
                </script>
            </div>
        </div>

        <fieldset>
            <legend class="block text-sm font-medium text-gray-700 mb-1.5">Status</legend>
            <div class="flex gap-4 text-sm">
                <label class="inline-flex items-center gap-2 cursor-pointer"><input type="radio" name="status" value="active" @checked($status === 'active') class="text-purple-600"> Active</label>
                <label class="inline-flex items-center gap-2 cursor-pointer"><input type="radio" name="status" value="inactive" @checked($status === 'inactive') class="text-purple-600"> Inactive</label>
            </div>
            <p class="text-xs text-gray-500 mt-1">One template at a time is used for each type. Making this one active switches off the current one.</p>
        </fieldset>

        <div>
            <label for="t-body" class="block text-sm font-medium text-gray-700 mb-1.5">Message Body <span class="text-red-500">*</span></label>
            <textarea id="t-body" name="body" x-ref="body" x-model="body" rows="5" maxlength="{{ \App\Models\SmsTemplate::MAX_BODY }}" required class="{{ $sfInput }} font-mono">{{ old('body', $template->body) }}</textarea>
            <div class="flex items-start justify-between gap-3 mt-1">
                <p class="text-xs text-red-700" x-show="unknown().length" x-cloak>Not available for this type: <span x-text="unknown().map(n => '{' + '{' + n + '}' + '}').join(', ')"></span></p>
                <p class="text-xs text-gray-500 ml-auto whitespace-nowrap"><span x-text="body.length"></span>/{{ \App\Models\SmsTemplate::MAX_BODY }}</p>
            </div>
            @foreach ($bodyErrors as $message)<p class="text-xs text-red-700 mt-1">{{ $message }}</p>@endforeach
        </div>

        <div>
            <p class="block text-sm font-medium text-gray-700 mb-1.5">Variables <span class="font-normal text-gray-500">- click one to put it in the message at the cursor</span></p>
            <div class="flex flex-wrap gap-2">
                <template x-for="v in current().variables" :key="v.name">
                    <button type="button" @click="insert(v.name)" :title="v.description"
                            class="px-2.5 py-1 rounded-lg border border-purple-200 bg-purple-50 text-purple-800 text-xs font-mono hover:bg-purple-100" x-text="'{' + '{' + v.name + '}' + '}'"></button>
                </template>
            </div>
            <ul class="mt-2 text-xs text-gray-500 space-y-0.5">
                <template x-for="v in current().variables" :key="'d-' + v.name"><li><span class="font-mono text-gray-700" x-text="v.name"></span> &ndash; <span x-text="v.description"></span></li></template>
            </ul>
        </div>

        <div>
            <p class="block text-sm font-medium text-gray-700 mb-1.5">Preview <span class="font-normal text-gray-500">- with example values</span></p>
            <p class="text-sm text-gray-800 bg-gray-50 rounded-xl px-3 py-2.5 whitespace-pre-line break-words min-h-[2.5rem]" x-text="preview() || 'Your message appears here.'"></p>
        </div>

        <div>
            <label for="t-mid" class="block text-sm font-medium text-gray-700 mb-1.5">Fast2SMS Message ID (DLT)</label>
            <input id="t-mid" type="text" name="dlt_message_id" value="{{ old('dlt_message_id', $template->dlt_message_id) }}" inputmode="numeric" maxlength="14" placeholder="e.g. 223577" class="{{ $sfInput }}">
            <p class="text-xs text-gray-500 mt-1">Paste the Message ID of your approved SMS template from Fast2SMS DLT Manager. The message text and variable order must match the approved DLT template. Configure the matching Sender ID in
                <a href="{{ route('settings.index', ['tab' => 'sms']) }}" class="underline">SMS Settings</a>.
                Leave the Message ID empty only when Quick SMS is intended.</p>
            <p class="text-xs mt-1.5 {{ $routing['sender_id'] === '' ? 'text-amber-800' : 'text-gray-500' }}">
                @if ($routing['sender_id'] === '')
                    <i class="fas fa-triangle-exclamation mr-1"></i> No Sender ID is saved in SMS Settings yet, so a template with a Message ID cannot be sent.
                @else
                    Sender ID in SMS Settings: <strong>{{ $routing['sender_id'] }}</strong>.
                @endif
                @if ($routing['default_message_id'] !== '')
                    Without a Message ID here, the default Message ID <strong>{{ $routing['default_message_id'] }}</strong> from SMS Settings is used.
                @endif
            </p>
        </div>

        <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2 pt-1">
            <a href="{{ route('admin.message-templates.index', ['channel' => 'sms']) }}" class="px-5 py-2.5 rounded-xl border border-gray-300 text-gray-700 text-sm font-semibold text-center hover:bg-gray-50">Cancel</a>
            <button type="submit" :disabled="saving" class="px-6 py-2.5 rounded-xl bg-gradient-to-r from-purple-600 to-indigo-600 text-white text-sm font-semibold shadow hover:from-purple-700 hover:to-indigo-700 disabled:opacity-60">Save Changes</button>
        </div>
    </form>
</div>

@push('scripts')
<script>
function sfSmsTemplateForm(cfg) {
    // matches a placeholder; built from a string so Blade never sees two opening braces in a row
    const re = () => new RegExp('\\{\\{\\s*([A-Za-z0-9_]+)\\s*\\}\\}', 'g');
    return {
        // an empty box comes back from a failed save as null (empty strings are converted), which broke the counter/preview
        purposes: cfg.purposes, purpose: cfg.purpose, body: cfg.body ?? '', max: cfg.max,
        current() { return this.purposes.find(p => p.key === this.purpose) || { variables: [], help: '' }; },
        insert(name) {
            const el = this.$refs.body, token = '{' + '{' + name + '}' + '}';
            const start = el.selectionStart ?? this.body.length, end = el.selectionEnd ?? start;
            this.body = (this.body.slice(0, start) + token + this.body.slice(end)).slice(0, this.max);
            this.$nextTick(() => { el.focus(); const pos = Math.min(start + token.length, this.body.length); el.setSelectionRange(pos, pos); });
        },
        preview() {
            const samples = Object.fromEntries(this.current().variables.map(v => [v.name, v.sample]));
            return this.body.replace(re(), (m, n) => (n in samples ? samples[n] : m));
        },
        unknown() {
            const allowed = this.current().variables.map(v => v.name);
            return [...new Set([...this.body.matchAll(re())].map(m => m[1]).filter(n => !allowed.includes(n)))];
        },
    };
}
</script>
@endpush
@endsection
