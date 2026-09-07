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
                        <p class="text-blue-100 text-lg">Welcome to your villa dashboard</p>
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

        <!-- Dashboard Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <!-- Today's Visitors -->
            <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-blue-500 hover:shadow-xl transition-shadow">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-users text-blue-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-2xl font-bold text-gray-900">{{ $todayVisitors ?? 0 }}</p>
                        <p class="text-gray-600 text-sm">Today's Visitors</p>
                    </div>
                </div>
            </div>

            <!-- Upcoming Bookings -->
            <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-green-500 hover:shadow-xl transition-shadow">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-calendar-check text-green-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-2xl font-bold text-gray-900">{{ $upcomingBookings ?? 0 }}</p>
                        <p class="text-gray-600 text-sm">Upcoming Bookings</p>
                    </div>
                </div>
            </div>

            <!-- Open Complaints -->
            <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-orange-500 hover:shadow-xl transition-shadow">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-exclamation-triangle text-orange-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-2xl font-bold text-gray-900">{{ $openComplaints ?? 0 }}</p>
                        <p class="text-gray-600 text-sm">Open Complaints</p>
                    </div>
                </div>
            </div>

            <!-- Pending Dues -->
            <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-red-500 hover:shadow-xl transition-shadow">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-rupee-sign text-red-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-2xl font-bold text-gray-900">₹{{ number_format($pendingDues ?? 0) }}</p>
                        <p class="text-gray-600 text-sm">Pending Dues</p>
                    </div>
                </div>
            </div>
        </div>
        <!-- Main Content Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Left Column - Main Content -->
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
                                        <span class="inline-flex px-3 py-1 text-sm font-semibold rounded-full {{ $statusColors[$villa->status] ?? 'bg-gray-100 text-gray-800' }}">
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

                <!-- Recent Activity -->
                <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                    <div class="bg-gradient-to-r from-green-500 to-teal-600 px-6 py-4">
                        <h2 class="text-xl font-bold text-white flex items-center">
                            <i class="fas fa-clock mr-3"></i>
                            Recent Activity
                        </h2>
                    </div>
                    <div class="p-6">
                        @if($recentVisitors && $recentVisitors->count() > 0)
                            <div class="space-y-4">
                                @foreach($recentVisitors->take(3) as $visitor)
                                    <div class="flex items-center p-4 bg-gray-50 rounded-lg">
                                        <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center mr-4">
                                            <i class="fas fa-user text-blue-600"></i>
                                        </div>
                                        <div class="flex-1">
                                            <p class="font-medium text-gray-900">{{ $visitor->visitor_name }}</p>
                                            <p class="text-sm text-gray-600">{{ $visitor->visitor_type }} • {{ $visitor->created_at->diffForHumans() }}</p>
                                        </div>
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full 
                                            {{ $visitor->approval_status === 'allowed' ? 'bg-green-100 text-green-800' : 
                                               ($visitor->approval_status === 'denied' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800') }}">
                                            {{ ucfirst($visitor->approval_status) }}
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="text-center py-8">
                                <i class="fas fa-history text-gray-300 text-3xl mb-3"></i>
                                <p class="text-gray-500">No recent activity</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Right Column - Sidebar -->
            <div class="space-y-6">
                <!-- Quick Actions -->
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                        <i class="fas fa-bolt mr-2 text-yellow-500"></i>
                        Quick Actions
                    </h3>
                    <div class="space-y-3">
                        <a href="{{ route('villa-owner.facilities') }}" class="flex items-center p-3 bg-green-50 hover:bg-green-100 rounded-lg transition-colors">
                            <i class="fas fa-calendar-plus text-green-600 mr-3"></i>
                            <span class="font-medium text-green-900">Book Facility</span>
                        </a>
                        <a href="{{ route('complaints.create') }}" class="flex items-center p-3 bg-orange-50 hover:bg-orange-100 rounded-lg transition-colors">
                            <i class="fas fa-exclamation-circle text-orange-600 mr-3"></i>
                            <span class="font-medium text-orange-900">Report Issue</span>
                        </a>
                        <a href="{{ route('villa-owner.visitors') }}" class="flex items-center p-3 bg-blue-50 hover:bg-blue-100 rounded-lg transition-colors">
                            <i class="fas fa-users text-blue-600 mr-3"></i>
                            <span class="font-medium text-blue-900">View Visitors</span>
                        </a>
                    </div>
                </div>

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
                        Contact our support team for any assistance with your villa or account.
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
