@extends('layouts.app')

@section('title', 'My Apartment')
@section('page-title', 'My Apartment')

@section('content')
<div class="container mx-auto px-4 py-6">
    <!-- Header -->
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-900">My Apartment</h1>
        <p class="text-gray-600 mt-1">View your apartment details</p>
    </div>

    <!-- Read-Only Notice -->
    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6 flex items-start">
        <i class="fas fa-info-circle text-blue-600 mt-1 mr-3 flex-shrink-0"></i>
        <div>
            <p class="text-blue-900 font-medium">This information is read-only</p>
            <p class="text-blue-800 text-sm">Contact the administrator if you need to make any changes to your apartment details.</p>
        </div>
    </div>

    @if($apartment)
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Main Content -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Apartment Details Card -->
                <div class="bg-white rounded-lg shadow-md overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-purple-50 to-blue-50">
                        <h2 class="text-lg font-semibold text-gray-900">Apartment Details</h2>
                    </div>
                    <div class="px-6 py-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Apartment Number -->
                            <div>
                                <label class="block text-sm font-medium text-gray-500 mb-1">Apartment Number</label>
                                <p class="text-lg font-semibold text-gray-900">{{ $apartment->flat_number }}</p>
                            </div>

                            <!-- Building -->
                            <div>
                                <label class="block text-sm font-medium text-gray-500 mb-1">Building</label>
                                <p class="text-lg font-semibold text-gray-900">{{ $apartment->building->name ?? 'N/A' }}</p>
                            </div>

                            <!-- Floor -->
                            <div>
                                <label class="block text-sm font-medium text-gray-500 mb-1">Floor</label>
                                <p class="text-lg font-semibold text-gray-900">{{ $apartment->floor ?? 'N/A' }}</p>
                            </div>

                            <!-- Type -->
                            <div>
                                <label class="block text-sm font-medium text-gray-500 mb-1">Type</label>
                                <p class="text-lg font-semibold text-gray-900">{{ ucfirst($apartment->type ?? 'N/A') }}</p>
                            </div>

                            <!-- Bedrooms -->
                            <div>
                                <label class="block text-sm font-medium text-gray-500 mb-1">Bedrooms</label>
                                <p class="text-lg font-semibold text-gray-900">{{ $apartment->bedrooms ?? 'N/A' }}</p>
                            </div>

                            <!-- Bathrooms -->
                            <div>
                                <label class="block text-sm font-medium text-gray-500 mb-1">Bathrooms</label>
                                <p class="text-lg font-semibold text-gray-900">{{ $apartment->bathrooms ?? 'N/A' }}</p>
                            </div>

                            <!-- Area -->
                            <div>
                                <label class="block text-sm font-medium text-gray-500 mb-1">Area (Sq Ft)</label>
                                <p class="text-lg font-semibold text-gray-900">{{ $apartment->area ?? 'N/A' }}</p>
                            </div>

                            <!-- Status -->
                            <div>
                                <label class="block text-sm font-medium text-gray-500 mb-1">Status</label>
                                <div>
                                    @php
                                        $statusColors = [
                                            'available' => 'bg-green-100 text-green-800',
                                            'occupied' => 'bg-blue-100 text-blue-800',
                                            'maintenance' => 'bg-yellow-100 text-yellow-800',
                                            'sold' => 'bg-gray-100 text-gray-800',
                                        ];
                                    @endphp
                                    <span class="px-3 py-1 text-sm font-semibold rounded-full {{ $statusColors[$apartment->status] ?? 'bg-gray-100 text-gray-800' }}">
                                        {{ ucfirst($apartment->status ?? 'N/A') }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Residents Card -->
                <div class="bg-white rounded-lg shadow-md overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-purple-50 to-blue-50">
                        <h2 class="text-lg font-semibold text-gray-900">Residents</h2>
                    </div>
                    <div class="px-6 py-6">
                        @if($apartment->residents && $apartment->residents->count() > 0)
                            <div class="space-y-4">
                                @foreach($apartment->residents as $resident)
                                    <div class="flex items-center p-4 bg-gray-50 rounded-lg border border-gray-200">
                                        <div class="w-12 h-12 rounded-full bg-gradient-to-br from-purple-400 to-blue-400 flex items-center justify-center text-white font-bold flex-shrink-0">
                                            {{ substr($resident->name, 0, 1) }}
                                        </div>
                                        <div class="ml-4 flex-1">
                                            <p class="font-medium text-gray-900">{{ $resident->name }}</p>
                                            <p class="text-sm text-gray-500">{{ $resident->email ?? 'N/A' }}</p>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-sm font-medium text-gray-700">{{ $resident->phone ?? 'N/A' }}</p>
                                            <p class="text-xs text-gray-500">{{ ucfirst($resident->type ?? 'Resident') }}</p>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="text-center py-8">
                                <i class="fas fa-users text-gray-300 text-4xl mb-3"></i>
                                <p class="text-gray-500">No residents assigned to this apartment</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="space-y-6">
                <!-- Quick Info Card -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Quick Info</h3>
                    <div class="space-y-4">
                        <div class="flex items-center justify-between pb-4 border-b border-gray-200">
                            <span class="text-gray-600">Apartment ID</span>
                            <span class="font-medium text-gray-900">{{ $apartment->id }}</span>
                        </div>
                        <div class="flex items-center justify-between pb-4 border-b border-gray-200">
                            <span class="text-gray-600">Building</span>
                            <span class="font-medium text-gray-900">{{ $apartment->building->name ?? 'N/A' }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-gray-600">Total Residents</span>
                            <span class="font-medium text-gray-900">{{ $apartment->residents->count() ?? 0 }}</span>
                        </div>
                    </div>
                </div>

                <!-- Help Card -->
                <div class="bg-gradient-to-br from-purple-50 to-blue-50 rounded-lg shadow-md p-6 border border-purple-200">
                    <h3 class="text-lg font-semibold text-gray-900 mb-3">Need Help?</h3>
                    <p class="text-gray-700 text-sm mb-4">
                        If you need to make changes to your apartment details or have any questions, please contact the administrator.
                    </p>
                    <div class="space-y-2 text-sm">
                        <p class="text-gray-600">
                            <i class="fas fa-envelope text-purple-600 mr-2"></i>
                            <span>Email: admin@societyflow.com</span>
                        </p>
                        <p class="text-gray-600">
                            <i class="fas fa-phone text-purple-600 mr-2"></i>
                            <span>Phone: +1 (555) 123-4567</span>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    @else
        <!-- No Apartment Assigned -->
        <div class="bg-white rounded-lg shadow-md p-12 text-center">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-gray-100 rounded-full mb-4">
                <i class="fas fa-door-open text-gray-400 text-2xl"></i>
            </div>
            <h3 class="text-lg font-medium text-gray-900 mb-2">No Apartment Assigned</h3>
            <p class="text-gray-500 mb-6">{{ $message ?? 'No apartment has been assigned to your account yet.' }}</p>
            <p class="text-gray-600 text-sm">
                Please contact the administrator to assign an apartment to your account.
            </p>
        </div>
    @endif
</div>
@endsection
