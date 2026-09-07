@extends('layouts.app')

@section('title', $notice->title)
@section('page-title', 'Notice')

@section('content')
<div class="p-6">
    <div class="max-w-3xl mx-auto">
        <div class="mb-6">
            <a href="{{ route('villa-owner.notices') }}" class="text-green-600 hover:text-green-800 text-sm">
                <i class="fas fa-arrow-left mr-1"></i> Back to Notices
            </a>
        </div>

        <div class="bg-white rounded-xl border border-gray-200">
            <div class="p-6 border-b border-gray-200">
                <div class="flex items-center space-x-2 mb-3">
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
                <h1 class="text-2xl font-bold text-gray-800">{{ $notice->title }}</h1>
                <div class="mt-3 flex items-center text-sm text-gray-500">
                    <i class="fas fa-calendar mr-2"></i>
                    {{ \Carbon\Carbon::parse($notice->created_at)->format('d M Y, h:i A') }}
                    @if($notice->creator)
                        <span class="mx-2">•</span>
                        <i class="fas fa-user mr-2"></i>
                        {{ $notice->creator->name }}
                    @endif
                </div>
            </div>

            @if($notice->image)
            <div class="p-6 border-b border-gray-200">
                <img src="{{ asset('storage/' . $notice->image) }}" alt="{{ $notice->title }}" class="w-full rounded-lg">
            </div>
            @endif

            <div class="p-6">
                <div class="prose max-w-none">
                    {!! nl2br(e($notice->content)) !!}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
