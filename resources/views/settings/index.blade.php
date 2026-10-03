@extends('layouts.app')

@section('title', 'Settings')
@section('page-title', 'Settings')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-purple-600">Dashboard</a>
    <span class="mx-2">/</span>
    <span class="text-gray-700">Settings</span>
@endsection

@section('content')
<div class="space-y-6">
    <!-- Page Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Society Settings</h1>
            <p class="text-gray-600 mt-1">Configure your society's settings and preferences</p>
        </div>
        <form action="{{ route('settings.clear-cache') }}" method="POST">
            @csrf
            <button type="submit" class="btn-secondary px-4 py-2 rounded-lg text-sm">
                <i class="fas fa-sync-alt mr-2"></i>Clear Cache
            </button>
        </form>
    </div>

    <!-- Success/Error Messages -->


    @php
        // Consolidated into 6 sections (was 12 flat tabs, forcing horizontal
        // scrolling to find anything). Every `activeTab` key below is
        // unchanged from before - each one still drives the exact same
        // `x-show="activeTab === '...'"` content block, form and POST route
        // further down this file. This is a navigation-only change; no
        // content, route or controller was touched to make this grouping
        // work.
        //
        // 'push' is deliberately not listed in any section: SettingsController
        // ::updatePush() writes to SystemSetting's 'push' group, which has no
        // reader anywhere in the app (confirmed by search) - the real push
        // infrastructure (PushNotificationService, the PushNotificationSetting
        // model, PushSubscriptionController) is configured on its own admin
        // page and is untouched. The route/content still exist (nothing was
        // deleted), just with no menu entry pointing at them.
        $sections = [
            'general' => [
                'icon' => 'fa-cog',
                'label' => 'General',
                'tabs' => [
                    'society' => ['icon' => 'fa-building', 'label' => 'Society Info'],
                    'app' => ['icon' => 'fa-cog', 'label' => 'App Settings'],
                    'currency-features' => ['icon' => 'fa-coins', 'label' => 'Currency & Features'],
                ],
            ],
            'appearance' => [
                'icon' => 'fa-palette',
                'label' => 'Appearance',
                'tabs' => [
                    'theme' => ['icon' => 'fa-palette', 'label' => 'Theme'],
                ],
            ],
            'modules' => [
                'icon' => 'fa-th-large',
                'label' => 'Modules & Access',
                'tabs' => [
                    'complaint-categories' => ['icon' => 'fa-list', 'label' => 'Complaint Categories'],
                    'permissions' => ['icon' => 'fa-user-shield', 'label' => 'Role & Module Permissions'],
                ],
            ],
            'communication' => [
                'icon' => 'fa-envelope',
                'label' => 'Communication',
                'tabs' => [
                    'email' => ['icon' => 'fa-envelope', 'label' => 'Email Settings'],
                    'email-templates' => ['icon' => 'fa-envelope-open-text', 'label' => 'Email Templates'],
                    'sms' => ['icon' => 'fa-sms', 'label' => 'SMS / OTP'],
                ],
            ],
            'payments' => [
                'icon' => 'fa-credit-card',
                'label' => 'Payments',
                'tabs' => [
                    'payment' => ['icon' => 'fa-credit-card', 'label' => 'Payment Gateway'],
                ],
            ],
            'security' => [
                'icon' => 'fa-shield-alt',
                'label' => 'Security',
                'tabs' => [
                    'security' => ['icon' => 'fa-shield-alt', 'label' => 'Security'],
                ],
            ],
        ];

        // Which section owns the tab the controller resolved from ?tab=...,
        // so a bookmarked/shared link still opens on the right section.
        $initialSection = 'general';
        foreach ($sections as $sectionKey => $sectionInfo) {
            if (array_key_exists($tab, $sectionInfo['tabs'])) {
                $initialSection = $sectionKey;
                break;
            }
        }
    @endphp

    <!-- Settings Navigation -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200"
        x-data="{ activeTab: '{{ $tab }}', activeSection: '{{ $initialSection }}' }">
        <div class="border-b border-gray-200 overflow-x-auto">
            <nav class="flex -mb-px min-w-max" aria-label="Settings sections">
                @foreach($sections as $sectionKey => $sectionInfo)
                    <button type="button"
                        @click="activeSection = '{{ $sectionKey }}'; activeTab = '{{ array_key_first($sectionInfo['tabs']) }}'"
                        :class="activeSection === '{{ $sectionKey }}' ? 'border-purple-500 text-purple-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                        class="whitespace-nowrap py-4 px-6 border-b-2 font-medium text-sm transition-colors">
                        <i class="fas {{ $sectionInfo['icon'] }} mr-2"></i>{{ $sectionInfo['label'] }}
                    </button>
                @endforeach
            </nav>
        </div>

        <!-- Sub-navigation: only sections with more than one sub-tab need a second row -->
        @foreach($sections as $sectionKey => $sectionInfo)
            @if(count($sectionInfo['tabs']) > 1)
                <div x-show="activeSection === '{{ $sectionKey }}'" x-cloak
                    class="border-b border-gray-100 bg-gray-50 overflow-x-auto">
                    <nav class="flex min-w-max px-3" aria-label="{{ $sectionInfo['label'] }} sub-sections">
                        @foreach($sectionInfo['tabs'] as $tabKey => $tabInfo)
                            <button type="button" @click="activeTab = '{{ $tabKey }}'"
                                :class="activeTab === '{{ $tabKey }}' ? 'text-purple-600 font-semibold' : 'text-gray-500 hover:text-gray-700'"
                                class="whitespace-nowrap py-2.5 px-4 text-xs transition-colors">
                                <i class="fas {{ $tabInfo['icon'] }} mr-1.5"></i>{{ $tabInfo['label'] }}
                            </button>
                        @endforeach
                    </nav>
                </div>
            @endif
        @endforeach

        <!-- Tab Content -->
        <div class="p-6">
            <!-- Society Info Tab -->
            <div x-show="activeTab === 'society'" x-cloak>
                <form action="{{ route('settings.society.update') }}" method="POST">
                    @csrf
                    <div class="space-y-6">
                        <div class="flex items-center space-x-3 pb-4 border-b border-gray-200">
                            <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-building text-purple-600"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">Society Information</h3>
                                <p class="text-sm text-gray-500">Update your society's basic information</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Society Name</label>
                                <input type="text" name="society_name" value="{{ auth()->user()->society->name ?? '' }}"
                                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Phone Number</label>
                                <input type="text" name="society_phone" value="{{ auth()->user()->society->phone ?? '' }}"
                                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Address *</label>
                                <textarea name="society_address" rows="3" required
                                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">{{ auth()->user()->society->address ?? '' }}</textarea>
                                @error('society_address')
                                    <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                                <input type="email" name="society_email" value="{{ auth()->user()->society->email ?? '' }}"
                                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Country</label>
                                <select name="society_country" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                                    <option value="IN" {{ (auth()->user()->society->country ?? 'IN') == 'IN' ? 'selected' : '' }}>India</option>
                                    <option value="US" {{ (auth()->user()->society->country ?? '') == 'US' ? 'selected' : '' }}>United States</option>
                                    <option value="GB" {{ (auth()->user()->society->country ?? '') == 'GB' ? 'selected' : '' }}>United Kingdom</option>
                                    <option value="CA" {{ (auth()->user()->society->country ?? '') == 'CA' ? 'selected' : '' }}>Canada</option>
                                    <option value="AU" {{ (auth()->user()->society->country ?? '') == 'AU' ? 'selected' : '' }}>Australia</option>
                                </select>
                            </div>
                        </div>

                        <div class="flex justify-end pt-4 border-t border-gray-200">
                            <button type="submit" class="px-6 py-2.5 bg-purple-600 hover:bg-purple-700 text-white font-medium rounded-lg transition-colors">
                                <i class="fas fa-save mr-2"></i>Save Changes
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- App Settings Tab -->
            <div x-show="activeTab === 'app'" x-cloak>
                <form action="{{ route('settings.app.update') }}" method="POST">
                    @csrf
                    <div class="space-y-6">
                        <div class="flex items-center space-x-3 pb-4 border-b border-gray-200">
                            <div class="w-10 h-10 bg-indigo-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-cog text-indigo-600"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">Application Settings</h3>
                                <p class="text-sm text-gray-500">Configure application preferences for your society</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Display Name</label>
                                <input type="text" name="app_name"
                                    value="{{ $settings['app']['app_name'] ?? auth()->user()->society->name ?? '' }}"
                                    maxlength="255"
                                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                                <p class="text-xs text-gray-500 mt-1">Shown in the sidebar and page header - separate from the official Society Name on the General tab.</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Default Language</label>
                                <select name="default_language" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                                    <option value="en" {{ ($settings['app']['default_language'] ?? 'en') == 'en' ? 'selected' : '' }}>English</option>
                                    <option value="hi" {{ ($settings['app']['default_language'] ?? '') == 'hi' ? 'selected' : '' }}>Hindi</option>
                                    <option value="es" {{ ($settings['app']['default_language'] ?? '') == 'es' ? 'selected' : '' }}>Spanish</option>
                                    <option value="fr" {{ ($settings['app']['default_language'] ?? '') == 'fr' ? 'selected' : '' }}>French</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Default Currency</label>
                                <select name="default_currency" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                                    <option value="INR" {{ ($settings['app']['default_currency'] ?? 'INR') == 'INR' ? 'selected' : '' }}>Indian Rupee (₹)</option>
                                    <option value="USD" {{ ($settings['app']['default_currency'] ?? '') == 'USD' ? 'selected' : '' }}>US Dollar ($)</option>
                                    <option value="EUR" {{ ($settings['app']['default_currency'] ?? '') == 'EUR' ? 'selected' : '' }}>Euro (€)</option>
                                    <option value="GBP" {{ ($settings['app']['default_currency'] ?? '') == 'GBP' ? 'selected' : '' }}>British Pound (£)</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Time Zone</label>
                                <select name="timezone" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                                    <option value="Asia/Kolkata" {{ ($settings['app']['timezone'] ?? 'Asia/Kolkata') == 'Asia/Kolkata' ? 'selected' : '' }}>Asia/Kolkata (IST)</option>
                                    <option value="America/New_York" {{ ($settings['app']['timezone'] ?? '') == 'America/New_York' ? 'selected' : '' }}>America/New_York (EST)</option>
                                    <option value="Europe/London" {{ ($settings['app']['timezone'] ?? '') == 'Europe/London' ? 'selected' : '' }}>Europe/London (GMT)</option>
                                    <option value="Australia/Sydney" {{ ($settings['app']['timezone'] ?? '') == 'Australia/Sydney' ? 'selected' : '' }}>Australia/Sydney (AEST)</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Date Format</label>
                                <select name="date_format" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                                    <option value="d/m/Y" {{ ($settings['app']['date_format'] ?? 'd/m/Y') == 'd/m/Y' ? 'selected' : '' }}>DD/MM/YYYY</option>
                                    <option value="m/d/Y" {{ ($settings['app']['date_format'] ?? '') == 'm/d/Y' ? 'selected' : '' }}>MM/DD/YYYY</option>
                                    <option value="Y-m-d" {{ ($settings['app']['date_format'] ?? '') == 'Y-m-d' ? 'selected' : '' }}>YYYY-MM-DD</option>
                                </select>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <h4 class="text-sm font-medium text-gray-900">Notification Settings</h4>
                            <div class="space-y-3">
                                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                    <span class="text-sm text-gray-700">Enable Email Notifications</span>
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox" name="enable_email_notifications" value="1" {{ ($settings['app']['enable_email_notifications'] ?? true) ? 'checked' : '' }} class="sr-only peer">
                                        <div class="w-11 h-6 bg-gray-200 peer-focus:ring-4 peer-focus:ring-purple-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-purple-600"></div>
                                    </label>
                                </div>
                                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                    <span class="text-sm text-gray-700">Auto-approve new residents</span>
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox" name="auto_approve_residents" value="1" {{ ($settings['app']['auto_approve_residents'] ?? false) ? 'checked' : '' }} class="sr-only peer">
                                        <div class="w-11 h-6 bg-gray-200 peer-focus:ring-4 peer-focus:ring-purple-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-purple-600"></div>
                                    </label>
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

            <!-- Currency & Features Tab -->
            <div x-show="activeTab === 'currency-features'" x-cloak>
                <form action="{{ route('settings.currency-features.update') }}" method="POST">
                    @csrf
                    <div class="space-y-6">
                        <div class="flex items-center space-x-3 pb-4 border-b border-gray-200">
                            <div class="w-10 h-10 bg-yellow-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-coins text-yellow-600"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">Currency & Features</h3>
                                <p class="text-sm text-gray-500">Configure currency settings and enable/disable features for your society</p>
                            </div>
                        </div>

                        <!-- Currency Settings -->
                        <div class="border-t border-gray-200 pt-6">
                            <h4 class="text-md font-semibold text-gray-900 mb-4">Currency Settings</h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Currency Code</label>
                                    <select name="currency" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                                        <option value="INR" {{ ($settings['currency']['currency'] ?? 'INR') == 'INR' ? 'selected' : '' }}>Indian Rupee (INR)</option>
                                        <option value="USD" {{ ($settings['currency']['currency'] ?? '') == 'USD' ? 'selected' : '' }}>US Dollar (USD)</option>
                                        <option value="EUR" {{ ($settings['currency']['currency'] ?? '') == 'EUR' ? 'selected' : '' }}>Euro (EUR)</option>
                                        <option value="GBP" {{ ($settings['currency']['currency'] ?? '') == 'GBP' ? 'selected' : '' }}>British Pound (GBP)</option>
                                        <option value="AED" {{ ($settings['currency']['currency'] ?? '') == 'AED' ? 'selected' : '' }}>UAE Dirham (AED)</option>
                                        <option value="SAR" {{ ($settings['currency']['currency'] ?? '') == 'SAR' ? 'selected' : '' }}>Saudi Riyal (SAR)</option>
                                        <option value="AUD" {{ ($settings['currency']['currency'] ?? '') == 'AUD' ? 'selected' : '' }}>Australian Dollar (AUD)</option>
                                        <option value="CAD" {{ ($settings['currency']['currency'] ?? '') == 'CAD' ? 'selected' : '' }}>Canadian Dollar (CAD)</option>
                                        <option value="JPY" {{ ($settings['currency']['currency'] ?? '') == 'JPY' ? 'selected' : '' }}>Japanese Yen (JPY)</option>
                                        <option value="CNY" {{ ($settings['currency']['currency'] ?? '') == 'CNY' ? 'selected' : '' }}>Chinese Yuan (CNY)</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Currency Symbol</label>
                                    <input type="text" name="currency_symbol" value="{{ $settings['currency']['currency_symbol'] ?? '₹' }}" maxlength="5"
                                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Symbol Position</label>
                                    <select name="currency_position" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                                        <option value="left" {{ ($settings['currency']['currency_position'] ?? 'left') == 'left' ? 'selected' : '' }}>Left (₹ 1000)</option>
                                        <option value="right" {{ ($settings['currency']['currency_position'] ?? '') == 'right' ? 'selected' : '' }}>Right (1000 ₹)</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Decimal Places</label>
                                    <input type="number" name="decimal_places" value="{{ $settings['currency']['decimal_places'] ?? 2 }}" min="0" max="4"
                                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Thousand Separator</label>
                                    <input type="text" name="thousand_separator" value="{{ $settings['currency']['thousand_separator'] ?? ',' }}" maxlength="1"
                                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Decimal Separator</label>
                                    <input type="text" name="decimal_separator" value="{{ $settings['currency']['decimal_separator'] ?? '.' }}" maxlength="1"
                                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                                </div>
                            </div>
                            <div class="mt-4 p-4 bg-blue-50 rounded-lg">
                                <p class="text-sm text-blue-800">
                                    <i class="fas fa-info-circle mr-2"></i>
                                    <strong>Preview:</strong> 
                                    <span id="currency-preview">₹ 1,000.00</span>
                                </p>
                            </div>
                        </div>

                        <!-- Feature Toggles -->
                        <div class="border-t border-gray-200 pt-6">
                            <h4 class="text-md font-semibold text-gray-900 mb-4">Feature Management</h4>
                            <p class="text-sm text-gray-600 mb-4">Enable or disable features for your society</p>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <!-- Core Features -->
                                <div class="space-y-3">
                                    <h5 class="text-sm font-medium text-gray-900 mb-3">Core Features</h5>
                                    
                                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                        <div class="flex items-center space-x-2">
                                            <i class="fas fa-swimming-pool text-blue-600"></i>
                                            <span class="text-sm text-gray-700">Facilities Management</span>
                                        </div>
                                        <label class="relative inline-flex items-center cursor-pointer">
                                            <input type="checkbox" name="enable_facilities" value="1" {{ ($settings['features']['enable_facilities'] ?? true) ? 'checked' : '' }} class="sr-only peer">
                                            <div class="w-11 h-6 bg-gray-200 peer-focus:ring-4 peer-focus:ring-purple-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-purple-600"></div>
                                        </label>
                                    </div>

                                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                        <div class="flex items-center space-x-2">
                                            <i class="fas fa-users text-green-600"></i>
                                            <span class="text-sm text-gray-700">Visitor Management</span>
                                        </div>
                                        <label class="relative inline-flex items-center cursor-pointer">
                                            <input type="checkbox" name="enable_visitors" value="1" {{ ($settings['features']['enable_visitors'] ?? true) ? 'checked' : '' }} class="sr-only peer">
                                            <div class="w-11 h-6 bg-gray-200 peer-focus:ring-4 peer-focus:ring-purple-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-purple-600"></div>
                                        </label>
                                    </div>

                                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                        <div class="flex items-center space-x-2">
                                            <i class="fas fa-exclamation-triangle text-red-600"></i>
                                            <span class="text-sm text-gray-700">Complaints & Tickets</span>
                                        </div>
                                        <label class="relative inline-flex items-center cursor-pointer">
                                            <input type="checkbox" name="enable_complaints" value="1" {{ ($settings['features']['enable_complaints'] ?? true) ? 'checked' : '' }} class="sr-only peer">
                                            <div class="w-11 h-6 bg-gray-200 peer-focus:ring-4 peer-focus:ring-purple-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-purple-600"></div>
                                        </label>
                                    </div>

                                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                        <div class="flex items-center space-x-2">
                                            <i class="fas fa-file-invoice-dollar text-yellow-600"></i>
                                            <span class="text-sm text-gray-700">Bills & Payments</span>
                                        </div>
                                        <label class="relative inline-flex items-center cursor-pointer">
                                            <input type="checkbox" name="enable_bills" value="1" {{ ($settings['features']['enable_bills'] ?? true) ? 'checked' : '' }} class="sr-only peer">
                                            <div class="w-11 h-6 bg-gray-200 peer-focus:ring-4 peer-focus:ring-purple-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-purple-600"></div>
                                        </label>
                                    </div>
                                </div>

                                <!-- Additional Features -->
                                <div class="space-y-3">
                                    <h5 class="text-sm font-medium text-gray-900 mb-3">Additional Features</h5>
                                    
                                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                        <div class="flex items-center space-x-2">
                                            <i class="fas fa-tools text-orange-600"></i>
                                            <span class="text-sm text-gray-700">Service Providers</span>
                                        </div>
                                        <label class="relative inline-flex items-center cursor-pointer">
                                            <input type="checkbox" name="enable_services" value="1" {{ ($settings['features']['enable_services'] ?? true) ? 'checked' : '' }} class="sr-only peer">
                                            <div class="w-11 h-6 bg-gray-200 peer-focus:ring-4 peer-focus:ring-purple-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-purple-600"></div>
                                        </label>
                                    </div>

                                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                        <div class="flex items-center space-x-2">
                                            <i class="fas fa-bullhorn text-purple-600"></i>
                                            <span class="text-sm text-gray-700">Notice Board</span>
                                        </div>
                                        <label class="relative inline-flex items-center cursor-pointer">
                                            <input type="checkbox" name="enable_notices" value="1" {{ ($settings['features']['enable_notices'] ?? true) ? 'checked' : '' }} class="sr-only peer">
                                            <div class="w-11 h-6 bg-gray-200 peer-focus:ring-4 peer-focus:ring-purple-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-purple-600"></div>
                                        </label>
                                    </div>

                                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                        <div class="flex items-center space-x-2">
                                            <i class="fas fa-parking text-indigo-600"></i>
                                            <span class="text-sm text-gray-700">Parking Management</span>
                                        </div>
                                        <label class="relative inline-flex items-center cursor-pointer">
                                            <input type="checkbox" name="enable_parking" value="1" {{ ($settings['features']['enable_parking'] ?? true) ? 'checked' : '' }} class="sr-only peer">
                                            <div class="w-11 h-6 bg-gray-200 peer-focus:ring-4 peer-focus:ring-purple-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-purple-600"></div>
                                        </label>
                                    </div>

                                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                        <div class="flex items-center space-x-2">
                                            <i class="fas fa-chart-bar text-teal-600"></i>
                                            <span class="text-sm text-gray-700">Reports & Analytics</span>
                                        </div>
                                        <label class="relative inline-flex items-center cursor-pointer">
                                            <input type="checkbox" name="enable_reports" value="1" {{ ($settings['features']['enable_reports'] ?? true) ? 'checked' : '' }} class="sr-only peer">
                                            <div class="w-11 h-6 bg-gray-200 peer-focus:ring-4 peer-focus:ring-purple-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-purple-600"></div>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- Module Features -->
                            <div class="mt-6 pt-6 border-t border-gray-200">
                                <h5 class="text-sm font-medium text-gray-900 mb-3">Module Features</h5>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                        <div class="flex items-center space-x-2">
                                            <i class="fas fa-home text-cyan-600"></i>
                                            <span class="text-sm text-gray-700">Tenant Module</span>
                                        </div>
                                        <label class="relative inline-flex items-center cursor-pointer">
                                            <input type="checkbox" name="enable_tenant_module" value="1" {{ ($settings['features']['enable_tenant_module'] ?? true) ? 'checked' : '' }} class="sr-only peer">
                                            <div class="w-11 h-6 bg-gray-200 peer-focus:ring-4 peer-focus:ring-purple-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-purple-600"></div>
                                        </label>
                                    </div>

                                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                        <div class="flex items-center space-x-2">
                                            <i class="fas fa-building text-rose-600"></i>
                                            <span class="text-sm text-gray-700">Villa Module</span>
                                        </div>
                                        <label class="relative inline-flex items-center cursor-pointer">
                                            <input type="checkbox" name="enable_villa_module" value="1" {{ ($settings['features']['enable_villa_module'] ?? true) ? 'checked' : '' }} class="sr-only peer">
                                            <div class="w-11 h-6 bg-gray-200 peer-focus:ring-4 peer-focus:ring-purple-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-purple-600"></div>
                                        </label>
                                    </div>

                                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                        <div class="flex items-center space-x-2">
                                            <i class="fas fa-calendar-alt text-purple-600"></i>
                                            <span class="text-sm text-gray-700">Events Module</span>
                                        </div>
                                        <label class="relative inline-flex items-center cursor-pointer">
                                            <input type="checkbox" name="enable_events" value="1" {{ ($settings['features']['enable_events'] ?? false) ? 'checked' : '' }} class="sr-only peer">
                                            <div class="w-11 h-6 bg-gray-200 peer-focus:ring-4 peer-focus:ring-purple-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-purple-600"></div>
                                        </label>
                                    </div>
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

            <!-- Role Permissions Tab -->
            <div x-show="activeTab === 'permissions'" x-cloak>
                <form action="{{ route('settings.permissions.update') }}" method="POST">
                    @csrf
                    <div class="space-y-6">
                        <div class="flex items-center space-x-3 pb-4 border-b border-gray-200">
                            <div class="w-10 h-10 bg-indigo-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-user-shield text-indigo-600"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">Role & Module Permissions</h3>
                                <p class="text-sm text-gray-500">Manage access permissions for different roles and modules based on your subscription plan</p>
                            </div>
                        </div>

                        <div class="bg-blue-50 border border-blue-100 rounded-lg px-4 py-2.5 text-xs text-blue-800">
                            <i class="fas fa-info-circle mr-1"></i>
                            Access is also protected by each role's built-in authorization rules - these toggles
                            record your intended configuration for the modules that already read it.
                        </div>

                        @php
                            $currentPlan = auth()->user()->society->subscription->subscriptionPlan ?? null;
                            $availableModules = [
                                'facilities' => [
                                    'name' => 'Facilities Management',
                                    'description' => 'Manage and book society facilities',
                                    'icon' => 'fa-swimming-pool',
                                ],
                                'visitors' => [
                                    'name' => 'Visitor Management',
                                    'description' => 'Track and manage visitor entries',
                                    'icon' => 'fa-users',
                                ],
                                'complaints' => [
                                    'name' => 'Complaints & Tickets',
                                    'description' => 'Handle resident complaints and support tickets',
                                    'icon' => 'fa-exclamation-triangle',
                                ],
                                'bills' => [
                                    'name' => 'Bills & Payments',
                                    'description' => 'Manage society bills and payment tracking',
                                    'icon' => 'fa-file-invoice-dollar',
                                ],
                                'services' => [
                                    'name' => 'Service Providers',
                                    'description' => 'Manage service providers and attendance',
                                    'icon' => 'fa-tools',
                                ],
                                'notices' => [
                                    'name' => 'Notice Board',
                                    'description' => 'Society announcements and notices',
                                    'icon' => 'fa-bullhorn',
                                ],
                                'reports' => [
                                    'name' => 'Reports & Analytics',
                                    'description' => 'Generate reports and view analytics',
                                    'icon' => 'fa-chart-bar',
                                ],
                                'advanced_settings' => [
                                    'name' => 'Advanced Settings',
                                    'description' => 'Advanced configuration options',
                                    'icon' => 'fa-cogs',
                                ],
                            ];

                            // The canonical business roles, other than Super Admin
                            // (system-level, not controlled by society module toggles -
                            // see AuthServiceProvider's Gate::before). Villa Owner/
                            // Apartment Owner are legacy names for 'Owner' (property
                            // type is a flat attribute, not a role) and are
                            // deliberately not shown here - see
                            // php artisan users:migrate-to-owner-role.
                            $roles = ['Admin', 'Owner', 'Tenant', 'Staff', 'Accountant', 'Guard'];

                            // SettingsController::updatePermissions() saves under the
                            // single 'module_permissions' key within the 'permissions'
                            // group (SystemSetting::set('permissions',
                            // 'module_permissions', ...)), but SystemSetting::getGroup()
                            // returns a group keyed by its setting keys - so the real
                            // value lives one level deeper. Reading this correctly is
                            // what makes a saved toggle survive a refresh.
                            $currentPermissions = $settings['permissions']['module_permissions'] ?? [];
                        @endphp

                        <!-- Current Plan Info -->
                        <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                            <div class="flex items-center">
                                <i class="fas fa-check-circle text-green-600 mr-3"></i>
                                <div>
                                    <h4 class="text-sm font-medium text-green-900">Free Forever Plan</h4>
                                    <p class="text-sm text-green-700">
                                        {{ $currentPlan ? $currentPlan->name : 'Free Forever Plan' }} -
                                        All modules and features are available for free! Configure permissions as needed for your society.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Module Permissions Grid -->
                        <div class="space-y-6">
                            @foreach($availableModules as $moduleKey => $module)
                                <div class="border border-gray-200 rounded-lg">
                                    <div class="p-4 border-b border-gray-200">
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center space-x-3">
                                                <div class="w-8 h-8 bg-purple-100 rounded-lg flex items-center justify-center">
                                                    <i class="fas {{ $module['icon'] }} text-purple-600 text-sm"></i>
                                                </div>
                                                <div>
                                                    <h4 class="text-sm font-medium text-gray-900">{{ $module['name'] }}</h4>
                                                    <p class="text-xs text-gray-500">{{ $module['description'] }}</p>
                                                </div>
                                            </div>
                                            <span class="px-2 py-1 bg-green-100 text-green-800 text-xs rounded-full">
                                                Available
                                            </span>
                                        </div>
                                    </div>

                                    <div class="p-4">
                                        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                                            @foreach($roles as $role)
                                                <div class="text-center">
                                                    <h5 class="text-xs font-medium text-gray-700 mb-2">{{ $role }}</h5>
                                                    <label class="relative inline-flex items-center cursor-pointer">
                                                        <input type="checkbox"
                                                            name="permissions[{{ $moduleKey }}][{{ $role }}]"
                                                            value="1"
                                                            {{ ($currentPermissions[$moduleKey][$role] ?? false) ? 'checked' : '' }}
                                                            class="sr-only peer">
                                                        <div class="w-9 h-5 bg-gray-200 peer-focus:ring-4 peer-focus:ring-purple-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-purple-600"></div>
                                                    </label>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <!-- Bulk Actions -->
                        <div class="bg-gray-50 rounded-lg p-4">
                            <h4 class="text-sm font-medium text-gray-900 mb-3">Quick Actions</h4>
                            <div class="flex flex-wrap gap-2">
                                <button type="button" onclick="toggleAllPermissions(true)"
                                    class="px-3 py-1.5 bg-green-100 hover:bg-green-200 text-green-800 text-xs rounded-lg transition-colors">
                                    <i class="fas fa-check mr-1"></i>Enable All
                                </button>
                                <button type="button" onclick="toggleAllPermissions(false)"
                                    class="px-3 py-1.5 bg-red-100 hover:bg-red-200 text-red-800 text-xs rounded-lg transition-colors">
                                    <i class="fas fa-times mr-1"></i>Disable All
                                </button>
                                <button type="button" onclick="resetToDefaults()"
                                    class="px-3 py-1.5 bg-blue-100 hover:bg-blue-200 text-blue-800 text-xs rounded-lg transition-colors">
                                    <i class="fas fa-undo mr-1"></i>Reset to Defaults
                                </button>
                            </div>
                        </div>

                        <div class="flex justify-end pt-4 border-t border-gray-200">
                            <button type="submit" class="px-6 py-2.5 bg-purple-600 hover:bg-purple-700 text-white font-medium rounded-lg transition-colors">
                                <i class="fas fa-save mr-2"></i>Save Permissions
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Complaint Categories Tab -->
            @include('settings.partials.complaint-categories')

            <!-- Theme Settings Tab -->
            <div x-show="activeTab === 'theme'" x-cloak>
                <form action="{{ route('settings.theme.update') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="space-y-6">
                        <div class="flex items-center space-x-3 pb-4 border-b border-gray-200">
                            <div class="w-10 h-10 bg-pink-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-palette text-pink-600"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">Theme Settings</h3>
                                <p class="text-sm text-gray-500">Customize the appearance of your society portal</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Primary Color</label>
                                <div class="flex items-center space-x-3">
                                    <input type="color" name="primary_color" value="{{ $settings['theme']['primary_color'] ?? '#7c3aed' }}"
                                        class="w-12 h-10 border border-gray-300 rounded-lg cursor-pointer">
                                    <input type="text" name="primary_color_hex" value="{{ $settings['theme']['primary_color'] ?? '#7c3aed' }}"
                                        class="flex-1 px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Secondary Color</label>
                                <div class="flex items-center space-x-3">
                                    <input type="color" name="secondary_color" value="{{ $settings['theme']['secondary_color'] ?? '#64748b' }}"
                                        class="w-12 h-10 border border-gray-300 rounded-lg cursor-pointer">
                                    <input type="text" name="secondary_color_hex" value="{{ $settings['theme']['secondary_color'] ?? '#64748b' }}"
                                        class="flex-1 px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
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

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Society Logo</label>
                            <div class="flex items-center space-x-4">
                                <div class="w-16 h-16 bg-gray-100 rounded-lg flex items-center justify-center overflow-hidden">
                                    @if($settings['theme']['society_logo'] ?? null)
                                        <img src="{{ asset('storage/' . $settings['theme']['society_logo']) }}" 
                                             alt="Society Logo" 
                                             class="w-full h-full object-cover">
                                    @else
                                        <i class="fas fa-building text-2xl text-gray-400"></i>
                                    @endif
                                </div>
                                <div>
                                    <input type="file" name="society_logo" accept="image/*" class="hidden" id="logo-upload">
                                    <label for="logo-upload" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg cursor-pointer transition-colors">
                                        <i class="fas fa-upload mr-2"></i>{{ ($settings['theme']['society_logo'] ?? null) ? 'Change Logo' : 'Upload Logo' }}
                                    </label>
                                    <p class="text-xs text-gray-500 mt-1">PNG, JPG up to 2MB</p>
                                    @if($settings['theme']['society_logo'] ?? null)
                                        <p class="text-xs text-green-600 mt-1">
                                            <i class="fas fa-check mr-1"></i>Logo uploaded
                                        </p>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <!-- Favicon Upload -->
                        <div class="border-t border-gray-200 pt-6">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Favicon (Browser Tab Icon)</label>
                            <div class="flex items-center space-x-4">
                                <div class="w-12 h-12 bg-gray-100 rounded-lg flex items-center justify-center overflow-hidden border-2 border-gray-300">
                                    @if($settings['theme']['society_favicon'] ?? null)
                                        <img src="{{ asset('storage/' . $settings['theme']['society_favicon']) }}" 
                                             alt="Society Favicon" 
                                             class="w-full h-full object-cover">
                                    @else
                                        <i class="fas fa-star text-lg text-gray-400"></i>
                                    @endif
                                </div>
                                <div>
                                    <input type="file" name="society_favicon" accept="image/x-icon,image/png,image/jpeg" class="hidden" id="favicon-upload">
                                    <label for="favicon-upload" class="px-4 py-2 bg-blue-100 hover:bg-blue-200 text-blue-700 rounded-lg cursor-pointer transition-colors">
                                        <i class="fas fa-upload mr-2"></i>{{ ($settings['theme']['society_favicon'] ?? null) ? 'Change Favicon' : 'Upload Favicon' }}
                                    </label>
                                    <p class="text-xs text-gray-500 mt-1">ICO, PNG, JPG up to 1MB (Recommended: 32x32px)</p>
                                    @if($settings['theme']['society_favicon'] ?? null)
                                        <p class="text-xs text-green-600 mt-1">
                                            <i class="fas fa-check mr-1"></i>Favicon uploaded
                                        </p>
                                    @endif
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
            <!-- Email Settings Tab -->
            <div x-show="activeTab === 'email'" x-cloak>
                <form action="{{ route('settings.email.update') }}" method="POST">
                    @csrf
                    <div class="space-y-6">
                        <div class="flex items-center space-x-3 pb-4 border-b border-gray-200">
                            <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-envelope text-blue-600"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">Email Configuration</h3>
                                <p class="text-sm text-gray-500">Configure SMTP settings for your society</p>
                            </div>
                        </div>

                        <!-- Mail Configuration -->
                        <div>
                            <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Mail Configuration</h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Mail Driver</label>
                                    <select name="mail_driver" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                                        <option value="smtp" {{ ($settings['email']['mail_driver'] ?? 'smtp') == 'smtp' ? 'selected' : '' }}>SMTP</option>
                                        <option value="sendmail" {{ ($settings['email']['mail_driver'] ?? '') == 'sendmail' ? 'selected' : '' }}>Sendmail</option>
                                        <option value="mailgun" {{ ($settings['email']['mail_driver'] ?? '') == 'mailgun' ? 'selected' : '' }}>Mailgun</option>
                                        <option value="ses" {{ ($settings['email']['mail_driver'] ?? '') == 'ses' ? 'selected' : '' }}>Amazon SES</option>
                                        <option value="postmark" {{ ($settings['email']['mail_driver'] ?? '') == 'postmark' ? 'selected' : '' }}>Postmark</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">SMTP Host</label>
                                    <input type="text" name="mail_host" value="{{ $settings['email']['mail_host'] ?? '' }}"
                                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500"
                                        placeholder="smtp.gmail.com">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">SMTP Port</label>
                                    <input type="number" name="mail_port" value="{{ $settings['email']['mail_port'] ?? '587' }}"
                                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500"
                                        placeholder="587">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Encryption</label>
                                    <select name="mail_encryption" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                                        <option value="tls" {{ ($settings['email']['mail_encryption'] ?? 'tls') == 'tls' ? 'selected' : '' }}>TLS</option>
                                        <option value="ssl" {{ ($settings['email']['mail_encryption'] ?? '') == 'ssl' ? 'selected' : '' }}>SSL</option>
                                        <option value="null" {{ ($settings['email']['mail_encryption'] ?? '') == 'null' ? 'selected' : '' }}>None</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Username</label>
                                    <input type="text" name="mail_username" value="{{ $settings['email']['mail_username'] ?? '' }}"
                                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Password</label>
                                    <input type="password" name="mail_password" value="{{ $settings['email']['mail_password'] ?? '' }}"
                                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                                </div>
                            </div>
                        </div>

                        <!-- Sender Information -->
                        <div class="border-t border-gray-200 pt-6">
                            <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Sender Information</h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">From Email</label>
                                    <input type="email" name="mail_from_address" value="{{ $settings['email']['mail_from_address'] ?? '' }}"
                                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500"
                                        required>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">From Name</label>
                                    <input type="text" name="mail_from_name" value="{{ $settings['email']['mail_from_name'] ?? auth()->user()->society->name ?? 'Society' }}"
                                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500"
                                        required>
                                </div>
                            </div>
                        </div>

                        <!-- Testing -->
                        <div class="border-t border-gray-200 pt-6">
                            <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Testing</h4>
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-4">
                                    <input type="email" id="test_email" placeholder="test@example.com"
                                        aria-label="Test email address"
                                        class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                                    <button type="button" onclick="testEmail()" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm">
                                        <i class="fas fa-paper-plane mr-2"></i>Send Test Email
                                    </button>
                                </div>
                                <button type="submit" class="px-6 py-2.5 bg-purple-600 hover:bg-purple-700 text-white font-medium rounded-lg transition-colors">
                                    <i class="fas fa-save mr-2"></i>Save Changes
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Email Templates Tab -->
            <div x-show="activeTab === 'email-templates'" x-cloak>
                <div class="space-y-6">
                    <div class="flex items-center justify-between pb-4 border-b border-gray-200">
                        <div class="flex items-center space-x-3">
                            <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-envelope-open-text text-purple-600"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">Email Templates</h3>
                                <p class="text-sm text-gray-500">Manage and customize email templates for your society</p>
                            </div>
                        </div>
                        <div class="flex space-x-3">
                            <a href="{{ route('admin.email-templates.create') }}" class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg text-sm">
                                <i class="fas fa-plus mr-2"></i>Create Template
                            </a>
                            <form method="POST" action="{{ route('admin.email-templates.initialize') }}" class="inline">
                                @csrf
                                <button type="submit" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg text-sm">
                                    <i class="fas fa-magic mr-2"></i>Initialize Defaults
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Email Templates: compact searchable table (was a card grid -
                         same data, same routes, just less scrolling to find one template) -->
                    @php
                        $templates = \App\Models\EmailTemplate::when(!auth()->user()->hasRole('Super Admin'), function($query) {
                            return $query->forSociety(auth()->user()->society_id);
                        })->orderBy('name')->get();
                    @endphp

                    <div class="flex items-center gap-4 text-xs text-gray-500">
                        <span><i class="fas fa-envelope text-blue-500 mr-1"></i>{{ $templates->count() }} total</span>
                        <span><i class="fas fa-check-circle text-green-500 mr-1"></i>{{ $templates->where('status', 'active')->count() }} active</span>
                        <span><i class="fas fa-cogs text-purple-500 mr-1"></i>{{ $templates->where('type', 'system')->count() }} system</span>
                        <span><i class="fas fa-user-edit text-orange-500 mr-1"></i>{{ $templates->where('type', 'custom')->count() }} custom</span>
                    </div>

                    @if($templates->count() > 0)
                        <div x-data="{ search: '' }">
                            <div class="relative">
                                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                                <input type="text" x-model="search" placeholder="Search templates…"
                                    aria-label="Search email templates"
                                    class="w-full pl-8 pr-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-300 focus:border-purple-500 mb-3">
                            </div>

                            <div class="border border-gray-200 rounded-lg overflow-hidden">
                                <table class="w-full text-sm">
                                    <thead class="bg-gray-50">
                                        <tr class="text-left text-xs font-medium text-gray-500">
                                            <th class="px-4 py-2">Template</th>
                                            <th class="px-4 py-2 hidden md:table-cell">Subject</th>
                                            <th class="px-4 py-2">Status</th>
                                            <th class="px-4 py-2 hidden sm:table-cell">Type</th>
                                            <th class="px-4 py-2 text-right">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        @foreach($templates as $template)
                                            <tr x-show="search === '' || {{ \Illuminate\Support\Js::from(strtolower($template->name.' '.$template->subject)) }}.includes(search.toLowerCase())"
                                                class="hover:bg-gray-50">
                                                <td class="px-4 py-2.5 font-medium text-gray-800">{{ $template->name }}</td>
                                                <td class="px-4 py-2.5 text-gray-500 hidden md:table-cell">{{ Str::limit($template->subject, 50) }}</td>
                                                <td class="px-4 py-2.5">
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $template->status === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                                        {{ ucfirst($template->status) }}
                                                    </span>
                                                </td>
                                                <td class="px-4 py-2.5 hidden sm:table-cell">
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $template->type === 'system' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800' }}">
                                                        {{ ucfirst($template->type) }}
                                                    </span>
                                                </td>
                                                <td class="px-4 py-2.5 text-right whitespace-nowrap">
                                                    <a href="{{ route('admin.email-templates.show', $template) }}" class="text-blue-600 hover:text-blue-800 mr-3" aria-label="View {{ $template->name }}">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="{{ route('admin.email-templates.edit', $template) }}" class="text-purple-600 hover:text-purple-800" aria-label="Edit {{ $template->name }}">
                                                        <i class="fas fa-edit"></i> Edit
                                                    </a>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @else
                        <div class="text-center py-8 bg-gray-50 rounded-lg">
                            <i class="fas fa-envelope fa-3x text-gray-400 mb-4"></i>
                            <h3 class="text-lg font-medium text-gray-900 mb-2">No Email Templates Found</h3>
                            <p class="text-gray-500 mb-6">Get started by creating your first email template or initializing default templates.</p>
                            <div class="flex justify-center space-x-3">
                                <a href="{{ route('admin.email-templates.create') }}" class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg">
                                    <i class="fas fa-plus mr-2"></i>Create Template
                                </a>
                                <form method="POST" action="{{ route('admin.email-templates.initialize') }}" class="inline">
                                    @csrf
                                    <button type="submit" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg">
                                        <i class="fas fa-magic mr-2"></i>Initialize Defaults
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Payment Settings Tab -->
            <div x-show="activeTab === 'payment'" x-cloak>
                <form action="{{ route('settings.payment.update') }}" method="POST">
                    @csrf
                    <div class="space-y-6">
                        <div class="flex items-center space-x-3 pb-4 border-b border-gray-200">
                            <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-credit-card text-green-600"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">Payment Gateway Settings</h3>
                                <p class="text-sm text-gray-500">Configure payment gateways for your society</p>
                            </div>
                        </div>

                        <!-- Test Mode -->
                        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                            <div>
                                <h4 class="text-sm font-medium text-gray-900">Test Mode</h4>
                                <p class="text-sm text-gray-500">Enable test mode for development</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="payment_test_mode" value="1" {{ ($settings['payment']['payment_test_mode'] ?? false) ? 'checked' : '' }} class="sr-only peer">
                                <div class="w-11 h-6 bg-gray-200 peer-focus:ring-4 peer-focus:ring-purple-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-purple-600"></div>
                            </label>
                        </div>

                        <!-- Razorpay Settings -->
                        <div class="space-y-4">
                            <div class="flex items-center justify-between">
                                <h4 class="text-lg font-medium text-gray-900">Razorpay</h4>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="razorpay_enabled" value="1" {{ ($settings['payment']['razorpay_enabled'] ?? false) ? 'checked' : '' }} class="sr-only peer">
                                    <div class="w-11 h-6 bg-gray-200 peer-focus:ring-4 peer-focus:ring-purple-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-purple-600"></div>
                                </label>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Razorpay Key</label>
                                    <input type="text" name="razorpay_key" value="{{ $settings['payment']['razorpay_key'] ?? '' }}"
                                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Razorpay Secret</label>
                                    <input type="password" name="razorpay_secret" value="{{ $settings['payment']['razorpay_secret'] ?? '' }}"
                                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                                </div>
                            </div>
                        </div>

                        <!-- Stripe Settings -->
                        <div class="space-y-4 border-t pt-4">
                            <div class="flex items-center justify-between">
                                <h4 class="text-lg font-medium text-gray-900">Stripe</h4>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="stripe_enabled" value="1" {{ ($settings['payment']['stripe_enabled'] ?? false) ? 'checked' : '' }} class="sr-only peer">
                                    <div class="w-11 h-6 bg-gray-200 peer-focus:ring-4 peer-focus:ring-purple-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-purple-600"></div>
                                </label>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Publishable Key</label>
                                    <input type="text" name="stripe_key" value="{{ $settings['payment']['stripe_key'] ?? '' }}"
                                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Secret Key</label>
                                    <input type="password" name="stripe_secret" value="{{ $settings['payment']['stripe_secret'] ?? '' }}"
                                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                                </div>
                            </div>
                        </div>

                        <!-- PayPal Settings -->
                        <div class="space-y-4 border-t pt-4">
                            <div class="flex items-center justify-between">
                                <h4 class="text-lg font-medium text-gray-900">PayPal</h4>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="paypal_enabled" value="1" {{ ($settings['payment']['paypal_enabled'] ?? false) ? 'checked' : '' }} class="sr-only peer">
                                    <div class="w-11 h-6 bg-gray-200 peer-focus:ring-4 peer-focus:ring-purple-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-purple-600"></div>
                                </label>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Client ID</label>
                                    <input type="text" name="paypal_client_id" value="{{ $settings['payment']['paypal_client_id'] ?? '' }}"
                                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Client Secret</label>
                                    <input type="password" name="paypal_secret" value="{{ $settings['payment']['paypal_secret'] ?? '' }}"
                                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                                </div>
                            </div>
                        </div>

                        <!-- Square Settings -->
                        <div class="space-y-4 border-t pt-4">
                            <div class="flex items-center justify-between">
                                <h4 class="text-lg font-medium text-gray-900">Square</h4>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="square_enabled" value="1" {{ ($settings['payment']['square_enabled'] ?? false) ? 'checked' : '' }} class="sr-only peer">
                                    <div class="w-11 h-6 bg-gray-200 peer-focus:ring-4 peer-focus:ring-purple-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-purple-600"></div>
                                </label>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Application ID</label>
                                    <input type="text" name="square_app_id" value="{{ $settings['payment']['square_app_id'] ?? '' }}"
                                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Access Token</label>
                                    <input type="password" name="square_access_token" value="{{ $settings['payment']['square_access_token'] ?? '' }}"
                                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Location ID</label>
                                    <input type="text" name="square_location_id" value="{{ $settings['payment']['square_location_id'] ?? '' }}"
                                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Environment</label>
                                    <select name="square_environment" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                                        <option value="sandbox" {{ ($settings['payment']['square_environment'] ?? 'sandbox') == 'sandbox' ? 'selected' : '' }}>Sandbox</option>
                                        <option value="production" {{ ($settings['payment']['square_environment'] ?? '') == 'production' ? 'selected' : '' }}>Production</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Paytm Settings -->
                        <div class="space-y-4 border-t pt-4">
                            <div class="flex items-center justify-between">
                                <h4 class="text-lg font-medium text-gray-900">Paytm</h4>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="paytm_enabled" value="1" {{ ($settings['payment']['paytm_enabled'] ?? false) ? 'checked' : '' }} class="sr-only peer">
                                    <div class="w-11 h-6 bg-gray-200 peer-focus:ring-4 peer-focus:ring-purple-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-purple-600"></div>
                                </label>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Merchant ID</label>
                                    <input type="text" name="paytm_merchant_id" value="{{ $settings['payment']['paytm_merchant_id'] ?? '' }}"
                                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Merchant Key</label>
                                    <input type="password" name="paytm_merchant_key" value="{{ $settings['payment']['paytm_merchant_key'] ?? '' }}"
                                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Website</label>
                                    <input type="text" name="paytm_website" value="{{ $settings['payment']['paytm_website'] ?? 'WEBSTAGING' }}"
                                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Industry Type</label>
                                    <input type="text" name="paytm_industry_type" value="{{ $settings['payment']['paytm_industry_type'] ?? 'Retail' }}"
                                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                                </div>
                            </div>
                        </div>

                        <!-- PhonePe Settings -->
                        <div class="space-y-4 border-t pt-4">
                            <div class="flex items-center justify-between">
                                <h4 class="text-lg font-medium text-gray-900">PhonePe</h4>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="phonepe_enabled" value="1" {{ ($settings['payment']['phonepe_enabled'] ?? false) ? 'checked' : '' }} class="sr-only peer">
                                    <div class="w-11 h-6 bg-gray-200 peer-focus:ring-4 peer-focus:ring-purple-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-purple-600"></div>
                                </label>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Merchant ID</label>
                                    <input type="text" name="phonepe_merchant_id" value="{{ $settings['payment']['phonepe_merchant_id'] ?? '' }}"
                                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Salt Key</label>
                                    <input type="password" name="phonepe_salt_key" value="{{ $settings['payment']['phonepe_salt_key'] ?? '' }}"
                                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Salt Index</label>
                                    <input type="text" name="phonepe_salt_index" value="{{ $settings['payment']['phonepe_salt_index'] ?? '1' }}"
                                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Environment</label>
                                    <select name="phonepe_environment" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                                        <option value="sandbox" {{ ($settings['payment']['phonepe_environment'] ?? 'sandbox') == 'sandbox' ? 'selected' : '' }}>Sandbox</option>
                                        <option value="production" {{ ($settings['payment']['phonepe_environment'] ?? '') == 'production' ? 'selected' : '' }}>Production</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Cashfree Settings -->
                        <div class="space-y-4 border-t pt-4">
                            <div class="flex items-center justify-between">
                                <h4 class="text-lg font-medium text-gray-900">Cashfree</h4>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="cashfree_enabled" value="1" {{ ($settings['payment']['cashfree_enabled'] ?? false) ? 'checked' : '' }} class="sr-only peer">
                                    <div class="w-11 h-6 bg-gray-200 peer-focus:ring-4 peer-focus:ring-purple-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-purple-600"></div>
                                </label>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">App ID</label>
                                    <input type="text" name="cashfree_app_id" value="{{ $settings['payment']['cashfree_app_id'] ?? '' }}"
                                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Secret Key</label>
                                    <input type="password" name="cashfree_secret_key" value="{{ $settings['payment']['cashfree_secret_key'] ?? '' }}"
                                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Environment</label>
                                    <select name="cashfree_environment" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                                        <option value="sandbox" {{ ($settings['payment']['cashfree_environment'] ?? 'sandbox') == 'sandbox' ? 'selected' : '' }}>Sandbox</option>
                                        <option value="production" {{ ($settings['payment']['cashfree_environment'] ?? '') == 'production' ? 'selected' : '' }}>Production</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Instamojo Settings -->
                        <div class="space-y-4 border-t pt-4">
                            <div class="flex items-center justify-between">
                                <h4 class="text-lg font-medium text-gray-900">Instamojo</h4>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="instamojo_enabled" value="1" {{ ($settings['payment']['instamojo_enabled'] ?? false) ? 'checked' : '' }} class="sr-only peer">
                                    <div class="w-11 h-6 bg-gray-200 peer-focus:ring-4 peer-focus:ring-purple-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-purple-600"></div>
                                </label>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">API Key</label>
                                    <input type="text" name="instamojo_api_key" value="{{ $settings['payment']['instamojo_api_key'] ?? '' }}"
                                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Auth Token</label>
                                    <input type="password" name="instamojo_auth_token" value="{{ $settings['payment']['instamojo_auth_token'] ?? '' }}"
                                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Environment</label>
                                    <select name="instamojo_environment" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                                        <option value="test" {{ ($settings['payment']['instamojo_environment'] ?? 'test') == 'test' ? 'selected' : '' }}>Test</option>
                                        <option value="production" {{ ($settings['payment']['instamojo_environment'] ?? '') == 'production' ? 'selected' : '' }}>Production</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- CCAvenue Settings -->
                        <div class="space-y-4 border-t pt-4">
                            <div class="flex items-center justify-between">
                                <h4 class="text-lg font-medium text-gray-900">CCAvenue</h4>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="ccavenue_enabled" value="1" {{ ($settings['payment']['ccavenue_enabled'] ?? false) ? 'checked' : '' }} class="sr-only peer">
                                    <div class="w-11 h-6 bg-gray-200 peer-focus:ring-4 peer-focus:ring-purple-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-purple-600"></div>
                                </label>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Merchant ID</label>
                                    <input type="text" name="ccavenue_merchant_id" value="{{ $settings['payment']['ccavenue_merchant_id'] ?? '' }}"
                                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Working Key</label>
                                    <input type="password" name="ccavenue_working_key" value="{{ $settings['payment']['ccavenue_working_key'] ?? '' }}"
                                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Access Code</label>
                                    <input type="text" name="ccavenue_access_code" value="{{ $settings['payment']['ccavenue_access_code'] ?? '' }}"
                                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
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

            <!-- Push Notifications Tab -->
            <div x-show="activeTab === 'push'" x-cloak>
                <form action="{{ route('settings.push.update') }}" method="POST">
                    @csrf
                    <div class="space-y-6">
                        <div class="flex items-center space-x-3 pb-4 border-b border-gray-200">
                            <div class="w-10 h-10 bg-yellow-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-bell text-yellow-600"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">Push Notification Settings</h3>
                                <p class="text-sm text-gray-500">Configure push notifications for your society</p>
                            </div>
                        </div>

                        <!-- Enable Push -->
                        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                            <div>
                                <h4 class="text-sm font-medium text-gray-900">Enable Push Notifications</h4>
                                <p class="text-sm text-gray-500">Allow sending push notifications to users</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="push_enabled" value="1" {{ ($settings['push']['push_enabled'] ?? false) ? 'checked' : '' }} class="sr-only peer">
                                <div class="w-11 h-6 bg-gray-200 peer-focus:ring-4 peer-focus:ring-purple-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-purple-600"></div>
                            </label>
                        </div>

                        <div class="grid grid-cols-1 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">FCM Server Key</label>
                                <textarea name="fcm_server_key" rows="3"
                                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500"
                                    placeholder="Firebase Cloud Messaging Server Key">{{ $settings['push']['fcm_server_key'] ?? '' }}</textarea>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">VAPID Public Key</label>
                                <textarea name="vapid_public_key" rows="3"
                                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500"
                                    placeholder="VAPID Public Key">{{ $settings['push']['vapid_public_key'] ?? '' }}</textarea>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">VAPID Private Key</label>
                                <textarea name="vapid_private_key" rows="3"
                                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500"
                                    placeholder="VAPID Private Key">{{ $settings['push']['vapid_private_key'] ?? '' }}</textarea>
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

            <!-- SMS / OTP Settings Tab -->
            <div x-show="activeTab === 'sms'" x-cloak>
                @include('settings.partials.sms')
            </div>

            <!-- Security Settings Tab -->
            <div x-show="activeTab === 'security'" x-cloak>
                <form action="{{ route('settings.security.update') }}" method="POST">
                    @csrf
                    <div class="space-y-6">
                        <div class="flex items-center space-x-3 pb-4 border-b border-gray-200">
                            <div class="w-10 h-10 bg-red-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-shield-alt text-red-600"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">Security Settings</h3>
                                <p class="text-sm text-gray-500">Configure security policies for your society</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Login Attempt Limit</label>
                                <input type="number" name="login_attempt_limit" value="{{ $settings['security']['login_attempt_limit'] ?? '5' }}"
                                    min="3" max="10" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                                <p class="text-xs text-gray-500 mt-1">Maximum failed login attempts before lockout</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Session Timeout (minutes)</label>
                                <input type="number" name="session_timeout" value="{{ $settings['security']['session_timeout'] ?? '120' }}"
                                    min="5" max="1440" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                                <p class="text-xs text-gray-500 mt-1">Automatic logout after inactivity</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Minimum Password Length</label>
                                <input type="number" name="password_min_length" value="{{ $settings['security']['password_min_length'] ?? '8' }}"
                                    min="6" max="32" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                            </div>
                        </div>

                        <div class="space-y-4">
                            <h4 class="text-sm font-medium text-gray-900">Password Requirements</h4>
                            <div class="space-y-3">
                                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                    <span class="text-sm text-gray-700">Require uppercase letter</span>
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox" name="password_require_uppercase" value="1" {{ ($settings['security']['password_require_uppercase'] ?? false) ? 'checked' : '' }} class="sr-only peer">
                                        <div class="w-11 h-6 bg-gray-200 peer-focus:ring-4 peer-focus:ring-purple-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-purple-600"></div>
                                    </label>
                                </div>
                                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                    <span class="text-sm text-gray-700">Require number</span>
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox" name="password_require_number" value="1" {{ ($settings['security']['password_require_number'] ?? false) ? 'checked' : '' }} class="sr-only peer">
                                        <div class="w-11 h-6 bg-gray-200 peer-focus:ring-4 peer-focus:ring-purple-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-purple-600"></div>
                                    </label>
                                </div>
                                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                    <span class="text-sm text-gray-700">Require special character</span>
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox" name="password_require_special" value="1" {{ ($settings['security']['password_require_special'] ?? false) ? 'checked' : '' }} class="sr-only peer">
                                        <div class="w-11 h-6 bg-gray-200 peer-focus:ring-4 peer-focus:ring-purple-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-purple-600"></div>
                                    </label>
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

            <!-- Language Settings Tab (Super Admin only) -->
            @if($isGlobalAdmin)
                <div x-show="activeTab === 'language'" x-cloak>
                    @include('super-admin.settings.partials.language')
                </div>

                <!-- Storage Settings Tab (Super Admin only) -->
                <div x-show="activeTab === 'storage'" x-cloak>
                    @include('super-admin.settings.partials.storage')
                </div>

                <!-- Currency Settings Tab (Super Admin only) -->
                <div x-show="activeTab === 'currency'" x-cloak>
                    @include('super-admin.settings.partials.currency')
                </div>
            @endif
        </div>
    </div>
</div>

<script>
function testEmail() {
    const testEmailInput = document.getElementById('test_email');
    const testEmail = testEmailInput.value.trim();
    
    if (!testEmail) {
        alert('Please enter a test email address');
        testEmailInput.focus();
        return;
    }
    
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
    
    // Send test email
    fetch('{{ route("settings.email.test") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({
            test_email: testEmail
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ Test email sent successfully! Check your inbox.');
        } else {
            alert('❌ Failed to send test email: ' + data.message + (data.details ? '\n\n' + data.details : ''));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('❌ Error sending test email. Please check your settings and try again.');
    })
    .finally(() => {
        // Restore button state
        button.innerHTML = originalText;
        button.disabled = false;
    });
}

function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

function toggleAllPermissions(enable) {
    const checkboxes = document.querySelectorAll('input[name^="permissions["]');
    checkboxes.forEach(checkbox => {
        checkbox.checked = enable;
    });
}

function resetToDefaults() {
    if (confirm('Are you sure you want to reset all permissions to default values? This will override your current settings.')) {
        // Least-privilege defaults for the final role set.
        const defaults = {
            'Admin': ['facilities', 'visitors', 'complaints', 'bills', 'services', 'notices', 'reports', 'advanced_settings'],
            'Owner': ['facilities', 'visitors', 'complaints', 'bills', 'services', 'notices'],
            'Tenant': ['facilities', 'visitors', 'complaints', 'bills', 'notices'],
            'Staff': ['visitors', 'complaints', 'services', 'notices'],
            'Accountant': ['bills', 'reports'],
            'Guard': ['visitors']
        };

        // Reset all checkboxes first
        toggleAllPermissions(false);

        // Apply defaults
        Object.keys(defaults).forEach(role => {
            defaults[role].forEach(module => {
                const checkbox = document.querySelector(`input[name="permissions[${module}][${role}]"]`);
                if (checkbox) {
                    checkbox.checked = true;
                }
            });
        });
    }
}
</script>
@endsection

<script>
    // Currency preview function
    function updateCurrencyPreview() {
        const currency = document.querySelector('input[name="currency_symbol"]')?.value || '₹';
        const position = document.querySelector('select[name="currency_position"]')?.value || 'left';
        const decimalPlaces = parseInt(document.querySelector('input[name="decimal_places"]')?.value || 2);
        const thousandSep = document.querySelector('input[name="thousand_separator"]')?.value || ',';
        const decimalSep = document.querySelector('input[name="decimal_separator"]')?.value || '.';
        
        const amount = 1000;
        const formatted = amount.toLocaleString('en-US', {
            minimumFractionDigits: decimalPlaces,
            maximumFractionDigits: decimalPlaces
        }).replace(/,/g, '|').replace(/\./g, decimalSep).replace(/\|/g, thousandSep);
        
        const preview = position === 'left' ? `${currency} ${formatted}` : `${formatted} ${currency}`;
        const previewElement = document.getElementById('currency-preview');
        if (previewElement) {
            previewElement.textContent = preview;
        }
    }

    // Add event listeners for currency preview
    document.addEventListener('DOMContentLoaded', function() {
        updateCurrencyPreview();
        
        const currencyInputs = document.querySelectorAll('input[name="currency_symbol"], select[name="currency_position"], input[name="decimal_places"], input[name="thousand_separator"], input[name="decimal_separator"]');
        currencyInputs.forEach(input => {
            input.addEventListener('change', updateCurrencyPreview);
            input.addEventListener('input', updateCurrencyPreview);
        });
    });
</script>
