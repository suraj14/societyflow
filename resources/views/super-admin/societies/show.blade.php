@extends('layouts.super-admin')

@section('title', 'Society Details')
@section('page-title', 'Society Details')

@section('content')
<div class="max-w-7xl mx-auto">
    <!-- Back Button -->
    <div class="mb-6">
        <a href="{{ route('super-admin.societies.index') }}" class="text-blue-600 hover:text-blue-800">
            <i class="fas fa-arrow-left mr-2"></i>Back to Societies
        </a>
    </div>

    <!-- Society Header -->
    <div class="bg-white rounded-lg shadow-md mb-6">
        <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex justify-between items-start">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">{{ $society->name }}</h1>
                    <p class="text-gray-600 mt-1">{{ $society->email }}</p>
                    <div class="flex items-center mt-2">
                        <span class="px-3 py-1 rounded-full text-xs font-medium
                            @if($society->status === 'active') bg-green-100 text-green-800
                            @elseif($society->status === 'inactive') bg-gray-100 text-gray-800
                            @else bg-red-100 text-red-800 @endif">
                            {{ ucfirst($society->status) }}
                        </span>
                        @if($society->isOnTrial())
                            <span class="ml-2 px-3 py-1 bg-yellow-100 text-yellow-800 rounded-full text-xs font-medium">
                                Trial: {{ $stats['trial_days_left'] }} days left
                            </span>
                        @endif
                    </div>
                </div>
                <div class="flex space-x-2">
                    <a href="{{ route('super-admin.societies.edit', $society) }}" 
                       class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        <i class="fas fa-edit mr-2"></i>Edit
                    </a>
                    @if($society->status === 'inactive')
                        <form action="{{ route('super-admin.societies.approve', $society) }}" method="POST" class="inline">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                                <i class="fas fa-check mr-2"></i>Approve
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="p-6">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-blue-50 p-4 rounded-lg">
                    <div class="text-2xl font-bold text-blue-600">{{ $stats['total_buildings'] }}</div>
                    <div class="text-sm text-gray-600">Buildings</div>
                </div>
                <div class="bg-green-50 p-4 rounded-lg">
                    <div class="text-2xl font-bold text-green-600">{{ $stats['total_flats'] }}</div>
                    <div class="text-sm text-gray-600">Flats</div>
                </div>
                <div class="bg-purple-50 p-4 rounded-lg">
                    <div class="text-2xl font-bold text-purple-600">{{ $stats['total_residents'] }}</div>
                    <div class="text-sm text-gray-600">Residents</div>
                </div>
                <div class="bg-yellow-50 p-4 rounded-lg">
                    <div class="text-2xl font-bold text-yellow-600">{{ $stats['total_users'] }}</div>
                    <div class="text-sm text-gray-600">Users</div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-indigo-50 p-4 rounded-lg">
                    <div class="text-2xl font-bold text-indigo-600">₹{{ number_format($stats['monthly_revenue'], 2) }}</div>
                    <div class="text-sm text-gray-600">Monthly Revenue</div>
                </div>
                <div class="bg-red-50 p-4 rounded-lg">
                    <div class="text-2xl font-bold text-red-600">{{ $stats['pending_complaints'] }}</div>
                    <div class="text-sm text-gray-600">Pending Complaints</div>
                </div>
                <div class="bg-teal-50 p-4 rounded-lg">
                    <div class="text-2xl font-bold text-teal-600">{{ $stats['active_facilities'] }}</div>
                    <div class="text-sm text-gray-600">Active Facilities</div>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Society Information -->
        <div class="bg-white rounded-lg shadow-md">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">Society Information</h2>
            </div>
            <div class="p-6">
                <dl class="space-y-4">
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Name</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $society->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Email</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $society->email }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Phone</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $society->phone }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Address</dt>
                        <dd class="mt-1 text-sm text-gray-900">
                            {{ $society->address }}<br>
                            {{ $society->city }}, {{ $society->state }} {{ $society->pincode }}<br>
                            {{ $society->country }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Created</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $society->created_at->format('M d, Y') }}</dd>
                    </div>
                    @if($society->trial_ends_at)
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Trial Ends</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $society->trial_ends_at->format('M d, Y') }}</dd>
                    </div>
                    @endif
                </dl>
            </div>
        </div>

        <!-- Admin Information -->
        <div class="bg-white rounded-lg shadow-md">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">Admin Information</h2>
            </div>
            <div class="p-6">
                @if($society->admin)
                    <dl class="space-y-4">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Name</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $society->admin->name }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Email</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $society->admin->email }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Status</dt>
                            <dd class="mt-1">
                                <span class="px-2 py-1 rounded-full text-xs font-medium
                                    @if($society->admin->status === 'active') bg-green-100 text-green-800
                                    @else bg-red-100 text-red-800 @endif">
                                    {{ ucfirst($society->admin->status) }}
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Last Login</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                @php
                                    $lastLogin = $society->admin->last_login_at;
                                    if ($lastLogin) {
                                        try {
                                            $lastLoginDate = is_string($lastLogin) ? \Carbon\Carbon::parse($lastLogin) : $lastLogin;
                                            $lastLoginText = $lastLoginDate->format('M d, Y H:i');
                                        } catch (Exception $e) {
                                            $lastLoginText = 'Unknown';
                                        }
                                    } else {
                                        $lastLoginText = 'Never';
                                    }
                                @endphp
                                {{ $lastLoginText }}
                            </dd>
                        </div>
                    </dl>

                    <!-- Admin Actions -->
                    <div class="mt-6 pt-6 border-t border-gray-200">
                        <h3 class="text-sm font-medium text-gray-900 mb-3">Admin Actions</h3>
                        <div class="space-y-2">
                            <button onclick="showResetPasswordModal()" 
                                    class="w-full text-left px-3 py-2 text-sm text-blue-600 hover:bg-blue-50 rounded">
                                <i class="fas fa-key mr-2"></i>Reset Password
                            </button>
                            <button onclick="confirmRemoveAdmin()" 
                                    class="w-full text-left px-3 py-2 text-sm text-red-600 hover:bg-red-50 rounded">
                                <i class="fas fa-user-minus mr-2"></i>Remove Admin
                            </button>
                        </div>
                    </div>
                @else
                    <div class="text-center py-8">
                        <i class="fas fa-user-slash text-4xl text-gray-400 mb-4"></i>
                        <p class="text-gray-500 mb-4">No admin assigned to this society</p>
                        <button onclick="showAssignAdminModal()" 
                                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                            <i class="fas fa-user-plus mr-2"></i>Assign Admin
                        </button>
                    </div>
                @endif
            </div>
        </div>

        <!-- Subscription Information -->
        <div class="bg-white rounded-lg shadow-md">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">Subscription Details</h2>
            </div>
            <div class="p-6">
                @if($society->subscriptionPlan)
                    <dl class="space-y-4">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Plan</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $society->subscriptionPlan->name }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Monthly Price</dt>
                            <dd class="mt-1 text-sm text-gray-900">₹{{ number_format($society->subscriptionPlan->monthly_price, 2) }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Yearly Price</dt>
                            <dd class="mt-1 text-sm text-gray-900">₹{{ number_format($society->subscriptionPlan->yearly_price, 2) }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Max Flats</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                {{ $society->subscriptionPlan->max_flats == 0 ? 'Unlimited' : $society->subscriptionPlan->max_flats }}
                            </dd>
                        </div>
                        @if($society->subscriptionPlan->features)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Features</dt>
                            <dd class="mt-1">
                                <div class="flex flex-wrap gap-1">
                                    @php
                                        $features = is_string($society->subscriptionPlan->features) 
                                            ? json_decode($society->subscriptionPlan->features, true) 
                                            : $society->subscriptionPlan->features;
                                        $features = $features ?: [];
                                    @endphp
                                    @foreach($features as $feature)
                                        <span class="px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded">{{ $feature }}</span>
                                    @endforeach
                                </div>
                            </dd>
                        </div>
                        @endif
                    </dl>
                @else
                    <p class="text-gray-500">No subscription plan assigned</p>
                @endif
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="bg-white rounded-lg shadow-md">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">Recent Activity</h2>
            </div>
            <div class="p-6">
                @if($society->payments && $society->payments->count() > 0)
                    <div class="space-y-3">
                        @foreach($society->payments->take(5) as $payment)
                        <div class="flex justify-between items-center py-2 border-b border-gray-100 last:border-b-0">
                            <div>
                                <p class="text-sm font-medium text-gray-900">Payment Received</p>
                                <p class="text-xs text-gray-500">{{ $payment->created_at->format('M d, Y H:i') }}</p>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-medium text-green-600">₹{{ number_format($payment->amount, 2) }}</p>
                                <p class="text-xs text-gray-500">{{ ucfirst($payment->status) }}</p>
                            </div>
                        </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-gray-500 text-center py-4">No recent activity</p>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Reset Password Modal -->
<div id="resetPasswordModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg max-w-md w-full">
            <form action="{{ route('super-admin.societies.reset-admin-password', $society) }}" method="POST">
                @csrf
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">Reset Admin Password</h3>
                </div>
                <div class="p-6">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">New Password</label>
                        <input type="password" name="new_password" required
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Confirm Password</label>
                        <input type="password" name="new_password_confirmation" required
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>
                <div class="px-6 py-4 border-t border-gray-200 flex justify-end space-x-3">
                    <button type="button" onclick="hideResetPasswordModal()" 
                            class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        Reset Password
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Assign Admin Modal -->
<div id="assignAdminModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg max-w-md w-full">
            <form action="{{ route('super-admin.societies.assign-admin', $society) }}" method="POST">
                @csrf
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">Assign Admin</h3>
                </div>
                <div class="p-6">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Admin Name</label>
                        <input type="text" name="admin_name" required
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Admin Email</label>
                        <input type="email" name="admin_email" required
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Password</label>
                        <input type="password" name="admin_password" required
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Confirm Password</label>
                        <input type="password" name="admin_password_confirmation" required
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>
                <div class="px-6 py-4 border-t border-gray-200 flex justify-end space-x-3">
                    <button type="button" onclick="hideAssignAdminModal()" 
                            class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        Assign Admin
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function showResetPasswordModal() {
    document.getElementById('resetPasswordModal').classList.remove('hidden');
}

function hideResetPasswordModal() {
    document.getElementById('resetPasswordModal').classList.add('hidden');
}

function showAssignAdminModal() {
    document.getElementById('assignAdminModal').classList.remove('hidden');
}

function hideAssignAdminModal() {
    document.getElementById('assignAdminModal').classList.add('hidden');
}

function confirmRemoveAdmin() {
    if (confirm('Are you sure you want to remove the admin from this society? This action cannot be undone.')) {
        // Create and submit form
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '{{ route("super-admin.societies.remove-admin", $society) }}';
        
        const csrfToken = document.createElement('input');
        csrfToken.type = 'hidden';
        csrfToken.name = '_token';
        csrfToken.value = '{{ csrf_token() }}';
        
        const methodField = document.createElement('input');
        methodField.type = 'hidden';
        methodField.name = '_method';
        methodField.value = 'DELETE';
        
        form.appendChild(csrfToken);
        form.appendChild(methodField);
        document.body.appendChild(form);
        form.submit();
    }
}

// Close modals when clicking outside
document.addEventListener('click', function(e) {
    if (e.target.id === 'resetPasswordModal') {
        hideResetPasswordModal();
    }
    if (e.target.id === 'assignAdminModal') {
        hideAssignAdminModal();
    }
});
</script>
@endsection