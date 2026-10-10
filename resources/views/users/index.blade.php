@extends('layouts.app')

@section('title', 'User Management')
@section('page-title', 'User Management')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">User Management</h1>
            <p class="text-gray-600 mt-1">Manage all users and their roles</p>
        </div>
        <a href="{{ route('users.create') }}"
           class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-medium transition-colors inline-flex items-center">
            <i class="fas fa-plus mr-2"></i>
            Add
        </a>
    </div>



    <!-- Filters -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                <input type="text" id="searchInput" placeholder="Search by name, email, or phone" 
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Filter by Role</label>
                <select id="roleFilter" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">All Roles</option>
                    @foreach($roles as $role)
                        <option value="{{ $role->name }}">{{ $role->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Filter by Status</label>
                <select id="statusFilter" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">All Status</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
            <div class="flex items-end space-x-2">
                <button onclick="clearFilters()" class="px-4 py-2 text-gray-600 hover:text-gray-800 transition-colors">
                    Clear filters
                </button>
                <button onclick="exportUsers()" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg transition-colors">
                    Export CSV
                </button>
            </div>
        </div>
    </div>

    <!-- Users Table -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        @if($users->count() > 0)
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200" id="usersTable">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Profile Image</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Full Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email Address</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Phone Number</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Login Admin</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200" id="usersTableBody">
                        @foreach($users as $user)
                            <tr class="hover:bg-gray-50 transition-colors user-row" 
                                data-user-id="{{ $user->id }}"
                                data-name="{{ strtolower($user->name) }}" 
                                data-email="{{ strtolower($user->email) }}" 
                                data-phone="{{ $user->phone }}" 
                                data-role="{{ $user->roles->first()?->name ?? '' }}" 
                                data-status="{{ $user->status }}">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex-shrink-0 h-10 w-10">
                                        <img class="h-10 w-10 rounded-full object-cover" 
                                             src="{{ $user->avatar ? asset('storage/' . $user->avatar) : 'https://ui-avatars.com/api/?name=' . urlencode($user->name) . '&background=667eea&color=fff' }}" 
                                             alt="{{ $user->name }}">
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">{{ $user->name }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">{{ $user->email }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">{{ $user->phone }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @php
                                        $userRole = $user->roles->first();
                                        $roleName = $userRole ? $userRole->name : 'No Role';
                                    @endphp
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        @if($roleName == 'Super Admin') bg-purple-100 text-purple-800
                                        @elseif($roleName == 'Admin') bg-blue-100 text-blue-800
                                        @elseif($roleName == 'Owner') bg-green-100 text-green-800
                                        @elseif($roleName == 'Villa Owner') bg-green-100 text-green-800
                                        @elseif($roleName == 'Apartment Owner') bg-yellow-100 text-yellow-800
                                        @elseif($roleName == 'Tenant') bg-indigo-100 text-indigo-800
                                        @else bg-gray-100 text-gray-800
                                        @endif">
                                        {{ $roleName }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        {{ $user->status == 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                        {{ ucfirst($user->status) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($user->id === auth()->id())
                                        <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                            <i class="fas fa-check-circle mr-1"></i> Current
                                        </span>
                                    @else
                                        <span class="text-gray-400 text-xs">-</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex items-center justify-end space-x-3">
                                        @if($user->id === auth()->id())
                                            <span class="text-gray-500 text-xs">You cannot change own role</span>
                                        @else
                                            {{-- The standard edit page (photo preview, validation errors shown
                                                 in place, committee designation) - the old right-side drawer
                                                 hid its own validation errors and never showed the photo. --}}
                                            <a href="{{ route('users.edit', $user) }}"
                                               class="text-indigo-600 hover:text-indigo-900 transition-colors"
                                               title="Update {{ $user->name }}">
                                                Update
                                            </a>
                                        @endif
                                        
                                        @if($user->id !== auth()->id())
                                            <button onclick="openResetPasswordModal({{ $user->id }})"
                                                    class="text-gray-600 hover:text-gray-900 transition-colors"
                                                    title="Reset Password">
                                                <i class="fas fa-key"></i>
                                            </button>

                                            <button onclick="openDeleteConfirmation({{ $user->id }}, '{{ $user->name }}')"
                                                    class="text-red-600 hover:text-red-900 transition-colors"
                                                    title="Delete User">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            
            <!-- Empty state for filtered results -->
            <div id="noResultsMessage" class="text-center py-12 hidden">
                <div class="inline-flex items-center justify-center w-16 h-16 bg-gray-100 rounded-full mb-4">
                    <i class="fas fa-search text-gray-400 text-2xl"></i>
                </div>
                <h3 class="text-lg font-medium text-gray-900 mb-2">No users found</h3>
                <p class="text-gray-500">Try adjusting your search or filter criteria.</p>
            </div>
        @else
            <!-- Empty State -->
            <div class="text-center py-12">
                <div class="inline-flex items-center justify-center w-16 h-16 bg-gray-100 rounded-full mb-4">
                    <i class="fas fa-users text-gray-400 text-2xl"></i>
                </div>
                <h3 class="text-lg font-medium text-gray-900 mb-2">No users found</h3>
                <p class="text-gray-500 mb-6">Get started by adding your first user.</p>
                <a href="{{ route('users.create') }}"
                   class="inline-flex items-center bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-medium transition-colors">
                    <i class="fas fa-plus mr-2"></i>
                    Add Your First User
                </a>
            </div>
        @endif
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteConfirmationModal" class="fixed inset-0 bg-gray-500 bg-opacity-75 z-50 hidden flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl max-w-sm w-full mx-4">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">Delete User</h3>
        </div>
        <div class="px-6 py-4">
            <p class="text-gray-600">Are you sure you want to delete <strong id="deleteUserName"></strong>? This action cannot be undone.</p>
            <input type="hidden" id="deleteUserId">
        </div>
        <div class="px-6 py-4 border-t border-gray-200 flex justify-end space-x-3">
            <button onclick="closeDeleteConfirmation()" 
                    class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                Cancel
            </button>
            <button onclick="confirmDelete()" 
                    class="px-4 py-2 text-sm font-medium text-white bg-red-600 border border-transparent rounded-lg hover:bg-red-700">
                Delete
            </button>
        </div>
    </div>
</div>

<!-- Reset Password Modal -->
<div id="resetPasswordModal" class="fixed inset-0 bg-gray-500 bg-opacity-75 z-50 hidden flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl max-w-sm w-full mx-4">
        <form id="resetPasswordForm" method="POST" action="">
            @csrf
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Reset Password</h3>
            </div>
            <div class="px-6 py-4 space-y-4">
                <div class="text-sm text-gray-600 space-y-1">
                    <p><span class="font-medium text-gray-800">User:</span> <span id="resetPasswordUserName"></span></p>
                    <p><span class="font-medium text-gray-800">Role:</span> <span id="resetPasswordUserRole"></span></p>
                    <p><span class="font-medium text-gray-800">Email:</span> <span id="resetPasswordUserEmail"></span></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
                    <input type="password" name="password" required minlength="8"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Confirm Password</label>
                    <input type="password" name="password_confirmation" required minlength="8"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
            </div>
            <div class="px-6 py-4 border-t border-gray-200 flex justify-end space-x-3">
                <button type="button" onclick="closeResetPasswordModal()"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                    Cancel
                </button>
                <button type="submit"
                        class="px-4 py-2 text-sm font-medium text-white bg-gray-800 border border-transparent rounded-lg hover:bg-gray-900">
                    Reset Password
                </button>
            </div>
        </form>
    </div>
</div>


@endsection

@push('scripts')
<script>
// Delete confirmation modal
function openDeleteConfirmation(userId, userName) {
    document.getElementById('deleteUserId').value = userId;
    document.getElementById('deleteUserName').textContent = userName;
    document.getElementById('deleteConfirmationModal').classList.remove('hidden');
}

function confirmDelete() {
    const userId = document.getElementById('deleteUserId').value;
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = `/users/${userId}`;
    form.innerHTML = `
        <input type="hidden" name="_token" value="{{ csrf_token() }}">
        <input type="hidden" name="_method" value="DELETE">
    `;
    document.body.appendChild(form);
    form.submit();
}

function closeDeleteConfirmation() {
    document.getElementById('deleteConfirmationModal').classList.add('hidden');
}

// Reset Password modal
function openResetPasswordModal(userId) {
    const user = usersData.find(u => u.id === userId);
    if (!user) return;

    document.getElementById('resetPasswordUserName').textContent = user.name;
    document.getElementById('resetPasswordUserRole').textContent = user.role || '-';
    document.getElementById('resetPasswordUserEmail').textContent = user.email;

    const form = document.getElementById('resetPasswordForm');
    form.action = `/users/${userId}/reset-password`;
    form.reset();

    document.getElementById('resetPasswordModal').classList.remove('hidden');
}

function closeResetPasswordModal() {
    document.getElementById('resetPasswordModal').classList.add('hidden');
}

// User data for JavaScript operations
const usersData = {!! json_encode($users->map(function($user) {
    return [
        'id' => $user->id,
        'name' => $user->name,
        'email' => $user->email,
        'phone' => $user->phone,
        'role' => $user->roles->first()->name ?? '',
        'status' => $user->status,
        'avatar' => $user->avatar ? asset('storage/' . $user->avatar) : null,
    ];
})) !!};

const currentUserId = {{ auth()->id() }};

// Filter functions
function filterUsers() {
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();
    const roleFilter = document.getElementById('roleFilter').value;
    const statusFilter = document.getElementById('statusFilter').value;
    
    const rows = document.querySelectorAll('.user-row');
    let visibleCount = 0;
    
    rows.forEach(row => {
        const name = row.dataset.name;
        const email = row.dataset.email;
        const phone = row.dataset.phone;
        const role = row.dataset.role;
        const status = row.dataset.status;
        
        const matchesSearch = !searchTerm || 
            name.includes(searchTerm) || 
            email.includes(searchTerm) || 
            phone.includes(searchTerm);
        
        const matchesRole = !roleFilter || role === roleFilter;
        const matchesStatus = !statusFilter || status === statusFilter;
        
        if (matchesSearch && matchesRole && matchesStatus) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });
    
    // Show/hide no results message
    const noResultsMessage = document.getElementById('noResultsMessage');
    if (visibleCount === 0 && rows.length > 0) {
        noResultsMessage.classList.remove('hidden');
    } else {
        noResultsMessage.classList.add('hidden');
    }
}

function clearFilters() {
    document.getElementById('searchInput').value = '';
    document.getElementById('roleFilter').value = '';
    document.getElementById('statusFilter').value = '';
    filterUsers();
}

function exportUsers() {
    // Get visible rows
    const visibleRows = Array.from(document.querySelectorAll('.user-row')).filter(row => row.style.display !== 'none');
    
    if (visibleRows.length === 0) {
        alert('No users to export');
        return;
    }
    
    // Create CSV content
    const headers = ['Full Name', 'Email Address', 'Phone Number', 'Role', 'Status'];
    let csvContent = headers.join(',') + '\n';
    
    visibleRows.forEach(row => {
        const cells = row.querySelectorAll('td');
        const rowData = [
            cells[1].textContent.trim(), // Full Name
            cells[2].textContent.trim(), // Email
            cells[3].textContent.trim(), // Phone
            cells[4].textContent.trim(), // Role
            cells[5].textContent.trim()  // Status
        ];
        csvContent += rowData.map(field => `"${field}"`).join(',') + '\n';
    });
    
    // Download CSV
    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'users_export.csv';
    a.click();
    window.URL.revokeObjectURL(url);
}

// Event listeners
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('searchInput').addEventListener('input', filterUsers);
    document.getElementById('roleFilter').addEventListener('change', filterUsers);
    document.getElementById('statusFilter').addEventListener('change', filterUsers);
    
});

function showSuccessMessage(message) {
    const alertDiv = document.createElement('div');
    alertDiv.className = 'fixed top-4 right-4 bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-lg shadow-lg z-50';
    alertDiv.innerHTML = `
        <div class="flex items-center">
            <i class="fas fa-check-circle mr-3"></i>
            <p class="font-medium">${message}</p>
        </div>
    `;
    document.body.appendChild(alertDiv);
    setTimeout(() => alertDiv.remove(), 3000);
}

</script>
@endpush