<form action="{{ route('super-admin.settings.update.security') }}" method="POST">
    @csrf
    <div class="space-y-6">
        <div class="flex items-center space-x-3 pb-4 border-b border-gray-200">
            <div class="w-10 h-10 bg-red-100 rounded-lg flex items-center justify-center">
                <i class="fas fa-shield-alt text-red-600"></i>
            </div>
            <div>
                <h3 class="text-lg font-semibold text-gray-900">Security Settings</h3>
                <p class="text-sm text-gray-500">Configure security policies for the platform</p>
            </div>
        </div>

        <div class="space-y-4">
            <h4 class="text-sm font-medium text-gray-900">Global Security Options</h4>
            
            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                <span class="text-sm text-gray-700">Enable Two-Factor Authentication</span>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" name="enable_2fa" value="1" {{ ($settings['security']['enable_2fa'] ?? false) ? 'checked' : '' }} class="sr-only peer">
                    <div class="w-11 h-6 bg-gray-200 peer-focus:ring-4 peer-focus:ring-purple-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-purple-600"></div>
                </label>
            </div>

            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                <span class="text-sm text-gray-700">Force HTTPS</span>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" name="force_https" value="1" {{ ($settings['security']['force_https'] ?? false) ? 'checked' : '' }} class="sr-only peer">
                    <div class="w-11 h-6 bg-gray-200 peer-focus:ring-4 peer-focus:ring-purple-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-purple-600"></div>
                </label>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 border-t pt-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Login Attempt Limit</label>
                <input type="number" name="login_attempt_limit" value="{{ $settings['security']['login_attempt_limit'] ?? 5 }}"
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500"
                    min="3" max="10">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Session Timeout (minutes)</label>
                <input type="number" name="session_timeout" value="{{ $settings['security']['session_timeout'] ?? 30 }}"
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500"
                    min="5" max="1440">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Password Minimum Length</label>
                <input type="number" name="password_min_length" value="{{ $settings['security']['password_min_length'] ?? 8 }}"
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500"
                    min="6" max="32">
            </div>
        </div>

        <div class="space-y-4 border-t pt-4">
            <h4 class="text-sm font-medium text-gray-900">Password Requirements</h4>
            
            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                <span class="text-sm text-gray-700">Require Uppercase Letters</span>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" name="password_require_uppercase" value="1" {{ ($settings['security']['password_require_uppercase'] ?? false) ? 'checked' : '' }} class="sr-only peer">
                    <div class="w-11 h-6 bg-gray-200 peer-focus:ring-4 peer-focus:ring-purple-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-purple-600"></div>
                </label>
            </div>

            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                <span class="text-sm text-gray-700">Require Numbers</span>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" name="password_require_number" value="1" {{ ($settings['security']['password_require_number'] ?? false) ? 'checked' : '' }} class="sr-only peer">
                    <div class="w-11 h-6 bg-gray-200 peer-focus:ring-4 peer-focus:ring-purple-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-purple-600"></div>
                </label>
            </div>

            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                <span class="text-sm text-gray-700">Require Special Characters</span>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" name="password_require_special" value="1" {{ ($settings['security']['password_require_special'] ?? false) ? 'checked' : '' }} class="sr-only peer">
                    <div class="w-11 h-6 bg-gray-200 peer-focus:ring-4 peer-focus:ring-purple-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-purple-600"></div>
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
