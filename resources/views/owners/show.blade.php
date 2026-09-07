@extends('layouts.app')

@section('title', 'Owner Details')
@section('page-title', $owner->name)

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="mb-6">
        <a href="{{ route('owners.index') }}" class="text-blue-600 hover:text-blue-800 text-sm">
            <i class="fas fa-arrow-left mr-1"></i> Back to Owners
        </a>
    </div>

    <!-- Owner Header Card -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <div class="flex items-start justify-between">
            <div class="flex items-start space-x-4">
                <!-- Avatar/Profile Image -->
                @if($owner->profile_image)
                    <img src="{{ asset('storage/' . $owner->profile_image) }}" alt="{{ $owner->name }}" class="w-20 h-20 rounded-lg object-cover">
                @else
                    <div class="w-20 h-20 bg-gradient-to-br from-blue-400 to-blue-600 rounded-lg flex items-center justify-center text-white text-2xl font-bold">
                        {{ strtoupper(substr($owner->name, 0, 1)) }}
                    </div>
                @endif
                
                <!-- Owner Info -->
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">{{ $owner->name }}</h1>
                    <div class="mt-2 space-y-1">
                        <p class="text-sm text-gray-600">
                            <strong>Full Name</strong><br>
                            {{ $owner->name }}
                        </p>
                        <p class="text-sm text-gray-600">
                            <strong>Email Address</strong><br>
                            {{ $owner->email }}
                        </p>
                        <p class="text-sm text-gray-600">
                            <strong>Phone Number</strong><br>
                            {{ $owner->phone ?? '-' }}
                        </p>
                    </div>
                </div>

                <!-- Status Badge -->
                <div class="ml-auto">
                    <span class="inline-block px-3 py-1 text-sm font-semibold rounded-full 
                        {{ $owner->status === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                        {{ ucfirst($owner->status) }}
                    </span>
                </div>
            </div>

            <!-- Edit Button -->
            <a href="{{ route('owners.edit', $owner) }}" class="text-blue-600 hover:text-blue-800">
                <i class="fas fa-edit text-xl"></i>
            </a>
        </div>

        <!-- Property Details Row -->
        <div class="mt-6 pt-6 border-t border-gray-200 grid grid-cols-2 md:grid-cols-4 gap-4">
            @if($owner->property_type === 'apartment')
                <div>
                    <p class="text-xs text-gray-600 uppercase tracking-wide">Apartment Number</p>
                    <p class="text-lg font-semibold text-gray-900">{{ $owner->flat_no ?? '-' }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-600 uppercase tracking-wide">Parking Code</p>
                    <p class="text-lg font-semibold text-gray-900">{{ $owner->building_id ?? '-' }}</p>
                </div>
            @elseif($owner->property_type === 'villa')
                <div>
                    <p class="text-xs text-gray-600 uppercase tracking-wide">Villa Number</p>
                    <p class="text-lg font-semibold text-gray-900">{{ $owner->villa_no ?? '-' }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-600 uppercase tracking-wide">Villa Area</p>
                    <p class="text-lg font-semibold text-gray-900">{{ $owner->villaArea->name ?? '-' }}</p>
                </div>
            @endif
        </div>
    </div>

    <!-- Tabs -->
    <div class="bg-white rounded-lg shadow-md">
        <div class="border-b border-gray-200">
            <div class="flex space-x-8 px-6">
                <button class="tab-button active py-4 px-1 border-b-2 border-blue-600 text-blue-600 font-medium" data-tab="apartment">
                    {{ $owner->property_type === 'apartment' ? 'Apartment' : 'Villa' }}
                </button>
                <button class="tab-button py-4 px-1 border-b-2 border-transparent text-gray-600 hover:text-gray-900 font-medium" data-tab="tenant">
                    Tenant
                </button>
                <button class="tab-button py-4 px-1 border-b-2 border-transparent text-gray-600 hover:text-gray-900 font-medium" data-tab="family">
                    Family Member
                </button>
                <button class="tab-button py-4 px-1 border-b-2 border-transparent text-gray-600 hover:text-gray-900 font-medium" data-tab="documents">
                    Owner Documents
                </button>
            </div>
        </div>

        <!-- Tab Content -->
        <div class="p-6">
            <!-- Apartment/Villa Tab -->
            <div id="apartment-tab" class="tab-content">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">#</th>
                                @if($owner->property_type === 'apartment')
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Apartment Number</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Apartment Area</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Apartment Type</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Tower Name</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Floors</th>
                                @else
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Villa Number</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Villa Area</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Villa Type</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Plot Area</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Built-up Area</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">1</td>
                                @if($owner->property_type === 'apartment')
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $owner->flat_no ?? '-' }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $apartmentFlat->carpet_area ?? '-' }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $apartmentFlat->type ?? '-' }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $owner->building->name ?? '-' }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $owner->floor ?? '-' }}</td>
                                @else
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $owner->villa_no ?? '-' }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $owner->villaArea->name ?? '-' }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $villaFlat->type ?? '-' }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $villaFlat->plot_area ?? '-' }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $villaFlat->built_up_area ?? '-' }}</td>
                                @endif
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Tenant Tab -->
            <div id="tenant-tab" class="tab-content hidden">
                <div class="text-center py-8">
                    <p class="text-gray-500">No tenant information available</p>
                </div>
            </div>

            <!-- Family Member Tab -->
            <div id="family-tab" class="tab-content hidden">
                @if($owner->family_members && count($owner->family_members) > 0)
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">#</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Name</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Relationship</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Phone</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($owner->family_members as $index => $member)
                                    @if($member['name'] || $member['relationship'] || $member['phone'])
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $index + 1 }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $member['name'] ?? '-' }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $member['relationship'] ?? '-' }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $member['phone'] ?? '-' }}</td>
                                        </tr>
                                    @endif
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center py-8">
                        <p class="text-gray-500">No family member information available</p>
                    </div>
                @endif
            </div>

            <!-- Owner Documents Tab -->
            <div id="documents-tab" class="tab-content hidden">
                @if($owner->document_path)
                    <div class="space-y-4">
                        <div class="flex items-center justify-between p-4 border border-gray-200 rounded-lg">
                            <div class="flex items-center space-x-3">
                                <i class="fas fa-file-pdf text-red-500 text-2xl"></i>
                                <div>
                                    <p class="font-medium text-gray-900">Owner Document</p>
                                    <p class="text-sm text-gray-600">{{ basename($owner->document_path) }}</p>
                                </div>
                            </div>
                            <a href="{{ asset('storage/' . $owner->document_path) }}" target="_blank" class="text-blue-600 hover:text-blue-800">
                                <i class="fas fa-download"></i>
                            </a>
                        </div>
                    </div>
                @else
                    <div class="text-center py-8">
                        <p class="text-gray-500">No documents uploaded</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Additional Information -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
        <!-- Identification -->
        @if($owner->id_type)
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Identification</h3>
                <div class="space-y-3">
                    <div>
                        <p class="text-sm text-gray-600">ID Type</p>
                        <p class="text-gray-900 font-medium">{{ $owner->id_type }}</p>
                    </div>
                </div>
            </div>
        @endif

        <!-- Bank Information -->
        @if($owner->bank_name || $owner->account_number || $owner->ifsc_code)
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Bank Information</h3>
                <div class="space-y-3">
                    @if($owner->bank_name)
                        <div>
                            <p class="text-sm text-gray-600">Bank Name</p>
                            <p class="text-gray-900 font-medium">{{ $owner->bank_name }}</p>
                        </div>
                    @endif
                    @if($owner->account_number)
                        <div>
                            <p class="text-sm text-gray-600">Account Number</p>
                            <p class="text-gray-900 font-medium">{{ $owner->account_number }}</p>
                        </div>
                    @endif
                    @if($owner->ifsc_code)
                        <div>
                            <p class="text-sm text-gray-600">IFSC Code</p>
                            <p class="text-gray-900 font-medium">{{ $owner->ifsc_code }}</p>
                        </div>
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>

<script>
document.querySelectorAll('.tab-button').forEach(button => {
    button.addEventListener('click', function() {
        const tabName = this.getAttribute('data-tab');
        
        // Hide all tabs
        document.querySelectorAll('.tab-content').forEach(tab => {
            tab.classList.add('hidden');
        });
        
        // Remove active state from all buttons
        document.querySelectorAll('.tab-button').forEach(btn => {
            btn.classList.remove('border-blue-600', 'text-blue-600');
            btn.classList.add('border-transparent', 'text-gray-600');
        });
        
        // Show selected tab
        document.getElementById(tabName + '-tab').classList.remove('hidden');
        
        // Add active state to clicked button
        this.classList.remove('border-transparent', 'text-gray-600');
        this.classList.add('border-blue-600', 'text-blue-600');
    });
});
</script>
@endsection
