{{-- Settings -> SMS / OTP. One place for SMS credentials. --}}
@php
    $sfSms = $settings['sms'] ?? [];
    $sfSmsSociety = auth()->user()->hasRole('Super Admin') ? null : auth()->user()->society_id;
    $sfSmsStatus = \App\Support\ChannelStatus::get('sms', $sfSmsSociety);
    $sfHasKey = !empty($sfSms['fast2sms_api_key']);
    $sfRoute = $sfSms['fast2sms_route'] ?? 'otp'; // SmsService's own default
    $sfTemplateId = (string) ($sfSms['fast2sms_template_id'] ?? '');
    $sfLooksLikeDltId = strlen(preg_replace('/\D+/', '', $sfTemplateId)) >= 15;
    $sfOtpMeta = is_array($sfSms['fast2sms_otp_template'] ?? null) ? $sfSms['fast2sms_otp_template'] : null;
    $sfNotifMeta = is_array($sfSms['fast2sms_notification_template'] ?? null) ? $sfSms['fast2sms_notification_template'] : null;
    $sfSmsConfigured = $sfHasKey && ($sfRoute === 'otp' ? !empty($sfSms['fast2sms_otp_id']) : ($sfTemplateId !== '' && !$sfLooksLikeDltId));
    $sfInput = 'w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-purple-500';
@endphp

<div class="max-w-3xl space-y-6" x-data="{ route: @js($sfRoute) }">
    <div>
        <h2 class="text-2xl font-bold text-gray-900">SMS / OTP</h2>
        <p class="text-sm text-gray-500 mt-1">Login OTPs and important alerts by SMS, through Fast2SMS. Set it up once - SocietyFlow sends automatically.</p>
    </div>

    @include('settings.partials.channel-status', [
        'configured' => $sfSmsConfigured,
        'status' => $sfSmsStatus,
        'okLabel' => 'SMS connected',
        'notConfiguredLabel' => 'SMS not configured',
        'notConfiguredHint' => $sfHasKey ? 'Your API key is saved. Use "Connect & Verify" below and choose your OTP template.' : 'Enter your Fast2SMS API key and save.',
        'savedLabel' => 'Configuration saved',
        'savedHint' => 'Use "Connect & Verify" or send a test SMS to confirm it works.',
    ])

    @if ($sfLooksLikeDltId)
        <div class="rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-900">
            <p class="font-semibold"><i class="fas fa-triangle-exclamation mr-1"></i> Your saved SMS template needs to be chosen again</p>
            <p class="mt-1">The saved value is a DLT Template ID from the telecom DLT portal. Fast2SMS sends using its own Message ID, so SMS would fail. Click <strong>Connect &amp; Verify</strong> below and pick the same template by its text.</p>
        </div>
    @endif

    <form action="{{ route('settings.sms.update') }}" method="POST" class="space-y-6">
        @csrf
        <div class="bg-white rounded-2xl border border-gray-200 p-5 sm:p-6 space-y-5">
            <label class="flex items-center justify-between gap-4 cursor-pointer">
                <span>
                    <span class="block font-semibold text-gray-900">Enable SMS / OTP</span>
                    <span class="block text-sm text-gray-500">Send login OTPs by SMS.</span>
                </span>
                <input type="hidden" name="sms_enabled" value="0">
                <input type="checkbox" name="sms_enabled" value="1" @checked($sfSms['sms_enabled'] ?? false) class="w-11 h-6 rounded-full text-purple-600 focus:ring-purple-500">
            </label>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">SMS Provider</label>
                <div class="px-4 py-2.5 rounded-xl bg-gray-50 border border-gray-200 text-sm text-gray-800">Fast2SMS</div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Fast2SMS API Authorization Key</label>
                <input type="password" name="fast2sms_api_key" autocomplete="new-password" value="{{ $sfSms['fast2sms_api_key'] ?? '' }}" placeholder="Paste from Fast2SMS > Dev API" class="{{ $sfInput }}">
                <p class="text-xs mt-1 {{ $sfHasKey ? 'text-green-700' : 'text-gray-500' }}">
                    @if ($sfHasKey) <i class="fas fa-lock mr-1"></i>API key configured (stored encrypted) @else API key not configured @endif
                </p>
            </div>

            <div>
                <span class="block text-sm font-medium text-gray-700 mb-2">How OTPs are sent</span>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <label class="flex items-start gap-2 p-3 rounded-xl border cursor-pointer" :class="route === 'dlt' ? 'border-purple-400 bg-purple-50' : 'border-gray-200'">
                        <input type="radio" name="fast2sms_route" value="dlt" x-model="route" class="mt-0.5 text-purple-600">
                        <span class="text-sm"><span class="font-medium text-gray-900">My approved DLT template</span><span class="block text-gray-500">Recommended. Choose it below.</span></span>
                    </label>
                    <label class="flex items-start gap-2 p-3 rounded-xl border cursor-pointer" :class="route === 'otp' ? 'border-purple-400 bg-purple-50' : 'border-gray-200'">
                        <input type="radio" name="fast2sms_route" value="otp" x-model="route" class="mt-0.5 text-purple-600">
                        <span class="text-sm"><span class="font-medium text-gray-900">Fast2SMS OTP service</span><span class="block text-gray-500">Needs an OTP ID from Fast2SMS.</span></span>
                    </label>
                </div>
                <div x-show="route === 'otp'" x-cloak class="mt-3">
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Fast2SMS OTP ID</label>
                    <input type="text" name="fast2sms_otp_id" value="{{ $sfSms['fast2sms_otp_id'] ?? '' }}" class="{{ $sfInput }}">
                </div>
            </div>

            <label class="flex items-center justify-between gap-4 cursor-pointer pt-1 border-t border-gray-100">
                <span class="pt-4">
                    <span class="block font-semibold text-gray-900">Also send important notifications by SMS</span>
                    <span class="block text-sm text-gray-500">Notices, society rules and gate-pass changes. Uses your notification template.</span>
                </span>
                <input type="hidden" name="notification_sms_enabled" value="0">
                <input type="checkbox" name="notification_sms_enabled" value="1" @checked($sfSms['notification_sms_enabled'] ?? false) class="mt-4 w-11 h-6 rounded-full text-purple-600 focus:ring-purple-500">
            </label>
        </div>

        <details class="bg-white rounded-2xl border border-gray-200 p-5 sm:p-6 group">
            <summary class="cursor-pointer text-sm font-medium text-gray-700 select-none">Advanced / Technical details</summary>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Sender ID</label>
                    <input type="text" name="fast2sms_sender_id" value="{{ $sfSms['fast2sms_sender_id'] ?? '' }}" class="{{ $sfInput }}">
                    <p class="text-xs text-gray-500 mt-1">Filled automatically when you choose a template.</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">OTP template Message ID</label>
                    <input type="text" name="fast2sms_template_id" value="{{ $sfTemplateId }}" class="{{ $sfInput }}">
                    <p class="text-xs text-gray-500 mt-1">Fast2SMS Message ID (not the DLT Template ID).</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">DLT Entity ID (for your records)</label>
                    <input type="text" name="fast2sms_entity_id" value="{{ $sfSms['fast2sms_entity_id'] ?? '' }}" class="{{ $sfInput }}">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Provider</label>
                    <select name="sms_provider" class="{{ $sfInput }}">
                        @foreach (['fast2sms' => 'Fast2SMS (recommended)', 'bulksmsplans' => 'BulkSMSPlans (legacy)', 'custom' => 'Custom HTTP API (legacy)'] as $v => $l)
                            <option value="{{ $v }}" @selected(($sfSms['sms_provider'] ?? 'fast2sms') === $v)>{{ $l }}</option>
                        @endforeach
                    </select>
                </div>
                @if (($sfSms['sms_provider'] ?? 'fast2sms') !== 'fast2sms')
                    <div class="sm:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <input type="password" name="bulksmsplans_api_id" value="{{ $sfSms['bulksmsplans_api_id'] ?? '' }}" placeholder="BulkSMSPlans API ID" class="{{ $sfInput }}">
                        <input type="password" name="bulksmsplans_api_password" value="{{ $sfSms['bulksmsplans_api_password'] ?? '' }}" placeholder="BulkSMSPlans API password" class="{{ $sfInput }}">
                        <input type="text" name="bulksmsplans_sender_id" value="{{ $sfSms['bulksmsplans_sender_id'] ?? '' }}" placeholder="BulkSMSPlans sender ID" class="{{ $sfInput }}">
                        <input type="text" name="custom_sms_api_url" value="{{ $sfSms['custom_sms_api_url'] ?? '' }}" placeholder="Custom API URL" class="{{ $sfInput }}">
                    </div>
                @endif
            </div>
        </details>

        <div class="flex justify-end">
            <button type="submit" class="px-6 py-2.5 rounded-xl bg-gradient-to-r from-purple-600 to-indigo-600 text-white text-sm font-semibold shadow hover:from-purple-700 hover:to-indigo-700">Save Changes</button>
        </div>
    </form>

    {{-- Templates: connect, then pick. Nothing typed is trusted - the server re-checks with Fast2SMS. --}}
    <div class="bg-white rounded-2xl border border-gray-200 p-5 sm:p-6 space-y-4" x-data="sfSmsTemplates()">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h3 class="font-semibold text-gray-900">SMS templates</h3>
                <p class="text-sm text-gray-500">Your DLT-approved templates from Fast2SMS. Choose one for OTP and one for notifications.</p>
            </div>
            <button type="button" @click="connect()" :disabled="busy" class="px-4 py-2.5 rounded-xl bg-purple-600 text-white text-sm font-semibold hover:bg-purple-700 disabled:opacity-60 whitespace-nowrap">
                <i class="fas fa-plug mr-1"></i> Connect &amp; Verify
            </button>
        </div>

        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
            <div class="rounded-xl bg-gray-50 p-3">
                <dt class="text-xs font-semibold uppercase text-gray-500">OTP template</dt>
                <dd class="mt-1 text-gray-800">
                    @if ($sfRoute === 'otp') Fast2SMS OTP service
                    @elseif ($sfOtpMeta) "{{ \Illuminate\Support\Str::limit($sfOtpMeta['text'], 90) }}"
                    @elseif ($sfTemplateId !== '' && !$sfLooksLikeDltId) Chosen (Message ID {{ $sfTemplateId }})
                    @else <span class="text-amber-700">Not chosen yet</span> @endif
                </dd>
            </div>
            <div class="rounded-xl bg-gray-50 p-3">
                <dt class="text-xs font-semibold uppercase text-gray-500">Notification template</dt>
                <dd class="mt-1 text-gray-800">
                    @if ($sfNotifMeta) "{{ \Illuminate\Support\Str::limit($sfNotifMeta['text'], 90) }}"
                    @else <span class="text-gray-500">Same as the OTP template</span> @endif
                </dd>
            </div>
        </dl>

        <form @submit.prevent="verify()" class="flex flex-col sm:flex-row gap-2">
            <input type="text" x-model="ref" placeholder="Already have a template ID? Paste it here" class="flex-1 {{ $sfInput }}">
            <button type="submit" :disabled="busy || !ref" class="px-4 py-2.5 rounded-xl border border-purple-300 text-purple-700 text-sm font-semibold hover:bg-purple-50 disabled:opacity-60">Verify</button>
        </form>

        <div x-show="msg" x-cloak class="rounded-xl px-4 py-3 text-sm" :class="ok ? 'bg-green-50 text-green-900' : 'bg-red-50 text-red-900'">
            <p x-text="(ok ? '✓ ' : '✕ ') + msg"></p>
            <template x-if="details"><details class="mt-1 text-xs"><summary class="cursor-pointer">View technical details</summary><p class="font-mono break-words mt-1" x-text="details"></p></details></template>
        </div>

        <template x-if="templates.length">
            <ul class="divide-y divide-gray-100 border border-gray-200 rounded-xl max-h-96 overflow-y-auto">
                <template x-for="t in templates" :key="t.message_id">
                    <li class="p-3 flex flex-col sm:flex-row sm:items-center gap-3" :class="highlight === t.message_id ? 'bg-purple-50' : ''">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm text-gray-900" x-text="t.text"></p>
                            <p class="text-xs text-gray-500 mt-0.5"><span x-text="t.sender_id"></span> &middot; <span x-text="t.var_count + ' variable(s)'"></span></p>
                        </div>
                        <div class="flex gap-2 shrink-0" x-show="t.var_count === 1">
                            <button type="button" @click="use('otp', t)" class="px-3 py-1.5 rounded-lg bg-purple-600 text-white text-xs font-semibold">Use for OTP</button>
                            <button type="button" @click="use('notification', t)" class="px-3 py-1.5 rounded-lg border border-purple-300 text-purple-700 text-xs font-semibold">Use for notifications</button>
                        </div>
                        <p class="text-xs text-gray-400 shrink-0" x-show="t.var_count !== 1">Needs exactly 1 variable</p>
                    </li>
                </template>
            </ul>
        </template>
    </div>

    @include('settings.partials.channel-test', [
        'title' => 'Test SMS',
        'hint' => 'Sends a real SMS through your saved Fast2SMS settings. DLT templates have fixed wording - your test value fills the template\'s variable.',
        'url' => route('settings.sms.test'),
        'fields' => [
            ['name' => 'phone', 'type' => 'tel', 'label' => 'Mobile number', 'value' => auth()->user()->phone, 'placeholder' => '10-digit mobile', 'maxlength' => 13, 'inputmode' => 'numeric'],
            ['name' => 'test_value', 'type' => 'text', 'label' => 'Test value', 'value' => '1234', 'maxlength' => 30],
            ['name' => 'template', 'type' => 'select', 'label' => 'Template', 'options' => ['otp' => 'OTP template', 'notification' => 'Notification template']],
        ],
        'button' => 'Send Test SMS',
        'icon' => 'fa-comment-sms',
    ])
</div>

@push('scripts')
<script>
function sfSmsTemplates() {
    const post = async (url, body) => {
        const r = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }, body: JSON.stringify(body || {}) });
        const data = await r.json().catch(() => ({}));
        if (r.status === 422) return { success: false, message: Object.values(data.errors || {}).flat()[0] || 'Please check the details.' };
        return data;
    };
    return {
        busy: false, ok: false, msg: '', details: null, ref: '', highlight: null, templates: [],
        show(d) { this.ok = !!d.success; this.msg = d.message || 'Something went wrong.'; this.details = d.details || null; if (d.templates) this.templates = d.templates; },
        async connect() { this.busy = true; this.show(await post(@js(route('settings.sms.connect')))); this.busy = false; },
        async verify() {
            this.busy = true;
            const d = await post(@js(route('settings.sms.verify')), { template_ref: this.ref });
            this.show(d);
            this.highlight = d.template ? d.template.message_id : null;
            this.busy = false;
        },
        async use(purpose, t) {
            this.busy = true;
            const d = await post(@js(route('settings.sms.use-template')), { purpose, message_id: t.message_id });
            this.show(d);
            this.busy = false;
            if (d.success) setTimeout(() => location.reload(), 900);
        },
    };
}
</script>
@endpush
