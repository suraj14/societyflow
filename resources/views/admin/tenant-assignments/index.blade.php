@extends('layouts.app')

@section('title', 'Tenant Assignments')
@section('page-title', 'Tenant Assignments')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Tenant Assignments</h1>
            <p class="text-gray-600 mt-1">Manage unit assignments for tenants</p>
        </div>
    </div>

    <!-- Success/Error Messages -->


    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Unassigned Tenants -->
        <div class="bg-white rounded-lg shadow-md">
            <div class="p-6 border-b border-gray-200">
                <h2 class="text-xl font-semibold text-gray-900 flex items-center">
                    <i class="fas fa-user-times text-red-500 mr-2"></i>
                    Unassigned Tenants ({{ $unassignedTenants->count() }})
                </h2>
                <p class="text-gray-600 text-sm mt-1">Tenants who need unit assignments</p>
            </div>
            <div class="p-6">
                @if($unassignedTenants->count() > 0)
                    <div class="space-y-4">
                        @foreach($unassignedTenants as $tenant)
                        <div class="border border-gray-200 rounded-lg p-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="font-semibold text-gray-900">{{ $tenant->name }}</h3>
                                    <p class="text-sm text-gray-600">{{ $tenant->email }}</p>
                                    <p class="text-sm text-gray-600">{{ $tenant->phone }}</p>
                                    <span class="inline-block bg-red-100 text-red-800 text-xs px-2 py-1 rounded-full mt-1">
                                        No Unit Assigned
                                    </span>
                                </div>
                                <button onclick="openAssignModal({{ $tenant->id }}, '{{ $tenant->name }}')"
                                        class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded text-sm transition-colors">
                                    Assign Unit
                                </button>
                            </div>
                        </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-8">
                        <i class="fas fa-check-circle text-green-500 text-4xl mb-3"></i>
                        <p class="text-gray-600">All tenants have been assigned units!</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Available Units -->
        <div class="bg-white rounded-lg shadow-md">
            <div class="p-6 border-b border-gray-200">
                <h2 class="text-xl font-semibold text-gray-900 flex items-center">
                    <i class="fas fa-home text-green-500 mr-2"></i>
                    Available Units ({{ $availableUnits->count() }})
                </h2>
                <p class="text-gray-600 text-sm mt-1">Units available for tenant assignment</p>
            </div>
            <div class="p-6">
                @if($availableUnits->count() > 0)
                    <div class="space-y-4">
                        @foreach($availableUnits as $unit)
                        <div class="border border-gray-200 rounded-lg p-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="font-semibold text-gray-900">{{ $unit->flat_number ?? $unit->villa_name }}</h3>
                                    <p class="text-sm text-gray-600">{{ $unit->building->name ?? $unit->villaArea->name ?? 'No Area' }}</p>
                                    @if($unit->bedrooms || $unit->bathrooms)
                                        <p class="text-sm text-gray-600">{{ $unit->bedrooms }} BR, {{ $unit->bathrooms }} BA</p>
                                    @endif
                                    @if($unit->carpet_area)
                                        <p class="text-sm text-gray-600">Area: {{ number_format($unit->carpet_area) }} sqft</p>
                                    @endif
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
                        <p class="text-gray-600">No units available for tenant assignment</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Assigned Tenants -->
    @if($assignedTenants->count() > 0)
    <div class="mt-6 bg-white rounded-lg shadow-md">
        <div class="p-6 border-b border-gray-200">
            <h2 class="text-xl font-semibold text-gray-900 flex items-center">
                <i class="fas fa-user-check text-blue-500 mr-2"></i>
                Assigned Tenants ({{ $assignedTenants->count() }})
            </h2>
            <p class="text-gray-600 text-sm mt-1">Tenants with assigned units</p>
        </div>
        <div class="p-6">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Tenant</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Unit</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Location</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Move-in Date</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($assignedTenants as $tenant)
                        <tr>
                            <td class="px-4 py-2">
                                <div>
                                    <div class="font-medium text-gray-900">{{ $tenant->name }}</div>
                                    <div class="text-sm text-gray-600">{{ $tenant->email }}</div>
                                    <div class="text-sm text-gray-600">{{ $tenant->phone }}</div>
                                </div>
                            </td>
                            <td class="px-4 py-2">
                                <div class="font-medium text-gray-900">{{ $tenant->flat->flat_number ?? $tenant->flat->villa_name }}</div>
                                <span class="inline-block bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded-full">
                                    Assigned
                                </span>
                            </td>
                            <td class="px-4 py-2 text-sm text-gray-900">
                                {{ $tenant->flat->building->name ?? $tenant->flat->villaArea->name ?? 'No Area' }}
                            </td>
                            <td class="px-4 py-2 text-sm text-gray-900">
                                {{ $tenant->move_in_date ? $tenant->move_in_date->format('M d, Y') : 'N/A' }}
                            </td>
                            <td class="px-4 py-2">
                                <div class="flex space-x-2">
                                    <button onclick="openReassignModal({{ $tenant->id }}, '{{ $tenant->name }}', '{{ $tenant->flat->flat_number ?? $tenant->flat->villa_name }}')"
                                            class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                                        Reassign
                                    </button>
                                    <button onclick="confirmUnassign({{ $tenant->id }}, '{{ $tenant->name }}', '{{ $tenant->flat->flat_number ?? $tenant->flat->villa_name }}')"
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

<!-- Assign Unit Modal -->
<div id="assignModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Assign Unit to Tenant</h3>
                <form id="assignForm" method="POST" action="{{ route('admin.tenant-assignments.assign') }}">
                    @csrf
                    <input type="hidden" id="assign_tenant_id" name="tenant_id">

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Tenant</label>
                        <p id="assign_tenant_name" class="text-gray-900 font-medium"></p>
                    </div>
                    
                    <div class="mb-4">
                        <label for="unit_id" class="block text-sm font-medium text-gray-700 mb-2">Select Unit</label>
                        <select name="unit_id" id="unit_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                            <option value="">Choose a unit...</option>
                            @foreach($availableUnits as $unit)
                            <option value="{{ $unit->id }}">
                                {{ $unit->flat_number ?? $unit->villa_name }} - {{ $unit->building->name ?? $unit->villaArea->name ?? 'No Area' }}
                                @if($unit->bedrooms) ({{ $unit->bedrooms }}BR) @endif
                            </option>
                            @endforeach
                        </select>
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeAssignModal()" class="px-4 py-2 text-gray-600 hover:text-gray-800">
                            Cancel
                        </button>
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                            Assign Unit
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Reassign Unit Modal -->
<div id="reassignModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Reassign Tenant Unit</h3>
                <form id="reassignForm" method="POST" action="{{ route('admin.tenant-assignments.reassign') }}">
                    @csrf
                    <input type="hidden" id="reassign_tenant_id" name="current_tenant_id">

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Tenant</label>
                        <p id="reassign_tenant_name" class="text-gray-900 font-medium"></p>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Current Unit</label>
                        <p id="reassign_current_unit" class="text-gray-600"></p>
                    </div>
                    
                    <div class="mb-4">
                        <label for="new_unit_id" class="block text-sm font-medium text-gray-700 mb-2">New Unit</label>
                        <select name="new_unit_id" id="new_unit_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                            <option value="">Choose a new unit...</option>
                            @foreach($availableUnits as $unit)
                            <option value="{{ $unit->id }}">
                                {{ $unit->flat_number ?? $unit->villa_name }} - {{ $unit->building->name ?? $unit->villaArea->name ?? 'No Area' }}
                                @if($unit->bedrooms) ({{ $unit->bedrooms }}BR) @endif
                            </option>
                            @endforeach
                        </select>
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeReassignModal()" class="px-4 py-2 text-gray-600 hover:text-gray-800">
                            Cancel
                        </button>
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                            Reassign Unit
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Unassign Form (Hidden) -->
<form id="unassignForm" method="POST" action="{{ route('admin.tenant-assignments.unassign') }}" style="display: none;">
    @csrf
    <input type="hidden" id="unassign_tenant_id" name="tenant_id">
</form>

<script>
function openAssignModal(tenantId, tenantName) {
    document.getElementById('assign_tenant_id').value = tenantId;
    document.getElementById('assign_tenant_name').textContent = tenantName;
    document.getElementById('assignModal').classList.remove('hidden');
}

function closeAssignModal() {
    document.getElementById('assignModal').classList.add('hidden');
}

function openReassignModal(tenantId, tenantName, currentUnit) {
    document.getElementById('reassign_tenant_id').value = tenantId;
    document.getElementById('reassign_tenant_name').textContent = tenantName;
    document.getElementById('reassign_current_unit').textContent = currentUnit;
    document.getElementById('reassignModal').classList.remove('hidden');
}

function closeReassignModal() {
    document.getElementById('reassignModal').classList.add('hidden');
}

function confirmUnassign(tenantId, tenantName, unitName) {
    if (confirm(`Are you sure you want to unassign unit "${unitName}" from tenant "${tenantName}"? This will move the tenant out and preserve their history.`)) {
        document.getElementById('unassign_tenant_id').value = tenantId;
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