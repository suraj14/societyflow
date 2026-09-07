@extends('layouts.app')

@section('title', 'Tenant Dashboard')
@section('page-title', 'My Dashboard')

@section('content')
<div class="container mx-auto px-4 py-6">
    <!-- Prominent Tenant Header -->
    <div class="bg-gradient-to-r from-blue-600 via-purple-600 to-indigo-700 rounded-2xl shadow-2xl p-8 mb-8 text-white relative overflow-hidden">
        <!-- Background Pattern -->
        <div class="absolute inset-0 opacity-10">
            <svg class="w-full h-full" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
                <defs>
                    <pattern id="tenant-pattern" x="0" y="0" width="20" height="20" patternUnits="userSpaceOnUse">
                        <rect x="0" y="0" width="20" height="20" fill="none" stroke="currentColor" stroke-width="0.5"/>
                        <circle cx="10" cy="10" r="2" fill="currentColor"/>
                    </pattern>
                </defs>
                <rect width="100%" height="100%" fill="url(#tenant-pattern)"/>
            </svg>
        </div>
        
        <!-- Tenant Icon -->
        <div class="absolute top-6 right-6 opacity-20">
            <i class="fas fa-user-friends text-6xl"></i>
        </div>
        
        <div class="relative z-10">
            <div class="flex items-center mb-4">
                <div class="w-16 h-16 bg-white bg-opacity-20 rounded-full flex items-center justify-center mr-4">
                    <i class="fas fa-home text-2xl"></i>
                </div>
                <div>
                    <h1 class="text-4xl font-bold mb-2">Tenant Dashboard</h1>
                    <p class="text-blue-100 text-lg">Welcome back, {{ auth()->user()->name }}!</p>
                </div>
            </div>
            
            <!-- Unit Information - Prominent Display -->
            @if(isset($unit) && $unit)
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-8">
                    <div class="bg-white bg-opacity-15 backdrop-blur-sm rounded-xl p-6 border border-white border-opacity-20">
                        <div class="flex items-center">
                            <div class="w-12 h-12 bg-yellow-400 rounded-lg flex items-center justify-center mr-4">
                                <i class="fas fa-building text-white text-xl"></i>
                            </div>
                            <div>
                                <p class="text-blue-100 text-sm font-medium uppercase tracking-wide">
                                    @if($unit->property_type === 'villa')
                                        Villa Area
                                    @else
                                        Tower Name
                                    @endif
                                </p>
                                <p class="text-3xl font-bold">
                                    @if($unit->property_type === 'villa')
                                        {{ $unit->villaArea->name ?? 'N/A' }}
                                    @else
                                        {{ $unit->building->name ?? 'N/A' }}
                                    @endif
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white bg-opacity-15 backdrop-blur-sm rounded-xl p-6 border border-white border-opacity-20">
                        <div class="flex items-center">
                            <div class="w-12 h-12 bg-green-400 rounded-lg flex items-center justify-center mr-4">
                                <i class="fas fa-home text-white text-xl"></i>
                            </div>
                            <div>
                                <p class="text-blue-100 text-sm font-medium uppercase tracking-wide">
                                    @if($unit->property_type === 'villa')
                                        Villa Number
                                    @else
                                        Apartment Number
                                    @endif
                                </p>
                                <p class="text-3xl font-bold">
                                    @if($unit->property_type === 'villa')
                                        @if($unit->villa_name && $unit->flat_number)
                                            {{ $unit->flat_number }}
                                        @elseif($unit->flat_number)
                                            {{ $unit->flat_number }}
                                        @else
                                            N/A
                                        @endif
                                    @else
                                        {{ $unit->flat_number ?: 'N/A' }}
                                        @if($unit->floor)
                                            <span class="text-lg text-blue-200">(Floor {{ $unit->floor }})</span>
                                        @endif
                                    @endif
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                
                @if($unit->property_type === 'villa' && $unit->villa_name)
                    <div class="mt-6">
                        <div class="bg-white bg-opacity-15 backdrop-blur-sm rounded-xl p-6 border border-white border-opacity-20">
                            <div class="flex items-center">
                                <div class="w-12 h-12 bg-pink-400 rounded-lg flex items-center justify-center mr-4">
                                    <i class="fas fa-tag text-white text-xl"></i>
                                </div>
                                <div>
                                    <p class="text-blue-100 text-sm font-medium uppercase tracking-wide">Villa Name</p>
                                    <p class="text-3xl font-bold">{{ $unit->villa_name }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
                
                <!-- Quick Stats -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-6">
                    <div class="text-center">
                        <p class="text-2xl font-bold">₹0</p>
                        <p class="text-blue-100 text-sm">Pending Rent</p>
                    </div>
                    <div class="text-center">
                        <p class="text-2xl font-bold">0</p>
                        <p class="text-blue-100 text-sm">My Requests</p>
                    </div>
                    <div class="text-center">
                        <p class="text-2xl font-bold">0</p>
                        <p class="text-blue-100 text-sm">My Visitors</p>
                    </div>
                    <div class="text-center">
                        <p class="text-2xl font-bold">0</p>
                        <p class="text-blue-100 text-sm">Notices</p>
                    </div>
                </div>
            @else
                <div class="bg-yellow-500/20 backdrop-blur-sm rounded-lg p-6 mt-8 border border-yellow-300/30">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-yellow-400 rounded-lg flex items-center justify-center mr-4">
                            <i class="fas fa-exclamation-triangle text-white text-xl"></i>
                        </div>
                        <div>
                            <p class="text-yellow-100 text-lg font-semibold">No Unit Assigned</p>
                            <p class="text-yellow-200 text-sm">Please contact the administrator to assign a unit to your account.</p>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <!-- Dashboard Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <!-- Pending Rent -->
        <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-red-500 hover:shadow-xl transition-shadow">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-file-invoice-dollar text-red-600 text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-2xl font-bold text-gray-900">₹0</p>
                    <p class="text-gray-600 text-sm">Pending Rent</p>
                </div>
            </div>
        </div>

        <!-- My Requests -->
        <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-blue-500 hover:shadow-xl transition-shadow">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-ticket-alt text-blue-600 text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-2xl font-bold text-gray-900">0</p>
                    <p class="text-gray-600 text-sm">My Requests</p>
                </div>
            </div>
        </div>

        <!-- My Visitors -->
        <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-green-500 hover:shadow-xl transition-shadow">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-user-friends text-green-600 text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-2xl font-bold text-gray-900">0</p>
                    <p class="text-gray-600 text-sm">My Visitors</p>
                </div>
            </div>
        </div>

        <!-- Notices -->
        <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-yellow-500 hover:shadow-xl transition-shadow">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-bell text-yellow-600 text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-2xl font-bold text-gray-900">0</p>
                    <p class="text-gray-600 text-sm">Notices</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Left Column - Main Content -->
        <div class="lg:col-span-2 space-y-8">
            <!-- Quick Actions Card -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="bg-gradient-to-r from-indigo-500 to-purple-600 px-6 py-4">
                    <h2 class="text-xl font-bold text-white flex items-center">
                        <i class="fas fa-bolt mr-3"></i>
                        Quick Actions
                    </h2>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <a href="#" class="flex flex-col items-center p-4 bg-blue-50 hover:bg-blue-100 rounded-lg transition-colors">
                            <div class="w-12 h-12 bg-blue-600 rounded-full flex items-center justify-center mb-2">
                                <i class="fas fa-credit-card text-white"></i>
                            </div>
                            <span class="text-sm font-medium text-gray-700">Pay Rent</span>
                        </a>
                        <a href="#" class="flex flex-col items-center p-4 bg-orange-50 hover:bg-orange-100 rounded-lg transition-colors">
                            <div class="w-12 h-12 bg-orange-600 rounded-full flex items-center justify-center mb-2">
                                <i class="fas fa-tools text-white"></i>
                            </div>
                            <span class="text-sm font-medium text-gray-700">Raise Request</span>
                        </a>
                        <a href="#" class="flex flex-col items-center p-4 bg-purple-50 hover:bg-purple-100 rounded-lg transition-colors">
                            <div class="w-12 h-12 bg-purple-600 rounded-full flex items-center justify-center mb-2">
                                <i class="fas fa-bullhorn text-white"></i>
                            </div>
                            <span class="text-sm font-medium text-gray-700">View Notices</span>
                        </a>
                        <a href="#" class="flex flex-col items-center p-4 bg-green-50 hover:bg-green-100 rounded-lg transition-colors">
                            <div class="w-12 h-12 bg-green-600 rounded-full flex items-center justify-center mb-2">
                                <i class="fas fa-home text-white"></i>
                            </div>
                            <span class="text-sm font-medium text-gray-700">My Unit</span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="bg-gradient-to-r from-green-500 to-teal-600 px-6 py-4">
                    <h2 class="text-xl font-bold text-white flex items-center">
                        <i class="fas fa-clock mr-3"></i>
                        Recent Activity
                    </h2>
                </div>
                <div class="p-6">
                    <div class="text-center py-8 text-gray-500">
                        <i class="fas fa-history text-4xl mb-3 text-gray-300"></i>
                        <p>No recent activity</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column - Sidebar -->
        <div class="space-y-6">
            <!-- Tenant Summary -->
            <div class="bg-gradient-to-br from-purple-50 to-indigo-50 rounded-xl shadow-lg p-6 border border-purple-200">
                <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                    <i class="fas fa-info-circle mr-2 text-purple-600"></i>
                    Tenant Summary
                </h3>
                <div class="space-y-4">
                    <div class="flex items-center justify-between py-2 border-b border-purple-200">
                        <span class="text-gray-600">User ID</span>
                        <span class="font-semibold text-gray-900">{{ auth()->user()->id }}</span>
                    </div>
                    <div class="flex items-center justify-between py-2 border-b border-purple-200">
                        <span class="text-gray-600">Role</span>
                        <span class="font-semibold text-gray-900">Tenant</span>
                    </div>
                    @if(isset($unit) && $unit)
                        <div class="flex items-center justify-between py-2">
                            <span class="text-gray-600">Unit Type</span>
                            <span class="font-semibold text-gray-900">{{ ucfirst($unit->property_type ?? 'Apartment') }}</span>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Contact Support -->
            <div class="bg-gradient-to-br from-blue-50 to-cyan-50 rounded-xl shadow-lg p-6 border border-blue-200">
                <h3 class="text-lg font-semibold text-gray-900 mb-3 flex items-center">
                    <i class="fas fa-headset mr-2 text-blue-600"></i>
                    Need Help?
                </h3>
                <p class="text-gray-700 text-sm mb-4">
                    Contact our support team for any assistance with your residence or account.
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
</div>
@endsection
