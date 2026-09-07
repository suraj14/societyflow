@extends('layouts.app')

@section('title', 'My Villa')
@section('page-title', 'My Villa')

@section('content')
<div class="container mx-auto px-4 py-6">
    @if($villa)
        <!-- Prominent Villa Header -->
        <div class="bg-gradient-to-r from-blue-600 via-purple-600 to-indigo-700 rounded-2xl shadow-2xl p-8 mb-8 text-white relative overflow-hidden">
            <!-- Background Pattern -->
            <div class="absolute inset-0 opacity-10">
                <svg class="w-full h-full" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
                    <defs>
                        <pattern id="villa-pattern" x="0" y="0" width="20" height="20" patternUnits="userSpaceOnUse">
                            <rect x="0" y="0" width="20" height="20" fill="none" stroke="currentColor" stroke-width="0.5"/>
                            <circle cx="10" cy="10" r="2" fill="currentColor"/>
                        </pattern>
                    </defs>
                    <rect width="100%" height="100%" fill="url(#villa-pattern)"/>
                </svg>
            </div>
            
            <!-- Villa Icon -->
            <div class="absolute top-6 right-6 opacity-20">
                <i class="fas fa-home text-6xl"></i>
            </div>
            
            <div class="relative z-10">
                <div class="flex items-center mb-4">
                    <div class="w-16 h-16 bg-white bg-opacity-20 rounded-full flex items-center justify-center mr-4">
                        <i class="fas fa-house-user text-2xl"></i>
                    </div>
                    <div>
                        <h1 class="text-4xl font-bold mb-2">My Villa</h1>
                        <p class="text-blue-100 text-lg">View your villa details</p>
                    </div>
                </div>
                
                <!-- Villa Number and Name - Prominent Display -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-8">
                    <div class="bg-white bg-opacity-15 backdrop-blur-sm rounded-xl p-6 border border-white border-opacity-20">
                        <div class="flex items-center">
                            <div class="w-12 h-12 bg-yellow-400 rounded-lg flex items-center justify-center mr-4">
                                <i class="fas fa-hashtag text-white text-xl"></i>
                            </div>
                            <div>
                                <p class="text-blue-100 text-sm font-medium uppercase tracking-wide">Villa Number</p>
                                <p class="text-3xl font-bold">{{ $villa->flat_number ?? 'N/A' }}</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white bg-opacity-15 backdrop-blur-sm rounded-xl p-6 border border-white border-opacity-20">
                        <div class="flex items-center">
                            <div class="w-12 h-12 bg-green-400 rounded-lg flex items-center justify-center mr-4">
                                <i class="fas fa-tag text-white text-xl"></i>
                            </div>
                            <div>
                                <p class="text-blue-100 text-sm font-medium uppercase tracking-wide">Villa Name</p>
                                <p class="text-3xl font-bold">{{ $villa->villa_name ?? 'N/A' }}</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Stats -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-6">
                    <div class="text-center">
                        <p class="text-2xl font-bold">{{ $villa->villaArea->name ?? 'N/A' }}</p>
                        <p class="text-blue-100 text-sm">Villa Area</p>
                    </div>
                    <div class="text-center">
                        <p class="text-2xl font-bold">{{ $villa->bedrooms ?? 'N/A' }}</p>
                        <p class="text-blue-100 text-sm">Bedrooms</p>
                    </div>
                    <div class="text-center">
                        <p class="text-2xl font-bold">{{ $villa->bathrooms ?? 'N/A' }}</p>
                        <p class="text-blue-100 text-sm">Bathrooms</p>
                    </div>
                    <div class="text-center">
                        <p class="text-2xl font-bold">{{ $villa->residents->count() ?? 0 }}</p>
                        <p class="text-blue-100 text-sm">Residents</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Read-Only Notice -->
        <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-8 flex items-start">
            <i class="fas fa-info-circle text-blue-600 mt-1 mr-3 flex-shrink-0"></i>
            <div>
                <p class="text-blue-900 font-medium">This information is read-only</p>
                <p class="text-blue-800 text-sm">Contact the administrator if you need to make any changes to your villa details.</p>
            </div>
        </div>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Main Content -->
            <div class="lg:col-span-2 space-y-8">
                <!-- Villa Details Card -->
                <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                    <div class="bg-gradient-to-r from-indigo-500 to-purple-600 px-6 py-4">
                        <h2 class="text-xl font-bold text-white flex items-center">
                            <i class="fas fa-home mr-3"></i>
                            Villa Details
                        </h2>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Plot Size -->
                            <div class="bg-gray-50 rounded-lg p-4">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                                        <i class="fas fa-ruler-combined text-blue-600"></i>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-600">Plot Size</p>
                                        <p class="text-lg font-semibold text-gray-900">{{ $villa->plot_area ?? $villa->plot_size ?? 'N/A' }} Sq Ft</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Built Area -->
                            <div class="bg-gray-50 rounded-lg p-4">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center mr-3">
                                        <i class="fas fa-building text-green-600"></i>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-600">Built Area</p>
                                        <p class="text-lg font-semibold text-gray-900">{{ $villa->built_up_area ?? $villa->built_area ?? 'N/A' }} Sq Ft</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Status -->
                            <div class="bg-gray-50 rounded-lg p-4">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center mr-3">
                                        <i class="fas fa-info-circle text-purple-600"></i>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-600">Status</p>
                                        @php
                                            $statusColors = [
                                                'available' => 'bg-green-100 text-green-800',
                                                'occupied' => 'bg-blue-100 text-blue-800',
                                                'maintenance' => 'bg-yellow-100 text-yellow-800',
                                                'sold' => 'bg-gray-100 text-gray-800',
                                            ];
                                        @endphp
                                        <span class="inline-flex px-3 py-1 text-sm font-semibold rounded-full {{ $statusColors[$villa->status] ?? 'bg-blue-100 text-blue-800' }}">
                                            {{ ucfirst($villa->status ?? 'Occupied') }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Monthly Maintenance -->
                            @if($villa->maintenance_amount)
                            <div class="bg-gray-50 rounded-lg p-4">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 bg-yellow-100 rounded-lg flex items-center justify-center mr-3">
                                        <i class="fas fa-rupee-sign text-yellow-600"></i>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-600">Monthly Maintenance</p>
                                        <p class="text-lg font-semibold text-gray-900">₹{{ number_format($villa->maintenance_amount, 0) }}</p>
                                    </div>
                                </div>
                            </div>
                            @endif
                        </div>

                        <!-- Amenities -->
                        @if($villa->has_garden || $villa->has_parking)
                        <div class="mt-6 pt-6 border-t border-gray-200">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                                <i class="fas fa-star mr-2 text-yellow-500"></i>
                                Amenities
                            </h3>
                            <div class="flex flex-wrap gap-3">
                                @if($villa->has_garden)
                                    <div class="flex items-center bg-green-50 text-green-700 px-4 py-2 rounded-full">
                                        <i class="fas fa-leaf mr-2"></i>
                                        <span class="font-medium">Garden</span>
                                    </div>
                                @endif
                                @if($villa->has_parking)
                                    <div class="flex items-center bg-blue-50 text-blue-700 px-4 py-2 rounded-full">
                                        <i class="fas fa-car mr-2"></i>
                                        <span class="font-medium">Parking ({{ $villa->parking_slots ?? 1 }} slots)</span>
                                    </div>
                                @endif
                            </div>
                        </div>
                        @endif
                    </div>
                </div>

                <!-- Residents Card -->
                <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                    <div class="bg-gradient-to-r from-green-500 to-teal-600 px-6 py-4">
                        <h2 class="text-xl font-bold text-white flex items-center">
                            <i class="fas fa-users mr-3"></i>
                            Residents
                        </h2>
                    </div>
                    <div class="p-6">
                        @if($villa->residents && $villa->residents->count() > 0)
                            <div class="space-y-4">
                                @foreach($villa->residents as $resident)
                                    <div class="flex items-center p-4 bg-gray-50 rounded-lg border border-gray-200 hover:shadow-md transition-shadow">
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
                                <p class="text-gray-500">No residents assigned to this villa</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="space-y-6">
                <!-- Villa Summary -->
                <div class="bg-gradient-to-br from-purple-50 to-indigo-50 rounded-xl shadow-lg p-6 border border-purple-200">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                        <i class="fas fa-info-circle mr-2 text-purple-600"></i>
                        Villa Summary
                    </h3>
                    <div class="space-y-4">
                        <div class="flex items-center justify-between py-2 border-b border-purple-200">
                            <span class="text-gray-600">Villa ID</span>
                            <span class="font-semibold text-gray-900">{{ $villa->id }}</span>
                        </div>
                        <div class="flex items-center justify-between py-2 border-b border-purple-200">
                            <span class="text-gray-600">Area</span>
                            <span class="font-semibold text-gray-900">{{ $villa->villaArea->name ?? 'N/A' }}</span>
                        </div>
                        <div class="flex items-center justify-between py-2">
                            <span class="text-gray-600">Total Residents</span>
                            <span class="font-semibold text-gray-900">{{ $villa->residents->count() ?? 0 }}</span>
                        </div>
                    </div>
                </div>

                <!-- Contact Support -->
                <div class="bg-gradient-to-br from-blue-50 to-cyan-50 rounded-xl shadow-lg p-6 border border-blue-200">
                    <h3 class="text-lg font-semibold text-gray-900 mb-3 flex items-center">
                        <i class="fas fa-headset mr-2 text-blue-600"></i>
                        Need Help?
                    </h3>
                    <p class="text-gray-700 text-sm mb-4">
                        If you need to make changes to your villa details or have any questions, please contact the administrator.
                    </p>
                    <div class="space-y-3 text-sm">
                        <div class="flex items-center text-gray-600">
                            <i class="fas fa-envelope text-blue-600 mr-3 w-4"></i>
                            <span>admin@societyflow.com</span>
                        </div>
                        <div class="flex items-center text-gray-600">
                            <i class="fas fa-phone text-blue-600 mr-3 w-4"></i>
                            <span>+1 (555) 123-4567</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @else
        <!-- No Villa Assigned -->
        <div class="bg-white rounded-lg shadow-md p-12 text-center">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-gray-100 rounded-full mb-4">
                <i class="fas fa-house-user text-gray-400 text-2xl"></i>
            </div>
            <h3 class="text-lg font-medium text-gray-900 mb-2">No Villa Assigned</h3>
            <p class="text-gray-500 mb-6">{{ $message ?? 'No villa has been assigned to your account yet.' }}</p>
            <p class="text-gray-600 text-sm">
                Please contact the administrator to assign a villa to your account.
            </p>
        </div>
    @endif
</div>
@endsection
