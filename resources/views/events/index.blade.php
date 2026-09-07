@extends('layouts.app')

@section('title', 'Events')
@section('page-title', 'Events')

@section('content')
<div class="space-y-6">
    <!-- Header with Actions -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Events</h2>
            <p class="text-gray-600 text-sm mt-1">Manage and view all events</p>
        </div>
        <div class="flex gap-2">
            @if(auth()->user()->hasRole(['Admin', 'Manager']))
                <a href="{{ route('events.create') }}" class="inline-flex items-center px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors">
                    <i class="fas fa-plus mr-2"></i>
                    Add Event
                </a>
            @endif
            <a href="{{ route('events.calendar') }}" class="inline-flex items-center px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 transition-colors">
                <i class="fas fa-calendar-alt mr-2"></i>
                Calendar View
            </a>
        </div>
    </div>

    <!-- Filters and Search -->
    <div class="bg-white rounded-lg shadow p-4">
        <form method="GET" action="{{ route('events.index') }}" class="flex flex-col sm:flex-row gap-4">
            <!-- Search -->
            <div class="flex-1">
                <input type="text" name="search" placeholder="Search events..." value="{{ request('search') }}" 
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
            </div>

            <!-- Status Filter -->
            <div class="w-full sm:w-48">
                <select name="status" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    <option value="">All Status</option>
                    <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
                    <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>Completed</option>
                    <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                </select>
            </div>

            <!-- Filter Button -->
            <button type="submit" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors">
                <i class="fas fa-filter mr-2"></i>
                Filter
            </button>

            @if(request('search') || request('status'))
                <a href="{{ route('events.index') }}" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 transition-colors">
                    <i class="fas fa-times mr-2"></i>
                    Clear
                </a>
            @endif
        </form>
    </div>

    <!-- Events Table -->
    <div class="bg-white rounded-lg shadow-lg overflow-hidden">
        @if($events->count() > 0)
            <div class="overflow-x-auto">
                <table class="w-full border-collapse">
                    <thead>
                        <tr class="bg-gradient-to-r from-purple-600 to-purple-700 text-white">
                            <th class="px-6 py-4 text-left text-sm font-semibold">Event Name</th>
                            <th class="px-6 py-4 text-left text-sm font-semibold">Location</th>
                            <th class="px-6 py-4 text-left text-sm font-semibold">Start Date & Time</th>
                            <th class="px-6 py-4 text-left text-sm font-semibold">End Date & Time</th>
                            <th class="px-6 py-4 text-left text-sm font-semibold">Status</th>
                            <th class="px-6 py-4 text-center text-sm font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($events as $event)
                            <tr class="border-b border-gray-200 hover:bg-purple-50 transition-colors duration-200">
                                <td class="px-6 py-4 border-r border-gray-200">
                                    <div class="text-sm font-semibold text-gray-900">{{ $event->event_name }}</div>
                                </td>
                                <td class="px-6 py-4 border-r border-gray-200">
                                    <div class="text-sm text-gray-700">
                                        <i class="fas fa-map-marker-alt text-purple-600 mr-2"></i>{{ $event->location }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 border-r border-gray-200">
                                    <div class="text-sm text-gray-700">
                                        <i class="fas fa-calendar text-blue-600 mr-2"></i>{{ $event->start_date->format('M d, Y') }}
                                        <div class="text-xs text-gray-600 ml-6">{{ $event->start_date->format('H:i') }}</div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 border-r border-gray-200">
                                    <div class="text-sm text-gray-700">
                                        <i class="fas fa-calendar text-blue-600 mr-2"></i>{{ $event->end_date->format('M d, Y') }}
                                        <div class="text-xs text-gray-600 ml-6">{{ $event->end_date->format('H:i') }}</div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 border-r border-gray-200">
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold
                                        @if($event->status === 'completed') bg-green-100 text-green-800
                                        @elseif($event->status === 'pending') bg-yellow-100 text-yellow-800
                                        @else bg-red-100 text-red-800
                                        @endif">
                                        <i class="fas mr-1
                                            @if($event->status === 'completed') fa-check-circle
                                            @elseif($event->status === 'pending') fa-clock
                                            @else fa-times-circle
                                            @endif"></i>
                                        {{ ucfirst($event->status) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex gap-3 justify-center">
                                        <a href="{{ route('events.show', $event) }}" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-blue-100 text-blue-600 hover:bg-blue-200 transition-colors" title="View">
                                            <i class="fas fa-eye text-sm"></i>
                                        </a>
                                        @if(auth()->user()->hasRole('Admin') || (auth()->user()->hasRole('Manager') && $event->created_by === auth()->id()))
                                            <a href="{{ route('events.edit', $event) }}" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-purple-100 text-purple-600 hover:bg-purple-200 transition-colors" title="Edit">
                                                <i class="fas fa-edit text-sm"></i>
                                            </a>
                                        @endif
                                        @if(auth()->user()->hasRole('Admin'))
                                            <form method="POST" action="{{ route('events.destroy', $event) }}" class="inline" onsubmit="return confirm('Are you sure?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-red-100 text-red-600 hover:bg-red-200 transition-colors" title="Delete">
                                                    <i class="fas fa-trash text-sm"></i>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="bg-gray-50 px-6 py-4 border-t border-gray-200">
                {{ $events->links() }}
            </div>
        @else
            <div class="text-center py-16">
                <i class="fas fa-calendar-alt text-5xl text-gray-300 mb-4"></i>
                <p class="text-gray-500 text-lg font-medium">No events found</p>
                <p class="text-gray-400 text-sm mt-1">Create your first event to get started</p>
                @if(auth()->user()->hasRole(['Admin', 'Manager']))
                    <a href="{{ route('events.create') }}" class="inline-block mt-6 px-6 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors font-medium">
                        <i class="fas fa-plus mr-2"></i>Create First Event
                    </a>
                @endif
            </div>
        @endif
    </div>
</div>
@endsection
