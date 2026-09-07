<form action="{{ route('settings.theme.update') }}" method="POST">
    @csrf
    <div class="space-y-6">
        <div class="flex items-center space-x-3 pb-4 border-b border-gray-200">
            <div class="w-10 h-10 bg-pink-100 rounded-lg flex items-center justify-center">
                <i class="fas fa-palette text-pink-600"></i>
            </div>
            <div>
                <h3 class="text-lg font-semibold text-gray-900">Theme Settings</h3>
                <p class="text-sm text-gray-500">Customize the look and feel of your application</p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Primary Color -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Primary Color</label>
                <div class="flex items-center space-x-3">
                    <input type="color" name="primary_color" value="{{ $settings['theme']['primary_color'] ?? '#7c3aed' }}"
                        class="w-12 h-12 rounded-lg border border-gray-300 cursor-pointer">
                    <input type="text" value="{{ $settings['theme']['primary_color'] ?? '#7c3aed' }}" readonly
                        class="flex-1 px-4 py-2.5 border border-gray-300 rounded-lg bg-gray-50 text-gray-600">
                </div>
            </div>

            <!-- Secondary Color -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Secondary Color</label>
                <div class="flex items-center space-x-3">
                    <input type="color" name="secondary_color" value="{{ $settings['theme']['secondary_color'] ?? '#4f46e5' }}"
                        class="w-12 h-12 rounded-lg border border-gray-300 cursor-pointer">
                    <input type="text" value="{{ $settings['theme']['secondary_color'] ?? '#4f46e5' }}" readonly
                        class="flex-1 px-4 py-2.5 border border-gray-300 rounded-lg bg-gray-50 text-gray-600">
                </div>
            </div>

            <!-- Sidebar Theme -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Sidebar Theme</label>
                <select name="sidebar_theme" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                    <option value="light" {{ ($settings['theme']['sidebar_theme'] ?? 'light') === 'light' ? 'selected' : '' }}>Light</option>
                    <option value="dark" {{ ($settings['theme']['sidebar_theme'] ?? 'light') === 'dark' ? 'selected' : '' }}>Dark</option>
                </select>
            </div>

            <!-- Button Style -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Button Style</label>
                <select name="button_style" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                    <option value="rounded" {{ ($settings['theme']['button_style'] ?? 'rounded') === 'rounded' ? 'selected' : '' }}>Rounded</option>
                    <option value="square" {{ ($settings['theme']['button_style'] ?? 'rounded') === 'square' ? 'selected' : '' }}>Square</option>
                    <option value="pill" {{ ($settings['theme']['button_style'] ?? 'rounded') === 'pill' ? 'selected' : '' }}>Pill</option>
                </select>
            </div>
        </div>

        <!-- Preview -->
        <div class="bg-gray-50 rounded-lg p-4">
            <h4 class="text-sm font-medium text-gray-700 mb-3">Button Preview</h4>
            <div class="flex flex-wrap gap-3">
                <button type="button" class="px-4 py-2 bg-purple-600 text-white rounded-lg">Primary Button</button>
                <button type="button" class="px-4 py-2 bg-indigo-600 text-white rounded-lg">Secondary Button</button>
                <button type="button" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg">Neutral Button</button>
            </div>
        </div>

        <div class="flex justify-end pt-4 border-t border-gray-200">
            <button type="submit" class="px-6 py-2.5 bg-purple-600 hover:bg-purple-700 text-white font-medium rounded-lg transition-colors">
                <i class="fas fa-save mr-2"></i>Save Settings
            </button>
        </div>
    </div>
</form>
