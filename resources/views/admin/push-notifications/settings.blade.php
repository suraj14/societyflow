@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-gradient-to-br from-slate-50 to-blue-50 py-8">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900 flex items-center">
                <div class="w-10 h-10 bg-gradient-to-r from-purple-500 to-pink-600 rounded-lg flex items-center justify-center mr-3">
                    <i class="fas fa-bell text-white"></i>
                </div>
                Push Notification Settings
            </h1>
            <p class="text-gray-600 mt-2">Configure Firebase Cloud Messaging and Web Push (VAPID)</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Main Settings -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Enable/Disable Card -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="px-6 py-4 bg-gradient-to-r from-purple-50 to-pink-50 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                            <i class="fas fa-toggle-on text-purple-600 mr-2"></i>
                            Global Settings
                        </h3>
                    </div>
                    <div class="p-6">
                        <form action="{{ route('admin.push-notifications.update-settings') }}" method="POST">
                            @csrf
                            
                            <!-- Enable/Disable Toggle -->
                            <div class="mb-6">
                                <label class="flex items-center cursor-pointer">
                                    <input type="checkbox" name="enabled" value="1" 
                                           {{ $setting && $setting->enabled ? 'checked' : '' }}
                                           class="w-5 h-5 text-purple-600 rounded focus:ring-2 focus:ring-purple-500">
                                    <span class="ml-3 text-gray-700 font-medium">Enable Push Notifications</span>
                                </label>
                                <p class="text-sm text-gray-500 mt-1 ml-8">Enable or disable all push notifications globally</p>
                            </div>

                            <!-- FCM Server Key -->
                            <div class="mb-6">
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="fas fa-key text-orange-500 mr-1"></i>
                                    FCM Server Key
                                </label>
                                <textarea name="fcm_server_key" rows="3" 
                                          class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 font-mono text-sm"
                                          placeholder="Paste your Firebase Cloud Messaging Server Key here">{{ $setting?->fcm_server_key }}</textarea>
                                <p class="text-xs text-gray-500 mt-1">Get this from Firebase Console → Project Settings → Cloud Messaging</p>
                            </div>

                            <!-- VAPID Public Key -->
                            <div class="mb-6">
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="fas fa-lock text-blue-500 mr-1"></i>
                                    VAPID Public Key
                                </label>
                                <textarea name="vapid_public_key" rows="3" 
                                          class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 font-mono text-sm"
                                          placeholder="Paste your VAPID Public Key here">{{ $setting?->vapid_public_key }}</textarea>
                                <p class="text-xs text-gray-500 mt-1">Used for Web Push (VAPID) authentication</p>
                            </div>

                            <!-- VAPID Private Key -->
                            <div class="mb-6">
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="fas fa-lock-open text-red-500 mr-1"></i>
                                    VAPID Private Key
                                </label>
                                <textarea name="vapid_private_key" rows="3" 
                                          class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 font-mono text-sm"
                                          placeholder="Paste your VAPID Private Key here (encrypted in database)">{{ $setting?->vapid_private_key }}</textarea>
                                <p class="text-xs text-gray-500 mt-1">Keep this secret! It's encrypted in the database</p>
                            </div>

                            <!-- Enabled Triggers -->
                            <div class="mb-6">
                                <label class="block text-sm font-medium text-gray-700 mb-3">
                                    <i class="fas fa-check-circle text-green-500 mr-1"></i>
                                    Enable Notifications For
                                </label>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                    @foreach(['bill_generated' => 'Bill Generated', 'payment_success' => 'Payment Success', 'payment_overdue' => 'Payment Overdue', 'notice_published' => 'Notice Published', 'ticket_created' => 'Ticket Created', 'ticket_updated' => 'Ticket Updated', 'visitor_approved' => 'Visitor Approved', 'visitor_rejected' => 'Visitor Rejected', 'event_reminder' => 'Event Reminder', 'user_added' => 'User Added', 'password_reset' => 'Password Reset'] as $trigger => $label)
                                    <label class="flex items-center">
                                        <input type="checkbox" name="enabled_triggers[]" value="{{ $trigger }}"
                                               {{ $setting && in_array($trigger, $setting->enabled_triggers ?? []) ? 'checked' : '' }}
                                               class="w-4 h-4 text-purple-600 rounded focus:ring-2 focus:ring-purple-500">
                                        <span class="ml-2 text-sm text-gray-700">{{ $label }}</span>
                                    </label>
                                    @endforeach
                                </div>
                            </div>

                            <!-- Role Permissions -->
                            <div class="mb-6">
                                <label class="block text-sm font-medium text-gray-700 mb-3">
                                    <i class="fas fa-users text-indigo-500 mr-1"></i>
                                    Role-Based Delivery
                                </label>
                                <div class="space-y-2">
                                    @foreach(['Admin' => 'Admin', 'Owner' => 'Owner', 'Tenant' => 'Tenant', 'Staff' => 'Staff'] as $role => $label)
                                    <label class="flex items-center">
                                        <input type="checkbox" name="role_permissions[{{ $role }}]" value="1"
                                               {{ $setting && ($setting->role_permissions[$role] ?? false) ? 'checked' : '' }}
                                               class="w-4 h-4 text-purple-600 rounded focus:ring-2 focus:ring-purple-500">
                                        <span class="ml-2 text-sm text-gray-700">Send to {{ $label }}</span>
                                    </label>
                                    @endforeach
                                </div>
                            </div>

                            <button type="submit" class="w-full px-6 py-3 bg-gradient-to-r from-purple-600 to-pink-600 text-white font-medium rounded-lg hover:from-purple-700 hover:to-pink-700 transition-all duration-200">
                                <i class="fas fa-save mr-2"></i>
                                Save Settings
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Test Push Notification -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="px-6 py-4 bg-gradient-to-r from-blue-50 to-cyan-50 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                            <i class="fas fa-paper-plane text-blue-600 mr-2"></i>
                            Test Push Notification
                        </h3>
                    </div>
                    <div class="p-6">
                        <div class="flex items-center space-x-4">
                            <input type="email" id="test_email" placeholder="Enter email address" 
                                   class="flex-1 px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <button type="button" onclick="testPushNotification()" 
                                    class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition-all duration-200">
                                <i class="fas fa-paper-plane mr-2"></i>
                                Send Test
                            </button>
                        </div>
                        <p class="text-xs text-gray-500 mt-3">
                            <i class="fas fa-info-circle mr-1"></i>
                            Send a test push notification to verify your configuration
                        </p>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="space-y-6">
                <!-- Quick Stats -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="px-6 py-4 bg-gradient-to-r from-gray-50 to-slate-50 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                            <i class="fas fa-chart-bar text-gray-600 mr-2"></i>
                            Statistics
                        </h3>
                    </div>
                    <div class="p-6 space-y-4" id="stats-container">
                        <div class="text-center py-4">
                            <i class="fas fa-spinner fa-spin text-gray-400"></i>
                        </div>
                    </div>
                </div>

                <!-- Documentation -->
                <div class="bg-gradient-to-br from-purple-50 to-pink-50 rounded-xl border border-purple-200 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                        <i class="fas fa-book text-purple-600 mr-2"></i>
                        Setup Guide
                    </h3>
                    <ol class="space-y-3 text-sm text-gray-700">
                        <li class="flex items-start">
                            <span class="flex-shrink-0 w-6 h-6 bg-purple-600 text-white rounded-full flex items-center justify-center text-xs font-bold mr-3">1</span>
                            <span>Get FCM Server Key from Firebase Console</span>
                        </li>
                        <li class="flex items-start">
                            <span class="flex-shrink-0 w-6 h-6 bg-purple-600 text-white rounded-full flex items-center justify-center text-xs font-bold mr-3">2</span>
                            <span>Generate VAPID keys using web-push CLI</span>
                        </li>
                        <li class="flex items-start">
                            <span class="flex-shrink-0 w-6 h-6 bg-purple-600 text-white rounded-full flex items-center justify-center text-xs font-bold mr-3">3</span>
                            <span>Paste keys in the form above</span>
                        </li>
                        <li class="flex items-start">
                            <span class="flex-shrink-0 w-6 h-6 bg-purple-600 text-white rounded-full flex items-center justify-center text-xs font-bold mr-3">4</span>
                            <span>Enable notifications and select triggers</span>
                        </li>
                        <li class="flex items-start">
                            <span class="flex-shrink-0 w-6 h-6 bg-purple-600 text-white rounded-full flex items-center justify-center text-xs font-bold mr-3">5</span>
                            <span>Test with the test button</span>
                        </li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function testPushNotification() {
    const email = document.getElementById('test_email').value;
    if (!email) {
        alert('Please enter an email address');
        return;
    }

    const button = event.target;
    const originalText = button.innerHTML;
    button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Sending...';
    button.disabled = true;

    fetch('{{ route("admin.push-notifications.test") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({ test_email: email })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Test push notification sent successfully!');
        } else {
            alert('Failed: ' + data.message);
        }
    })
    .catch(error => {
        alert('Error: ' + error.message);
    })
    .finally(() => {
        button.innerHTML = originalText;
        button.disabled = false;
    });
}

// Load statistics
document.addEventListener('DOMContentLoaded', () => {
    fetch('{{ route("admin.push-notifications.statistics") }}')
        .then(response => response.json())
        .then(data => {
            const html = `
                <div class="flex items-center justify-between py-3 border-b border-gray-100">
                    <span class="text-sm text-gray-600">Sent</span>
                    <span class="text-lg font-bold text-green-600">${data.total_sent}</span>
                </div>
                <div class="flex items-center justify-between py-3 border-b border-gray-100">
                    <span class="text-sm text-gray-600">Failed</span>
                    <span class="text-lg font-bold text-red-600">${data.total_failed}</span>
                </div>
                <div class="flex items-center justify-between py-3">
                    <span class="text-sm text-gray-600">Pending</span>
                    <span class="text-lg font-bold text-yellow-600">${data.total_pending}</span>
                </div>
            `;
            document.getElementById('stats-container').innerHTML = html;
        })
        .catch(error => console.error('Error loading stats:', error));
});
</script>
@endsection
