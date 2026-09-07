@extends('layouts.app')

@section('title', $event->event_name)
@section('page-title', $event->event_name)

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="bg-white rounded-lg shadow">
        <!-- Header -->
        <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-semibold text-gray-900">{{ $event->event_name }}</h2>
                <p class="text-gray-600 text-sm mt-1">
                    <i class="fas fa-map-marker-alt mr-2"></i>{{ $event->location }}
                </p>
            </div>
            <div class="flex gap-2">
                @if(auth()->user()->hasRole('Admin') || (auth()->user()->hasRole('Manager') && $event->created_by === auth()->id()))
                    <a href="{{ route('events.edit', $event) }}" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors">
                        <i class="fas fa-edit mr-2"></i>
                        Edit
                    </a>
                @endif
                <a href="{{ route('events.index') }}" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 transition-colors">
                    <i class="fas fa-arrow-left mr-2"></i>
                    Back
                </a>
            </div>
        </div>

        <!-- Content -->
        <div class="p-6 space-y-6">
            <!-- Status Badge -->
            <div>
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium
                    @if($event->status === 'completed') bg-green-100 text-green-800
                    @elseif($event->status === 'pending') bg-yellow-100 text-yellow-800
                    @else bg-red-100 text-red-800
                    @endif">
                    {{ ucfirst($event->status) }}
                </span>
                @php
                    $canMarkCompleted = $event->end_date <= now();
                @endphp
                @if($event->status === 'pending' && !$canMarkCompleted)
                    <p class="text-sm text-blue-600 mt-2">
                        <i class="fas fa-info-circle mr-1"></i>
                        Can be marked as "Completed" after {{ $event->end_date->format('M d, Y H:i') }}
                    </p>
                @endif
            </div>

            <!-- Event Details Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Start Date/Time -->
                <div class="bg-gray-50 rounded-lg p-4">
                    <p class="text-sm text-gray-600 mb-1">Start Date & Time</p>
                    <p class="text-lg font-semibold text-gray-900">
                        <i class="fas fa-calendar-alt mr-2 text-purple-600"></i>
                        {{ $event->start_date->format('M d, Y') }}
                    </p>
                    <p class="text-lg font-semibold text-gray-900 mt-1">
                        <i class="fas fa-clock mr-2 text-purple-600"></i>
                        {{ $event->start_date->format('H:i') }}
                    </p>
                </div>

                <!-- End Date/Time -->
                <div class="bg-gray-50 rounded-lg p-4">
                    <p class="text-sm text-gray-600 mb-1">End Date & Time</p>
                    <p class="text-lg font-semibold text-gray-900">
                        <i class="fas fa-calendar-alt mr-2 text-purple-600"></i>
                        {{ $event->end_date->format('M d, Y') }}
                    </p>
                    <p class="text-lg font-semibold text-gray-900 mt-1">
                        <i class="fas fa-clock mr-2 text-purple-600"></i>
                        {{ $event->end_date->format('H:i') }}
                    </p>
                </div>
            </div>

            <!-- Duration -->
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <p class="text-sm text-blue-600 mb-1">Duration</p>
                <p class="text-lg font-semibold text-blue-900">
                    @php
                        $duration = $event->end_date->diff($event->start_date);
                        $days = $duration->days;
                        $hours = $duration->h;
                        $minutes = $duration->i;
                    @endphp
                    @if($days > 0)
                        {{ $days }} day{{ $days > 1 ? 's' : '' }}, 
                    @endif
                    {{ $hours }} hour{{ $hours > 1 ? 's' : '' }}, 
                    {{ $minutes }} minute{{ $minutes > 1 ? 's' : '' }}
                </p>
            </div>

            <!-- Description -->
            <div>
                <h3 class="text-lg font-semibold text-gray-900 mb-3">Description</h3>
                <div class="bg-gray-50 rounded-lg p-4 text-gray-700 whitespace-pre-wrap">
                    {{ $event->description }}
                </div>
            </div>

            <!-- Visibility Information -->
            <div class="border-t border-gray-200 pt-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-3">Visibility</h3>
                @if($event->is_role_based)
                    <div class="bg-gray-50 rounded-lg p-4">
                        <p class="text-sm text-gray-600 mb-2">Visible to Roles:</p>
                        <div class="flex flex-wrap gap-2">
                            @forelse($event->visible_roles ?? [] as $role)
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-purple-100 text-purple-800">
                                    {{ $role }}
                                </span>
                            @empty
                                <p class="text-gray-600">No roles selected</p>
                            @endforelse
                        </div>
                    </div>
                @else
                    <div class="bg-gray-50 rounded-lg p-4">
                        <p class="text-sm text-gray-600 mb-2">Visible to Users:</p>
                        <div class="space-y-2">
                            @forelse($event->visible_users ?? [] as $userId)
                                @php
                                    $user = \App\Models\User::find($userId);
                                @endphp
                                @if($user)
                                    <div class="flex items-center">
                                        <img class="h-8 w-8 rounded-full object-cover mr-2" 
                                             src="https://ui-avatars.com/api/?name={{ urlencode($user->name) }}&background=667eea&color=fff" 
                                             alt="{{ $user->name }}">
                                        <div>
                                            <p class="text-sm font-medium text-gray-900">{{ $user->name }}</p>
                                            <p class="text-xs text-gray-600">{{ $user->email }}</p>
                                        </div>
                                    </div>
                                @endif
                            @empty
                                <p class="text-gray-600">No users selected</p>
                            @endforelse
                        </div>
                    </div>
                @endif
            </div>

            <!-- Creator Information -->
            <div class="border-t border-gray-200 pt-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-3">Event Information</h3>
                <div class="bg-gray-50 rounded-lg p-4 space-y-3">
                    <div class="flex items-center">
                        <p class="text-sm text-gray-600 w-32">Created by:</p>
                        <div class="flex items-center">
                            <img class="h-8 w-8 rounded-full object-cover mr-2" 
                                 src="https://ui-avatars.com/api/?name={{ urlencode($event->creator->name ?? 'Unknown') }}&background=667eea&color=fff" 
                                 alt="{{ $event->creator->name ?? 'Unknown' }}">
                            <div>
                                <p class="text-sm font-medium text-gray-900">{{ $event->creator->name ?? 'Unknown' }}</p>
                                <p class="text-xs text-gray-600">{{ $event->creator->email ?? '' }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center">
                        <p class="text-sm text-gray-600 w-32">Created on:</p>
                        <p class="text-sm text-gray-900">{{ $event->created_at->format('M d, Y H:i') }}</p>
                    </div>
                    <div class="flex items-center">
                        <p class="text-sm text-gray-600 w-32">Last updated:</p>
                        <p class="text-sm text-gray-900">{{ $event->updated_at->format('M d, Y H:i') }}</p>
                    </div>
                </div>
            </div>

            <!-- Delete Button (Admin only) -->
            @if(auth()->user()->hasRole('Admin'))
                <div class="border-t border-gray-200 pt-6">
                    <form method="POST" action="{{ route('events.destroy', $event) }}" onsubmit="return confirm('Are you sure you want to delete this event?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors">
                            <i class="fas fa-trash mr-2"></i>
                            Delete Event
                        </button>
                    </form>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
