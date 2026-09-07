@extends('layouts.super-admin')

@section('title', 'System Settings')
@section('page-title', 'System Settings')

@section('content')
<div class="space-y-6">
    <!-- Page Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">System Settings</h1>
            <p class="text-gray-600 mt-1">Configure global application settings and manage the SocietyFlow platform</p>
        </div>
        <form action="{{ route('settings.clear-cache') }}" method="POST">
            @csrf
            <button type="submit" class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg text-sm">
                <i class="fas fa-sync-alt mr-2"></i>Clear Cache
            </button>
        </form>
    </div>

    <!-- Success/Error Messages -->
    @if(session('success'))
        <div class="bg-green-50 border-l-4 border-green-500 p-4 rounded-r-lg">
            <div class="flex items-center">
                <i class="fas fa-check-circle text-green-500 mr-3"></i>
                <p class="text-sm text-green-800">{{ session('success') }}</p>
            </div>
        </div>
    @endif

    @if(session('error'))
        <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded-r-lg">
            <div class="flex items-center">
                <i class="fas fa-exclamation-circle text-red-500 mr-3"></i>
                <p class="text-sm text-red-800">{{ session('error') }}</p>
            </div>
        </div>
    @endif

    <!-- Tabs Navigation -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200" x-data="{ activeTab: '{{ $tab }}' }">
        <div class="border-b border-gray-200 overflow-x-auto">
            <nav class="flex -mb-px min-w-max" aria-label="Tabs">
                @php
                    // Super Admin global settings tabs
                    $tabs = [
                        'app' => ['icon' => 'fa-cog', 'label' => 'App Settings'],
                        'language' => ['icon' => 'fa-language', 'label' => 'Language'],
                        'storage' => ['icon' => 'fa-database', 'label' => 'Storage'],
                        'theme' => ['icon' => 'fa-palette', 'label' => 'Theme'],
                        'currency' => ['icon' => 'fa-dollar-sign', 'label' => 'Currency'],
                        'email' => ['icon' => 'fa-envelope', 'label' => 'Email Settings'],
                        'payment' => ['icon' => 'fa-credit-card', 'label' => 'Payment Gateway'],
                        'push' => ['icon' => 'fa-bell', 'label' => 'Push Notifications'],
                        'security' => ['icon' => 'fa-shield-alt', 'label' => 'Security'],
                    ];
                @endphp
                @foreach($tabs as $key => $tabInfo)
                    <button type="button" @click="activeTab = '{{ $key }}'"
                        :class="activeTab === '{{ $key }}' ? 'border-purple-500 text-purple-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                        class="whitespace-nowrap py-4 px-6 border-b-2 font-medium text-sm transition-colors">
                        <i class="fas {{ $tabInfo['icon'] }} mr-2"></i>{{ $tabInfo['label'] }}
                    </button>
                @endforeach
            </nav>
        </div>

        <!-- Tab Content -->
        <div class="p-6">
            <!-- App Settings Tab -->
            <div x-show="activeTab === 'app'" x-cloak>
                <form action="{{ route('super-admin.settings.update.app') }}" method="POST">
                    @csrf
                    <div class="space-y-6">
                        <div class="flex items-center space-x-3 pb-4 border-b border-gray-200">
                            <div class="w-10 h-10 bg-indigo-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-cog text-indigo-600"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">Application Settings</h3>
                                <p class="text-sm text-gray-500">Configure global application preferences</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Application Name</label>
                                <input type="text" name="app_name" value="{{ $settings['app']['app_name'] ?? 'SocietyFlow' }}"
                                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500"
                                    required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Default Language</label>
                                <select name="default_language" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                                    @foreach($languages as $code => $name)
                                        <option value="{{ $code }}" {{ ($settings['app']['default_language'] ?? 'en') == $code ? 'selected' : '' }}>{{ $name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Default Currency</label>
                                <select name="default_currency" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                                    @foreach($currencies as $code => $currency)
                                        <option value="{{ $code }}" {{ ($settings['app']['default_currency'] ?? 'INR') == $code ? 'selected' : '' }}>{{ $currency['name'] }} ({{ $currency['symbol'] }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Society Requires Approval</label>
                                <div class="flex items-center space-x-3 pt-2">
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox" name="society_requires_approval" value="1" {{ ($settings['app']['society_requires_approval'] ?? false) ? 'checked' : '' }} class="sr-only peer">
                                        <div class="w-11 h-6 bg-gray-200 peer-focus:ring-4 peer-focus:ring-purple-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-purple-600"></div>
                                    </label>
                                    <span class="text-sm text-gray-600">Require admin approval for new society registrations</span>
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end pt-4 border-t border-gray-200">
                            <button type="submit" class="px-6 py-2.5 bg-purple-600 hover:bg-purple-700 text-white font-medium rounded-lg transition-colors">
                                <i class="fas fa-save mr-2"></i>Save Settings
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Language Settings Tab -->
            <div x-show="activeTab === 'language'" x-cloak>
                <form action="{{ route('super-admin.settings.update.language') }}" method="POST">
                    @csrf
                    <div class="space-y-6">
                        <div class="flex items-center space-x-3 pb-4 border-b border-gray-200">
                            <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-language text-blue-600"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">Language Settings</h3>
                                <p class="text-sm text-gray-500">Configure available languages for the platform</p>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-3">Enabled Languages</label>
                            <div class="space-y-2">
                                @foreach($languages as $code => $name)
                                    <div class="flex items-center">
                                        <input type="checkbox" name="enabled_languages[]" value="{{ $code }}" 
                                            {{ in_array($code, $settings['app']['enabled_languages'] ?? ['en']) ? 'checked' : '' }}
                                            class="h-4 w-4 text-purple-600 focus:ring-purple-500 border-gray-300 rounded">
                                        <label class="ml-3 text-sm text-gray-700">{{ $name }}</label>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="flex justify-end pt-4 border-t border-gray-200">
                            <button type="submit" class="px-6 py-2.5 bg-purple-600 hover:bg-purple-700 text-white font-medium rounded-lg transition-colors">
                                <i class="fas fa-save mr-2"></i>Save Settings
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Storage Settings Tab -->
            <div x-show="activeTab === 'storage'" x-cloak>
                <form action="{{ route('super-admin.settings.update.storage') }}" method="POST">
                    @csrf
                    <div class="space-y-6">
                        <div class="flex items-center space-x-3 pb-4 border-b border-gray-200">
                            <div class="w-10 h-10 bg-orange-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-database text-orange-600"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">Storage Configuration</h3>
                                <p class="text-sm text-gray-500">Configure file storage settings</p>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Storage Driver</label>
                            <select name="storage_driver" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                                <option value="local" {{ ($settings['storage']['storage_driver'] ?? 'local') == 'local' ? 'selected' : '' }}>Local</option>
                                <option value="public" {{ ($settings['storage']['storage_driver'] ?? '') == 'public' ? 'selected' : '' }}>Public</option>
                                <option value="s3" {{ ($settings['storage']['storage_driver'] ?? '') == 's3' ? 'selected' : '' }}>Amazon S3</option>
                            </select>
                        </div>

                        <div id="s3-settings" class="space-y-4" style="display: {{ ($settings['storage']['storage_driver'] ?? 'local') == 's3' ? 'block' : 'none' }};">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">AWS Key</label>
                                    <input type="text" name="aws_key" value="{{ $settings['storage']['aws_key'] ?? '' }}"
                                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">AWS Secret</label>
                                    <input type="password" name="aws_secret" value="{{ $settings['storage']['aws_secret'] ?? '' }}"
                                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">AWS Region</label>
                                    <input type="text" name="aws_region" value="{{ $settings['storage']['aws_region'] ?? '' }}"
                                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500"
                                        placeholder="us-east-1">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">AWS Bucket</label>
                                    <input type="text" name="aws_bucket" value="{{ $settings['storage']['aws_bucket'] ?? '' }}"
                                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center justify-between pt-4 border-t border-gray-200">
                            <button type="button" onclick="testStorage()" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm">
                                <i class="fas fa-plug mr-2"></i>Test Connection
                            </button>
                            <button type="submit" class="px-6 py-2.5 bg-purple-600 hover:bg-purple-700 text-white font-medium rounded-lg transition-colors">
                                <i class="fas fa-save mr-2"></i>Save Settings
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Theme Settings Tab -->
            <div x-show="activeTab === 'theme'" x-cloak>
                <form action="{{ route('super-admin.settings.update.theme') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="space-y-6">
                        <div class="flex items-center space-x-3 pb-4 border-b border-gray-200">
                            <div class="w-10 h-10 bg-pink-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-palette text-pink-600"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">Theme Settings</h3>
                                <p class="text-sm text-gray-500">Customize the global appearance of the platform</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Primary Color</label>
                                <div class="flex items-center space-x-3">
                                    <input type="color" name="primary_color" value="{{ $settings['theme']['primary_color'] ?? '#7c3aed' }}"
                                        class="w-12 h-10 border border-gray-300 rounded-lg cursor-pointer">
                                    <input type="text" value="{{ $settings['theme']['primary_color'] ?? '#7c3aed' }}"
                                        class="flex-1 px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500" readonly>
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Secondary Color</label>
                                <div class="flex items-center space-x-3">
                                    <input type="color" name="secondary_color" value="{{ $settings['theme']['secondary_color'] ?? '#64748b' }}"
                                        class="w-12 h-10 border border-gray-300 rounded-lg cursor-pointer">
                                    <input type="text" value="{{ $settings['theme']['secondary_color'] ?? '#64748b' }}"
                                        class="flex-1 px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500" readonly>
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Sidebar Theme</label>
                                <select name="sidebar_theme" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                                    <option value="light" {{ ($settings['theme']['sidebar_theme'] ?? 'light') == 'light' ? 'selected' : '' }}>Light</option>
                                    <option value="dark" {{ ($settings['theme']['sidebar_theme'] ?? '') == 'dark' ? 'selected' : '' }}>Dark</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Button Style</label>
                                <select name="button_style" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                                    <option value="rounded" {{ ($settings['theme']['button_style'] ?? 'rounded') == 'rounded' ? 'selected' : '' }}>Rounded</option>
                                    <option value="square" {{ ($settings['theme']['button_style'] ?? '') == 'square' ? 'selected' : '' }}>Square</option>
                                    <option value="pill" {{ ($settings['theme']['button_style'] ?? '') == 'pill' ? 'selected' : '' }}>Pill</option>
                                </select>
                            </div>
                        </div>

                        <div class="flex justify-end pt-4 border-t border-gray-200">
                            <button type="submit" class="px-6 py-2.5 bg-purple-600 hover:bg-purple-700 text-white font-medium rounded-lg transition-colors">
                                <i class="fas fa-save mr-2"></i>Save Settings
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Currency Settings Tab -->
            <div x-show="activeTab === 'currency'" x-cloak>
                <form action="{{ route('super-admin.settings.update.currency') }}" method="POST">
                    @csrf
                    <div class="space-y-6">
                        <div class="flex items-center space-x-3 pb-4 border-b border-gray-200">
                            <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-dollar-sign text-green-600"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">Currency Settings</h3>
                                <p class="text-sm text-gray-500">Configure currency display and formatting</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Default Currency</label>
                                <select name="default_currency" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                                    @foreach($currencies as $code => $currency)
                                        <option value="{{ $code }}">{{ $currency['name'] }} ({{ $currency['symbol'] }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Currency Position</label>
                                <select name="currency_position" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                                    <option value="left">Left ($100)</option>
                                    <option value="right">Right (100$)</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Decimal Separator</label>
                                <input type="text" name="decimal_separator" value="." maxlength="1"
                                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Thousand Separator</label>
                                <input type="text" name="thousand_separator" value="," maxlength="1"
                                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                            </div>
                        </div>

                        <div class="flex justify-end pt-4 border-t border-gray-200">
                            <button type="submit" class="px-6 py-2.5 bg-purple-600 hover:bg-purple-700 text-white font-medium rounded-lg transition-colors">
                                <i class="fas fa-save mr-2"></i>Save Settings
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Email Settings Tab -->
            <div x-show="activeTab === 'email'" x-cloak>
                @include('super-admin.settings.partials.email')
            </div>

            <!-- Payment Settings Tab -->
            <div x-show="activeTab === 'payment'" x-cloak>
                @include('super-admin.settings.partials.payment')
            </div>

            <!-- Push Notifications Tab -->
            <div x-show="activeTab === 'push'" x-cloak>
                @include('super-admin.settings.partials.push')
            </div>

            <!-- Security Settings Tab -->
            <div x-show="activeTab === 'security'" x-cloak>
                @include('super-admin.settings.partials.security')
            </div>
        </div>
    </div>
</div>

<script>
    document.querySelector('select[name="storage_driver"]').addEventListener('change', function() {
        document.getElementById('s3-settings').style.display = this.value === 's3' ? 'block' : 'none';
    });

    function testStorage() {
        fetch('{{ route("super-admin.settings.test.storage") }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Content-Type': 'application/json'
            }
        })
        .then(r => r.json())
        .then(data => {
            alert(data.message);
        })
        .catch(e => alert('Error: ' + e.message));
    }

    function testEmail() {
        const testEmailInput = document.getElementById('test_email');
        const testEmail = testEmailInput ? testEmailInput.value.trim() : '';
        
        if (!testEmail) {
            const email = prompt('Enter test email address:');
            if (!email) return;
            
            const button = event.target;
            const originalText = button.innerHTML;
            
            // Show loading state
            button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Sending...';
            button.disabled = true;
            
            fetch('{{ route("settings.email.test") }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ test_email: email })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert('✅ Test email sent successfully! Check your inbox.');
                } else {
                    alert('❌ Failed to send test email: ' + data.message + (data.details ? '\n\n' + data.details : ''));
                }
            })
            .catch(e => {
                console.error('Error:', e);
                alert('❌ Error sending test email. Please check your settings and try again.');
            })
            .finally(() => {
                // Restore button state
                button.innerHTML = originalText;
                button.disabled = false;
            });
        } else {
            // Use the input field value
            if (!isValidEmail(testEmail)) {
                alert('Please enter a valid email address');
                testEmailInput.focus();
                return;
            }
            
            const button = event.target;
            const originalText = button.innerHTML;
            
            // Show loading state
            button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Sending...';
            button.disabled = true;
            
            fetch('{{ route("settings.email.test") }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ test_email: testEmail })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert('✅ Test email sent successfully! Check your inbox.');
                } else {
                    alert('❌ Failed to send test email: ' + data.message + (data.details ? '\n\n' + data.details : ''));
                }
            })
            .catch(e => {
                console.error('Error:', e);
                alert('❌ Error sending test email. Please check your settings and try again.');
            })
            .finally(() => {
                // Restore button state
                button.innerHTML = originalText;
                button.disabled = false;
            });
        }
    }

    function isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }
</script>
@endsection
