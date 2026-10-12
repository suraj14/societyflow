{{-- Settings -> SMS / OTP: two cards, like the reference - API Configuration, then Test SMS. Approved templates are
     managed on the Message Templates page (linked below); nothing about them is duplicated here. --}}
@php
    $sfSms = $settings['sms'] ?? [];
    $sfSmsSociety = auth()->user()->hasRole('Super Admin') ? null : auth()->user()->society_id;
    $sfSmsStatus = \App\Support\ChannelStatus::get('sms', $sfSmsSociety);
    $sfHasKey = !empty($sfSms['fast2sms_api_key']);
    $sfRoute = $sfSms['fast2sms_route'] ?? 'dlt'; // SmsService's own default
    $sfTemplateId = (string) ($sfSms['fast2sms_template_id'] ?? '');
    $sfLooksLikeDltId = strlen(preg_replace('/\D+/', '', $sfTemplateId)) >= 15;
    $sfOtpMeta = is_array($sfSms['fast2sms_otp_template'] ?? null) ? $sfSms['fast2sms_otp_template'] : null;
    $sfNotifMeta = is_array($sfSms['fast2sms_notification_template'] ?? null) ? $sfSms['fast2sms_notification_template'] : null;
    $sfNotifId = (string) ($sfSms['fast2sms_notification_template_id'] ?? '');
    $sfOtpReady = $sfRoute === 'otp' ? !empty($sfSms['fast2sms_otp_id']) : ($sfRoute === 'quick' || ($sfTemplateId !== '' && !$sfLooksLikeDltId));
    $sfSmsConfigured = $sfHasKey && $sfOtpReady;
    $sfEnabled = (bool) ($sfSms['sms_enabled'] ?? false);
    $sfInput = 'w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-purple-500';
@endphp

<div class="max-w-3xl space-y-6" x-data="{ route: @js($sfRoute) }">
    <div class="flex items-start justify-between gap-3">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">SMS / OTP</h2>
            <p class="text-sm text-gray-500 mt-1">Login OTPs and important alerts by SMS, through Fast2SMS.</p>
        </div>
        <span class="shrink-0 inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold {{ $sfEnabled ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-600' }}" role="status">
            <span class="w-1.5 h-1.5 rounded-full {{ $sfEnabled ? 'bg-green-500' : 'bg-gray-400' }}"></span> {{ $sfEnabled ? 'Enabled' : 'Disabled' }}
        </span>
    </div>

    @unless (\App\Support\ExternalMessaging::enabled())
        <div class="rounded-2xl border border-blue-200 bg-blue-50 px-5 py-4 text-sm text-blue-900" role="status">
            <p class="font-semibold"><i class="fas fa-shield-halved mr-1"></i> Real SMS delivery is switched off on this server</p>
            <p class="mt-1">Settings can be saved, but no SMS is sent and nothing here is reported as sent. Checking the connection and sending a test are not performed until an administrator enables external messaging.</p>
        </div>
    @endunless

    {{-- Distinct states: saved / connection checked / provider accepted a test. Never "delivered". --}}
    @include('settings.partials.channel-status', [
        'configured' => $sfSmsConfigured,
        'status' => $sfSmsStatus,
        'okLabel' => 'Provider check passed',
        'notConfiguredLabel' => 'SMS not configured',
        'notConfiguredHint' => $sfHasKey ? 'API key saved. Choose how OTPs are sent below (an OTP ID, or a template Message ID).' : 'Enter your Fast2SMS API key and save.',
        'savedLabel' => 'Not tested yet',
        'savedHint' => 'Settings are saved. Press "Verify connection" or "Send Test SMS" to see Fast2SMS\'s answer.',
    ])

    @if ($sfLooksLikeDltId)
        <div class="rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-900" role="alert">
            <p class="font-semibold"><i class="fas fa-triangle-exclamation mr-1"></i> Your saved SMS template needs to be chosen again</p>
            <p class="mt-1">The saved value is a DLT Template ID, not a Fast2SMS Message ID. Fast2SMS sends using its own short <strong>Message ID</strong> (Fast2SMS &rarr; Dev API &rarr; DLT Manager). Pick the template again on the <a href="{{ route('admin.message-templates.index', ['channel' => 'sms']) }}" class="underline font-medium">Message Templates</a> page or type its Message ID below.</p>
        </div>
    @endif

    {{-- ───────────── Card 1: API Configuration ───────────── --}}
    <form action="{{ route('settings.sms.update') }}" method="POST" class="space-y-6">
        @csrf
        <div class="bg-white rounded-2xl border border-gray-200 p-5 sm:p-6 space-y-5">
            <h3 class="font-semibold text-gray-900">API Configuration</h3>

            <label class="flex items-center justify-between gap-4 cursor-pointer">
                <span>
                    <span class="block font-medium text-gray-900">Enable SMS / OTP</span>
                    <span class="block text-sm text-gray-500">Turn off to stop all SMS without losing your setup.</span>
                </span>
                <input type="hidden" name="sms_enabled" value="0">
                <input type="checkbox" name="sms_enabled" value="1" @checked($sfEnabled) class="w-11 h-6 rounded-full text-purple-600 focus:ring-purple-500">
            </label>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">SMS Provider</label>
                    <div class="px-4 py-2.5 rounded-xl bg-gray-50 border border-gray-200 text-sm text-gray-800">Fast2SMS</div>
                </div>
                <div>
                    <label for="sf-sender" class="block text-sm font-medium text-gray-700 mb-1.5">Sender ID (DLT)</label>
                    <input id="sf-sender" type="text" name="fast2sms_sender_id" value="{{ $sfSms['fast2sms_sender_id'] ?? '' }}" maxlength="20" placeholder="e.g. SOCFLW" class="{{ $sfInput }}">
                    <p class="text-xs text-gray-500 mt-1">The approved sender header (6 letters). Filled in when you choose a template.</p>
                </div>
                <div class="sm:col-span-2">
                    <label for="sf-key" class="block text-sm font-medium text-gray-700 mb-1.5">Fast2SMS API Authorization Key</label>
                    <input id="sf-key" type="password" name="fast2sms_api_key" autocomplete="new-password" value="{{ $sfSms['fast2sms_api_key'] ?? '' }}" placeholder="Paste from Fast2SMS > Dev API" class="{{ $sfInput }}">
                    <p class="text-xs mt-1 {{ $sfHasKey ? 'text-green-700' : 'text-gray-500' }}">
                        @if ($sfHasKey) <i class="fas fa-lock mr-1"></i>API key configured (stored encrypted). Leave this unchanged to keep it. @else API key not configured. Get it from Fast2SMS &rarr; Dev API. @endif
                    </p>
                </div>
            </div>

            {{-- Mode: DLT (default) or Quick SMS (testing). The Fast2SMS OTP service lives under Advanced. --}}
            <div class="space-y-3">
                <p class="text-sm font-medium text-gray-900">Mode</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <label class="flex items-start gap-2 p-3 rounded-xl border cursor-pointer" :class="route === 'dlt' ? 'border-purple-400 bg-purple-50' : 'border-gray-200'">
                        <input type="radio" name="fast2sms_route" value="dlt" x-model="route" class="mt-0.5 text-purple-600">
                        <span class="text-sm"><span class="font-medium text-gray-900">DLT (Sender ID + Message ID)</span></span>
                    </label>
                    <label class="flex items-start gap-2 p-3 rounded-xl border cursor-pointer" :class="route === 'quick' ? 'border-amber-400 bg-amber-50' : 'border-gray-200'">
                        <input type="radio" name="fast2sms_route" value="quick" x-model="route" class="mt-0.5 text-amber-600">
                        <span class="text-sm"><span class="font-medium text-gray-900">Quick SMS (testing only, no DLT)</span></span>
                    </label>
                </div>
                <div x-show="route === 'dlt' || route === 'otp'" x-cloak>
                    <label for="sf-msgid" class="block text-sm font-medium text-gray-700 mb-1.5">Default Message ID</label>
                    <input id="sf-msgid" type="text" name="fast2sms_template_id" value="{{ $sfTemplateId }}" inputmode="numeric" placeholder="e.g. 223577" class="{{ $sfInput }}">
                    <p class="text-xs text-gray-500 mt-1">The number Fast2SMS shows next to your approved template (DLT Manager). Not the long 19-digit DLT Template ID.</p>
                </div>
                <p x-show="route === 'quick'" x-cloak class="text-xs text-amber-800">Quick SMS sends plain text with no DLT template. Use it for testing only.</p>
            </div>

            {{-- Notifications: a summary, the templates themselves live on their own page --}}
            <div class="rounded-xl border border-gray-200 p-4 space-y-2">
                <label class="flex items-center justify-between gap-4 cursor-pointer">
                    <span>
                        <span class="block text-sm font-medium text-gray-900">Also send important notifications by SMS</span>
                        <span class="block text-xs text-gray-500">Notices, society documents and gate-pass changes. Uses the notification template only - never the OTP one.</span>
                    </span>
                    <input type="hidden" name="notification_sms_enabled" value="0">
                    <input type="checkbox" name="notification_sms_enabled" value="1" @checked($sfSms['notification_sms_enabled'] ?? false) class="w-11 h-6 rounded-full text-purple-600 focus:ring-purple-500">
                </label>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-sm">
                    <div class="rounded-lg bg-gray-50 px-3 py-2">
                        <dt class="text-xs font-semibold uppercase text-gray-500">OTP</dt>
                        <dd class="text-gray-800">
                            @if ($sfRoute === 'otp') {{ $sfOtpReady ? 'OTP service (OTP ID set)' : 'OTP service - OTP ID missing' }}
                            @elseif ($sfOtpReady) {{ $sfOtpMeta ? '"' . \Illuminate\Support\Str::limit($sfOtpMeta['text'], 60) . '"' : 'Message ID ' . $sfTemplateId }}
                            @else <span class="text-amber-700">Not set</span> @endif
                        </dd>
                    </div>
                    <div class="rounded-lg bg-gray-50 px-3 py-2">
                        <dt class="text-xs font-semibold uppercase text-gray-500">Notifications</dt>
                        <dd class="text-gray-800">
                            @if ($sfNotifMeta) "{{ \Illuminate\Support\Str::limit($sfNotifMeta['text'], 60) }}"
                            @elseif ($sfNotifId !== '') Message ID {{ $sfNotifId }}
                            @else <span class="text-amber-700">No template chosen - nothing is sent (the OTP template is never reused)</span> @endif
                        </dd>
                    </div>
                </dl>
                <a href="{{ route('admin.message-templates.index', ['channel' => 'sms']) }}" class="inline-flex items-center gap-1 text-sm font-medium text-purple-700 hover:underline">Manage approved templates <i class="fas fa-arrow-right text-xs"></i></a>
            </div>
        </div>

        <details class="bg-white rounded-2xl border border-gray-200 p-5 sm:p-6 group">
            <summary class="cursor-pointer text-sm font-medium text-gray-700 select-none">Advanced / Technical details</summary>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4">
                <div class="sm:col-span-2 rounded-xl border border-gray-200 p-4 space-y-3">
                    <label class="flex items-start gap-2 cursor-pointer">
                        <input type="radio" name="fast2sms_route" value="otp" x-model="route" class="mt-0.5 text-purple-600">
                        <span class="text-sm"><span class="font-medium text-gray-900">Use the Fast2SMS OTP service for login codes</span><span class="block text-gray-500">Needs an <strong>OTP ID</strong>, which is a different number from a Message ID.</span></span>
                    </label>
                    <div>
                        <label for="sf-otpid" class="block text-sm font-medium text-gray-700 mb-1.5">Fast2SMS OTP ID</label>
                        <input id="sf-otpid" type="text" name="fast2sms_otp_id" value="{{ $sfSms['fast2sms_otp_id'] ?? '' }}" class="{{ $sfInput }}">
                        <p class="text-xs text-gray-500 mt-1">Your OTP template identifier from Fast2SMS (<span class="font-mono">otp_id</span>). Only used when the option above is selected.</p>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">DLT Entity ID (for your records)</label>
                    <input type="text" name="fast2sms_entity_id" value="{{ $sfSms['fast2sms_entity_id'] ?? '' }}" class="{{ $sfInput }}">
                    <p class="text-xs text-gray-500 mt-1">Registered with your telecom operator. Never sent to Fast2SMS.</p>
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

        <div class="flex flex-col-reverse sm:flex-row sm:items-center sm:justify-between gap-3">
            {{-- Connection check: reads the account (balance) only - it never sends an SMS --}}
            <div x-data="sfSmsVerify()" class="flex-1 min-w-0">
                <button type="button" @click="check()" :disabled="busy" class="px-4 py-2.5 rounded-xl border border-gray-300 text-gray-700 text-sm font-semibold hover:bg-gray-50 disabled:opacity-60">
                    <i class="fas fa-plug mr-1"></i> Verify connection
                </button>
                <p class="text-xs text-gray-500 mt-1">Saved settings only. Reads your Fast2SMS account; sends no SMS.</p>
                <div x-show="msg" x-cloak class="mt-2 rounded-xl px-4 py-3 text-sm" role="status"
                     :class="skipped ? 'bg-blue-50 text-blue-900' : (ok ? 'bg-green-50 text-green-900' : 'bg-red-50 text-red-900')">
                    <p x-text="(skipped ? '' : (ok ? '✓ ' : '✕ ')) + msg"></p>
                    <template x-if="details"><details class="mt-1 text-xs"><summary class="cursor-pointer">View technical details</summary><p class="font-mono break-words mt-1" x-text="details"></p></details></template>
                </div>
            </div>
            <button type="submit" class="px-6 py-2.5 rounded-xl bg-gradient-to-r from-purple-600 to-indigo-600 text-white text-sm font-semibold shadow hover:from-purple-700 hover:to-indigo-700">Save Changes</button>
        </div>
    </form>

    {{-- ───────────── Card 2: Test SMS ───────────── --}}
    @include('settings.partials.channel-test', [
        'title' => 'Test SMS',
        'hint' => 'Sends one real SMS with your saved settings and shows Fast2SMS\'s reply. Nothing is sent until you press the button.',
        'url' => route('settings.sms.test'),
        'fields' => [
            ['name' => 'phone', 'type' => 'tel', 'label' => 'Phone number', 'value' => auth()->user()->phone, 'placeholder' => '10 digits', 'maxlength' => 13, 'inputmode' => 'numeric'],
            ['name' => 'variables', 'type' => 'text', 'label' => 'Variables (separate with |)', 'value' => '1234', 'maxlength' => 200, 'placeholder' => 'e.g. 1234|Alpha Heights'],
            ['name' => 'template', 'type' => 'select', 'label' => 'Template', 'options' => ['otp' => 'OTP template', 'notification' => 'Notification template']],
        ],
        'button' => 'Send Test SMS',
        'icon' => 'fa-comment-sms',
    ])
</div>

@push('scripts')
<script>
function sfSmsVerify() {
    return {
        busy: false, ok: false, skipped: false, msg: '', details: null,
        async check() {
            this.busy = true;
            try {
                const r = await fetch(@js(route('settings.sms.connect')), { method: 'POST', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content } });
                const d = await r.json().catch(() => ({}));
                this.ok = !!d.success; this.skipped = !!d.skipped; this.msg = d.message || 'Something went wrong.'; this.details = d.details || null;
            } catch (e) { this.ok = false; this.skipped = false; this.msg = 'Could not complete the check. Try again.'; }
            this.busy = false;
        },
    };
}
</script>
@endpush
