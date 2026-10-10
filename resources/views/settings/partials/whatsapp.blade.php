{{-- Settings -> WhatsApp. One place for WhatsApp credentials; templates are picked, never typed. --}}
@php
    $sfWa = $settings['whatsapp'] ?? [];
    $sfWaSociety = auth()->user()->hasRole('Super Admin') ? null : auth()->user()->society_id;
    $sfWaStatus = \App\Support\ChannelStatus::get('whatsapp', $sfWaSociety);
    $sfWaHasKey = !empty($sfWa['whatsapp_api_key']);
    $sfWaConfigured = $sfWaHasKey && !empty($sfWa['whatsapp_phone_number_id']);
    $sfWaTemplates = \App\Models\WhatsAppTemplate::bySociety($sfWaSociety)->orderByRaw("CASE WHEN upper(status) = 'APPROVED' THEN 0 ELSE 1 END")->orderBy('name')->get();
    $sfWaPurposes = \App\Support\WhatsAppPurposes::all();
    $sfWaByName = $sfWaTemplates->keyBy('name');
    $sfWaAssigned = [];
    foreach ($sfWaPurposes as $key => $p) {
        $name = (string) ($sfWa["whatsapp_{$key}_template_name"] ?? '');
        $sfWaAssigned[$key] = ['name' => $name, 'template' => $sfWaByName->get($name)];
    }
    $sfUsedFor = [];
    foreach ($sfWaAssigned as $key => $a) {
        if ($a['template']) { $sfUsedFor[$a['template']->id][] = $sfWaPurposes[$key]['label']; }
    }
    $sfInput = 'w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-purple-500';
    $sfJsTemplates = $sfWaTemplates->filter->isApproved()->map(fn ($t) => [
        'id' => $t->id, 'label' => $t->displayName(), 'language' => $t->language, 'vars' => $t->var_count, 'body' => $t->body_text,
    ])->values();
    $sfJsPurposes = collect($sfWaPurposes)->map(fn ($p, $k) => ['key' => $k, 'label' => $p['label'], 'fields' => $p['fields']])->values();
@endphp

<div class="max-w-4xl space-y-6" x-data="sfWhatsApp(@js($sfJsTemplates), @js($sfJsPurposes))">
    <div>
        <h2 class="text-2xl font-bold text-gray-900">WhatsApp</h2>
        <p class="text-sm text-gray-500 mt-1">Send society updates on WhatsApp through Fast2SMS. Connect once, then choose which approved templates to use. Anything without a template is simply not sent on WhatsApp.</p>
    </div>

    @include('settings.partials.channel-status', [
        'configured' => $sfWaConfigured,
        'status' => $sfWaStatus,
        'okLabel' => 'WhatsApp connected',
        'notConfiguredLabel' => 'WhatsApp not configured',
        'notConfiguredHint' => $sfWaHasKey ? 'Your API key is saved. Click "Connect & Verify" to find your WhatsApp number.' : 'Enter your Fast2SMS API key and save, then click "Connect & Verify".',
        'savedLabel' => 'Configuration saved',
        'savedHint' => 'Click "Connect & Verify" or send a test message to confirm.',
    ])

    <form action="{{ route('settings.whatsapp.update') }}" method="POST" class="space-y-6">
        @csrf
        <div class="bg-white rounded-2xl border border-gray-200 p-5 sm:p-6 space-y-5">
            <label class="flex items-center justify-between gap-4 cursor-pointer">
                <span>
                    <span class="block font-semibold text-gray-900">Enable WhatsApp</span>
                    <span class="block text-sm text-gray-500">Turn off to stop all WhatsApp messages without losing your setup.</span>
                </span>
                <input type="hidden" name="whatsapp_enabled" value="0">
                <input type="checkbox" name="whatsapp_enabled" value="1" @checked($sfWa['whatsapp_enabled'] ?? false) class="w-11 h-6 rounded-full text-green-600 focus:ring-green-500">
            </label>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">WhatsApp Provider</label>
                    <div class="px-4 py-2.5 rounded-xl bg-gray-50 border border-gray-200 text-sm text-gray-800">Fast2SMS WhatsApp</div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Fast2SMS API Authorization Key</label>
                    <input type="password" name="whatsapp_api_key" autocomplete="new-password" value="{{ $sfWa['whatsapp_api_key'] ?? '' }}" placeholder="Paste from Fast2SMS > Dev API" class="{{ $sfInput }}">
                    <p class="text-xs mt-1 {{ $sfWaHasKey ? 'text-green-700' : 'text-gray-500' }}">@if ($sfWaHasKey)<i class="fas fa-lock mr-1"></i>API key configured (stored encrypted)@else Not configured @endif</p>
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Phone Number ID</label>
                    <input type="text" name="whatsapp_phone_number_id" value="{{ $sfWa['whatsapp_phone_number_id'] ?? '' }}" placeholder="Filled in by Connect & Verify" class="{{ $sfInput }}">
                    <p class="text-xs text-gray-500 mt-1">You don't need to find this yourself - "Connect & Verify" fills it in.</p>
                </div>
            </div>
        </div>

        <details class="bg-white rounded-2xl border border-gray-200 p-5 sm:p-6">
            <summary class="cursor-pointer text-sm font-medium text-gray-700 select-none">Advanced / Technical details</summary>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">WhatsApp Business Account ID</label>
                    <input type="text" name="whatsapp_waba_id" value="{{ $sfWa['whatsapp_waba_id'] ?? '' }}" class="{{ $sfInput }}">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">API Version</label>
                    <input type="text" name="whatsapp_api_version" value="{{ $sfWa['whatsapp_api_version'] ?? 'v26.0' }}" class="{{ $sfInput }}">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Default template language</label>
                    <input type="text" name="whatsapp_template_language" value="{{ $sfWa['whatsapp_template_language'] ?? 'en_US' }}" class="{{ $sfInput }}">
                    <p class="text-xs text-gray-500 mt-1">Synced templates always use their own language.</p>
                </div>
            </div>
        </details>

        <div class="flex justify-end">
            <button type="submit" class="px-6 py-2.5 rounded-xl bg-gradient-to-r from-purple-600 to-indigo-600 text-white text-sm font-semibold shadow hover:from-purple-700 hover:to-indigo-700">Save Changes</button>
        </div>
    </form>

    {{-- Connect / sync / verify --}}
    <div class="bg-white rounded-2xl border border-gray-200 p-5 sm:p-6 space-y-4">
        <div class="flex flex-wrap gap-2">
            <button type="button" @click="connect()" :disabled="busy" class="px-4 py-2.5 rounded-xl bg-green-600 text-white text-sm font-semibold hover:bg-green-700 disabled:opacity-60"><i class="fas fa-plug mr-1"></i> Connect &amp; Verify</button>
            <button type="button" @click="call(@js(route('settings.whatsapp.sync')))" :disabled="busy" class="px-4 py-2.5 rounded-xl border border-gray-300 text-gray-700 text-sm font-semibold hover:bg-gray-50 disabled:opacity-60"><i class="fas fa-rotate mr-1"></i> Sync Approved Templates</button>
        </div>

        <template x-if="numbers.length">
            <div class="flex flex-col sm:flex-row gap-2">
                <select x-model="numberId" class="flex-1 {{ $sfInput }}">
                    <template x-for="n in numbers" :key="n.phone_number_id"><option :value="n.phone_number_id" x-text="n.label"></option></template>
                </select>
                <button type="button" @click="connect(numberId)" class="px-4 py-2.5 rounded-xl bg-green-600 text-white text-sm font-semibold">Use this number</button>
            </div>
        </template>

        <form @submit.prevent="verify()" class="flex flex-col sm:flex-row gap-2">
            <input type="text" x-model="ref" placeholder="Already have a template ID or name? Paste it here" class="flex-1 {{ $sfInput }}">
            <button type="submit" :disabled="busy || !ref" class="px-4 py-2.5 rounded-xl border border-green-300 text-green-700 text-sm font-semibold hover:bg-green-50 disabled:opacity-60">Verify Template</button>
        </form>

        <div x-show="msg" x-cloak class="rounded-xl px-4 py-3 text-sm" :class="ok ? 'bg-green-50 text-green-900' : 'bg-red-50 text-red-900'">
            <p x-text="(ok ? '✓ ' : '✕ ') + msg"></p>
            <template x-if="details"><details class="mt-1 text-xs"><summary class="cursor-pointer">View technical details</summary><p class="font-mono break-words mt-1" x-text="details"></p></details></template>
        </div>
    </div>

    {{-- What is sent on WhatsApp --}}
    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <div class="px-5 sm:px-6 py-4 border-b border-gray-100">
            <h3 class="font-semibold text-gray-900">Notifications on WhatsApp</h3>
            <p class="text-sm text-gray-500">Choose a template only for the messages you want on WhatsApp. The rest still go by email (and SMS, if on).</p>
        </div>
        <ul class="divide-y divide-gray-100">
            @foreach ($sfWaPurposes as $key => $p)
                @php $a = $sfWaAssigned[$key]; @endphp
                <li class="px-5 sm:px-6 py-3 flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-4">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-900">{{ $p['label'] }}</p>
                        <p class="text-xs text-gray-500">{{ $p['help'] }}</p>
                    </div>
                    <div class="text-sm sm:text-right">
                        @if ($a['template'])
                            <span class="inline-flex items-center gap-1 text-green-700"><i class="fas fa-circle-check"></i> {{ $a['template']->displayName() }}</span>
                        @elseif ($a['name'] !== '')
                            <span class="inline-flex items-center gap-1 text-amber-700" title="Not among your synced templates"><i class="fas fa-triangle-exclamation"></i> Needs a new template</span>
                        @else
                            <span class="text-gray-400">Not sent on WhatsApp</span>
                        @endif
                    </div>
                    <div class="flex gap-2 shrink-0">
                        <button type="button" @click="open(@js($key), {{ $a['template']->id ?? 'null' }})" class="px-3 py-1.5 rounded-lg border border-gray-300 text-gray-700 text-xs font-semibold hover:bg-gray-50">{{ $a['name'] !== '' ? 'Change' : 'Choose template' }}</button>
                        @if ($a['name'] !== '')
                            <button type="button" @click="unassign(@js($key))" class="px-3 py-1.5 rounded-lg text-red-600 text-xs font-semibold hover:bg-red-50">Turn off</button>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    </div>

    {{-- Choose template + variables --}}
    <div x-show="panel" x-cloak class="bg-white rounded-2xl border-2 border-green-300 p-5 sm:p-6 space-y-4" x-ref="panel">
        <div class="flex items-center justify-between">
            <h3 class="font-semibold text-gray-900">Use a template</h3>
            <button type="button" @click="panel = false" class="text-gray-400 hover:text-gray-600"><i class="fas fa-xmark"></i></button>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Send it for</label>
                <select x-model="purpose" @change="resetVars()" class="{{ $sfInput }}">
                    <template x-for="p in purposes" :key="p.key"><option :value="p.key" x-text="p.label"></option></template>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Approved template</label>
                <select x-model.number="templateId" @change="resetVars()" class="{{ $sfInput }}">
                    <option value="">Choose&hellip;</option>
                    <template x-for="t in templates" :key="t.id"><option :value="t.id" x-text="t.label + ' (' + t.vars + ' variable' + (t.vars === 1 ? '' : 's') + ')'"></option></template>
                </select>
            </div>
        </div>
        <template x-if="tpl()">
            <div class="space-y-3">
                <p class="text-sm bg-gray-50 rounded-xl p-3 text-gray-700 whitespace-pre-line" x-text="tpl().body || '(no body text)'"></p>
                <p x-show="tpl().vars > fields().length" class="text-sm text-red-700">This template needs <span x-text="tpl().vars"></span> values but this notification can fill only <span x-text="fields().length"></span>. Choose a simpler template.</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3" x-show="tpl().vars <= fields().length">
                    <template x-for="(v, i) in vars" :key="i">
                        <label class="text-sm">
                            <span class="block text-gray-700 mb-1">Fill <span class="font-mono" x-text="'{' + '{' + (i + 1) + '}' + '}'"></span> with</span>
                            <select x-model="vars[i]" class="{{ $sfInput }}">
                                <template x-for="(label, key) in fields(true)" :key="key"><option :value="key" x-text="label" :selected="key === vars[i]"></option></template>
                            </select>
                        </label>
                    </template>
                </div>
            </div>
        </template>
        <div class="flex justify-end gap-2">
            <button type="button" @click="panel = false" class="px-4 py-2 rounded-xl border border-gray-200 text-sm">Cancel</button>
            <button type="button" @click="assign()" :disabled="busy || !tpl() || tpl().vars > fields().length" class="px-5 py-2 rounded-xl bg-green-600 text-white text-sm font-semibold disabled:opacity-50">Use this template</button>
        </div>
    </div>

    {{-- Approved templates --}}
    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <div class="px-5 sm:px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="font-semibold text-gray-900">Approved templates</h3>
            <span class="text-xs text-gray-500">{{ $sfWaTemplates->count() }} synced</span>
        </div>
        @if ($sfWaTemplates->isEmpty())
            <p class="px-6 py-8 text-sm text-gray-500 text-center">No templates yet. Click "Connect &amp; Verify" to fetch your approved templates.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                        <tr><th class="px-5 py-2.5">Template</th><th class="px-3 py-2.5">Language</th><th class="px-3 py-2.5">Status</th><th class="px-3 py-2.5">Category</th><th class="px-3 py-2.5">Variables</th><th class="px-3 py-2.5">Used for</th><th class="px-5 py-2.5"></th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($sfWaTemplates as $t)
                            <tr class="{{ $t->isApproved() ? '' : 'text-gray-400' }}">
                                <td class="px-5 py-2.5 font-medium {{ $t->isApproved() ? 'text-gray-900' : '' }}" title="{{ \Illuminate\Support\Str::limit($t->body_text, 200) }}">{{ $t->displayName() }}</td>
                                <td class="px-3 py-2.5">{{ $t->language }}</td>
                                <td class="px-3 py-2.5">
                                    <span class="px-2 py-0.5 rounded-full text-xs font-semibold {{ $t->isApproved() ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-500' }}">{{ ucfirst(strtolower($t->status)) }}</span>
                                </td>
                                <td class="px-3 py-2.5">{{ ucfirst(strtolower((string) $t->category)) }}</td>
                                <td class="px-3 py-2.5">{{ $t->var_count }}</td>
                                <td class="px-3 py-2.5 text-xs">{{ implode(', ', $sfUsedFor[$t->id] ?? []) ?: '-' }}</td>
                                <td class="px-5 py-2.5 text-right">
                                    @if ($t->isApproved())
                                        <button type="button" @click="open(null, {{ $t->id }})" class="px-3 py-1 rounded-lg bg-green-600 text-white text-xs font-semibold">Use</button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @include('settings.partials.channel-test', [
        'title' => 'Send Test WhatsApp',
        'hint' => 'Sends one approved template, with sample values, to the number you enter.',
        'url' => route('settings.whatsapp.test-template'),
        'fields' => [
            ['name' => 'phone', 'type' => 'tel', 'label' => 'WhatsApp number', 'value' => auth()->user()->phone, 'placeholder' => '10-digit mobile', 'maxlength' => 13, 'inputmode' => 'numeric'],
            ['name' => 'template_id', 'type' => 'select', 'label' => 'Template', 'options' => $sfWaTemplates->filter->isApproved()->mapWithKeys(fn ($t) => [$t->id => $t->displayName()])->all() ?: ['' => 'Sync templates first']],
        ],
        'button' => 'Send Test WhatsApp',
        'icon' => 'fa-brands fa-whatsapp',
    ])
</div>

@push('scripts')
<script>
function sfWhatsApp(templates, purposes) {
    const post = async (url, body) => {
        const r = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }, body: JSON.stringify(body || {}) });
        const data = await r.json().catch(() => ({}));
        if (r.status === 422) return { success: false, message: Object.values(data.errors || {}).flat()[0] || 'Please check the details.' };
        return data;
    };
    return {
        templates, purposes,
        busy: false, ok: false, msg: '', details: null, ref: '', numbers: [], numberId: '',
        panel: false, purpose: purposes[0].key, templateId: '', vars: [],
        show(d) { this.ok = !!d.success; this.msg = d.message || 'Something went wrong.'; this.details = d.details || null; if (d.success && d.reload) setTimeout(() => location.reload(), 1000); },
        async call(url, body) { this.busy = true; const d = await post(url, body); this.show(d); this.busy = false; return d; },
        async connect(numberId) {
            const d = await this.call(@js(route('settings.whatsapp.connect')), numberId ? { phone_number_id: numberId } : {});
            this.numbers = d.choose ? d.numbers : [];
            if (d.choose && this.numbers.length) this.numberId = this.numbers[0].phone_number_id;
        },
        async verify() {
            const d = await this.call(@js(route('settings.whatsapp.verify-template')), { template_ref: this.ref });
            if (d.success && d.template) this.open(null, d.template.id);
        },
        async unassign(purpose) { if (confirm('Stop sending this notification on WhatsApp?')) await this.call(@js(route('settings.whatsapp.unassign')), { purpose }); },
        async assign() { await this.call(@js(route('settings.whatsapp.assign')), { purpose: this.purpose, template_id: this.templateId, variables: this.vars }); },
        open(purpose, templateId) {
            if (purpose) this.purpose = purpose;
            this.templateId = templateId || '';
            this.resetVars();
            this.panel = true;
            this.$nextTick(() => this.$refs.panel.scrollIntoView({ behavior: 'smooth', block: 'center' }));
        },
        tpl() { return this.templates.find(t => t.id === Number(this.templateId)) || null; },
        fields(asObject) {
            const f = (this.purposes.find(p => p.key === this.purpose) || { fields: {} }).fields;
            return asObject ? f : Object.keys(f);
        },
        resetVars() { const t = this.tpl(); const keys = this.fields(); this.vars = t ? Array.from({ length: t.vars }, (_, i) => keys[i] || keys[0]) : []; },
    };
}
</script>
@endpush
