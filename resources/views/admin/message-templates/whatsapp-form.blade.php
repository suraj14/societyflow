@extends('layouts.app')

@section('title', $template->exists ? 'Edit WhatsApp Template' : 'New WhatsApp Template')
@section('page-title', $template->exists ? 'Edit WhatsApp Template' : 'New WhatsApp Template')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-purple-600">Dashboard</a>
    <span class="mx-2">/</span>
    <a href="{{ route('admin.message-templates.index', ['channel' => 'whatsapp']) }}" class="hover:text-purple-600">Message Templates</a>
    <span class="mx-2">/</span>
    <span class="text-gray-700">{{ $template->exists ? 'Edit' : 'New' }} WhatsApp template</span>
@endsection

@section('content')
@php
    $editing = $template->exists;
    $action = $editing ? route('admin.message-templates.whatsapp.update', $template) : route('admin.message-templates.whatsapp.store');
    $sfInput = 'w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-purple-500';
    $status = old('status', $template->is_active ? 'active' : 'inactive');
    $config = [
        'purposes'  => $purposes,
        'purpose'   => old('purpose', $template->purpose ?: 'notice'),
        'body'      => (string) old('body_text', (string) $template->body_text),
        'variables' => array_values((array) old('variables', $template->exists ? $template->variableFields() : [])),
    ];
    $bodyErrors = $errors->get('body_text');
@endphp

<div class="max-w-3xl space-y-6" x-data="sfWhatsAppTemplateForm(@js($config))">
    <div class="flex items-center justify-between gap-3">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">{{ $editing ? 'Edit WhatsApp Template' : 'New WhatsApp Template' }}</h2>
            <p class="text-sm text-gray-500 mt-1">Copy the approved template from your Fast2SMS WhatsApp account. The wording and the order of the variables must match exactly.</p>
        </div>
        <a href="{{ route('admin.message-templates.index', ['channel' => 'whatsapp']) }}" class="shrink-0 text-sm font-semibold text-gray-600 hover:text-gray-900"><i class="fas fa-arrow-left text-xs mr-1"></i> Back</a>
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
            <input id="t-name" type="text" name="name" value="{{ old('name', $template->friendly_name ?: ($editing ? $template->displayName() : '')) }}" maxlength="120" required placeholder="e.g. Visitor approved" class="{{ $sfInput }}">
            <p class="text-xs text-gray-500 mt-1">A name for you. It is not sent to anyone.</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label for="t-purpose" class="block text-sm font-medium text-gray-700 mb-1.5">Notification Type <span class="text-red-500">*</span></label>
                <select id="t-purpose" name="purpose" x-model="purpose" @change="reset()" class="{{ $sfInput }}">
                    @foreach ($purposes as $p)
                        <option value="{{ $p['key'] }}">{{ $p['label'] }}</option>
                    @endforeach
                </select>
                <p class="text-xs text-gray-500 mt-1" x-text="current().help"></p>
            </div>
            <fieldset>
                <legend class="block text-sm font-medium text-gray-700 mb-1.5">Status</legend>
                <div class="flex gap-4 text-sm pt-2">
                    <label class="inline-flex items-center gap-2 cursor-pointer"><input type="radio" name="status" value="active" @checked($status === 'active') class="text-purple-600"> Active</label>
                    <label class="inline-flex items-center gap-2 cursor-pointer"><input type="radio" name="status" value="inactive" @checked($status === 'inactive') class="text-purple-600"> Inactive</label>
                </div>
                <p class="text-xs text-gray-500 mt-1">One template at a time is used for each type.</p>
            </fieldset>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="sm:col-span-2">
                <label for="t-tname" class="block text-sm font-medium text-gray-700 mb-1.5">Approved Template Name <span class="text-red-500">*</span></label>
                <input id="t-tname" type="text" name="template_name" value="{{ old('template_name', $template->name) }}" maxlength="200" required placeholder="e.g. visitor_approved_v2" autocapitalize="none" class="{{ $sfInput }} font-mono">
            </div>
            <div>
                <label for="t-lang" class="block text-sm font-medium text-gray-700 mb-1.5">Language</label>
                <input id="t-lang" type="text" name="language" value="{{ old('language', $template->language ?: 'en') }}" maxlength="6" placeholder="en" class="{{ $sfInput }}">
            </div>
        </div>

        <div>
            <label for="t-mid" class="block text-sm font-medium text-gray-700 mb-1.5">Fast2SMS WhatsApp Message ID</label>
            <input id="t-mid" type="text" name="message_id" value="{{ old('message_id', $template->message_id) }}" inputmode="numeric" maxlength="15" placeholder="e.g. 8123" class="{{ $sfInput }}">
            <p class="text-xs text-gray-500 mt-1">The Message ID Fast2SMS shows next to the template. Without it the template is sent by its name and language. Set the Phone Number ID in
                <a href="{{ route('settings.index', ['tab' => 'whatsapp']) }}" class="underline">WhatsApp Settings</a>.</p>
        </div>

        <div>
            <label for="t-body" class="block text-sm font-medium text-gray-700 mb-1.5">Message Body <span class="text-red-500">*</span></label>
            <textarea id="t-body" name="body_text" x-model="body" rows="6" maxlength="1024" required placeholder="Hello @{{1}}, your visitor @{{2}} has been approved." class="{{ $sfInput }}">{{ old('body_text', $template->body_text) }}</textarea>
            <div class="flex items-start justify-between gap-3 mt-1">
                <p class="text-xs text-gray-500">Paste the approved text. Variables are numbered <span class="font-mono">@{{1}}</span>, <span class="font-mono">@{{2}}</span> ...</p>
                <p class="text-xs text-gray-500 whitespace-nowrap"><span x-text="body.length"></span>/1024</p>
            </div>
            @foreach ($bodyErrors as $message)<p class="text-xs text-red-700 mt-1">{{ $message }}</p>@endforeach
        </div>

        <div x-show="numbers().length" x-cloak>
            <p class="block text-sm font-medium text-gray-700 mb-1.5">Variables <span class="font-normal text-gray-500">- what fills each one</span></p>
            <p class="text-xs text-red-700 mb-2" x-show="numbers().length > current().fields.length">This notification can fill at most <span x-text="current().fields.length"></span> values.</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <template x-for="(n, i) in numbers()" :key="n">
                    <label class="text-sm">
                        <span class="block text-gray-700 mb-1">Fill <span class="font-mono" x-text="'{' + '{' + n + '}' + '}'"></span> with</span>
                        <select name="variables[]" x-model="vars[i]" class="{{ $sfInput }}">
                            <template x-for="f in current().fields" :key="f.key"><option :value="f.key" x-text="f.label" :selected="f.key === vars[i]"></option></template>
                        </select>
                    </label>
                </template>
            </div>
        </div>

        <div>
            <p class="block text-sm font-medium text-gray-700 mb-1.5">Preview <span class="font-normal text-gray-500">- with example values</span></p>
            <p class="text-sm text-gray-800 bg-gray-50 rounded-xl px-3 py-2.5 whitespace-pre-line break-words min-h-[2.5rem]" x-text="preview() || 'Your message appears here.'"></p>
        </div>

        <p class="text-xs text-gray-500 bg-gray-50 rounded-xl px-3 py-2.5">
            <i class="fas fa-circle-info mr-1"></i> Saving does not contact WhatsApp or Fast2SMS and does not mark the template approved. Only a template that is approved in your Fast2SMS account can be delivered.
        </p>

        <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2 pt-1">
            <a href="{{ route('admin.message-templates.index', ['channel' => 'whatsapp']) }}" class="px-5 py-2.5 rounded-xl border border-gray-300 text-gray-700 text-sm font-semibold text-center hover:bg-gray-50">Cancel</a>
            <button type="submit" :disabled="saving" class="px-6 py-2.5 rounded-xl bg-gradient-to-r from-purple-600 to-indigo-600 text-white text-sm font-semibold shadow hover:from-purple-700 hover:to-indigo-700 disabled:opacity-60">Save Changes</button>
        </div>
    </form>
</div>

@push('scripts')
<script>
function sfWhatsAppTemplateForm(cfg) {
    // {{1}}, {{2}} ... in the pasted text (built from a string so Blade never sees two opening braces in a row)
    const re = () => new RegExp('\\{\\{\\s*(\\d+)\\s*\\}\\}', 'g');
    return {
        purposes: cfg.purposes, purpose: cfg.purpose, body: cfg.body ?? '', vars: [...(cfg.variables || [])],
        current() { return this.purposes.find(p => p.key === this.purpose) || { fields: [], help: '' }; },
        numbers() {
            const n = [...new Set([...this.body.matchAll(re())].map(m => parseInt(m[1], 10)))].sort((a, b) => a - b);
            // keep one chosen value per variable, defaulting to the notification's own order
            const keys = this.current().fields.map(f => f.key);
            while (this.vars.length < n.length) this.vars.push(keys[this.vars.length] || keys[0] || '');
            if (this.vars.length > n.length) this.vars.length = n.length;
            return n;
        },
        reset() { this.vars = []; },
        preview() {
            const fields = this.current().fields;
            return this.body.replace(re(), (m, n) => { const f = fields.find(x => x.key === this.vars[parseInt(n, 10) - 1]); return f ? f.sample : m; });
        },
    };
}
</script>
@endpush
@endsection
