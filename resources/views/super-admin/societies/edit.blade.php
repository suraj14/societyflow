@extends('layouts.super-admin')

@section('title', 'Edit Society')
@section('page-title', 'Edit Society')

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('super-admin.societies.index') }}" class="text-blue-600 hover:text-blue-800">
            <i class="fas fa-arrow-left mr-2"></i>Back to Societies
        </a>
    </div>

    <div class="bg-white rounded-lg shadow-md">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Edit: {{ $society->name }}</h2>
        </div>

        <form action="{{ route('super-admin.societies.update', $society) }}" method="POST" class="p-6">
            @csrf
            @method('PUT')

            <!-- Society Details -->
            <div class="mb-8">
                <h3 class="text-md font-medium text-gray-900 mb-4">Society Details</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Society Name *</label>
                        <input type="text" name="name" value="{{ old('name', $society->name) }}" required
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 @error('name') border-red-500 @enderror">
                        @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Email *</label>
                        <input type="email" name="email" value="{{ old('email', $society->email) }}" required
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 @error('email') border-red-500 @enderror">
                        @error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Phone *</label>
                        <input type="text" name="phone" value="{{ old('phone', $society->phone) }}" required
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 @error('phone') border-red-500 @enderror">
                        @error('phone')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Subscription Plan *</label>
                        <select name="subscription_plan_id" required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Select Plan</option>
                            @foreach($subscriptionPlans as $plan)
                                <option value="{{ $plan->id }}" {{ old('subscription_plan_id', $society->subscription_plan_id) == $plan->id ? 'selected' : '' }}>
                                    {{ $plan->name }} - ₹{{ number_format($plan->monthly_price) }}/month
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Status *</label>
                        <select name="status" required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                            <option value="active" {{ old('status', $society->status) == 'active' ? 'selected' : '' }}>Active</option>
                            <option value="inactive" {{ old('status', $society->status) == 'inactive' ? 'selected' : '' }}>Inactive</option>
                            <option value="suspended" {{ old('status', $society->status) == 'suspended' ? 'selected' : '' }}>Suspended</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Address -->
            <div class="mb-8">
                <h3 class="text-md font-medium text-gray-900 mb-4">Address</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Address *</label>
                        <textarea name="address" rows="2" required
                                  class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">{{ old('address', $society->address) }}</textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">City *</label>
                        <input type="text" name="city" value="{{ old('city', $society->city) }}" required
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">State *</label>
                        <input type="text" name="state" value="{{ old('state', $society->state) }}" required
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Pincode *</label>
                        <input type="text" name="pincode" value="{{ old('pincode', $society->pincode) }}" required
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>
            </div>

            <!-- Admin Management -->
            <div class="mb-8">
                <h3 class="text-md font-medium text-gray-900 mb-4">Society Admin</h3>
                
                @if($society->hasAdmin())
                    <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-4">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-user-shield text-green-400"></i>
                                </div>
                                <div class="ml-3">
                                    <h4 class="text-sm font-medium text-green-800">Current Admin</h4>
                                    <p class="text-sm text-green-700">
                                        <strong>{{ $society->admin->name }}</strong> ({{ $society->admin->email }})
                                    </p>
                                    <p class="text-xs text-green-600">
                                        @php
                                            $lastLogin = $society->admin->last_login_at;
                                            if ($lastLogin) {
                                                try {
                                                    $lastLoginDate = is_string($lastLogin) ? \Carbon\Carbon::parse($lastLogin) : $lastLogin;
                                                    $lastLoginText = $lastLoginDate->diffForHumans();
                                                } catch (Exception $e) {
                                                    $lastLoginText = 'Unknown';
                                                }
                                            } else {
                                                $lastLoginText = 'Never';
                                            }
                                        @endphp
                                        Last login: {{ $lastLoginText }}
                                    </p>
                                </div>
                            </div>
                            <div class="flex space-x-2">
                                <button type="button" onclick="showResetPasswordModal()" 
                                        class="px-3 py-1 bg-yellow-600 text-white text-xs rounded hover:bg-yellow-700">
                                    Reset Password
                                </button>
                                <button type="button" onclick="showRemoveAdminModal()" 
                                        class="px-3 py-1 bg-red-600 text-white text-xs rounded hover:bg-red-700">
                                    Remove Admin
                                </button>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-exclamation-triangle text-yellow-400"></i>
                                </div>
                                <div class="ml-3">
                                    <h4 class="text-sm font-medium text-yellow-800">No Admin Assigned</h4>
                                    <p class="text-sm text-yellow-700">This society does not have an admin assigned.</p>
                                </div>
                            </div>
                            <button type="button" onclick="showAssignAdminModal()" 
                                    class="px-3 py-1 bg-blue-600 text-white text-xs rounded hover:bg-blue-700">
                                Assign Admin
                            </button>
                        </div>
                    </div>
                @endif
            </div>

            <div class="flex justify-end space-x-3 pt-6 border-t">
                <a href="{{ route('super-admin.societies.index') }}" 
                   class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">Cancel</a>
                <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                    Update Society
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Reset Password Modal -->
@if($society->hasAdmin())
<div id="resetPasswordModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
        <form action="{{ route('super-admin.societies.reset-admin-password', $society) }}" method="POST">
            @csrf
            <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                <h3 class="text-lg font-semibold text-gray-900">Reset Admin Password</h3>
                <button type="button" onclick="hideResetPasswordModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="p-6 space-y-4">
                @if ($errors->any())
                    <div class="bg-red-50 border border-red-200 rounded-lg p-3">
                        <ul class="text-sm text-red-600 list-disc list-inside">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">New Password *</label>
                    <input type="password" name="new_password" required
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 @error('new_password') border-red-500 @enderror"
                           placeholder="Minimum 8 characters">
                    @error('new_password')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Confirm Password *</label>
                    <input type="password" name="new_password_confirmation" required
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
                           placeholder="Confirm password">
                </div>
            </div>
            <div class="px-6 py-4 border-t border-gray-200 flex justify-end space-x-3">
                <button type="button" onclick="hideResetPasswordModal()" 
                        class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-yellow-600 text-white rounded-lg hover:bg-yellow-700 flex items-center">
                    <i class="fas fa-key mr-2"></i>Reset Password
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Remove Admin Modal -->
<div id="removeAdminModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
        <form action="{{ route('super-admin.societies.remove-admin', $society) }}" method="POST">
            @csrf
            @method('DELETE')
            <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                <h3 class="text-lg font-semibold text-gray-900">Remove Admin</h3>
                <button type="button" onclick="hideRemoveAdminModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="p-6">
                <p class="text-sm text-gray-600">
                    Are you sure you want to remove <strong>{{ $society->admin->name }}</strong> as admin of this society? 
                    This action will remove their admin role and society access.
                </p>
            </div>
            <div class="px-6 py-4 border-t border-gray-200 flex justify-end space-x-3">
                <button type="button" onclick="hideRemoveAdminModal()" 
                        class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 flex items-center">
                    <i class="fas fa-trash mr-2"></i>Remove Admin
                </button>
            </div>
        </form>
    </div>
</div>
@endif

<!-- Assign Admin Modal -->
@if(!$society->hasAdmin())
<div id="assignAdminModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
        <form action="{{ route('super-admin.societies.assign-admin', $society) }}" method="POST" id="assignAdminForm">
            @csrf
            <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                <h3 class="text-lg font-semibold text-gray-900">Assign New Admin</h3>
                <button type="button" onclick="hideAssignAdminModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="p-6 space-y-4">
                @if ($errors->any())
                    <div class="bg-red-50 border border-red-200 rounded-lg p-3">
                        <ul class="text-sm text-red-600 list-disc list-inside">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Admin Name *</label>
                    <input type="text" name="admin_name" value="{{ old('admin_name') }}" required
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 @error('admin_name') border-red-500 @enderror"
                           placeholder="Enter admin name">
                    @error('admin_name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Admin Email *</label>
                    <input type="email" name="admin_email" value="{{ old('admin_email') }}" required
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 @error('admin_email') border-red-500 @enderror"
                           placeholder="Enter admin email">
                    @error('admin_email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Password *</label>
                    <input type="password" name="admin_password" required
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 @error('admin_password') border-red-500 @enderror"
                           placeholder="Minimum 8 characters">
                    @error('admin_password')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Confirm Password *</label>
                    <input type="password" name="admin_password_confirmation" required
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
                           placeholder="Confirm password">
                </div>
            </div>
            <div class="px-6 py-4 border-t border-gray-200 flex justify-end space-x-3">
                <button type="button" onclick="hideAssignAdminModal()" 
                        class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 flex items-center">
                    <i class="fas fa-check mr-2"></i>Assign Admin
                </button>
            </div>
        </form>
    </div>
</div>
@endif

<script>
function showResetPasswordModal() {
    const modal = document.getElementById('resetPasswordModal');
    if (modal) {
        modal.classList.remove('hidden');
        modal.style.display = 'flex';
    }
}

function hideResetPasswordModal() {
    const modal = document.getElementById('resetPasswordModal');
    if (modal) {
        modal.classList.add('hidden');
        modal.style.display = 'none';
    }
}

function showRemoveAdminModal() {
    const modal = document.getElementById('removeAdminModal');
    if (modal) {
        modal.classList.remove('hidden');
        modal.style.display = 'flex';
    }
}

function hideRemoveAdminModal() {
    const modal = document.getElementById('removeAdminModal');
    if (modal) {
        modal.classList.add('hidden');
        modal.style.display = 'none';
    }
}

function showAssignAdminModal() {
    const modal = document.getElementById('assignAdminModal');
    if (modal) {
        modal.classList.remove('hidden');
        modal.style.display = 'flex';
    }
}

function hideAssignAdminModal() {
    const modal = document.getElementById('assignAdminModal');
    if (modal) {
        modal.classList.add('hidden');
        modal.style.display = 'none';
    }
}

// Close modals when clicking outside
document.addEventListener('click', function(event) {
    const modals = ['resetPasswordModal', 'removeAdminModal', 'assignAdminModal'];
    modals.forEach(modalId => {
        const modal = document.getElementById(modalId);
        if (modal && !modal.classList.contains('hidden')) {
            if (event.target === modal) {
                modal.classList.add('hidden');
                modal.style.display = 'none';
            }
        }
    });
});

// Close modals on Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        hideResetPasswordModal();
        hideRemoveAdminModal();
        hideAssignAdminModal();
    }
});
</script>
@endsection
