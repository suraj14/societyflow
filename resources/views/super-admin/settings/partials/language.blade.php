<form action="{{ route('settings.language.update') }}" method="POST">
    @csrf
    <div class="space-y-6">
        <div class="flex items-center space-x-3 pb-4 border-b border-gray-200">
            <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                <i class="fas fa-language text-blue-600"></i>
            </div>
            <div>
                <h3 class="text-lg font-semibold text-gray-900">Language Settings</h3>
                <p class="text-sm text-gray-500">Configure language preferences and available languages</p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Default Language -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Default Language</label>
                <select name="default_language" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                    @foreach($languages as $code => $name)
                        <option value="{{ $code }}" {{ ($settings['language']['default_language'] ?? 'en') === $code ? 'selected' : '' }}>{{ $name }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-gray-500">Default language for new users</p>
            </div>
        </div>

        <!-- Enabled Languages -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-3">Enabled Languages</label>
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3">
                @php $enabledLangs = $settings['language']['enabled_languages'] ?? ['en']; @endphp
                @foreach($languages as $code => $name)
                    <label class="flex items-center p-3 bg-gray-50 rounded-lg cursor-pointer hover:bg-gray-100 transition-colors">
                        <input type="checkbox" name="enabled_languages[]" value="{{ $code }}"
                            {{ in_array($code, $enabledLangs) ? 'checked' : '' }}
                            class="w-4 h-4 text-purple-600 border-gray-300 rounded focus:ring-purple-500">
                        <span class="ml-2 text-sm text-gray-700">{{ $name }}</span>
                    </label>
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
