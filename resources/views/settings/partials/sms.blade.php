<form action="{{ route('settings.sms.update') }}" method="POST" x-data="{
    provider: '{{ $settings['sms']['sms_provider'] ?? 'fast2sms' }}'
}">
    @csrf
    <div class="space-y-6">

        {{-- Header --}}
        <div class="flex items-center space-x-3 pb-4 border-b border-gray-200">
            <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                <i class="fas fa-sms text-green-600"></i>
            </div>
            <div>
                <h3 class="text-lg font-semibold text-gray-900">SMS / OTP Settings</h3>
                <p class="text-sm text-gray-500">Configure SMS provider for mobile OTP login</p>
            </div>
        </div>

        {{-- Enable toggle --}}
        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
            <div>
                <h4 class="text-sm font-medium text-gray-900">Enable SMS OTP</h4>
                <p class="text-sm text-gray-500 mt-0.5">When disabled, OTP is returned in API response (dev mode only).</p>
            </div>
            <label class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" name="sms_enabled" value="1"
                    {{ ($settings['sms']['sms_enabled'] ?? false) ? 'checked' : '' }}
                    class="sr-only peer">
                <div class="w-11 h-6 bg-gray-200 peer-focus:ring-4 peer-focus:ring-green-300 rounded-full peer
                    peer-checked:after:translate-x-full peer-checked:after:border-white
                    after:content-[''] after:absolute after:top-[2px] after:left-[2px]
                    after:bg-white after:border-gray-300 after:border after:rounded-full
                    after:h-5 after:w-5 after:transition-all peer-checked:bg-green-600"></div>
            </label>
        </div>

        {{-- Provider selector --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-3">SMS Provider</label>
            <div class="grid grid-cols-3 gap-3">
                {{-- Fast2SMS --}}
                <label class="relative cursor-pointer">
                    <input type="radio" name="sms_provider" value="fast2sms" x-model="provider" class="sr-only peer">
                    <div class="flex flex-col items-center p-4 border-2 rounded-xl transition-all
                        peer-checked:border-purple-500 peer-checked:bg-purple-50 border-gray-200 hover:border-gray-300">
                        <div class="w-10 h-10 bg-orange-100 rounded-lg flex items-center justify-center mb-2">
                            <i class="fas fa-bolt text-orange-500 text-lg"></i>
                        </div>
                        <span class="text-sm font-semibold text-gray-800">Fast2SMS</span>
                        <span class="text-xs text-gray-500 mt-0.5">Dev API</span>
                    </div>
                </label>

                {{-- BulkSMSPlans --}}
                <label class="relative cursor-pointer">
                    <input type="radio" name="sms_provider" value="bulksmsplans" x-model="provider" class="sr-only peer">
                    <div class="flex flex-col items-center p-4 border-2 rounded-xl transition-all
                        peer-checked:border-purple-500 peer-checked:bg-purple-50 border-gray-200 hover:border-gray-300">
                        <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mb-2">
                            <i class="fas fa-comments text-blue-500 text-lg"></i>
                        </div>
                        <span class="text-sm font-semibold text-gray-800">BulkSMSPlans</span>
                        <span class="text-xs text-gray-500 mt-0.5">bulksmsplans.com</span>
                    </div>
                </label>

                {{-- Custom --}}
                <label class="relative cursor-pointer">
                    <input type="radio" name="sms_provider" value="custom" x-model="provider" class="sr-only peer">
                    <div class="flex flex-col items-center p-4 border-2 rounded-xl transition-all
                        peer-checked:border-purple-500 peer-checked:bg-purple-50 border-gray-200 hover:border-gray-300">
                        <div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center mb-2">
                            <i class="fas fa-code text-gray-500 text-lg"></i>
                        </div>
                        <span class="text-sm font-semibold text-gray-800">Custom API</span>
                        <span class="text-xs text-gray-500 mt-0.5">Any HTTP API</span>
                    </div>
                </label>
            </div>
        </div>

        {{-- Fast2SMS fields --}}
        <div x-show="provider === 'fast2sms'" x-cloak class="space-y-4 p-4 bg-orange-50 border border-orange-200 rounded-xl">
            <div class="flex items-center space-x-2 mb-3">
                <i class="fas fa-bolt text-orange-500"></i>
                <span class="text-sm font-semibold text-orange-800">Fast2SMS Configuration</span>
                <a href="https://www.fast2sms.com/dashboard/dev-api" target="_blank"
                   class="ml-auto text-xs text-purple-600 hover:underline">
                    <i class="fas fa-external-link-alt mr-1"></i>Get API Key
                </a>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-2">API Authorization Key</label>
                    <div class="relative">
                        <input type="password" name="fast2sms_api_key" id="f2s_key"
                            placeholder="{{ ($settings['sms']['sms_provider'] ?? '') === 'fast2sms' && ($settings['sms']['sms_enabled'] ?? false) ? '••••••••••••••••••••••••' : 'Paste your Fast2SMS API key' }}"
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 pr-12">
                        <button type="button" onclick="toggleVis('f2s_key', 'f2s_eye')"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                            <i class="fas fa-eye" id="f2s_eye"></i>
                        </button>
                    </div>
                    <p class="mt-1 text-xs text-gray-500">From Fast2SMS → Dev API → API Key tab. Stored encrypted.</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Route</label>
                    <select name="fast2sms_route" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
                        <option value="otp" {{ ($settings['sms']['fast2sms_route'] ?? 'otp') === 'otp' ? 'selected' : '' }}>OTP (Dev API — no DLT needed)</option>
                        <option value="dlt" {{ ($settings['sms']['fast2sms_route'] ?? '') === 'dlt' ? 'selected' : '' }}>DLT (Registered template)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Sender ID</label>
                    <input type="text" name="fast2sms_sender_id"
                        value="{{ $settings['sms']['fast2sms_sender_id'] ?? '' }}"
                        placeholder="e.g. SYITPL (DLT route only)"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
                    <p class="mt-1 text-xs text-gray-500">Required only for DLT route</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Template ID</label>
                    <input type="text" name="fast2sms_template_id"
                        value="{{ $settings['sms']['fast2sms_template_id'] ?? '' }}"
                        placeholder="e.g. 181789 (DLT route only)"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
                    <p class="mt-1 text-xs text-gray-500">Your DLT approved template ID</p>
                </div>
            </div>
            <div class="bg-orange-100 rounded-lg p-3 text-xs text-orange-800">
                <i class="fas fa-info-circle mr-1"></i>
                <strong>OTP route:</strong> No DLT needed, works instantly for testing.<br>
                <strong>DLT route:</strong> Requires registered Sender ID + Template ID from Fast2SMS DLT panel.
            </div>
        </div>

        {{-- BulkSMSPlans fields --}}
        <div x-show="provider === 'bulksmsplans'" x-cloak class="space-y-4 p-4 bg-blue-50 border border-blue-200 rounded-xl">
            <div class="flex items-center space-x-2 mb-3">
                <i class="fas fa-comments text-blue-500"></i>
                <span class="text-sm font-semibold text-blue-800">BulkSMSPlans Configuration</span>
                <a href="https://bulksmsplans.com" target="_blank"
                   class="ml-auto text-xs text-purple-600 hover:underline">
                    <i class="fas fa-external-link-alt mr-1"></i>bulksmsplans.com
                </a>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">API ID <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <input type="password" name="bulksmsplans_api_id" id="bsp_id"
                            placeholder="{{ ($settings['sms']['sms_provider'] ?? '') === 'bulksmsplans' && !empty($settings['sms']['bulksmsplans_api_id'] ?? null) ? '••••••••••••' : 'e.g. API1234567890' }}"
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 pr-12">
                        <button type="button" onclick="toggleVis('bsp_id','bsp_id_eye')"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                            <i class="fas fa-eye" id="bsp_id_eye"></i>
                        </button>
                    </div>
                    @if(($settings['sms']['sms_provider'] ?? '') === 'bulksmsplans' && !empty($settings['sms']['bulksmsplans_api_id'] ?? null))
                        <p class="mt-1 text-xs text-green-600"><i class="fas fa-check-circle mr-1"></i>Saved. Leave blank to keep.</p>
                    @endif
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">API Password <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <input type="password" name="bulksmsplans_api_password" id="bsp_pass"
                            placeholder="{{ ($settings['sms']['sms_provider'] ?? '') === 'bulksmsplans' && !empty($settings['sms']['bulksmsplans_api_password'] ?? null) ? '••••••••' : 'e.g. mBrYqQR' }}"
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 pr-12">
                        <button type="button" onclick="toggleVis('bsp_pass','bsp_pass_eye')"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                            <i class="fas fa-eye" id="bsp_pass_eye"></i>
                        </button>
                    </div>
                    @if(($settings['sms']['sms_provider'] ?? '') === 'bulksmsplans' && !empty($settings['sms']['bulksmsplans_api_password'] ?? null))
                        <p class="mt-1 text-xs text-green-600"><i class="fas fa-check-circle mr-1"></i>Saved. Leave blank to keep.</p>
                    @endif
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Sender ID <span class="text-red-500">*</span></label>
                    <input type="text" name="bulksmsplans_sender_id"
                        value="{{ $settings['sms']['bulksmsplans_sender_id'] ?? 'DEMOSM' }}"
                        maxlength="11"
                        placeholder="e.g. DEMOSM"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
                    <p class="mt-1 text-xs text-gray-500">Your DLT registered Sender ID (6 chars)</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Template ID</label>
                    <input type="text" name="bulksmsplans_template_id"
                        value="{{ $settings['sms']['bulksmsplans_template_id'] ?? '0' }}"
                        placeholder="0 (or your DLT template ID)"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
                    <p class="mt-1 text-xs text-gray-500">Leave 0 if not using DLT template. <a href="https://bulksmsplans.com" target="_blank" class="text-purple-600 hover:underline">Get from dashboard</a></p>
                </div>
            </div>
            <div class="bg-blue-100 rounded-lg p-3 text-xs text-blue-800 space-y-1">
                <p class="font-semibold"><i class="fas fa-info-circle mr-1"></i>Where to find these values:</p>
                <p>Login to <strong>bulksmsplans.com</strong> → <strong>Web API Documents</strong> → <strong>API Settings</strong></p>
                <p>Copy <strong>API Id</strong> and <strong>API Password</strong> from the top of that page.</p>
                <p>Endpoint used: <code class="bg-blue-200 px-1 rounded">https://bulksmsplans.com/api/send_sms</code></p>
            </div>
        </div>

        {{-- Custom API fields --}}
        <div x-show="provider === 'custom'" x-cloak class="space-y-4 p-4 bg-gray-50 border border-gray-200 rounded-xl">
            <div class="flex items-center space-x-2 mb-3">
                <i class="fas fa-code text-gray-500"></i>
                <span class="text-sm font-semibold text-gray-800">Custom HTTP API</span>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">API URL</label>
                <input type="text" name="custom_sms_api_url"
                    value="{{ $settings['sms']['custom_sms_api_url'] ?? '' }}"
                    placeholder="https://api.yourprovider.com/send?key=YOUR_KEY&mobile={phone}&message={otp}"
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
                <p class="mt-1 text-xs text-gray-500">
                    Use <code class="bg-gray-200 px-1 rounded">{phone}</code> and <code class="bg-gray-200 px-1 rounded">{otp}</code> as placeholders for GET-style URLs.
                    For POST APIs, leave placeholders out — the system will POST JSON with <code class="bg-gray-200 px-1 rounded">mobile</code> and <code class="bg-gray-200 px-1 rounded">otp</code> fields.
                </p>
            </div>
        </div>

        {{-- Test SMS --}}
        <div class="border border-gray-200 rounded-lg p-4">
            <h4 class="text-sm font-medium text-gray-900 mb-3">Test SMS (sends OTP: 1234)</h4>
            <div class="flex gap-3">
                <input type="text" id="test_phone" placeholder="10-digit mobile number"
                    class="flex-1 px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
                <button type="button" onclick="testSms()"
                    class="px-4 py-2.5 bg-green-600 hover:bg-green-700 text-white rounded-lg text-sm font-medium whitespace-nowrap">
                    <i class="fas fa-paper-plane mr-2"></i>Send Test OTP
                </button>
            </div>
            <p id="sms-test-result" class="mt-2 text-sm hidden"></p>
        </div>

        <div class="flex justify-end pt-4 border-t border-gray-200">
            <button type="submit" class="px-6 py-2.5 bg-purple-600 hover:bg-purple-700 text-white font-medium rounded-lg transition-colors">
                <i class="fas fa-save mr-2"></i>Save SMS Settings
            </button>
        </div>
    </div>
</form>

<script>
function toggleVis(inputId, iconId) {
    const input = document.getElementById(inputId);
    const icon  = document.getElementById(iconId);
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
}

function testSms() {
    const phone  = document.getElementById('test_phone').value.trim();
    const result = document.getElementById('sms-test-result');

    if (!phone || phone.length < 10) {
        result.textContent = 'Please enter a valid 10-digit mobile number.';
        result.className = 'mt-2 text-sm text-red-600';
        result.classList.remove('hidden');
        return;
    }

    result.textContent = 'Sending test OTP...';
    result.className = 'mt-2 text-sm text-gray-500';
    result.classList.remove('hidden');

    fetch('{{ route("settings.sms.test") }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ phone }),
    })
    .then(r => r.json())
    .then(data => {
        result.textContent = data.success ? '✅ ' + data.message : '❌ ' + data.message;
        result.className = 'mt-2 text-sm ' + (data.success ? 'text-green-600' : 'text-red-600');
    })
    .catch(() => {
        result.textContent = '❌ Request failed. Please try again.';
        result.className = 'mt-2 text-sm text-red-600';
    });
}
</script>
