<form action="{{ route('settings.storage.update') }}" method="POST" x-data="{ driver: '{{ $settings['storage']['storage_driver'] ?? 'local' }}' }">
    @csrf
    <div class="space-y-6">
        <div class="flex items-center space-x-3 pb-4 border-b border-gray-200">
            <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                <i class="fas fa-database text-green-600"></i>
            </div>
            <div>
                <h3 class="text-lg font-semibold text-gray-900">Storage Settings</h3>
                <p class="text-sm text-gray-500">Configure file storage driver and cloud storage</p>
            </div>
        </div>

        <!-- Storage Driver -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Storage Driver</label>
            <select name="storage_driver" x-model="driver" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                <option value="local">Local Storage</option>
                <option value="public">Public Storage</option>
                <option value="s3">Amazon S3</option>
            </select>
        </div>

        <!-- S3 Configuration -->
        <div x-show="driver === 's3'" x-cloak class="space-y-4 p-4 bg-orange-50 rounded-lg border border-orange-200">
            <h4 class="font-medium text-gray-900 flex items-center">
                <i class="fab fa-aws text-orange-600 mr-2"></i>AWS S3 Configuration
            </h4>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">AWS Access Key</label>
                    <input type="text" name="aws_key" value="{{ $settings['storage']['aws_key'] ?? '' }}"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500" placeholder="AKIAIOSFODNN7EXAMPLE">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">AWS Secret Key</label>
                    <input type="password" name="aws_secret" value="{{ $settings['storage']['aws_secret'] ?? '' }}"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500" placeholder="••••••••••••••••">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">AWS Region</label>
                    <input type="text" name="aws_region" value="{{ $settings['storage']['aws_region'] ?? '' }}"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500" placeholder="us-east-1">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">S3 Bucket Name</label>
                    <input type="text" name="aws_bucket" value="{{ $settings['storage']['aws_bucket'] ?? '' }}"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500" placeholder="my-bucket">
                </div>
            </div>
        </div>

        <div class="flex justify-between items-center pt-4 border-t border-gray-200">
            <button type="button" onclick="testStorage()" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium rounded-lg transition-colors">
                <i class="fas fa-plug mr-2"></i>Test Connection
            </button>
            <button type="submit" class="px-6 py-2.5 bg-purple-600 hover:bg-purple-700 text-white font-medium rounded-lg transition-colors">
                <i class="fas fa-save mr-2"></i>Save Settings
            </button>
        </div>
    </div>
</form>

<script>
function testStorage() {
    fetch('{{ route("settings.storage.test") }}', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Content-Type': 'application/json' }
    })
    .then(r => r.json())
    .then(data => alert(data.message))
    .catch(() => alert('Connection test failed'));
}
</script>
