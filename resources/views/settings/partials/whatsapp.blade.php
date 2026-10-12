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

<div class="max-w-4xl space-y-6" x-data="sfWhatsApp([], [])">
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
                    <p class="text-xs text-gray-500 mt-1">This is your WhatsApp Business number's ID in Fast2SMS - <strong>not</strong> the mobile number of the person who receives the message. "Connect &amp; Verify" fills it in.</p>
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
        <div x-show="msg" x-cloak class="rounded-xl px-4 py-3 text-sm" role="status" :class="skipped ? 'bg-blue-50 text-blue-900' : (ok ? 'bg-green-50 text-green-900' : 'bg-red-50 text-red-900')">
            <p x-text="(ok ? '✓ ' : '✕ ') + msg"></p>
            <template x-if="details"><details class="mt-1 text-xs"><summary class="cursor-pointer">View technical details</summary><p class="font-mono break-words mt-1" x-text="details"></p></details></template>
        </div>
    </div>

    {{-- Templates are managed on their own page; this is only a summary --}}
    <div class="bg-white rounded-2xl border border-gray-200 p-5 sm:p-6 flex flex-col sm:flex-row sm:items-center gap-3">
        <div class="flex-1 min-w-0">
            <h3 class="font-semibold text-gray-900">Templates</h3>
            <p class="text-sm text-gray-500"><strong>{{ $sfWaTemplates->filter->isApproved()->count() }}</strong> approved template(s) synced &middot; <strong>{{ collect($sfWaAssigned)->filter(fn ($a) => $a['template'])->count() }}</strong> of {{ count($sfWaPurposes) }} notifications use WhatsApp. A notification without a template is simply not sent on WhatsApp.</p>
        </div>
        <a href="{{ route('admin.message-templates.index', ['channel' => 'whatsapp']) }}" class="shrink-0 px-4 py-2.5 rounded-xl border border-purple-300 text-purple-700 text-sm font-semibold hover:bg-purple-50">Manage templates <i class="fas fa-arrow-right text-xs ml-1"></i></a>
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

@include('settings.partials.whatsapp-js')
