@extends('layouts.app')

@section('title', 'Notices')
@section('page-title', 'Notices')

@section('content')
<div class="p-6">
    <div class="mb-6">
        <p class="text-gray-500">Stay updated with society announcements</p>
    </div>

    <!-- Notices List -->
    <div class="space-y-4">
        @forelse($notices as $notice)
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden hover:shadow-md transition-shadow">
                <div class="p-6">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <div class="flex items-center space-x-2 mb-2">
                                <span class="px-2 py-1 text-xs rounded-full 
                                    {{ $notice->type === 'urgent' ? 'bg-red-100 text-red-700' : '' }}
                                    {{ $notice->type === 'general' ? 'bg-blue-100 text-blue-700' : '' }}
                                    {{ $notice->type === 'maintenance' ? 'bg-yellow-100 text-yellow-700' : '' }}
                                    {{ $notice->type === 'event' ? 'bg-purple-100 text-purple-700' : '' }}
                                    {{ $notice->type === 'meeting' ? 'bg-green-100 text-green-700' : '' }}">
                                    {{ ucfirst($notice->type) }}
                                </span>
                                @if($notice->priority === 'high')
                                    <span class="px-2 py-1 text-xs bg-red-100 text-red-700 rounded-full">
                                        <i class="fas fa-exclamation-circle mr-1"></i> Important
                                    </span>
                                @endif
                            </div>
                            <h3 class="text-lg font-semibold text-gray-800">{{ $notice->title }}</h3>
                            <p class="text-gray-600 mt-2">{{ Str::limit($notice->content, 200) }}</p>
                            <div class="mt-4 flex items-center text-sm text-gray-500">
                                <i class="fas fa-calendar mr-2"></i>
                                {{ \Carbon\Carbon::parse($notice->created_at)->format('d M Y') }}
                                @if($notice->creator)
                                    <span class="mx-2">•</span>
                                    <i class="fas fa-user mr-2"></i>
                                    {{ $notice->creator->name }}
                                @endif
                            </div>
                        </div>
                        @if($notice->image)
                            <div class="ml-4 w-24 h-24 bg-gray-100 rounded-lg overflow-hidden flex-shrink-0">
                                <img src="{{ asset('storage/' . $notice->image) }}" alt="" class="w-full h-full object-cover">
                            </div>
                        @endif
                    </div>
                    <div class="mt-4 pt-4 border-t border-gray-100">
                        <a href="{{ route('villa-owner.notices.show', $notice) }}" class="text-green-600 hover:text-green-800 text-sm font-medium">
                            Read More <i class="fas fa-arrow-right ml-1"></i>
                        </a>
                    </div>
                </div>
            </div>
        @empty
            <div class="bg-white rounded-xl border border-gray-200 p-12 text-center">
                <i class="fas fa-bullhorn text-4xl text-gray-300 mb-3"></i>
                <p class="text-gray-500">No notices available</p>
            </div>
        @endforelse
    </div>

    @if($notices->hasPages())
        <div class="mt-6">
            {{ $notices->links() }}
        </div>
    @endif
</div>
@endsection
