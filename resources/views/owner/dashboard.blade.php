@extends('layouts.app')

@section('title', 'My Dashboard')
@section('page-title', 'My Dashboard')

@section('content')
<div class="container mx-auto px-4 py-6">
    <!-- Prominent Owner Header -->
    <div class="bg-gradient-to-r from-blue-600 via-purple-600 to-indigo-700 rounded-2xl shadow-2xl p-8 mb-8 text-white relative overflow-hidden">
        <!-- Background Pattern -->
        <div class="absolute inset-0 opacity-10">
            <svg class="w-full h-full" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
                <defs>
                    <pattern id="owner-pattern" x="0" y="0" width="20" height="20" patternUnits="userSpaceOnUse">
                        <rect x="0" y="0" width="20" height="20" fill="none" stroke="currentColor" stroke-width="0.5"/>
                        <circle cx="10" cy="10" r="2" fill="currentColor"/>
                    </pattern>
                </defs>
                <rect width="100%" height="100%" fill="url(#owner-pattern)"/>
            </svg>
        </div>
        
        <!-- Owner Icon -->
        <div class="absolute top-6 right-6 opacity-20">
            <i class="fas fa-home text-6xl"></i>
        </div>
        
        <div class="relative z-10">
            <div class="flex items-center mb-4">
                <div class="w-16 h-16 bg-white bg-opacity-20 rounded-full flex items-center justify-center mr-4">
                    <i class="fas fa-user-tie text-2xl"></i>
                </div>
                <div>
                    <h1 class="text-4xl font-bold mb-2">My Dashboard</h1>
                    <p class="text-blue-100 text-lg">Welcome back, {{ auth()->user()->name }}!</p>
                </div>
            </div>
            
            <!-- Unit Information - Enhanced for Multiple Properties -->
            @if(isset($properties) && $properties->count() > 0)
                @if($properties->count() > 1)
                    <!-- Multiple Properties Display -->
                    <div class="bg-white bg-opacity-15 backdrop-blur-sm rounded-xl p-6 border border-white border-opacity-20 mb-6">
                        <div class="flex items-center mb-4">
                            <div class="w-12 h-12 bg-purple-400 rounded-lg flex items-center justify-center mr-4">
                                <i class="fas fa-building text-white text-xl"></i>
                            </div>
                            <div>
                                <p class="text-blue-100 text-sm font-medium uppercase tracking-wide">Property Portfolio</p>
                                <p class="text-3xl font-bold">{{ $properties->count() }} Properties</p>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="text-center">
                                <p class="text-2xl font-bold">{{ $stats['apartments'] ?? 0 }}</p>
                                <p class="text-blue-100 text-sm">Apartments</p>
                            </div>
                            <div class="text-center">
                                <p class="text-2xl font-bold">{{ $stats['villas'] ?? 0 }}</p>
                                <p class="text-blue-100 text-sm">Villas</p>
                            </div>
                            <div class="text-center">
                                <p class="text-2xl font-bold">{{ $stats['total_properties'] ?? 0 }}</p>
                                <p class="text-blue-100 text-sm">Total Units</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Properties List -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @foreach($properties as $property)
                            <div class="bg-white bg-opacity-15 backdrop-blur-sm rounded-xl p-4 border border-white border-opacity-20">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 bg-{{ $property->property_type === 'villa' ? 'green' : 'blue' }}-400 rounded-lg flex items-center justify-center mr-3">
                                        <i class="fas fa-{{ $property->property_type === 'villa' ? 'home' : 'building' }} text-white"></i>
                                    </div>
                                    <div>
                                        <p class="text-blue-100 text-xs font-medium uppercase tracking-wide">
                                            {{ ucfirst($property->property_type) }}
                                        </p>
                                        <p class="text-lg font-bold">
                                            @if($property->property_type === 'villa')
                                                {{ $property->villa_name ?? $property->flat_number }}
                                            @else
                                                {{ $property->flat_number }}
                                            @endif
                                        </p>
                                        <p class="text-blue-200 text-xs">
                                            @if($property->property_type === 'villa')
                                                {{ $property->villaArea->name ?? 'N/A' }}
                                            @else
                                                {{ $property->building->name ?? 'N/A' }}
                                            @endif
                                        </p>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <!-- Single Property Display (Original Layout) -->
                    @php $unit = $properties->first(); @endphp
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
                @endif
                
                <!-- Quick Stats -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-6">
                    <div class="text-center">
                        <p class="text-2xl font-bold">{{ $stats['pending_bills'] ?? 0 }}</p>
                        <p class="text-blue-100 text-sm">Pending Bills</p>
                    </div>
                    <div class="text-center">
                        <p class="text-2xl font-bold">₹{{ number_format($stats['total_due'] ?? 0) }}</p>
                        <p class="text-blue-100 text-sm">Total Due</p>
                    </div>
                    <div class="text-center">
                        <p class="text-2xl font-bold">{{ $stats['my_tickets'] ?? 0 }}</p>
                        <p class="text-blue-100 text-sm">My Tickets</p>
                    </div>
                    <div class="text-center">
                        <p class="text-2xl font-bold">{{ $stats['upcoming_visitors'] ?? 0 }}</p>
                        <p class="text-blue-100 text-sm">Visitors</p>
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
        <!-- Pending Bills -->
        <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-orange-500 hover:shadow-xl transition-shadow">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-file-invoice-dollar text-orange-600 text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-2xl font-bold text-gray-900">{{ $stats['pending_bills'] ?? 0 }}</p>
                    <p class="text-gray-600 text-sm">Pending Bills</p>
                </div>
            </div>
        </div>

        <!-- Total Due -->
        <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-red-500 hover:shadow-xl transition-shadow">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-rupee-sign text-red-600 text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-2xl font-bold text-gray-900">₹{{ number_format($stats['total_due'] ?? 0) }}</p>
                    <p class="text-gray-600 text-sm">Total Due</p>
                </div>
            </div>
        </div>

        <!-- My Tickets -->
        <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-blue-500 hover:shadow-xl transition-shadow">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-ticket-alt text-blue-600 text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-2xl font-bold text-gray-900">{{ $stats['my_tickets'] ?? 0 }}</p>
                    <p class="text-gray-600 text-sm">My Tickets</p>
                    @if(($stats['open_tickets'] ?? 0) > 0)
                        <p class="text-xs text-orange-600">{{ $stats['open_tickets'] }} open</p>
                    @endif
                </div>
            </div>
        </div>

        <!-- Upcoming Visitors -->
        <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-green-500 hover:shadow-xl transition-shadow">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-user-friends text-green-600 text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-2xl font-bold text-gray-900">{{ $stats['upcoming_visitors'] ?? 0 }}</p>
                    <p class="text-gray-600 text-sm">Visitors</p>
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
                        <a href="{{ route('complaints.create') }}" class="flex flex-col items-center p-4 bg-purple-50 hover:bg-purple-100 rounded-lg transition-colors">
                            <div class="w-12 h-12 bg-purple-600 rounded-full flex items-center justify-center mb-2">
                                <i class="fas fa-plus text-white"></i>
                            </div>
                            <span class="text-sm font-medium text-gray-700">New Ticket</span>
                        </a>
                        <a href="{{ route('facilities.index') }}" class="flex flex-col items-center p-4 bg-green-50 hover:bg-green-100 rounded-lg transition-colors">
                            <div class="w-12 h-12 bg-green-600 rounded-full flex items-center justify-center mb-2">
                                <i class="fas fa-calendar-plus text-white"></i>
                            </div>
                            <span class="text-sm font-medium text-gray-700">Book Facility</span>
                        </a>
                        <a href="{{ route('payments.index') }}" class="flex flex-col items-center p-4 bg-orange-50 hover:bg-orange-100 rounded-lg transition-colors">
                            <div class="w-12 h-12 bg-orange-600 rounded-full flex items-center justify-center mb-2">
                                <i class="fas fa-credit-card text-white"></i>
                            </div>
                            <span class="text-sm font-medium text-gray-700">Pay Bills</span>
                        </a>
                        <a href="{{ route('notices.index') }}" class="flex flex-col items-center p-4 bg-indigo-50 hover:bg-indigo-100 rounded-lg transition-colors">
                            <div class="w-12 h-12 bg-indigo-600 rounded-full flex items-center justify-center mb-2">
                                <i class="fas fa-bullhorn text-white"></i>
                            </div>
                            <span class="text-sm font-medium text-gray-700">View Notices</span>
                        </a>
                    </div>
                </div>
            </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- My Bills -->
        <div class="bg-white rounded-xl shadow-lg overflow-hidden">
            <div class="bg-gradient-to-r from-green-500 to-teal-600 px-6 py-4">
                <h2 class="text-xl font-bold text-white flex items-center">
                    <i class="fas fa-file-invoice mr-3"></i>
                    Recent Bills
                </h2>
            </div>
            <div class="divide-y divide-gray-200">
                @forelse($myBills ?? [] as $bill)
                    <div class="px-6 py-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-900">{{ $bill->description ?? 'Maintenance Bill' }}</p>
                                <p class="text-xs text-gray-500">Due: {{ $bill->due_date ? $bill->due_date->format('M d, Y') : 'N/A' }}</p>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-bold {{ $bill->status === 'paid' ? 'text-green-600' : 'text-red-600' }}">₹{{ number_format($bill->amount, 2) }}</p>
                                <span class="px-2 py-0.5 text-xs rounded-full {{ $bill->status === 'paid' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                    {{ ucfirst($bill->status) }}
                                </span>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="px-6 py-8 text-center text-gray-500">
                        <i class="fas fa-file-invoice text-3xl mb-2"></i>
                        <p>No bills found</p>
                    </div>
                @endforelse
            </div>
        </div>

        <!-- My Tickets -->
        <div class="bg-white rounded-xl shadow-lg overflow-hidden">
            <div class="bg-gradient-to-r from-purple-500 to-pink-600 px-6 py-4">
                <h2 class="text-xl font-bold text-white flex items-center">
                    <i class="fas fa-ticket-alt mr-3"></i>
                    My Tickets
                </h2>
            </div>
            <div class="divide-y divide-gray-200">
                @forelse($myTickets ?? [] as $ticket)
                    <div class="px-6 py-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-900">{{ Str::limit($ticket->subject, 35) }}</p>
                                <p class="text-xs text-gray-500">{{ $ticket->created_at->diffForHumans() }}</p>
                            </div>
                            <span class="px-2 py-1 text-xs rounded-full {{ $ticket->status === 'open' ? 'bg-red-100 text-red-700' : ($ticket->status === 'in_progress' ? 'bg-yellow-100 text-yellow-700' : 'bg-green-100 text-green-700') }}">
                                {{ ucfirst(str_replace('_', ' ', $ticket->status)) }}
                            </span>
                        </div>
                    </div>
                @empty
                    <div class="px-6 py-8 text-center text-gray-500">
                        <i class="fas fa-ticket-alt text-3xl mb-2"></i>
                        <p>No tickets found</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

        <!-- Right Column - Sidebar -->
        <div class="space-y-6">
            <!-- Owner Summary -->
            <div class="bg-gradient-to-br from-purple-50 to-indigo-50 rounded-xl shadow-lg p-6 border border-purple-200">
                <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                    <i class="fas fa-info-circle mr-2 text-purple-600"></i>
                    Owner Summary
                </h3>
                <div class="space-y-4">
                    <div class="flex items-center justify-between py-2 border-b border-purple-200">
                        <span class="text-gray-600">User ID</span>
                        <span class="font-semibold text-gray-900">{{ auth()->user()->id }}</span>
                    </div>
                    <div class="flex items-center justify-between py-2 border-b border-purple-200">
                        <span class="text-gray-600">Role</span>
                        <span class="font-semibold text-gray-900">Owner</span>
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

    <!-- Notices -->
    @if(isset($notices) && $notices->count() > 0)
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
            <h3 class="font-semibold text-gray-900">Latest Notices</h3>
            <a href="{{ route('notices.index') }}" class="text-sm text-purple-600 hover:text-purple-700">View All</a>
        </div>
        <div class="p-6 space-y-4">
            @foreach($notices as $notice)
                <div class="flex items-start p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                    <div class="w-10 h-10 bg-yellow-100 rounded-full flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-bullhorn text-yellow-600"></i>
                    </div>
                    <div class="ml-4">
                        <h4 class="font-medium text-gray-900">{{ $notice->title }}</h4>
                        <p class="text-sm text-gray-600 mt-1">{{ Str::limit($notice->content, 100) }}</p>
                        <p class="text-xs text-gray-500 mt-2">{{ $notice->created_at->diffForHumans() }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    @endif
</div>
@endsection
