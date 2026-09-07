@extends('layouts.app')

@section('title', 'Visitor Details')
@section('page-title', 'Visitor Details')

@section('content')
<div class="p-6">
    <div class="max-w-2xl mx-auto">
        <div class="mb-6">
            <a href="{{ route('villa-owner.visitors') }}" class="text-green-600 hover:text-green-800 text-sm">
                <i class="fas fa-arrow-left mr-1"></i> Back to Visitors
            </a>
        </div>

        <div class="bg-white rounded-xl border border-gray-200">
            <div class="p-6 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-user text-gray-500 text-2xl"></i>
                        </div>
                        <div class="ml-4">
                            <h2 class="text-xl font-bold text-gray-800">{{ $visitor->visitor_name }}</h2>
                            <p class="text-gray-500">{{ $visitor->visitor_phone }}</p>
                        </div>
                    </div>
                    <span class="px-3 py-1 text-sm rounded-full 
                        {{ $visitor->status === 'checked_in' ? 'bg-green-100 text-green-700' : '' }}
                        {{ $visitor->status === 'checked_out' ? 'bg-gray-100 text-gray-700' : '' }}
                        {{ $visitor->status === 'expected' ? 'bg-yellow-100 text-yellow-700' : '' }}
                        {{ $visitor->status === 'cancelled' ? 'bg-red-100 text-red-700' : '' }}">
                        {{ ucfirst(str_replace('_', ' ', $visitor->status)) }}
                    </span>
                </div>
            </div>

            <div class="p-6 space-y-4">
                <div class="grid md:grid-cols-2 gap-4">
                    <div>
                        <p class="text-sm text-gray-500">Purpose</p>
                        <p class="font-medium text-gray-800">{{ $visitor->purpose }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Visit Date</p>
                        <p class="font-medium text-gray-800">{{ \Carbon\Carbon::parse($visitor->visit_date)->format('d M Y') }}</p>
                    </div>
                    @if($visitor->expected_time)
                    <div>
                        <p class="text-sm text-gray-500">Expected Time</p>
                        <p class="font-medium text-gray-800">{{ $visitor->expected_time }}</p>
                    </div>
                    @endif
                    @if($visitor->visitor_email)
                    <div>
                        <p class="text-sm text-gray-500">Email</p>
                        <p class="font-medium text-gray-800">{{ $visitor->visitor_email }}</p>
                    </div>
                    @endif
                    @if($visitor->vehicle_number)
                    <div>
                        <p class="text-sm text-gray-500">Vehicle Number</p>
                        <p class="font-medium text-gray-800">{{ $visitor->vehicle_number }}</p>
                    </div>
                    @endif
                    @if($visitor->check_in_time)
                    <div>
                        <p class="text-sm text-gray-500">Check-in Time</p>
                        <p class="font-medium text-gray-800">{{ \Carbon\Carbon::parse($visitor->check_in_time)->format('d M Y h:i A') }}</p>
                    </div>
                    @endif
                    @if($visitor->check_out_time)
                    <div>
                        <p class="text-sm text-gray-500">Check-out Time</p>
                        <p class="font-medium text-gray-800">{{ \Carbon\Carbon::parse($visitor->check_out_time)->format('d M Y h:i A') }}</p>
                    </div>
                    @endif
                </div>

                @if($visitor->notes)
                <div class="pt-4 border-t border-gray-200">
                    <p class="text-sm text-gray-500">Notes</p>
                    <p class="text-gray-700">{{ $visitor->notes }}</p>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
