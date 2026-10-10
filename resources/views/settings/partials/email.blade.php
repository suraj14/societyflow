{{-- Settings -> Email Settings. One place for email credentials. --}}
@php
    $sfEmail = $settings['email'] ?? [];
    $sfEmailSociety = auth()->user()->hasRole('Super Admin') ? null : auth()->user()->society_id;
    $sfEmailStatus = \App\Support\ChannelStatus::get('email', $sfEmailSociety);
    $sfEmailConfigured = !empty($sfEmail['mail_host']) && !empty($sfEmail['mail_from_address']);
    $sfInput = 'w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-purple-500';
@endphp

<div class="max-w-3xl space-y-6">
    <div>
        <h2 class="text-2xl font-bold text-gray-900">Email Settings</h2>
        <p class="text-sm text-gray-500 mt-1">Connect your society's email account once. SocietyFlow then sends notices, bills, receipts and visitor updates automatically.</p>
    </div>

    @include('settings.partials.channel-status', [
        'configured' => $sfEmailConfigured,
        'status' => $sfEmailStatus,
        'okLabel' => 'Email connected',
        'notConfiguredLabel' => 'Email not configured',
        'savedLabel' => 'Configuration saved',
        'savedHint' => 'Send a test email below to confirm it works.',
    ])

    <form action="{{ route('settings.email.update') }}" method="POST" class="space-y-6" x-data="sfEmailForm()">
        @csrf
        <input type="hidden" name="mail_driver" value="smtp">

        <div class="bg-white rounded-2xl border border-gray-200 p-5 sm:p-6">
            <h3 class="font-semibold text-gray-900 flex items-center gap-2 mb-4">
                <span class="w-6 h-6 rounded-full bg-purple-600 text-white text-xs flex items-center justify-center">1</span> Email server
            </h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Email Provider</label>
                    <select x-model="preset" @change="applyPreset()" class="{{ $sfInput }}">
                        <option value="custom">Other / custom SMTP</option>
                        <option value="gmail">Gmail / Google Workspace</option>
                        <option value="outlook">Outlook / Microsoft 365</option>
                        <option value="zoho">Zoho Mail</option>
                        <option value="hostinger">Hostinger</option>
                    </select>
                    <p class="text-xs text-gray-500 mt-1">Choosing a provider fills in the server details for you.</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">SMTP Host</label>
                    <input type="text" name="mail_host" x-ref="host" value="{{ old('mail_host', $sfEmail['mail_host'] ?? '') }}" placeholder="smtp.example.com" class="{{ $sfInput }}">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">SMTP Port</label>
                        <input type="number" name="mail_port" x-ref="port" value="{{ old('mail_port', $sfEmail['mail_port'] ?? 587) }}" class="{{ $sfInput }}">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Encryption</label>
                        <select name="mail_encryption" x-ref="enc" class="{{ $sfInput }}">
                            @foreach (['tls' => 'TLS', 'ssl' => 'SSL', 'null' => 'None'] as $v => $l)
                                <option value="{{ $v }}" @selected(old('mail_encryption', $sfEmail['mail_encryption'] ?? 'tls') === $v)>{{ $l }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 p-5 sm:p-6">
            <h3 class="font-semibold text-gray-900 flex items-center gap-2 mb-4">
                <span class="w-6 h-6 rounded-full bg-purple-600 text-white text-xs flex items-center justify-center">2</span> Sender
            </h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">From Name</label>
                    <input type="text" name="mail_from_name" required value="{{ old('mail_from_name', $sfEmail['mail_from_name'] ?? (auth()->user()->society->name ?? 'Society Office')) }}" class="{{ $sfInput }}">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">From Email Address</label>
                    <input type="email" name="mail_from_address" required value="{{ old('mail_from_address', $sfEmail['mail_from_address'] ?? '') }}" placeholder="office@yoursociety.in" class="{{ $sfInput }}">
                    @error('mail_from_address') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 p-5 sm:p-6">
            <h3 class="font-semibold text-gray-900 flex items-center gap-2 mb-4">
                <span class="w-6 h-6 rounded-full bg-purple-600 text-white text-xs flex items-center justify-center">3</span> Login
            </h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">SMTP Username / Auth Email</label>
                    <input type="text" name="mail_username" autocomplete="off" value="{{ old('mail_username', $sfEmail['mail_username'] ?? '') }}" class="{{ $sfInput }}">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">SMTP Password / API Key</label>
                    <input type="password" name="mail_password" autocomplete="new-password" value="{{ $sfEmail['mail_password'] ?? '' }}"
                           placeholder="{{ !empty($sfEmail['mail_password']) ? 'Saved' : 'App password or API key' }}" class="{{ $sfInput }}">
                    <p class="text-xs text-gray-500 mt-1">Stored encrypted. Leave as is to keep the saved password.</p>
                </div>
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="px-6 py-2.5 rounded-xl bg-gradient-to-r from-purple-600 to-indigo-600 text-white text-sm font-semibold shadow hover:from-purple-700 hover:to-indigo-700">
                Save Changes
            </button>
        </div>
    </form>

    @include('settings.partials.channel-test', [
        'title' => 'Send a test email',
        'hint' => 'Uses your saved settings. Save first if you changed anything.',
        'url' => route('settings.email.test'),
        'fields' => [['name' => 'test_email', 'type' => 'email', 'label' => 'Send to', 'value' => auth()->user()->email, 'placeholder' => 'you@example.com']],
        'button' => 'Send Test Email',
        'icon' => 'fa-paper-plane',
    ])
</div>

@push('scripts')
<script>
function sfEmailForm() {
    const presets = {
        gmail: ['smtp.gmail.com', 587, 'tls'],
        outlook: ['smtp.office365.com', 587, 'tls'],
        zoho: ['smtp.zoho.in', 587, 'tls'],
        hostinger: ['smtp.hostinger.com', 465, 'ssl'],
    };
    return {
        preset: 'custom',
        init() {
            const host = this.$refs.host.value;
            for (const [k, v] of Object.entries(presets)) if (v[0] === host) this.preset = k;
        },
        applyPreset() {
            const p = presets[this.preset];
            if (!p) return;
            this.$refs.host.value = p[0];
            this.$refs.port.value = p[1];
            this.$refs.enc.value = p[2];
        },
    };
}
</script>
@endpush
