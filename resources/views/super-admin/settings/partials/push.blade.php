<form action="{{ route('super-admin.settings.update.push') }}" method="POST">
    @csrf
    <div class="space-y-6">
        <div class="flex items-center space-x-3 pb-4 border-b border-gray-200">
            <div class="w-10 h-10 bg-yellow-100 rounded-lg flex items-center justify-center">
                <i class="fas fa-bell text-yellow-600"></i>
            </div>
            <div>
                <h3 class="text-lg font-semibold text-gray-900">Push Notifications</h3>
                <p class="text-sm text-gray-500">Configure push notification settings</p>
            </div>
        </div>

        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
            <div>
                <h4 class="text-sm font-medium text-gray-900">Enable Push Notifications</h4>
                <p class="text-sm text-gray-500">Allow push notifications across the platform</p>
            </div>
            <label class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" name="push_enabled" value="1" {{ ($settings['push']['push_enabled'] ?? false) ? 'checked' : '' }} class="sr-only peer">
                <div class="w-11 h-6 bg-gray-200 peer-focus:ring-4 peer-focus:ring-purple-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-purple-600"></div>
            </label>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">FCM Server Key</label>
                <input type="password" name="fcm_server_key" value="{{ $settings['push']['fcm_server_key'] ?? '' }}"
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500"
                    placeholder="Your Firebase Cloud Messaging server key">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">VAPID Public Key</label>
                <input type="text" name="vapid_public_key" value="{{ $settings['push']['vapid_public_key'] ?? '' }}"
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500"
                    placeholder="Your VAPID public key">
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-2">VAPID Private Key</label>
                <input type="password" name="vapid_private_key" value="{{ $settings['push']['vapid_private_key'] ?? '' }}"
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500"
                    placeholder="Your VAPID private key">
            </div>
        </div>

        <div class="flex justify-end pt-4 border-t border-gray-200">
            <button type="submit" class="px-6 py-2.5 bg-purple-600 hover:bg-purple-700 text-white font-medium rounded-lg transition-colors">
                <i class="fas fa-save mr-2"></i>Save Settings
            </button>
        </div>
    </div>
</form>
