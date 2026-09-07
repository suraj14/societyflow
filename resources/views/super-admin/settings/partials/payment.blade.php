<form action="{{ route('super-admin.settings.update.payment') }}" method="POST">
    @csrf
    <div class="space-y-6">
        <div class="flex items-center space-x-3 pb-4 border-b border-gray-200">
            <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                <i class="fas fa-credit-card text-green-600"></i>
            </div>
            <div>
                <h3 class="text-lg font-semibold text-gray-900">Payment Gateway Settings</h3>
                <p class="text-sm text-gray-500">Configure payment gateways for the platform</p>
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

        <!-- Razorpay -->
        <div class="space-y-4 border-t pt-4">
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

        <!-- Stripe -->
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

        <!-- PayPal -->
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

        <div class="flex justify-end pt-4 border-t border-gray-200">
            <button type="submit" class="px-6 py-2.5 bg-purple-600 hover:bg-purple-700 text-white font-medium rounded-lg transition-colors">
                <i class="fas fa-save mr-2"></i>Save Settings
            </button>
        </div>
    </div>
</form>
