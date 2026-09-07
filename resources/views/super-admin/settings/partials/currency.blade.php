<form action="{{ route('settings.currency.update') }}" method="POST">
    @csrf
    <div class="space-y-6">
        <div class="flex items-center space-x-3 pb-4 border-b border-gray-200">
            <div class="w-10 h-10 bg-yellow-100 rounded-lg flex items-center justify-center">
                <i class="fas fa-dollar-sign text-yellow-600"></i>
            </div>
            <div>
                <h3 class="text-lg font-semibold text-gray-900">Currency Settings</h3>
                <p class="text-sm text-gray-500">Configure currency display format</p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Default Currency -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Default Currency</label>
                <select name="default_currency" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                    @foreach($currencies as $code => $info)
                        <option value="{{ $code }}" {{ ($settings['currency']['default_currency'] ?? 'USD') === $code ? 'selected' : '' }}>{{ $code }} - {{ $info['name'] }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Currency Symbol -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Currency Symbol</label>
                <input type="text" name="currency_symbol" value="{{ $settings['currency']['currency_symbol'] ?? '$' }}"
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
            </div>

            <!-- Currency Position -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Symbol Position</label>
                <select name="currency_position" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                    <option value="left" {{ ($settings['currency']['currency_position'] ?? 'left') === 'left' ? 'selected' : '' }}>Left ($100)</option>
                    <option value="right" {{ ($settings['currency']['currency_position'] ?? 'left') === 'right' ? 'selected' : '' }}>Right (100$)</option>
                </select>
            </div>

            <!-- Decimal Separator -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Decimal Separator</label>
                <select name="decimal_separator" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                    <option value="." {{ ($settings['currency']['decimal_separator'] ?? '.') === '.' ? 'selected' : '' }}>Period (.)</option>
                    <option value="," {{ ($settings['currency']['decimal_separator'] ?? '.') === ',' ? 'selected' : '' }}>Comma (,)</option>
                </select>
            </div>

            <!-- Thousand Separator -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Thousand Separator</label>
                <select name="thousand_separator" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                    <option value="," {{ ($settings['currency']['thousand_separator'] ?? ',') === ',' ? 'selected' : '' }}>Comma (,)</option>
                    <option value="." {{ ($settings['currency']['thousand_separator'] ?? ',') === '.' ? 'selected' : '' }}>Period (.)</option>
                    <option value=" " {{ ($settings['currency']['thousand_separator'] ?? ',') === ' ' ? 'selected' : '' }}>Space ( )</option>
                </select>
            </div>
        </div>

        <!-- Preview -->
        <div class="bg-gray-50 rounded-lg p-4">
            <h4 class="text-sm font-medium text-gray-700 mb-2">Format Preview</h4>
            <p class="text-2xl font-bold text-gray-900">{{ $settings['currency']['currency_symbol'] ?? '$' }}1,234.56</p>
        </div>

        <div class="flex justify-end pt-4 border-t border-gray-200">
            <button type="submit" class="px-6 py-2.5 bg-purple-600 hover:bg-purple-700 text-white font-medium rounded-lg transition-colors">
                <i class="fas fa-save mr-2"></i>Save Settings
            </button>
        </div>
    </div>
</form>
