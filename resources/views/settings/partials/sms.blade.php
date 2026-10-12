{{-- Settings -> SMS / OTP. Short info line, status + Enabled toggle, (1) API Configuration, (2) Test SMS.
     Which route a message takes (DLT or Quick SMS) is automatic - see SmsService::sendByTemplate. Templates and their
     Message IDs are managed on the Message Templates page. --}}
@php
    $sfSms = $settings['sms'] ?? [];
    $sfSmsSociety = auth()->user()->hasRole('Super Admin') ? null : auth()->user()->society_id;
    $sfSmsStatus = \App\Support\ChannelStatus::get('sms', $sfSmsSociety);
    $sfHasKey = !empty($sfSms['fast2sms_api_key']);
    $sfRoute = ($sfSms['fast2sms_route'] ?? 'dlt') === 'otp' ? 'otp' : 'auto';
    $sfTemplateId = (string) ($sfSms['fast2sms_template_id'] ?? '');
    $sfLooksLikeDltId = strlen(preg_replace('/\D+/', '', $sfTemplateId)) >= 15;
    $sfSmsConfigured = $sfHasKey && ($sfRoute !== 'otp' || !empty($sfSms['fast2sms_otp_id']));
    $sfEnabled = (bool) ($sfSms['sms_enabled'] ?? false);
    $sfInput = 'w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-purple-500';
@endphp

<div class="max-w-3xl space-y-6">
    <div>
        <h2 class="text-2xl font-bold text-gray-900">SMS / OTP</h2>
        <p class="text-sm text-gray-500 mt-1">SMS for SocietyFlow is sent through your Fast2SMS account.</p>
    </div>

    @unless (\App\Support\ExternalMessaging::enabled())
        <p class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-2.5 text-sm text-amber-900" role="status">
            <i class="fas fa-shield-halved mr-1"></i> Real SMS delivery is switched off on this server (EXTERNAL_MESSAGING_ENABLED=false). Nothing is sent.
        </p>
    @endunless

    @if ($sfLooksLikeDltId)
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="alert">
            <p class="font-semibold"><i class="fas fa-triangle-exclamation mr-1"></i> Your saved SMS template needs to be chosen again</p>
            <p class="mt-1">The saved value is a DLT Template ID, not a Fast2SMS Message ID (the short number in DLT Manager, e.g. 223577).</p>
        </div>
    @endif

    <form action="{{ route('settings.sms.update') }}" method="POST" class="space-y-6">
        @csrf

        {{-- Status + Enabled toggle --}}
        <div class="bg-white rounded-2xl border border-gray-200 px-5 py-4 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3 min-w-0">
                <span class="w-2.5 h-2.5 rounded-full shrink-0 {{ $sfEnabled ? 'bg-green-500' : 'bg-gray-400' }}"></span>
                <div class="min-w-0">
                    <p class="font-semibold text-gray-900" role="status">{{ $sfEnabled ? 'Integration active' : 'Integration inactive' }}</p>
                    <p class="text-xs text-gray-500">SMS is sent only while this is on, an API key is saved, and the server allows external messaging. Turning it off keeps your settings.</p>
                </div>
            </div>
            <label class="flex items-center gap-2 cursor-pointer shrink-0">
                <span class="text-sm text-gray-700">Enabled</span>
                <input type="hidden" name="sms_enabled" value="0">
                <input type="checkbox" name="sms_enabled" value="1" @checked($sfEnabled) class="w-11 h-6 rounded-full text-purple-600 focus:ring-purple-500" aria-label="Enable SMS">
            </label>
        </div>

        {{-- The last real result (success, or Fast2SMS's exact error). Shown only once there is one, or when no key is saved. --}}
        @if ($sfSmsStatus || !$sfSmsConfigured)
            @include('settings.partials.channel-status', [
                'configured' => $sfSmsConfigured,
                'status' => $sfSmsStatus,
                'okLabel' => 'Provider check passed',
                'notConfiguredLabel' => 'SMS not configured',
                'notConfiguredHint' => $sfHasKey ? 'Enter the OTP ID under Advanced, or untick the OTP service.' : 'Enter your Fast2SMS API key and save.',
                'savedLabel' => '',
                'savedHint' => '',
            ])
        @endif

        {{-- 1. API Configuration --}}
        <div class="bg-white rounded-2xl border border-gray-200 p-5 sm:p-6 space-y-5">
            <h3 class="font-semibold text-gray-900"><span class="text-purple-600">&#9312;</span> API Configuration</h3>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">SMS Provider</label>
                <div class="px-4 py-2.5 rounded-xl bg-gray-50 border border-gray-200 text-sm text-gray-800">Fast2SMS</div>
            </div>

            <div>
                <label for="sf-key" class="block text-sm font-medium text-gray-700 mb-1.5">Fast2SMS API Key</label>
                <input id="sf-key" type="password" name="fast2sms_api_key" autocomplete="new-password" value="{{ $sfSms['fast2sms_api_key'] ?? '' }}" placeholder="{{ $sfHasKey ? 'Saved - paste a new key to replace it' : 'Paste your key' }}" class="{{ $sfInput }}">
                <p class="text-xs mt-1 {{ $sfHasKey ? 'text-green-700' : 'text-gray-500' }}">
                    @if ($sfHasKey) <i class="fas fa-lock mr-1"></i>API key configured (stored encrypted). Leave it as is to keep it. @else API key not configured. Get your API key from fast2sms.com &rarr; Dev API. @endif
                </p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="sf-sender" class="block text-sm font-medium text-gray-700 mb-1.5">Sender ID (DLT, optional)</label>
                    <input id="sf-sender" type="text" name="fast2sms_sender_id" value="{{ $sfSms['fast2sms_sender_id'] ?? '' }}" maxlength="6" placeholder="e.g. BOOKMZ" class="{{ $sfInput }}">
                    <p class="text-xs text-gray-500 mt-1">Your DLT-approved 3&ndash;6 character sender header. Needed for DLT sending; leave empty for Quick SMS.</p>
                </div>
                <div>
                    <label for="sf-msgid" class="block text-sm font-medium text-gray-700 mb-1.5">Default Fast2SMS Message ID (DLT, optional)</label>
                    <input id="sf-msgid" type="text" name="fast2sms_template_id" value="{{ $sfTemplateId }}" inputmode="numeric" placeholder="e.g. 223577" class="{{ $sfInput }}">
                    <p class="text-xs text-gray-500 mt-1">Fallback, used only when a template has no Message ID of its own. Normally leave empty.</p>
                </div>
            </div>

            <p class="rounded-xl bg-purple-50 border border-purple-100 px-4 py-3 text-xs text-purple-900">
                <i class="fas fa-lightbulb mr-1"></i> <strong>DLT sending:</strong> set the Sender ID here, then enter each template's Message ID in
                <a href="{{ route('admin.message-templates.index', ['channel' => 'sms']) }}" class="underline font-medium">Message Templates &rarr; SMS</a>.
                Templates with a Message ID go on the DLT route; templates without one use Quick SMS (random sender, fine for testing).
            </p>

            <label class="flex items-center justify-between gap-4 cursor-pointer">
                <span>
                    <span class="block text-sm font-medium text-gray-900">Also send important notifications by SMS</span>
                    <span class="block text-xs text-gray-500">Uses the Notice template only. Manage approved templates in <a href="{{ route('admin.message-templates.index', ['channel' => 'sms']) }}" class="underline">Message Templates</a>.</span>
                </span>
                <input type="hidden" name="notification_sms_enabled" value="0">
                <input type="checkbox" name="notification_sms_enabled" value="1" @checked($sfSms['notification_sms_enabled'] ?? false) class="w-11 h-6 rounded-full text-purple-600 focus:ring-purple-500">
            </label>
        </div>

        <details class="bg-white rounded-2xl border border-gray-200 p-5 sm:p-6" @if ($sfRoute === 'otp') open @endif>
            <summary class="cursor-pointer text-sm font-medium text-gray-700 select-none">Advanced</summary>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4">
                <div class="sm:col-span-2 rounded-xl border border-gray-200 p-4 space-y-3">
                    <label class="flex items-start gap-2 cursor-pointer">
                        <input type="checkbox" name="fast2sms_use_otp_service" value="1" @checked($sfRoute === 'otp') class="mt-0.5 rounded text-purple-600">
                        <span class="text-sm"><span class="font-medium text-gray-900">Send login codes with the Fast2SMS OTP service</span><span class="block text-gray-500">Needs an <strong>OTP ID</strong> (a different number from a Message ID).</span></span>
                    </label>
                    <div>
                        <label for="sf-otpid" class="block text-sm font-medium text-gray-700 mb-1.5">Fast2SMS OTP ID</label>
                        <input id="sf-otpid" type="text" name="fast2sms_otp_id" value="{{ $sfSms['fast2sms_otp_id'] ?? '' }}" class="{{ $sfInput }}">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">DLT Entity ID (for your records)</label>
                    <input type="text" name="fast2sms_entity_id" value="{{ $sfSms['fast2sms_entity_id'] ?? '' }}" class="{{ $sfInput }}">
                    <p class="text-xs text-gray-500 mt-1">Never sent to Fast2SMS.</p>
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

    {{-- 2. Test SMS --}}
    @include('settings.partials.channel-test', [
        'title' => 'Test SMS',
        'hint' => 'Sends one real SMS with your saved settings and shows Fast2SMS\'s reply. With a Sender ID and Message ID saved, the text fills the template variables (separate with |); otherwise it goes as Quick SMS.',
        'url' => route('settings.sms.test'),
        'fields' => [
            ['name' => 'phone', 'type' => 'tel', 'label' => 'Phone Number', 'value' => auth()->user()->phone, 'placeholder' => '10 digits', 'maxlength' => 13, 'inputmode' => 'numeric'],
            ['name' => 'message', 'type' => 'textarea', 'label' => 'Test Message', 'value' => 'Test message from SocietyFlow', 'maxlength' => 160, 'counter' => 160, 'wide' => true],
        ],
        'button' => 'Send Test SMS',
        'icon' => 'fa-comment-sms',
    ])
</div>

