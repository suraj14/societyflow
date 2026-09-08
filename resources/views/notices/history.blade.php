@extends('layouts.app')

@section('title', 'Notice History')
@section('page-title', 'Notice History')

@section('content')
<div class="container mx-auto px-4 py-6">
    <!-- Header -->
    <div class="flex justify-between items-center mb-6">
        <div class="flex items-center">
            <a href="{{ route('notices.index') }}" class="text-gray-600 hover:text-gray-900 mr-4">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Notice History</h1>
                <p class="text-gray-600 mt-1">Every notice raised, with its approval outcome</p>
            </div>
        </div>
    </div>

    <!-- Date filters -->
    <div class="bg-white rounded-lg shadow-md p-4 mb-6">
        <form method="GET" action="{{ route('notices.history') }}" class="flex flex-wrap items-end gap-4">
            <div>
                <label for="date_from" class="block text-sm font-medium text-gray-700 mb-1">From</label>
                <input type="date" name="date_from" id="date_from" value="{{ request('date_from') }}"
                       class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div>
                <label for="date_to" class="block text-sm font-medium text-gray-700 mb-1">To</label>
                <input type="date" name="date_to" id="date_to" value="{{ request('date_to') }}"
                       class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                <i class="fas fa-filter mr-2"></i>Filter
            </button>
            @if(request()->hasAny(['date_from', 'date_to']))
                <a href="{{ route('notices.history') }}" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                    Clear
                </a>
            @endif
        </form>
    </div>

    <!-- History list -->
    <div class="space-y-4">
        @forelse($notices as $notice)
            @php
                $typeColors = [
                    'general' => 'bg-blue-100 text-blue-800',
                    'urgent' => 'bg-red-100 text-red-800',
                    'maintenance' => 'bg-orange-100 text-orange-800',
                    'event' => 'bg-purple-100 text-purple-800',
                    'meeting' => 'bg-green-100 text-green-800',
                ];
                $statusColors = [
                    'draft' => 'bg-gray-100 text-gray-800',
                    'pending' => 'bg-yellow-100 text-yellow-800',
                    'published' => 'bg-green-100 text-green-800',
                    'rejected' => 'bg-red-100 text-red-800',
                    'expired' => 'bg-gray-100 text-gray-600',
                ];
            @endphp
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="p-6">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <div class="flex items-center space-x-3 mb-2">
                                <span class="px-2 py-1 text-xs font-semibold rounded-full {{ $typeColors[$notice->type] ?? 'bg-gray-100 text-gray-800' }}">
                                    {{ ucfirst($notice->type) }}
                                </span>
                                <span class="px-2 py-1 text-xs font-semibold rounded-full {{ $statusColors[$notice->status] ?? 'bg-gray-100 text-gray-800' }}">
                                    {{ ucfirst($notice->status) }}
                                </span>
                            </div>

                            <h3 class="text-xl font-semibold text-gray-900 mb-2">{{ $notice->title }}</h3>
                            <p class="text-gray-600 mb-4">{{ Str::limit($notice->content, 200) }}</p>

                            <div class="flex flex-wrap items-center text-sm text-gray-500 gap-x-4 gap-y-1">
                                <span><i class="fas fa-user mr-1"></i> {{ $notice->createdBy->name ?? 'Unknown' }}</span>
                                <span><i class="fas fa-building mr-1"></i> {{ $notice->society->name ?? 'N/A' }}</span>
                                <span><i class="fas fa-calendar mr-1"></i> {{ $notice->created_at?->format('M d, Y h:i A') }}</span>
                                @if($notice->approvedBy)
                                    <span class="text-green-600">
                                        <i class="fas fa-check-circle mr-1"></i>
                                        Approved by {{ $notice->approvedBy->name }}
                                        @if($notice->approved_at)
                                            on {{ $notice->approved_at->format('M d, Y') }}
                                        @endif
                                    </span>
                                @endif
                            </div>

                            @if($notice->status === 'rejected' && $notice->rejection_reason)
                                <div class="mt-4 bg-red-50 border-l-4 border-red-400 p-3 rounded">
                                    <p class="text-sm text-red-700">
                                        <span class="font-semibold">Rejection reason:</span>
                                        {{ $notice->rejection_reason }}
                                    </p>
                                </div>
                            @endif
                        </div>

                        <div class="ml-4 flex-shrink-0">
                            <a href="{{ route('notices.show', $notice) }}"
                               class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                                <i class="fas fa-eye mr-2"></i>View
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="bg-white rounded-lg shadow-md p-12 text-center">
                <div class="inline-flex items-center justify-center w-16 h-16 bg-gray-100 rounded-full mb-4">
                    <i class="fas fa-history text-gray-400 text-2xl"></i>
                </div>
                <h3 class="text-lg font-medium text-gray-900 mb-2">No notices in this period</h3>
                <p class="text-gray-500">
                    @if(request()->hasAny(['date_from', 'date_to']))
                        Try widening the date range.
                    @else
                        Notices will appear here once they have been raised.
                    @endif
                </p>
            </div>
        @endforelse
    </div>

    <!-- Pagination -->
    @if($notices->hasPages())
        <div class="mt-6">
            {{ $notices->appends(request()->query())->links() }}
        </div>
    @endif
</div>
@endsection
