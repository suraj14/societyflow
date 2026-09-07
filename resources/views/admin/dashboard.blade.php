@extends('layouts.app')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@section('content')
<div class="p-6">
    <!-- Stats Grid - Simple Clean Design -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <!-- Buildings -->
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm font-medium">Buildings</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2">{{ $stats['total_buildings'] ?? 0 }}</p>
                </div>
                <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-building text-blue-600 text-xl"></i>
                </div>
            </div>
        </div>

        <!-- Units -->
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm font-medium">Units</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2">{{ $stats['total_units'] ?? 0 }}</p>
                </div>
                <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-door-open text-green-600 text-xl"></i>
                </div>
            </div>
        </div>

        <!-- Residents -->
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm font-medium">Residents</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2">{{ $stats['total_residents'] ?? 0 }}</p>
                </div>
                <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-users text-purple-600 text-xl"></i>
                </div>
            </div>
        </div>

        <!-- Open Tickets -->
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm font-medium">Open Tickets</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2">{{ $stats['open_tickets'] ?? 0 }}</p>
                </div>
                <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-ticket-alt text-red-600 text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Secondary Stats -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <!-- Monthly Revenue -->
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm font-medium">Monthly Revenue</p>
                    <p class="text-2xl font-bold text-gray-900 mt-2">₹{{ number_format($stats['monthly_revenue'] ?? 0) }}</p>
                </div>
                <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-rupee-sign text-green-600 text-xl"></i>
                </div>
            </div>
        </div>

        <!-- Pending Dues -->
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm font-medium">Pending Dues</p>
                    <p class="text-2xl font-bold text-gray-900 mt-2">₹{{ number_format($stats['pending_dues'] ?? 0) }}</p>
                </div>
                <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-exclamation-circle text-orange-600 text-xl"></i>
                </div>
            </div>
        </div>

        <!-- Visitors Today -->
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm font-medium">Visitors Today</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2">{{ $stats['visitors_today'] ?? 0 }}</p>
                </div>
                <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-users text-blue-600 text-xl"></i>
                </div>
            </div>
        </div>

        <!-- Occupied Units -->
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm font-medium">Occupied Units</p>
                    <p class="text-2xl font-bold text-gray-900 mt-2">{{ $stats['occupied_units'] ?? 0 }}</p>
                </div>
                <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-check-circle text-green-600 text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Data -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- Recent Tickets -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                <h2 class="text-lg font-semibold text-gray-900">Recent Tickets</h2>
                <a href="{{ route('complaints.index') }}" class="text-purple-600 text-sm font-medium hover:text-purple-700">View All</a>
            </div>
            <div class="divide-y divide-gray-200">
                @forelse($recentTickets ?? [] as $ticket)
                    <div class="px-6 py-4 hover:bg-gray-50 transition-colors">
                        <div class="flex items-center justify-between">
                            <div class="flex-1">
                                <p class="text-sm font-medium text-gray-900">{{ $ticket->title ?? 'Complaint' }}</p>
                                <p class="text-xs text-gray-500 mt-1">{{ $ticket->created_at?->diffForHumans() ?? 'Recently' }}</p>
                            </div>
                            <span class="px-3 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">Open</span>
                        </div>
                    </div>
                @empty
                    <div class="px-6 py-8 text-center">
                        <p class="text-gray-500 text-sm">No recent tickets</p>
                    </div>
                @endforelse
            </div>
        </div>

        <!-- Recent Visitors -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                <h2 class="text-lg font-semibold text-gray-900">Recent Visitors</h2>
                <a href="{{ route('visitors.index') }}" class="text-purple-600 text-sm font-medium hover:text-purple-700">View All</a>
            </div>
            <div class="divide-y divide-gray-200">
                @forelse($recentVisitors ?? [] as $visitor)
                    <div class="px-6 py-4 hover:bg-gray-50 transition-colors">
                        <div class="flex items-center">
                            <div class="w-10 h-10 bg-gray-200 rounded-full flex items-center justify-center mr-3">
                                <i class="fas fa-user text-gray-600"></i>
                            </div>
                            <div class="flex-1">
                                <p class="text-sm font-medium text-gray-900">{{ $visitor->name ?? 'Visitor' }}</p>
                                <p class="text-xs text-gray-500">{{ $visitor->created_at?->format('M d, Y') ?? 'Recently' }}</p>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="px-6 py-8 text-center">
                        <p class="text-gray-500 text-sm">No recent visitors</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Latest Notices -->
    @if(isset($notices) && $notices->count() > 0)
    <div class="mt-8 bg-white rounded-lg shadow overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Latest Notices</h2>
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
