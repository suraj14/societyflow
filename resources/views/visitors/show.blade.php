@extends('layouts.app')

@section('title', 'Visitor Details')
@section('page-title', 'Visitor Details')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="flex justify-between items-center mb-6">
        <div class="flex items-center">
            <a href="{{ route('visitors.index') }}" class="text-gray-600 hover:text-gray-900 mr-4">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div>
                <h1 class="text-3xl font-bold text-gray-900">{{ $visitor->visitor_name }}</h1>
                <p class="text-gray-600 mt-1">Visitor Details</p>
            </div>
        </div>
        <div class="flex space-x-3">
            @if($visitor->approval_status === 'approved' && $visitor->entry_status === 'pending')
                <form action="{{ route('visitors.check-in', $visitor) }}" method="POST" class="inline-block">
                    @csrf
                    <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-lg font-medium transition-colors inline-flex items-center">
                        <i class="fas fa-sign-in-alt mr-2"></i>Check In
                    </button>
                </form>
            @endif
            @if($visitor->entry_status === 'entered')
                <form action="{{ route('visitors.check-out', $visitor) }}" method="POST" class="inline-block">
                    @csrf
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-medium transition-colors inline-flex items-center">
                        <i class="fas fa-sign-out-alt mr-2"></i>Check Out
                    </button>
                </form>
            @endif
            <a href="{{ route('visitors.edit', $visitor) }}" 
               class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-3 rounded-lg font-medium transition-colors inline-flex items-center">
                <i class="fas fa-edit mr-2"></i>Edit
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Details -->
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white rounded-lg shadow-md">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">Visitor Information</h2>
                </div>
                <div class="px-6 py-4">
                    <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Visitor Name</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $visitor->visitor_name }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Mobile Number</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                <i class="fas fa-phone text-gray-400 mr-2"></i>{{ $visitor->visitor_phone }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Visitor Type</dt>
                            <dd class="mt-1">
                                @php
                                    $typeColors = [
                                        'guest' => 'bg-blue-100 text-blue-800',
                                        'delivery' => 'bg-orange-100 text-orange-800',
                                        'cab' => 'bg-purple-100 text-purple-800',
                                        'service' => 'bg-green-100 text-green-800',
                                        'other' => 'bg-gray-100 text-gray-800'
                                    ];
                                @endphp
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $typeColors[$visitor->visitor_type] ?? 'bg-gray-100 text-gray-800' }}">
                                    {{ ucfirst($visitor->visitor_type) }}
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Number of Visitors</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $visitor->expected_count }}</dd>
                        </div>
                        @if($visitor->vehicle_number)
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Vehicle Number</dt>
                                <dd class="mt-1 text-sm text-gray-900">
                                    <i class="fas fa-car text-gray-400 mr-2"></i>{{ $visitor->vehicle_number }}
                                </dd>
                            </div>
                        @endif
                    </dl>

                    @if($visitor->purpose)
                        <div class="mt-4 pt-4 border-t border-gray-200">
                            <dt class="text-sm font-medium text-gray-500">Purpose of Visit</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $visitor->purpose }}</dd>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Visit Details -->
            <div class="bg-white rounded-lg shadow-md">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">Visit Details</h2>
                </div>
                <div class="px-6 py-4">
                    <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Visiting Apartment</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                {{ $visitor->flat->building->name ?? '' }} - {{ $visitor->flat->flat_number ?? '' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Host</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $visitor->host->name ?? 'N/A' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Expected Entry</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ \Carbon\Carbon::parse($visitor->expected_entry_time)->format('M d, Y h:i A') }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Expected Exit</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                {{ $visitor->expected_exit_time ? \Carbon\Carbon::parse($visitor->expected_exit_time)->format('M d, Y h:i A') : 'N/A' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Actual Entry</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                {{ $visitor->actual_entry_time ? \Carbon\Carbon::parse($visitor->actual_entry_time)->format('M d, Y h:i A') : '-' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Actual Exit</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                {{ $visitor->actual_exit_time ? \Carbon\Carbon::parse($visitor->actual_exit_time)->format('M d, Y h:i A') : '-' }}
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>

            <!-- Activity Log -->
            @if($visitor->logs && $visitor->logs->count() > 0)
                <div class="bg-white rounded-lg shadow-md">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2 class="text-lg font-semibold text-gray-900">Activity Log</h2>
                    </div>
                    <div class="px-6 py-4">
                        <div class="flow-root">
                            <ul class="-mb-8">
                                @foreach($visitor->logs as $log)
                                    <li>
                                        <div class="relative pb-8">
                                            @if(!$loop->last)
                                                <span class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-gray-200"></span>
                                            @endif
                                            <div class="relative flex space-x-3">
                                                <div>
                                                    @php
                                                        $actionColors = [
                                                            'created' => 'bg-blue-500',
                                                            'approved' => 'bg-green-500',
                                                            'rejected' => 'bg-red-500',
                                                            'entry' => 'bg-green-500',
                                                            'exit' => 'bg-gray-500',
                                                            'updated' => 'bg-yellow-500',
                                                        ];
                                                    @endphp
                                                    <span class="h-8 w-8 rounded-full {{ $actionColors[$log->action] ?? 'bg-gray-500' }} flex items-center justify-center ring-8 ring-white">
                                                        <i class="fas fa-check text-white text-xs"></i>
                                                    </span>
                                                </div>
                                                <div class="min-w-0 flex-1 pt-1.5 flex justify-between space-x-4">
                                                    <div>
                                                        <p class="text-sm text-gray-500">
                                                            {{ ucfirst($log->action) }} by <span class="font-medium text-gray-900">{{ $log->user->name ?? 'System' }}</span>
                                                        </p>
                                                        @if($log->notes)
                                                            <p class="text-sm text-gray-500">{{ $log->notes }}</p>
                                                        @endif
                                                    </div>
                                                    <div class="text-right text-sm whitespace-nowrap text-gray-500">
                                                        {{ $log->created_at->format('M d, h:i A') }}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
            <!-- Status Card -->
            <div class="bg-white rounded-lg shadow-md">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">Status</h2>
                </div>
                <div class="px-6 py-4">
                    <dl class="space-y-4">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Approval Status</dt>
                            <dd class="mt-1">
                                @php
                                    $approvalColors = [
                                        'pending' => 'bg-yellow-100 text-yellow-800',
                                        'approved' => 'bg-green-100 text-green-800',
                                        'rejected' => 'bg-red-100 text-red-800',
                                    ];
                                @endphp
                                <span class="px-3 py-1 inline-flex text-sm leading-5 font-semibold rounded-full {{ $approvalColors[$visitor->approval_status] ?? 'bg-gray-100 text-gray-800' }}">
                                    {{ ucfirst($visitor->approval_status) }}
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Entry Status</dt>
                            <dd class="mt-1">
                                @php
                                    $entryColors = [
                                        'pending' => 'bg-yellow-100 text-yellow-800',
                                        'entered' => 'bg-green-100 text-green-800',
                                        'exited' => 'bg-gray-100 text-gray-800',
                                    ];
                                    $entryLabels = [
                                        'pending' => 'Pending',
                                        'entered' => 'Checked In',
                                        'exited' => 'Checked Out',
                                    ];
                                @endphp
                                <span class="px-3 py-1 inline-flex text-sm leading-5 font-semibold rounded-full {{ $entryColors[$visitor->entry_status] ?? 'bg-gray-100 text-gray-800' }}">
                                    {{ $entryLabels[$visitor->entry_status] ?? ucfirst($visitor->entry_status) }}
                                </span>
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="bg-white rounded-lg shadow-md">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">Quick Actions</h2>
                </div>
                <div class="px-6 py-4 space-y-3">
                    <a href="{{ route('visitors.edit', $visitor) }}" 
                       class="w-full bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg font-medium transition-colors inline-flex items-center justify-center">
                        <i class="fas fa-edit mr-2"></i>Edit Visitor
                    </a>
                    <form action="{{ route('visitors.destroy', $visitor) }}" method="POST" class="w-full"
                          onsubmit="return confirm('Are you sure you want to delete this visitor record?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="w-full bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg font-medium transition-colors inline-flex items-center justify-center">
                            <i class="fas fa-trash mr-2"></i>Delete
                        </button>
                    </form>
                </div>
            </div>

            <!-- Timestamps -->
            <div class="bg-white rounded-lg shadow-md">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">Record Info</h2>
                </div>
                <div class="px-6 py-4">
                    <dl class="space-y-4">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Created At</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $visitor->created_at->format('M d, Y h:i A') }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Last Updated</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $visitor->updated_at->format('M d, Y h:i A') }}</dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
