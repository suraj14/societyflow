{{-- Settings -> WhatsApp: Integration status + switch, (1) API Configuration, (2) Test WhatsApp. Which approved template each
     notification uses is managed on Message Templates -> WhatsApp (linked below). --}}
@php
    $sfWa = $settings['whatsapp'] ?? [];
    $sfWaSociety = auth()->user()->hasRole('Super Admin') ? null : auth()->user()->society_id;
    $sfWaStatus = \App\Support\ChannelStatus::get('whatsapp', $sfWaSociety);
    $sfWaOwnKey = !empty($sfWa['whatsapp_api_key']);
    $sfWaUsesSmsKey = !$sfWaOwnKey && \App\Services\SmsService::apiKey($sfWaSociety) !== '';
    $sfWaHasKey = $sfWaOwnKey || $sfWaUsesSmsKey;
    $sfWaConfigured = $sfWaHasKey && !empty($sfWa['whatsapp_phone_number_id']);
    $sfWaEnabled = (bool) ($sfWa['whatsapp_enabled'] ?? false);
    $sfWaTemplates = \App\Models\WhatsAppTemplate::bySociety($sfWaSociety)->orderBy('friendly_name')->orderBy('name')->get();
    $sfWaPurposes = \App\Support\WhatsAppPurposes::selectable();
    $sfWaInUse = \App\Models\WhatsAppTemplate::bySociety($sfWaSociety)->active()->whereNotNull('purpose')->distinct()->count('purpose');
    $sfInput = 'w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-purple-500';
    $sfWaJs = $sfWaTemplates->map(fn ($t) => ['id' => $t->id, 'label' => $t->displayName(), 'vars' => (int) $t->var_count, 'body' => (string) $t->body_text])->values();
@endphp

<div class="max-w-3xl space-y-6">
    <div>
        <h2 class="text-2xl font-bold text-gray-900">WhatsApp</h2>
        <p class="text-sm text-gray-500 mt-1">Society updates on WhatsApp, sent through your Fast2SMS account.</p>
    </div>

    @unless (\App\Support\ExternalMessaging::enabled())
        <p class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-2.5 text-sm text-amber-900" role="status">
            <i class="fas fa-shield-halved mr-1"></i> Real WhatsApp delivery is switched off on this server (EXTERNAL_MESSAGING_ENABLED=false). Nothing is sent.
        </p>
    @endunless

    <form action="{{ route('settings.whatsapp.update') }}" method="POST" class="space-y-6">
        @csrf

        {{-- Status + Enabled switch --}}
        <div class="bg-white rounded-2xl border border-gray-200 px-5 py-4 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3 min-w-0">
                <span class="w-2.5 h-2.5 rounded-full shrink-0 {{ $sfWaEnabled ? 'bg-green-500' : 'bg-gray-400' }}"></span>
                <div class="min-w-0">
                    <p class="font-semibold text-gray-900" role="status">{{ $sfWaEnabled ? 'Integration active' : 'Integration inactive' }}</p>
                    <p class="text-xs text-gray-500">The notifications that have a template in <a href="{{ route('admin.message-templates.index', ['channel' => 'whatsapp']) }}" class="underline">Message Templates</a> ({{ $sfWaInUse }} of {{ count($sfWaPurposes) }} now) are sent on WhatsApp only while this is on, the key and Phone Number ID are saved, and the server allows external messaging. Turning it off keeps your settings.</p>
                </div>
            </div>
            <label class="flex items-center gap-2 cursor-pointer shrink-0">
                <span class="text-sm text-gray-700">Enabled</span>
                <input type="hidden" name="whatsapp_enabled" value="0">
                <input type="checkbox" name="whatsapp_enabled" value="1" @checked($sfWaEnabled) class="w-11 h-6 rounded-full text-green-600 focus:ring-green-500" aria-label="Enable WhatsApp">
            </label>
        </div>

        {{-- The last real result (Fast2SMS's own answer), or what is still missing --}}
        @if ($sfWaStatus || !$sfWaConfigured)
            @include('settings.partials.channel-status', [
                'configured' => $sfWaConfigured,
                'status' => $sfWaStatus,
                'okLabel' => 'Fast2SMS accepted the settings',
                'notConfiguredLabel' => 'WhatsApp not configured',
                'notConfiguredHint' => $sfWaHasKey ? 'Add the Phone Number ID and save.' : 'Add the Fast2SMS API key (or save one in SMS settings) and the Phone Number ID.',
                'savedLabel' => '',
                'savedHint' => '',
            ])
        @endif

        {{-- 1. API Configuration --}}
        <div class="bg-white rounded-2xl border border-gray-200 p-5 sm:p-6 space-y-5">
            <h3 class="font-semibold text-gray-900"><span class="text-purple-600">&#9312;</span> API Configuration</h3>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">WhatsApp Provider</label>
                <div class="px-4 py-2.5 rounded-xl bg-gray-50 border border-gray-200 text-sm text-gray-800">Fast2SMS WhatsApp</div>
            </div>

            <div>
                <label for="sf-wa-key" class="block text-sm font-medium text-gray-700 mb-1.5">Fast2SMS API Key</label>
                <input id="sf-wa-key" type="password" name="whatsapp_api_key" autocomplete="new-password" value="{{ $sfWa['whatsapp_api_key'] ?? '' }}" placeholder="{{ $sfWaOwnKey ? 'Saved - paste a new key to replace it' : ($sfWaUsesSmsKey ? 'Using the key saved in SMS settings' : 'Paste your key') }}" class="{{ $sfInput }}">
                <p class="text-xs mt-1 {{ $sfWaHasKey ? 'text-green-700' : 'text-gray-500' }}">
                    @if ($sfWaOwnKey) <i class="fas fa-lock mr-1"></i>API key configured (stored encrypted). Leave it as is to keep it.
                    @elseif ($sfWaUsesSmsKey) <i class="fas fa-link mr-1"></i>Using the Fast2SMS key saved in SMS settings (same account). Paste a key only to use a different one.
                    @else Leave empty to reuse the Fast2SMS key saved in SMS settings (same account works for both). @endif
                </p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="sf-wa-pnid" class="block text-sm font-medium text-gray-700 mb-1.5">WhatsApp Phone Number ID</label>
                    <input id="sf-wa-pnid" type="text" name="whatsapp_phone_number_id" value="{{ $sfWa['whatsapp_phone_number_id'] ?? '' }}" inputmode="numeric" placeholder="e.g. 1209719742234591" class="{{ $sfInput }}">
                    <p class="text-xs text-gray-500 mt-1">From Fast2SMS &rarr; WhatsApp Business: the numeric phone-number ID, <strong>not</strong> the phone number itself (and not the recipient's number).</p>
                </div>
                <div>
                    <label for="sf-wa-cc" class="block text-sm font-medium text-gray-700 mb-1.5">Default Country Code</label>
                    <input id="sf-wa-cc" type="text" name="whatsapp_country_code" value="{{ $sfWa['whatsapp_country_code'] ?? '91' }}" maxlength="4" inputmode="numeric" class="{{ $sfInput }}">
                    <p class="text-xs text-gray-500 mt-1">Added to 10-digit numbers automatically.</p>
                </div>
            </div>

            <p class="rounded-xl bg-green-50 border border-green-100 px-4 py-3 text-xs text-green-900">
                <i class="fas fa-lightbulb mr-1"></i> Messages go through your approved WhatsApp Business account. Outside the 24-hour customer window, WhatsApp only delivers approved templates - so every message here is a template. Add and manage them in
                <a href="{{ route('admin.message-templates.index', ['channel' => 'whatsapp']) }}" class="underline font-medium">Message Templates &rarr; WhatsApp</a>.
            </p>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="px-6 py-2.5 rounded-xl bg-gradient-to-r from-purple-600 to-indigo-600 text-white text-sm font-semibold shadow hover:from-purple-700 hover:to-indigo-700">Save Changes</button>
        </div>
    </form>

    {{-- 2. Test WhatsApp --}}
    <div class="rounded-2xl border border-green-200 bg-green-50/60 p-5 sm:p-6" x-data="sfWaTest(@js(route('settings.whatsapp.test-template')), @js($sfWaJs))" x-init="reset()">
        <h3 class="font-semibold text-gray-900 flex items-center gap-2"><i class="fa-brands fa-whatsapp text-green-600"></i> Test WhatsApp</h3>
        <p class="text-sm text-gray-600 mt-1 mb-4">Sends one of your saved templates with your saved settings and shows Fast2SMS's reply. Nothing is sent until you press the button.</p>

        @if ($sfWaTemplates->isEmpty())
            <p class="text-sm text-amber-800 bg-amber-50 border border-amber-200 rounded-xl px-4 py-3">No template saved yet. Add one in <a href="{{ route('admin.message-templates.index', ['channel' => 'whatsapp']) }}" class="underline font-medium">Message Templates &rarr; WhatsApp</a>.</p>
        @else
            <form @submit.prevent="send()" class="grid grid-cols-1 sm:grid-cols-2 gap-3" data-allow-resubmit>
                <div>
                    <label for="sf-wa-test-phone" class="block text-sm font-medium text-gray-700 mb-1.5">Phone Number</label>
                    <input id="sf-wa-test-phone" type="tel" x-model="phone" required maxlength="13" inputmode="numeric" placeholder="10 digits" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm bg-white focus:ring-2 focus:ring-green-500">
                </div>
                <div>
                    <label for="sf-wa-test-tpl" class="block text-sm font-medium text-gray-700 mb-1.5">Template</label>
                    <select id="sf-wa-test-tpl" x-model.number="templateId" @change="reset()" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm bg-white focus:ring-2 focus:ring-green-500">
                        <template x-for="t in templates" :key="t.id"><option :value="t.id" x-text="t.label"></option></template>
                    </select>
                </div>
                <div class="sm:col-span-2" x-show="tpl() && tpl().vars > 0">
                    <label for="sf-wa-test-vars" class="block text-sm font-medium text-gray-700 mb-1.5">Variables (separate with |) &middot; this template needs <span x-text="tpl() ? tpl().vars : 0"></span></label>
                    <input id="sf-wa-test-vars" type="text" x-model="variables" maxlength="500" placeholder="e.g. Asha|Block A|12 Nov" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm bg-white focus:ring-2 focus:ring-green-500">
                    <p class="text-xs mt-1" :class="countOk() ? 'text-gray-500' : 'text-red-700'"><span x-text="count()"></span> value(s) entered</p>
                </div>
                <p class="sm:col-span-2 text-sm bg-white/70 rounded-xl p-3 text-gray-700 whitespace-pre-line" x-show="tpl() && tpl().body" x-text="tpl() ? tpl().body : ''"></p>
                <div class="sm:col-span-2">
                    <button type="submit" :disabled="busy || !countOk()" class="px-5 py-2.5 rounded-xl bg-green-600 text-white text-sm font-semibold hover:bg-green-700 disabled:opacity-60">
                        <span x-show="!busy">Send Test WhatsApp</span><span x-show="busy" x-cloak><i class="fas fa-spinner fa-spin mr-1"></i> Sending&hellip;</span>
                    </button>
                </div>
            </form>

            <div x-show="result" x-cloak class="mt-4 rounded-xl px-4 py-3 text-sm" role="status"
                 :class="result && result.skipped ? 'bg-blue-100 text-blue-900' : (result && result.success ? 'bg-green-100 text-green-900' : 'bg-red-100 text-red-900')">
                <p class="font-medium" x-text="(result && !result.skipped ? (result.success ? '✓ ' : '✕ ') : '') + (result ? result.message : '')"></p>
                <template x-if="result && result.details"><p class="mt-1 text-xs font-mono break-words" x-text="result.details"></p></template>
                <template x-if="result && result.raw">
                    <div class="mt-2"><p class="text-xs font-semibold">Fast2SMS response</p><pre class="mt-1 text-xs font-mono whitespace-pre-wrap break-words bg-white/70 rounded-lg p-2" x-text="result.raw"></pre></div>
                </template>
            </div>
        @endif
    </div>
</div>

@push('scripts')
<script>
function sfWaTest(url, templates) {
    return {
        templates, templateId: templates.length ? templates[0].id : '', phone: @js(auth()->user()->phone), variables: '', busy: false, result: null,
        tpl() { return this.templates.find(t => t.id === Number(this.templateId)) || null; },
        // pre-fill sample values so one click works; replace them with real ones to see a real message
        reset() { const t = this.tpl(); this.variables = t && t.vars ? Array.from({ length: t.vars }, (_, i) => 'Test ' + (i + 1)).join('|') : ''; this.result = null; },
        count() { return this.variables.trim() === '' ? 0 : this.variables.split('|').length; },
        countOk() { const t = this.tpl(); return !t || t.vars === 0 || this.count() === t.vars; },
        async send() {
            this.busy = true; this.result = null;
            try {
                const r = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                    body: JSON.stringify({ phone: this.phone, template_id: this.templateId, variables: this.variables }) });
                const d = await r.json().catch(() => ({}));
                this.result = r.status === 422
                    ? { success: false, message: Object.values(d.errors || {}).flat()[0] || 'Please check the details entered.' }
                    : { success: !!d.success, skipped: !!d.skipped, message: d.message || 'Something went wrong.', details: d.details || null, raw: d.raw || null };
            } catch (e) { this.result = { success: false, message: 'Could not reach the server. Check your connection and try again.' }; }
            this.busy = false;
        },
    };
}
</script>
@endpush
