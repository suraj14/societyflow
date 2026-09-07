@extends('layouts.app')

@section('title', 'Service Provider Details')
@section('page-title', 'Service Provider Details')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center">
            <a href="{{ route('service-providers.index') }}" 
               class="text-gray-600 hover:text-gray-900 mr-4 transition-colors">
                <i class="fas fa-arrow-left text-xl"></i>
            </a>
            <div>
                <h1 class="text-3xl font-bold text-gray-900">{{ $serviceProvider->name }}</h1>
                <p class="text-gray-600 mt-1">{{ $serviceProvider->service->name }} Provider</p>
            </div>
        </div>
        @can('update', $serviceProvider)
        <div class="flex space-x-3">
            <a href="{{ route('service-providers.edit', $serviceProvider) }}" 
               class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium transition-colors inline-flex items-center">
                <i class="fas fa-edit mr-2"></i>
                Edit Provider
            </a>
        </div>
        @endcan
    </div>

    <div class="max-w-4xl mx-auto">
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="p-6">
                <!-- Provider Information Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Left Column -->
                    <div class="space-y-6">
                        <!-- Basic Information -->
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">Provider Information</h3>
                            <div class="space-y-3">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Name:</span>
                                    <span class="font-medium">{{ $serviceProvider->name }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Contact Number:</span>
                                    <span class="font-medium">{{ $serviceProvider->contact_number }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Service Type:</span>
                                    <span class="font-medium">{{ $serviceProvider->service->name }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Category:</span>
                                    <span class="font-medium">{{ $serviceProvider->service->category_label }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Daily Help:</span>
                                    <span class="font-medium">{{ $serviceProvider->is_daily_help ? 'Yes' : 'No' }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Status Information -->
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">Status</h3>
                            <div class="space-y-3">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Availability:</span>
                                    {!! $serviceProvider->availability_badge !!}
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Status:</span>
                                    {!! $serviceProvider->status_badge !!}
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Added On:</span>
                                    <span class="font-medium">{{ $serviceProvider->created_at->format('M d, Y') }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column -->
                    <div class="space-y-6">
                        <!-- Pricing Information -->
                        @if(auth()->user()->hasAnyRole(['Admin', 'Super Admin']))
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">Pricing</h3>
                            <div class="bg-gray-50 rounded-lg p-4">
                                <div class="text-2xl font-bold text-green-600 mb-2">
                                    {{ $serviceProvider->formatted_price }}
                                </div>
                                @if($serviceProvider->price)
                                <div class="text-sm text-gray-600">
                                    Base rate: ₹{{ number_format($serviceProvider->price, 2) }}
                                </div>
                                @endif
                            </div>
                        </div>
                        @endif

                        <!-- Service Icon -->
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">Service</h3>
                            <div class="bg-gray-50 rounded-lg p-4 text-center">
                                <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-3">
                                    <i class="{{ $serviceProvider->service->icon_class }} text-blue-600 text-2xl"></i>
                                </div>
                                <h4 class="font-semibold text-gray-900">{{ $serviceProvider->service->name }}</h4>
                                <p class="text-sm text-gray-600">{{ $serviceProvider->service->category_label }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Notes Section -->
                @if($serviceProvider->notes)
                <div class="mt-6 pt-6 border-t border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900 mb-3">Notes</h3>
                    <div class="bg-gray-50 rounded-lg p-4">
                        <p class="text-gray-700">{{ $serviceProvider->notes }}</p>
                    </div>
                </div>
                @endif

                <!-- Recent Attendance -->
                @if($serviceProvider->attendance->count() > 0)
                <div class="mt-6 pt-6 border-t border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Recent Attendance</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Clock In</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Clock Out</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Duration</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($serviceProvider->attendance as $record)
                                <tr>
                                    <td class="px-4 py-2 text-sm text-gray-900">
                                        {{ $record->attendance_date->format('M d, Y') }}
                                    </td>
                                    <td class="px-4 py-2 text-sm text-gray-900">
                                        {{ $record->clock_in_time ? \Carbon\Carbon::parse($record->clock_in_time)->format('h:i A') : '-' }}
                                    </td>
                                    <td class="px-4 py-2 text-sm text-gray-900">
                                        {{ $record->clock_out_time ? \Carbon\Carbon::parse($record->clock_out_time)->format('h:i A') : '-' }}
                                    </td>
                                    <td class="px-4 py-2 text-sm text-gray-900">
                                        {{ $record->duration ?: '-' }}
                                    </td>
                                    <td class="px-4 py-2">
                                        {!! $record->status_badge !!}
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                @endif

                <!-- Actions -->
                <div class="mt-6 pt-6 border-t border-gray-200 flex justify-between">
                    <div class="flex space-x-3">
                        @can('update', $serviceProvider)
                        <a href="{{ route('service-providers.edit', $serviceProvider) }}" 
                           class="text-blue-600 hover:text-blue-800 font-medium transition-colors">
                            <i class="fas fa-edit mr-1"></i>
                            Edit Provider
                        </a>
                        @endcan
                    </div>
                    @can('delete', $serviceProvider)
                    <form action="{{ route('service-providers.destroy', $serviceProvider) }}" 
                          method="POST" 
                          class="inline-block"
                          onsubmit="return confirm('Are you sure you want to delete this service provider? This action cannot be undone.');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" 
                                class="text-red-600 hover:text-red-800 font-medium transition-colors">
                            <i class="fas fa-trash mr-1"></i>
                            Delete Provider
                        </button>
                    </form>
                    @endcan
                </div>
            </div>
        </div>
    </div>
</div>
@endsection