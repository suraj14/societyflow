<form action="{{ route('settings.app.update') }}" method="POST">
    @csrf
    <div class="space-y-6">
        <div class="flex items-center space-x-3 pb-4 border-b border-gray-200">
            <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center">
                <i class="fas fa-cog text-purple-600"></i>
            </div>
            <div>
                <h3 class="text-lg font-semibold text-gray-900">Application Settings</h3>
                <p class="text-sm text-gray-500">Configure basic application settings</p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- App Name -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Application Name</label>
                <input type="text" name="app_name" value="{{ $settings['app']['app_name'] ?? config('app.name') }}"
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                <p class="mt-1 text-xs text-gray-500">The name displayed throughout the application</p>
            </div>

            <!-- Default Language -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Default Language</label>
                <select name="default_language" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                    @foreach($languages as $code => $name)
                        <option value="{{ $code }}" {{ ($settings['app']['default_language'] ?? 'en') === $code ? 'selected' : '' }}>{{ $name }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Default Currency -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Default Currency</label>
                <select name="default_currency" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                    @foreach($currencies as $code => $info)
                        <option value="{{ $code }}" {{ ($settings['app']['default_currency'] ?? 'USD') === $code ? 'selected' : '' }}>{{ $info['symbol'] }} - {{ $info['name'] }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <!-- Society Approval Toggle -->
        <div class="bg-gray-50 rounded-lg p-4">
            <div class="flex items-center justify-between">
                <div>
                    <h4 class="text-sm font-medium text-gray-900">Society Requires Approval</h4>
                    <p class="text-sm text-gray-500 mt-1">Enable this to require admin approval for new society registrations</p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" name="society_requires_approval" value="1" class="sr-only peer"
                        {{ ($settings['app']['society_requires_approval'] ?? false) ? 'checked' : '' }}>
                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-purple-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-purple-600"></div>
                </label>
            </div>
        </div>

        <div class="flex justify-end pt-4 border-t border-gray-200">
            <button type="submit" class="px-6 py-2.5 bg-purple-600 hover:bg-purple-700 text-white font-medium rounded-lg transition-colors">
                <i class="fas fa-save mr-2"></i>Save Settings
            </button>
        </div>
    </div>
</form>
