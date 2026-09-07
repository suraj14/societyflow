@extends('layouts.app')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@section('content')
<div class="space-y-6">
    <!-- Welcome Banner -->
    <div class="bg-gradient-to-r from-purple-600 to-indigo-600 rounded-xl p-8 text-white shadow-lg">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-3xl font-bold">Welcome back, {{ auth()->user()->name }}!</h2>
                <p class="text-purple-100 mt-2 text-lg">Here's what's happening in your society today.</p>
            </div>
            <div class="hidden md:block opacity-20">
                <i class="fas fa-building text-7xl"></i>
            </div>
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <!-- Total Buildings -->
        <div class="stat-card">
            <div class="flex items-center">
                <div class="stat-card-icon bg-blue-100 text-blue-600">
                    <i class="fas fa-city"></i>
                </div>
                <div class="ml-4">
                    <p class="stat-card-label">Buildings</p>
                    <p class="stat-card-value">{{ $stats['total_buildings'] ?? 0 }}</p>
                </div>
            </div>
        </div>

        <!-- Total Units -->
        <div class="stat-card">
            <div class="flex items-center">
                <div class="stat-card-icon bg-green-100 text-green-600">
                    <i class="fas fa-door-open"></i>
                </div>
                <div class="ml-4">
                    <p class="stat-card-label">Units</p>
                    <p class="stat-card-value">{{ $stats['total_units'] ?? 0 }}</p>
                    <p class="text-xs text-green-600 font-medium mt-1">{{ $stats['occupied_units'] ?? 0 }} occupied</p>
                </div>
            </div>
        </div>

        <!-- Total Residents -->
        <div class="stat-card">
            <div class="flex items-center">
                <div class="stat-card-icon bg-purple-100 text-purple-600">
                    <i class="fas fa-users"></i>
                </div>
                <div class="ml-4">
                    <p class="stat-card-label">Residents</p>
                    <p class="stat-card-value">{{ $stats['total_residents'] ?? 0 }}</p>
                </div>
            </div>
        </div>

        <!-- Open Tickets -->
        <div class="stat-card">
            <div class="flex items-center">
                <div class="stat-card-icon bg-red-100 text-red-600">
                    <i class="fas fa-ticket-alt"></i>
                </div>
                <div class="ml-4">
                    <p class="stat-card-label">Open Tickets</p>
                    <p class="stat-card-value">{{ $stats['open_tickets'] ?? 0 }}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Financial Stats -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Monthly Revenue -->
        <div class="stat-card">
            <div class="flex items-center justify-between">
                <div>
                    <p class="stat-card-label">Monthly Revenue</p>
                    <p class="text-2xl font-bold text-green-600">${{ number_format($stats['monthly_revenue'] ?? 0, 2) }}</p>
                </div>
                <div class="stat-card-icon bg-green-100 text-green-600">
                    <i class="fas fa-dollar-sign"></i>
                </div>
            </div>
        </div>

        <!-- Pending Dues -->
        <div class="stat-card">
            <div class="flex items-center justify-between">
                <div>
                    <p class="stat-card-label">Pending Dues</p>
                    <p class="text-2xl font-bold text-orange-600">${{ number_format($stats['pending_dues'] ?? 0, 2) }}</p>
                </div>
                <div class="stat-card-icon bg-orange-100 text-orange-600">
                    <i class="fas fa-exclamation-circle"></i>
                </div>
            </div>
        </div>

        <!-- Visitors Today -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Visitors Today</p>
                    <p class="text-2xl font-bold text-blue-600">{{ $stats['visitors_today'] ?? 0 }}</p>
                </div>
                <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-user-friends text-blue-600 text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Recent Tickets -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200">
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                <h3 class="font-semibold text-gray-900">Recent Tickets</h3>
                <a href="{{ route('complaints.index') }}" class="text-sm text-purple-600 hover:text-purple-700">View All</a>
            </div>
            <div class="divide-y divide-gray-200">
                @forelse($recentTickets ?? [] as $ticket)
                    <div class="px-6 py-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-900">{{ Str::limit($ticket->subject, 40) }}</p>
                                <p class="text-xs text-gray-500">{{ $ticket->createdBy->name ?? 'Unknown' }} • {{ $ticket->created_at->diffForHumans() }}</p>
                            </div>
                            <span class="px-2 py-1 text-xs rounded-full {{ $ticket->status === 'open' ? 'bg-red-100 text-red-700' : ($ticket->status === 'in_progress' ? 'bg-yellow-100 text-yellow-700' : 'bg-green-100 text-green-700') }}">
                                {{ ucfirst($ticket->status) }}
                            </span>
                        </div>
                    </div>
                @empty
                    <div class="px-6 py-8 text-center text-gray-500">
                        <i class="fas fa-ticket-alt text-3xl mb-2"></i>
                        <p>No recent tickets</p>
                    </div>
                @endforelse
            </div>
        </div>

        <!-- Recent Visitors -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200">
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                <h3 class="font-semibold text-gray-900">Recent Visitors</h3>
                <a href="{{ route('visitors.index') }}" class="text-sm text-purple-600 hover:text-purple-700">View All</a>
            </div>
            <div class="divide-y divide-gray-200">
                @forelse($recentVisitors ?? [] as $visitor)
                    <div class="px-6 py-4">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <div class="w-10 h-10 bg-gray-100 rounded-full flex items-center justify-center">
                                    <i class="fas fa-user text-gray-500"></i>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm font-medium text-gray-900">{{ $visitor->name }}</p>
                                    <p class="text-xs text-gray-500">{{ $visitor->flat->unit_number ?? 'N/A' }} • {{ $visitor->purpose }}</p>
                                </div>
                            </div>
                            <span class="px-2 py-1 text-xs rounded-full {{ $visitor->status === 'checked_in' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700' }}">
                                {{ ucfirst(str_replace('_', ' ', $visitor->status)) }}
                            </span>
                        </div>
                    </div>
                @empty
                    <div class="px-6 py-8 text-center text-gray-500">
                        <i class="fas fa-user-friends text-3xl mb-2"></i>
                        <p>No recent visitors</p>
                    </div>
                @endforelse
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
        <div class="p-6 grid grid-cols-1 md:grid-cols-3 gap-4">
            @foreach($notices as $notice)
                <div class="border border-gray-200 rounded-lg p-4 hover:border-purple-300 transition-colors">
                    <div class="flex items-center mb-2">
                        <span class="px-2 py-0.5 text-xs rounded-full bg-purple-100 text-purple-700">{{ ucfirst($notice->type ?? 'General') }}</span>
                        <span class="ml-auto text-xs text-gray-500">{{ $notice->created_at->diffForHumans() }}</span>
                    </div>
                    <h4 class="font-medium text-gray-900 mb-1">{{ Str::limit($notice->title, 30) }}</h4>
                    <p class="text-sm text-gray-500">{{ Str::limit($notice->content, 60) }}</p>
                </div>
            @endforeach
        </div>
    </div>
    @endif
</div>
@endsection
