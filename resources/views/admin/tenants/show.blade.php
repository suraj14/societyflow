@extends('layouts.app')

@section('title', 'Tenant Details')
@section('page-title', 'Tenant Details')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="mb-6">
        <a href="{{ route('admin.tenants.index') }}" class="text-blue-600 hover:text-blue-800 text-sm">
            <i class="fas fa-arrow-left mr-1"></i> Back to Tenants
        </a>
    </div>

    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        <!-- Left Column - Profile Card -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-lg shadow-md p-6">
                <!-- Profile Image -->
                <div class="text-center mb-6">
                    @if($tenant->user && $tenant->user->avatar)
                        <img src="{{ asset('storage/' . $tenant->user->avatar) }}" alt="{{ $tenant->name }}" class="w-32 h-32 rounded-lg object-cover mx-auto border-4 border-gray-200">
                    @else
                        <div class="w-32 h-32 bg-gradient-to-br from-blue-400 to-blue-600 rounded-lg flex items-center justify-center text-white text-5xl font-bold mx-auto">
                            {{ strtoupper(substr($tenant->name, 0, 1)) }}
                        </div>
                    @endif
                </div>

                <!-- Tenant Name and Status -->
                <h2 class="text-2xl font-bold text-gray-900 text-center mb-2">{{ $tenant->name }}</h2>
                <div class="text-center mb-6">
                    <span class="inline-block px-4 py-1 text-sm font-semibold rounded-full 
                        {{ $tenant->status === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                        {{ ucfirst($tenant->status) }}
                    </span>
                </div>

                <!-- Profile Information -->
                <div class="space-y-4 border-t pt-4">
                    <div>
                        <p class="text-xs text-gray-600 uppercase tracking-wide font-semibold">Full Name</p>
                        <p class="text-gray-900 font-medium">{{ $tenant->name }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-600 uppercase tracking-wide font-semibold">Email Address</p>
                        <p class="text-gray-900 font-medium break-all">{{ $tenant->email }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-600 uppercase tracking-wide font-semibold">Phone Number</p>
                        <p class="text-gray-900 font-medium">{{ $tenant->phone ?? '-' }}</p>
                    </div>
                </div>

                <!-- Edit Button -->
                <a href="{{ route('admin.tenants.edit', $tenant) }}" class="w-full mt-6 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium transition-colors text-center block">
                    <i class="fas fa-edit mr-2"></i> Edit Tenant
                </a>
            </div>
        </div>

        <!-- Right Column - Quick Info -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Quick Information</h3>
                
                @if($tenant->flat)
                    <div class="grid grid-cols-2 gap-4">
                        <div class="border border-gray-200 rounded-lg p-4">
                            <p class="text-xs text-gray-600 uppercase tracking-wide font-semibold">Apartment Number</p>
                            <p class="text-lg font-bold text-gray-900">{{ $tenant->flat->flat_number ?? '-' }}</p>
                        </div>
                        <div class="border border-gray-200 rounded-lg p-4">
                            <p class="text-xs text-gray-600 uppercase tracking-wide font-semibold">Parking Code</p>
                            <p class="text-lg font-bold text-gray-900">-</p>
                        </div>
                    </div>

                    <div class="mt-6 pt-6 border-t">
                        <h4 class="font-semibold text-gray-800 mb-4">Rental Details</h4>
                        <div class="grid grid-cols-2 gap-4">
                            @if($tenant->contract_start_date)
                                <div>
                                    <p class="text-xs text-gray-600 uppercase tracking-wide font-semibold">Contract Start Date</p>
                                    <p class="text-gray-900 font-medium">{{ $tenant->contract_start_date->format('M d, Y') }}</p>
                                </div>
                            @endif
                            
                            @if($tenant->contract_end_date)
                                <div>
                                    <p class="text-xs text-gray-600 uppercase tracking-wide font-semibold">Contract End Date</p>
                                    <p class="text-gray-900 font-medium">{{ $tenant->contract_end_date->format('M d, Y') }}</p>
                                </div>
                            @endif
                            
                            @if($tenant->monthly_rent)
                                <div>
                                    <p class="text-xs text-gray-600 uppercase tracking-wide font-semibold">Rent Amount</p>
                                    <p class="text-gray-900 font-medium">₹{{ number_format($tenant->monthly_rent, 2) }}</p>
                                </div>
                            @endif
                            
                            @if($tenant->rent_billing_cycle)
                                <div>
                                    <p class="text-xs text-gray-600 uppercase tracking-wide font-semibold">Rent Billing Cycle</p>
                                    <p class="text-gray-900 font-medium">{{ $tenant->rent_billing_cycle }}</p>
                                </div>
                            @endif
                        </div>
                    </div>
                @else
                    <div class="text-center py-8">
                        <p class="text-gray-500">No apartment assigned</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Tabs Section -->
    <div class="bg-white rounded-lg shadow-md">
        <div class="border-b border-gray-200">
            <div class="flex space-x-8 px-6 overflow-x-auto">
                <button class="tab-button active py-4 px-1 border-b-2 border-red-600 text-red-600 font-medium whitespace-nowrap" data-tab="apartment">
                    Apartment
                </button>
                <button class="tab-button py-4 px-1 border-b-2 border-transparent text-gray-600 hover:text-gray-900 font-medium whitespace-nowrap" data-tab="rent">
                    Rent
                </button>
                <button class="tab-button py-4 px-1 border-b-2 border-transparent text-gray-600 hover:text-gray-900 font-medium whitespace-nowrap" data-tab="family">
                    Family Members
                </button>
                <button class="tab-button py-4 px-1 border-b-2 border-transparent text-gray-600 hover:text-gray-900 font-medium whitespace-nowrap" data-tab="documents">
                    Tenant Documents
                </button>
            </div>
        </div>

        <!-- Tab Content -->
        <div class="p-6">
            <!-- Apartment Tab -->
            <div id="apartment-tab" class="tab-content">
                @if($tenant->flat)
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">#</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Apartment Number</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Owner Name</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Contract Start Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Contract End Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Rent Amount</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Rent Billing Cycle</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">1</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $tenant->flat->flat_number ?? '-' }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        @if($tenant->flat->ownerModel)
                                            {{ $tenant->flat->ownerModel->name }}
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ $tenant->contract_start_date ? $tenant->contract_start_date->format('M d, Y') : '-' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ $tenant->contract_end_date ? $tenant->contract_end_date->format('M d, Y') : '-' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <span class="px-2 py-1 rounded-full text-xs font-semibold {{ $tenant->flat->status === 'occupied' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">
                                            {{ ucfirst($tenant->flat->status ?? '-') }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ $tenant->monthly_rent ? '₹' . number_format($tenant->monthly_rent, 2) : '-' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ $tenant->rent_billing_cycle ?? '-' }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center py-8">
                        <p class="text-gray-500">No apartment assigned</p>
                    </div>
                @endif
            </div>

            <!-- Rent Tab -->
            <div id="rent-tab" class="tab-content hidden">
                @if($tenant->contract_start_date || $tenant->contract_end_date || $tenant->monthly_rent || $tenant->rent_billing_cycle)
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        @if($tenant->contract_start_date)
                            <div class="border border-gray-200 rounded-lg p-4">
                                <p class="text-sm text-gray-600">Contract Start Date</p>
                                <p class="text-lg font-semibold text-gray-900">{{ $tenant->contract_start_date->format('M d, Y') }}</p>
                            </div>
                        @endif
                        
                        @if($tenant->contract_end_date)
                            <div class="border border-gray-200 rounded-lg p-4">
                                <p class="text-sm text-gray-600">Contract End Date</p>
                                <p class="text-lg font-semibold text-gray-900">{{ $tenant->contract_end_date->format('M d, Y') }}</p>
                            </div>
                        @endif
                        
                        @if($tenant->monthly_rent)
                            <div class="border border-gray-200 rounded-lg p-4">
                                <p class="text-sm text-gray-600">Rent Amount</p>
                                <p class="text-lg font-semibold text-gray-900">₹{{ number_format($tenant->monthly_rent, 2) }}</p>
                            </div>
                        @endif
                        
                        @if($tenant->rent_billing_cycle)
                            <div class="border border-gray-200 rounded-lg p-4">
                                <p class="text-sm text-gray-600">Billing Cycle</p>
                                <p class="text-lg font-semibold text-gray-900">{{ $tenant->rent_billing_cycle }}</p>
                            </div>
                        @endif
                    </div>
                @else
                    <div class="text-center py-8">
                        <p class="text-gray-500">No rental information available</p>
                    </div>
                @endif
            </div>

            <!-- Family Members Tab -->
            <div id="family-tab" class="tab-content hidden">
                @if($tenant->family_members && is_array($tenant->family_members) && count($tenant->family_members) > 0)
                    <div class="space-y-4">
                        <h4 class="text-lg font-semibold text-gray-800 mb-4">Family Members ({{ count($tenant->family_members) }})</h4>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            @foreach($tenant->family_members as $index => $member)
                                <div class="border border-gray-200 rounded-lg p-4 bg-gray-50">
                                    <div class="flex items-center mb-3">
                                        <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center mr-3">
                                            <i class="fas fa-user text-blue-600"></i>
                                        </div>
                                        <div>
                                            <p class="font-semibold text-gray-900">{{ $member['name'] ?? 'N/A' }}</p>
                                            <p class="text-sm text-gray-600">{{ $member['relationship'] ?? 'N/A' }}</p>
                                        </div>
                                    </div>
                                    
                                    @if(!empty($member['phone']))
                                        <div class="flex items-center text-sm text-gray-600">
                                            <i class="fas fa-phone text-gray-400 mr-2"></i>
                                            <span>{{ $member['phone'] }}</span>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @else
                    <div class="text-center py-8">
                        <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-users text-gray-400 text-2xl"></i>
                        </div>
                        <p class="text-gray-500 text-lg font-medium">No Family Members</p>
                        <p class="text-gray-400 text-sm">No family member information has been added for this tenant.</p>
                    </div>
                @endif
            </div>

            <!-- Tenant Documents Tab -->
            <div id="documents-tab" class="tab-content hidden">
                @if($tenant->id_type || $tenant->document_path || $tenant->notes)
                    <div class="space-y-4">
                        @if($tenant->id_type)
                            <div class="border border-gray-200 rounded-lg p-4">
                                <p class="text-sm text-gray-600">ID Type</p>
                                <p class="text-gray-900 font-medium">{{ $tenant->id_type }}</p>
                            </div>
                        @endif
                        
                        @if($tenant->document_path)
                            <div class="border border-gray-200 rounded-lg p-4">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm text-gray-600">Document</p>
                                        <p class="text-gray-900 font-medium">{{ $tenant->id_type ?? 'Identity Document' }}</p>
                                        <p class="text-xs text-gray-500 mt-1">Uploaded document file</p>
                                    </div>
                                    <div class="flex space-x-2">
                                        <a href="{{ asset('storage/' . $tenant->document_path) }}" 
                                           target="_blank"
                                           class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                            <i class="fas fa-eye mr-2"></i>
                                            View
                                        </a>
                                        <a href="{{ asset('storage/' . $tenant->document_path) }}" 
                                           download
                                           class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                            <i class="fas fa-download mr-2"></i>
                                            Download
                                        </a>
                                    </div>
                                </div>
                            </div>
                        @endif
                        
                        @if($tenant->notes)
                            <div class="border border-gray-200 rounded-lg p-4">
                                <p class="text-sm text-gray-600">Notes</p>
                                <p class="text-gray-900">{{ $tenant->notes }}</p>
                            </div>
                        @endif
                    </div>
                @else
                    <div class="text-center py-8">
                        <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-file-alt text-gray-400 text-2xl"></i>
                        </div>
                        <p class="text-gray-500 text-lg font-medium">No Documents Available</p>
                        <p class="text-gray-400 text-sm">No documents or information has been uploaded for this tenant.</p>
                    </div>
                @endif
            </div>
        </div>
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
            btn.classList.remove('border-red-600', 'text-red-600');
            btn.classList.add('border-transparent', 'text-gray-600');
        });
        
        // Show selected tab
        document.getElementById(tabName + '-tab').classList.remove('hidden');
        
        // Add active state to clicked button
        this.classList.remove('border-transparent', 'text-gray-600');
        this.classList.add('border-red-600', 'text-red-600');
    });
});
</script>
@endsection
