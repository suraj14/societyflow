@extends('layouts.app')

@section('title', 'Apartment Assignments')
@section('page-title', 'Apartment Assignments')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Apartment Assignments</h1>
            <p class="text-gray-600 mt-1">Manage apartment assignments for apartment owners</p>
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
        <!-- Unassigned Apartment Owners -->
        <div class="bg-white rounded-lg shadow-md">
            <div class="p-6 border-b border-gray-200">
                <h2 class="text-xl font-semibold text-gray-900 flex items-center">
                    <i class="fas fa-user-times text-red-500 mr-2"></i>
                    Unassigned Apartment Owners ({{ $unassignedApartmentOwners->count() }})
                </h2>
                <p class="text-gray-600 text-sm mt-1">Apartment owners who need apartment assignments</p>
            </div>
            <div class="p-6">
                @if($unassignedApartmentOwners->count() > 0)
                    <div class="space-y-4">
                        @foreach($unassignedApartmentOwners as $user)
                        <div class="border border-gray-200 rounded-lg p-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="font-semibold text-gray-900">{{ $user->name }}</h3>
                                    <p class="text-sm text-gray-600">{{ $user->email }}</p>
                                    <p class="text-sm text-gray-600">{{ $user->phone }}</p>
                                    <span class="inline-block bg-red-100 text-red-800 text-xs px-2 py-1 rounded-full mt-1">
                                        No Apartment Assigned
                                    </span>
                                </div>
                                <button onclick="openAssignModal({{ $user->id }}, '{{ $user->name }}')" 
                                        class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded text-sm transition-colors">
                                    Assign Apartment
                                </button>
                            </div>
                        </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-8">
                        <i class="fas fa-check-circle text-green-500 text-4xl mb-3"></i>
                        <p class="text-gray-600">All apartment owners have been assigned apartments!</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Available Apartments -->
        <div class="bg-white rounded-lg shadow-md">
            <div class="p-6 border-b border-gray-200">
                <h2 class="text-xl font-semibold text-gray-900 flex items-center">
                    <i class="fas fa-door-open text-green-500 mr-2"></i>
                    Available Apartments ({{ $availableApartments->count() }})
                </h2>
                <p class="text-gray-600 text-sm mt-1">Apartments available for assignment</p>
            </div>
            <div class="p-6">
                @if($availableApartments->count() > 0)
                    <div class="space-y-4">
                        @foreach($availableApartments as $apartment)
                        <div class="border border-gray-200 rounded-lg p-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="font-semibold text-gray-900">{{ $apartment->flat_number }}</h3>
                                    <p class="text-sm text-gray-600">{{ $apartment->building->name ?? 'No Building' }}</p>
                                    <p class="text-sm text-gray-600">{{ $apartment->bedrooms }} BR, {{ $apartment->bathrooms }} BA</p>
                                    <p class="text-sm text-gray-600">Area: {{ number_format($apartment->carpet_area) }} sqft</p>
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
                        <i class="fas fa-door-open text-gray-400 text-4xl mb-3"></i>
                        <p class="text-gray-600">No apartments available for assignment</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Assigned Apartment Owners -->
    @if($assignedApartmentOwners->count() > 0)
    <div class="mt-6 bg-white rounded-lg shadow-md">
        <div class="p-6 border-b border-gray-200">
            <h2 class="text-xl font-semibold text-gray-900 flex items-center">
                <i class="fas fa-user-check text-blue-500 mr-2"></i>
                Assigned Apartment Owners ({{ $assignedApartmentOwners->count() }})
            </h2>
            <p class="text-gray-600 text-sm mt-1">Apartment owners with assigned apartments</p>
        </div>
        <div class="p-6">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Owner</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Apartment</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Building</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Details</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($assignedApartmentOwners as $user)
                        <tr>
                            <td class="px-4 py-2">
                                <div>
                                    <div class="font-medium text-gray-900">{{ $user->name }}</div>
                                    <div class="text-sm text-gray-600">{{ $user->email }}</div>
                                    <div class="text-sm text-gray-600">{{ $user->phone }}</div>
                                </div>
                            </td>
                            <td class="px-4 py-2">
                                <div class="font-medium text-gray-900">{{ $user->ownedFlat->flat_number }}</div>
                                <span class="inline-block bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded-full">
                                    Assigned
                                </span>
                            </td>
                            <td class="px-4 py-2 text-sm text-gray-900">
                                {{ $user->ownedFlat->building->name ?? 'No Building' }}
                            </td>
                            <td class="px-4 py-2 text-sm text-gray-900">
                                {{ $user->ownedFlat->bedrooms }} BR, {{ $user->ownedFlat->bathrooms }} BA<br>
                                Area: {{ number_format($user->ownedFlat->carpet_area) }} sqft
                            </td>
                            <td class="px-4 py-2">
                                <div class="flex space-x-2">
                                    <button onclick="openReassignModal({{ $user->id }}, '{{ $user->name }}', '{{ $user->ownedFlat->flat_number }}')" 
                                            class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                                        Reassign
                                    </button>
                                    <button onclick="confirmUnassign({{ $user->id }}, '{{ $user->name }}', '{{ $user->ownedFlat->flat_number }}')" 
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

<!-- Assign Apartment Modal -->
<div id="assignModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Assign Apartment</h3>
                <form id="assignForm" method="POST" action="{{ route('admin.apartment-assignments.assign') }}">
                    @csrf
                    <input type="hidden" id="assign_user_id" name="user_id">
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Apartment Owner</label>
                        <p id="assign_user_name" class="text-gray-900 font-medium"></p>
                    </div>
                    
                    <div class="mb-4">
                        <label for="apartment_id" class="block text-sm font-medium text-gray-700 mb-2">Select Apartment</label>
                        <select name="apartment_id" id="apartment_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                            <option value="">Choose an apartment...</option>
                            @foreach($availableApartments as $apartment)
                            <option value="{{ $apartment->id }}">
                                {{ $apartment->flat_number }} - {{ $apartment->building->name ?? 'No Building' }} ({{ $apartment->bedrooms }}BR)
                            </option>
                            @endforeach
                        </select>
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeAssignModal()" class="px-4 py-2 text-gray-600 hover:text-gray-800">
                            Cancel
                        </button>
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                            Assign Apartment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Reassign Apartment Modal -->
<div id="reassignModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Reassign Apartment</h3>
                <form id="reassignForm" method="POST" action="{{ route('admin.apartment-assignments.reassign') }}">
                    @csrf
                    <input type="hidden" id="reassign_user_id" name="current_user_id">
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Apartment Owner</label>
                        <p id="reassign_user_name" class="text-gray-900 font-medium"></p>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Current Apartment</label>
                        <p id="reassign_current_apartment" class="text-gray-600"></p>
                    </div>
                    
                    <div class="mb-4">
                        <label for="new_apartment_id" class="block text-sm font-medium text-gray-700 mb-2">New Apartment</label>
                        <select name="new_apartment_id" id="new_apartment_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                            <option value="">Choose a new apartment...</option>
                            @foreach($availableApartments as $apartment)
                            <option value="{{ $apartment->id }}">
                                {{ $apartment->flat_number }} - {{ $apartment->building->name ?? 'No Building' }} ({{ $apartment->bedrooms }}BR)
                            </option>
                            @endforeach
                        </select>
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeReassignModal()" class="px-4 py-2 text-gray-600 hover:text-gray-800">
                            Cancel
                        </button>
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                            Reassign Apartment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Unassign Form (Hidden) -->
<form id="unassignForm" method="POST" action="{{ route('admin.apartment-assignments.unassign') }}" style="display: none;">
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

function openReassignModal(userId, userName, currentApartment) {
    document.getElementById('reassign_user_id').value = userId;
    document.getElementById('reassign_user_name').textContent = userName;
    document.getElementById('reassign_current_apartment').textContent = currentApartment;
    document.getElementById('reassignModal').classList.remove('hidden');
}

function closeReassignModal() {
    document.getElementById('reassignModal').classList.add('hidden');
}

function confirmUnassign(userId, userName, apartmentNumber) {
    if (confirm(`Are you sure you want to unassign apartment "${apartmentNumber}" from "${userName}"? This action cannot be undone.`)) {
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