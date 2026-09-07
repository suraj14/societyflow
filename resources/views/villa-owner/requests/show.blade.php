@extends('layouts.app')

@section('title', 'Request Details')
@section('page-title', 'Request Details')

@section('content')
<div class="p-6">
    <div class="max-w-3xl mx-auto">
        <div class="mb-6">
            <a href="{{ route('villa-owner.requests') }}" class="text-green-600 hover:text-green-800 text-sm">
                <i class="fas fa-arrow-left mr-1"></i> Back to Requests
            </a>
        </div>

        <div class="bg-white rounded-xl border border-gray-200">
            <div class="p-6 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-xl font-bold text-gray-800">{{ $request->title }}</h2>
                        <p class="text-gray-500 text-sm mt-1">
                            Submitted on {{ \Carbon\Carbon::parse($request->created_at)->format('d M Y, h:i A') }}
                        </p>
                    </div>
                    <div class="flex items-center space-x-2">
                        <span class="px-3 py-1 text-sm rounded-full 
                            {{ $request->priority === 'urgent' ? 'bg-red-100 text-red-700' : '' }}
                            {{ $request->priority === 'high' ? 'bg-orange-100 text-orange-700' : '' }}
                            {{ $request->priority === 'medium' ? 'bg-yellow-100 text-yellow-700' : '' }}
                            {{ $request->priority === 'low' ? 'bg-gray-100 text-gray-700' : '' }}">
                            {{ ucfirst($request->priority) }} Priority
                        </span>
                        <span class="px-3 py-1 text-sm rounded-full 
                            {{ $request->status === 'open' ? 'bg-yellow-100 text-yellow-700' : '' }}
                            {{ $request->status === 'in_progress' ? 'bg-blue-100 text-blue-700' : '' }}
                            {{ $request->status === 'resolved' ? 'bg-green-100 text-green-700' : '' }}
                            {{ $request->status === 'closed' ? 'bg-gray-100 text-gray-700' : '' }}">
                            {{ ucfirst(str_replace('_', ' ', $request->status)) }}
                        </span>
                    </div>
                </div>
            </div>

            <div class="p-6">
                @if($request->category)
                <div class="mb-4">
                    <p class="text-sm text-gray-500">Category</p>
                    <p class="font-medium text-gray-800">{{ $request->category->name }}</p>
                </div>
                @endif

                <div class="mb-6">
                    <p class="text-sm text-gray-500 mb-2">Description</p>
                    <div class="bg-gray-50 rounded-lg p-4">
                        <p class="text-gray-700 whitespace-pre-wrap">{{ $request->description }}</p>
                    </div>
                </div>

                @if($request->updates && $request->updates->count() > 0)
                <div class="border-t border-gray-200 pt-6">
                    <h3 class="font-semibold text-gray-800 mb-4">Updates</h3>
                    <div class="space-y-4">
                        @foreach($request->updates as $update)
                            <div class="bg-gray-50 rounded-lg p-4">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-sm font-medium text-gray-800">{{ $update->user->name ?? 'System' }}</span>
                                    <span class="text-xs text-gray-500">{{ \Carbon\Carbon::parse($update->created_at)->format('d M Y, h:i A') }}</span>
                                </div>
                                <p class="text-gray-700">{{ $update->comment }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
