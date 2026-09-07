@extends('layouts.app')

@section('title', 'Staff Dashboard')
@section('page-title', 'Staff Dashboard')

@section('content')
<div class="container mx-auto px-4 py-6">
    <!-- Prominent Staff Header -->
    <div class="bg-gradient-to-r from-blue-600 via-purple-600 to-indigo-700 rounded-2xl shadow-2xl p-8 mb-8 text-white relative overflow-hidden">
        <!-- Background Pattern -->
        <div class="absolute inset-0 opacity-10">
            <svg class="w-full h-full" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
                <defs>
                    <pattern id="staff-pattern" x="0" y="0" width="20" height="20" patternUnits="userSpaceOnUse">
                        <rect x="0" y="0" width="20" height="20" fill="none" stroke="currentColor" stroke-width="0.5"/>
                        <circle cx="10" cy="10" r="2" fill="currentColor"/>
                    </pattern>
                </defs>
                <rect width="100%" height="100%" fill="url(#staff-pattern)"/>
            </svg>
        </div>
        
        <!-- Staff Icon -->
        <div class="absolute top-6 right-6 opacity-20">
            <i class="fas fa-user-tie text-6xl"></i>
        </div>
        
        <div class="relative z-10">
            <div class="flex items-center mb-4">
                <div class="w-16 h-16 bg-white bg-opacity-20 rounded-full flex items-center justify-center mr-4">
                    <i class="fas fa-clipboard-list text-2xl"></i>
                </div>
                <div>
                    <h1 class="text-4xl font-bold mb-2">Staff Dashboard</h1>
                    <p class="text-blue-100 text-lg">Welcome back, {{ auth()->user()->name }}!</p>
                </div>
            </div>
            
            <!-- Staff Role and Department Info -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-8">
                <div class="bg-white bg-opacity-15 backdrop-blur-sm rounded-xl p-6 border border-white border-opacity-20">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-yellow-400 rounded-lg flex items-center justify-center mr-4">
                            <i class="fas fa-id-badge text-white text-xl"></i>
                        </div>
                        <div>
                            <p class="text-blue-100 text-sm font-medium uppercase tracking-wide">Role</p>
                            <p class="text-3xl font-bold">Staff Member</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white bg-opacity-15 backdrop-blur-sm rounded-xl p-6 border border-white border-opacity-20">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-green-400 rounded-lg flex items-center justify-center mr-4">
                            <i class="fas fa-building text-white text-xl"></i>
                        </div>
                        <div>
                            <p class="text-blue-100 text-sm font-medium uppercase tracking-wide">Society</p>
                            <p class="text-3xl font-bold">{{ auth()->user()->society->name ?? 'N/A' }}</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Quick Stats -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-6">
                <div class="text-center">
                    <p class="text-2xl font-bold">{{ $stats['visitors_today'] ?? 0 }}</p>
                    <p class="text-blue-100 text-sm">Visitors Today</p>
                </div>
                <div class="text-center">
                    <p class="text-2xl font-bold">{{ $stats['pending_tickets'] ?? 0 }}</p>
                    <p class="text-blue-100 text-sm">Pending Tickets</p>
                </div>
                <div class="text-center">
                    <p class="text-2xl font-bold">{{ $stats['assigned_services'] ?? 0 }}</p>
                    <p class="text-blue-100 text-sm">Assigned Services</p>
                </div>
                <div class="text-center">
                    <p class="text-2xl font-bold">{{ $stats['todays_events'] ?? 0 }}</p>
                    <p class="text-blue-100 text-sm">Today's Events</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Dashboard Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <!-- Visitors Today -->
        <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-blue-500 hover:shadow-xl transition-shadow">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-user-friends text-blue-600 text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-2xl font-bold text-gray-900">{{ $stats['visitors_today'] ?? 0 }}</p>
                    <p class="text-gray-600 text-sm">Visitors Today</p>
                </div>
            </div>
        </div>

        <!-- Pending Tickets -->
        <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-orange-500 hover:shadow-xl transition-shadow">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-ticket-alt text-orange-600 text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-2xl font-bold text-gray-900">{{ $stats['pending_tickets'] ?? 0 }}</p>
                    <p class="text-gray-600 text-sm">Pending Tickets</p>
                </div>
            </div>
        </div>

        <!-- Assigned Services -->
        <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-green-500 hover:shadow-xl transition-shadow">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-concierge-bell text-green-600 text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-2xl font-bold text-gray-900">{{ $stats['assigned_services'] ?? 0 }}</p>
                    <p class="text-gray-600 text-sm">Assigned Services</p>
                </div>
            </div>
        </div>

        <!-- Today's Events -->
        <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-purple-500 hover:shadow-xl transition-shadow">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-calendar-alt text-purple-600 text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-2xl font-bold text-gray-900">{{ $stats['todays_events'] ?? 0 }}</p>
                    <p class="text-gray-600 text-sm">Today's Events</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Left Column - Main Content -->
        <div class="lg:col-span-2 space-y-8">
            <!-- Assigned Tickets -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="bg-gradient-to-r from-indigo-500 to-purple-600 px-6 py-4">
                    <h2 class="text-xl font-bold text-white flex items-center">
                        <i class="fas fa-clipboard-list mr-3"></i>
                        My Assigned Tickets
                    </h2>
                </div>
                <div class="divide-y divide-gray-200 max-h-96 overflow-y-auto">
                    @forelse($assignedTickets ?? [] as $ticket)
                        <div class="px-6 py-4 hover:bg-gray-50">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center">
                                        <span class="px-2 py-0.5 text-xs rounded-full {{ $ticket->priority === 'high' ? 'bg-red-100 text-red-700' : ($ticket->priority === 'medium' ? 'bg-yellow-100 text-yellow-700' : 'bg-gray-100 text-gray-700') }}">
                                            {{ ucfirst($ticket->priority ?? 'Normal') }}
                                        </span>
                                        <span class="ml-2 text-xs text-gray-500">#{{ $ticket->id }}</span>
                                    </div>
                                    <p class="text-sm font-medium text-gray-900 mt-1">{{ $ticket->title }}</p>
                                    <p class="text-xs text-gray-500 mt-1">
                                        <i class="fas fa-user mr-1"></i>{{ $ticket->createdBy->name ?? 'Unknown' }}
                                        <span class="mx-2">•</span>
                                        {{ $ticket->created_at->diffForHumans() }}
                                    </p>
                                </div>
                                <div class="ml-4">
                                    <a href="{{ route('complaints.show', $ticket) }}" class="px-3 py-1.5 bg-purple-600 hover:bg-purple-700 text-white text-xs rounded-lg">
                                        View
                                    </a>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="px-6 py-8 text-center text-gray-500">
                            <i class="fas fa-check-circle text-4xl mb-2 text-green-500"></i>
                            <p>No pending tickets!</p>
                            <p class="text-sm">All caught up.</p>
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- Today's Visitors -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="bg-gradient-to-r from-green-500 to-teal-600 px-6 py-4">
                    <h2 class="text-xl font-bold text-white flex items-center">
                        <i class="fas fa-user-friends mr-3"></i>
                        Today's Visitors
                    </h2>
                </div>
                <div class="divide-y divide-gray-200 max-h-96 overflow-y-auto">
                    @forelse($todayVisitors ?? [] as $visitor)
                        <div class="px-6 py-4 hover:bg-gray-50">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 bg-gray-100 rounded-full flex items-center justify-center">
                                        <i class="fas fa-user text-gray-500"></i>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm font-medium text-gray-900">{{ $visitor->name }}</p>
                                        <p class="text-xs text-gray-500">
                                            {{ $visitor->flat->unit_number ?? 'N/A' }} • {{ $visitor->purpose }}
                                        </p>
                                    </div>
                                </div>
                                <div class="flex items-center space-x-2">
                                    @if($visitor->status === 'expected')
                                        <form action="{{ route('visitors.check-in', $visitor) }}" method="POST" class="inline">
                                            @csrf
                                            <button type="submit" class="px-3 py-1.5 bg-green-600 hover:bg-green-700 text-white text-xs rounded-lg">
                                                <i class="fas fa-sign-in-alt mr-1"></i>Check In
                                            </button>
                                        </form>
                                    @elseif($visitor->status === 'checked_in')
                                        <form action="{{ route('visitors.check-out', $visitor) }}" method="POST" class="inline">
                                            @csrf
                                            <button type="submit" class="px-3 py-1.5 bg-orange-600 hover:bg-orange-700 text-white text-xs rounded-lg">
                                                <i class="fas fa-sign-out-alt mr-1"></i>Check Out
                                            </button>
                                        </form>
                                    @else
                                        <span class="px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-600">
                                            {{ ucfirst(str_replace('_', ' ', $visitor->status)) }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="px-6 py-8 text-center text-gray-500">
                            <i class="fas fa-user-friends text-4xl mb-2"></i>
                            <p>No visitors expected today</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- Right Column - Sidebar -->
        <div class="space-y-6">
            <!-- Staff Summary -->
            <div class="bg-gradient-to-br from-purple-50 to-indigo-50 rounded-xl shadow-lg p-6 border border-purple-200">
                <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                    <i class="fas fa-info-circle mr-2 text-purple-600"></i>
                    Staff Summary
                </h3>
                <div class="space-y-4">
                    <div class="flex items-center justify-between py-2 border-b border-purple-200">
                        <span class="text-gray-600">User ID</span>
                        <span class="font-semibold text-gray-900">{{ auth()->user()->id }}</span>
                    </div>
                    <div class="flex items-center justify-between py-2 border-b border-purple-200">
                        <span class="text-gray-600">Role</span>
                        <span class="font-semibold text-gray-900">Staff</span>
                    </div>
                    <div class="flex items-center justify-between py-2">
                        <span class="text-gray-600">Society</span>
                        <span class="font-semibold text-gray-900">{{ auth()->user()->society->name ?? 'N/A' }}</span>
                    </div>
                </div>
            </div>

            <!-- Recent Notices (Read-Only) -->
            <div class="bg-gradient-to-br from-blue-50 to-cyan-50 rounded-xl shadow-lg p-6 border border-blue-200">
                <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                    <i class="fas fa-bullhorn mr-2 text-blue-600"></i>
                    Recent Notices
                </h3>
                <div class="space-y-3">
                    @forelse($notices ?? [] as $notice)
                        <div class="bg-white rounded-lg p-3 border border-blue-100">
                            <h4 class="text-sm font-medium text-gray-900 mb-1">{{ $notice->title }}</h4>
                            <p class="text-xs text-gray-600 mb-2">{{ Str::limit($notice->content, 80) }}</p>
                            <p class="text-xs text-blue-600">{{ $notice->created_at->diffForHumans() }}</p>
                        </div>
                    @empty
                        <div class="text-center text-gray-500 py-4">
                            <i class="fas fa-bullhorn text-2xl mb-2"></i>
                            <p class="text-sm">No recent notices</p>
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- Contact Support -->
            <div class="bg-gradient-to-br from-green-50 to-emerald-50 rounded-xl shadow-lg p-6 border border-green-200">
                <h3 class="text-lg font-semibold text-gray-900 mb-3 flex items-center">
                    <i class="fas fa-headset mr-2 text-green-600"></i>
                    Need Help?
                </h3>
                <p class="text-gray-700 text-sm mb-4">
                    Contact technical support for assistance with your tasks or system issues.
                </p>
                <div class="space-y-3 text-sm">
                    <div class="flex items-center text-gray-600">
                        <i class="fas fa-envelope text-green-600 mr-3 w-4"></i>
                        <span>support@societyflow.com</span>
                    </div>
                    <div class="flex items-center text-gray-600">
                        <i class="fas fa-phone text-green-600 mr-3 w-4"></i>
                        <span>+1 (555) 123-4567</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
