@extends('layouts.app')

@section('title', 'Villa Assignments')
@section('page-title', 'Villa Assignments')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Villa Assignments</h1>
            <p class="text-gray-600 mt-1">Manage villa assignments for villa owners</p>
        </div>
    </div>

    <!-- Success/Error Messages -->
    @if(session('success'))
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
        {{ session('success') }}
    </div>
    @endif

    @if(session('error'))
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
        {{ session('error') }}
    </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Unassigned Villa Owners -->
        <div class="bg-white rounded-lg shadow-md">
            <div class="p-6 border-b border-gray-200">
                <h2 class="text-xl font-semibold text-gray-900 flex items-center">
                    <i class="fas fa-user-times text-red-500 mr-2"></i>
                    Unassigned Villa Owners ({{ $unassignedVillaOwners->count() }})
                </h2>
                <p class="text-gray-600 text-sm mt-1">Villa owners who need villa assignments</p>
            </div>
            <div class="p-6">
                @if($unassignedVillaOwners->count() > 0)
                    <div class="space-y-4">
                        @foreach($unassignedVillaOwners as $user)
                        <div class="border border-gray-200 rounded-lg p-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="font-semibold text-gray-900">{{ $user->name }}</h3>
                                    <p class="text-sm text-gray-600">{{ $user->email }}</p>
                                    <p class="text-sm text-gray-600">{{ $user->phone }}</p>
                                    <span class="inline-block bg-red-100 text-red-800 text-xs px-2 py-1 rounded-full mt-1">
                                        No Villa Assigned
                                    </span>
                                </div>
                                <button onclick="openAssignModal({{ $user->id }}, '{{ $user->name }}')" 
                                        class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded text-sm transition-colors">
                                    Assign Villa
                                </button>
                            </div>
                        </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-8">
                        <i class="fas fa-check-circle text-green-500 text-4xl mb-3"></i>
                        <p class="text-gray-600">All villa owners have been assigned villas!</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Available Villas -->
        <div class="bg-white rounded-lg shadow-md">
            <div class="p-6 border-b border-gray-200">
                <h2 class="text-xl font-semibold text-gray-900 flex items-center">
                    <i class="fas fa-home text-green-500 mr-2"></i>
                    Available Villas ({{ $availableVillas->count() }})
                </h2>
                <p class="text-gray-600 text-sm mt-1">Villas available for assignment</p>
            </div>
            <div class="p-6">
                @if($availableVillas->count() > 0)
                    <div class="space-y-4">
                        @foreach($availableVillas as $villa)
                        <div class="border border-gray-200 rounded-lg p-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="font-semibold text-gray-900">{{ $villa->villa_name ?: $villa->flat_number }}</h3>
                                    <p class="text-sm text-gray-600">{{ $villa->villaArea->name ?? 'No Area' }}</p>
                                    <p class="text-sm text-gray-600">{{ $villa->bedrooms }} BR, {{ $villa->bathrooms }} BA</p>
                                    <p class="text-sm text-gray-600">Plot: {{ number_format($villa->plot_area) }} sqft</p>
                                    <span class="inline-block bg-green-100 text-green-800 text-xs px-2 py-1 rounded-full mt-1">
                                        Available
                                    </span>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-8">
                        <i class="fas fa-home text-gray-400 text-4xl mb-3"></i>
                        <p class="text-gray-600">No villas available for assignment</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Assigned Villa Owners -->
    @if($assignedVillaOwners->count() > 0)
    <div class="mt-6 bg-white rounded-lg shadow-md">
        <div class="p-6 border-b border-gray-200">
            <h2 class="text-xl font-semibold text-gray-900 flex items-center">
                <i class="fas fa-user-check text-blue-500 mr-2"></i>
                Assigned Villa Owners ({{ $assignedVillaOwners->count() }})
            </h2>
            <p class="text-gray-600 text-sm mt-1">Villa owners with assigned villas</p>
        </div>
        <div class="p-6">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Owner</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Villa</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Villa Area</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Details</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($assignedVillaOwners as $user)
                        <tr>
                            <td class="px-4 py-2">
                                <div>
                                    <div class="font-medium text-gray-900">{{ $user->name }}</div>
                                    <div class="text-sm text-gray-600">{{ $user->email }}</div>
                                    <div class="text-sm text-gray-600">{{ $user->phone }}</div>
                                </div>
                            </td>
                            <td class="px-4 py-2">
                                <div class="font-medium text-gray-900">{{ $user->ownedVilla->villa_name ?: $user->ownedVilla->flat_number }}</div>
                                <span class="inline-block bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded-full">
                                    Assigned
                                </span>
                            </td>
                            <td class="px-4 py-2 text-sm text-gray-900">
                                {{ $user->ownedVilla->villaArea->name ?? 'No Area' }}
                            </td>
                            <td class="px-4 py-2 text-sm text-gray-900">
                                {{ $user->ownedVilla->bedrooms }} BR, {{ $user->ownedVilla->bathrooms }} BA<br>
                                Plot: {{ number_format($user->ownedVilla->plot_area) }} sqft
                            </td>
                            <td class="px-4 py-2">
                                <div class="flex space-x-2">
                                    <button onclick="openReassignModal({{ $user->id }}, '{{ $user->name }}', '{{ $user->ownedVilla->villa_name ?: $user->ownedVilla->flat_number }}')" 
                                            class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                                        Reassign
                                    </button>
                                    <button onclick="confirmUnassign({{ $user->id }}, '{{ $user->name }}', '{{ $user->ownedVilla->villa_name ?: $user->ownedVilla->flat_number }}')" 
                                            class="text-red-600 hover:text-red-800 text-sm font-medium">
                                        Unassign
                                    </button>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif
</div>

<!-- Assign Villa Modal -->
<div id="assignModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Assign Villa</h3>
                <form id="assignForm" method="POST" action="{{ route('admin.villa-assignments.assign') }}">
                    @csrf
                    <input type="hidden" id="assign_user_id" name="user_id">
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Villa Owner</label>
                        <p id="assign_user_name" class="text-gray-900 font-medium"></p>
                    </div>
                    
                    <div class="mb-4">
                        <label for="villa_id" class="block text-sm font-medium text-gray-700 mb-2">Select Villa</label>
                        <select name="villa_id" id="villa_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                            <option value="">Choose a villa...</option>
                            @foreach($availableVillas as $villa)
                            <option value="{{ $villa->id }}">
                                {{ $villa->villa_name ?: $villa->flat_number }} - {{ $villa->villaArea->name ?? 'No Area' }} ({{ $villa->bedrooms }}BR)
                            </option>
                            @endforeach
                        </select>
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeAssignModal()" class="px-4 py-2 text-gray-600 hover:text-gray-800">
                            Cancel
                        </button>
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                            Assign Villa
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Reassign Villa Modal -->
<div id="reassignModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Reassign Villa</h3>
                <form id="reassignForm" method="POST" action="{{ route('admin.villa-assignments.reassign') }}">
                    @csrf
                    <input type="hidden" id="reassign_user_id" name="current_user_id">
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Villa Owner</label>
                        <p id="reassign_user_name" class="text-gray-900 font-medium"></p>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Current Villa</label>
                        <p id="reassign_current_villa" class="text-gray-600"></p>
                    </div>
                    
                    <div class="mb-4">
                        <label for="new_villa_id" class="block text-sm font-medium text-gray-700 mb-2">New Villa</label>
                        <select name="new_villa_id" id="new_villa_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                            <option value="">Choose a new villa...</option>
                            @foreach($availableVillas as $villa)
                            <option value="{{ $villa->id }}">
                                {{ $villa->villa_name ?: $villa->flat_number }} - {{ $villa->villaArea->name ?? 'No Area' }} ({{ $villa->bedrooms }}BR)
                            </option>
                            @endforeach
                        </select>
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeReassignModal()" class="px-4 py-2 text-gray-600 hover:text-gray-800">
                            Cancel
                        </button>
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                            Reassign Villa
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Unassign Form (Hidden) -->
<form id="unassignForm" method="POST" action="{{ route('admin.villa-assignments.unassign') }}" style="display: none;">
    @csrf
    <input type="hidden" id="unassign_user_id" name="user_id">
</form>

<script>
function openAssignModal(userId, userName) {
    document.getElementById('assign_user_id').value = userId;
    document.getElementById('assign_user_name').textContent = userName;
    document.getElementById('assignModal').classList.remove('hidden');
}

function closeAssignModal() {
    document.getElementById('assignModal').classList.add('hidden');
}

function openReassignModal(userId, userName, currentVilla) {
    document.getElementById('reassign_user_id').value = userId;
    document.getElementById('reassign_user_name').textContent = userName;
    document.getElementById('reassign_current_villa').textContent = currentVilla;
    document.getElementById('reassignModal').classList.remove('hidden');
}

function closeReassignModal() {
    document.getElementById('reassignModal').classList.add('hidden');
}

function confirmUnassign(userId, userName, villaName) {
    if (confirm(`Are you sure you want to unassign villa "${villaName}" from "${userName}"? This action cannot be undone.`)) {
        document.getElementById('unassign_user_id').value = userId;
        document.getElementById('unassignForm').submit();
    }
}

// Close modals when clicking outside
document.getElementById('assignModal').addEventListener('click', function(e) {
    if (e.target === this) closeAssignModal();
});

document.getElementById('reassignModal').addEventListener('click', function(e) {
    if (e.target === this) closeReassignModal();
});
</script>
@endsection