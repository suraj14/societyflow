@extends('layouts.app')

@section('title', 'Notice Details')
@section('page-title', 'Notice Details')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="flex justify-between items-center mb-6">
        <div class="flex items-center">
            <a href="{{ route('notices.index') }}" class="text-gray-600 hover:text-gray-900 mr-4">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div>
                <h1 class="text-3xl font-bold text-gray-900">{{ $notice->title }}</h1>
                <p class="text-gray-600 mt-1">Notice Details</p>
            </div>
        </div>
        <div class="flex space-x-3">
            @if($notice->status === 'pending' && auth()->user()->hasRole(['Admin', 'Super Admin']))
                <form action="{{ route('notices.approve', $notice) }}" method="POST" class="inline">
                    @csrf
                    <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-lg font-medium transition-colors inline-flex items-center">
                        <i class="fas fa-check mr-2"></i>Approve
                    </button>
                </form>
                <button onclick="document.getElementById('rejectModal').classList.remove('hidden')" 
                        class="bg-red-600 hover:bg-red-700 text-white px-6 py-3 rounded-lg font-medium transition-colors inline-flex items-center">
                    <i class="fas fa-times mr-2"></i>Reject
                </button>
            @endif
            @can('manage_notices')
            <a href="{{ route('notices.edit', $notice) }}" 
               class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-3 rounded-lg font-medium transition-colors inline-flex items-center">
                <i class="fas fa-edit mr-2"></i>Edit
            </a>
            @endcan
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Content -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Notice Image -->
            @if($notice->image)
                <div class="bg-white rounded-lg shadow-md overflow-hidden">
                    <img src="{{ asset('storage/' . $notice->image) }}" alt="{{ $notice->title }}" class="w-full h-64 object-cover">
                </div>
            @endif

            <!-- Notice Content -->
            <div class="bg-white rounded-lg shadow-md">
                <div class="px-6 py-4 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-gray-900">Notice Content</h2>
                        @php
                            $priorityColors = [
                                'low' => 'bg-green-100 text-green-800',
                                'medium' => 'bg-yellow-100 text-yellow-800',
                                'high' => 'bg-red-100 text-red-800',
                            ];
                        @endphp
                        <span class="px-3 py-1 text-xs font-semibold rounded-full {{ $priorityColors[$notice->priority] ?? 'bg-gray-100 text-gray-800' }}">
                            {{ ucfirst($notice->priority) }} Priority
                        </span>
                    </div>
                </div>
                <div class="px-6 py-4">
                    <div class="prose max-w-none">
                        {!! nl2br(e($notice->content)) !!}
                    </div>
                </div>
            </div>

            <!-- Rejection Reason (if rejected) -->
            @if($notice->status === 'rejected' && $notice->rejection_reason)
                <div class="bg-red-50 rounded-lg shadow-md border border-red-200">
                    <div class="px-6 py-4">
                        <h3 class="text-lg font-semibold text-red-800 mb-2">
                            <i class="fas fa-exclamation-circle mr-2"></i>Rejection Reason
                        </h3>
                        <p class="text-red-700">{{ $notice->rejection_reason }}</p>
                        @if($notice->approvedBy)
                            <p class="text-sm text-red-600 mt-2">
                                Rejected by {{ $notice->approvedBy->name }} on {{ $notice->updated_at->format('M d, Y h:i A') }}
                            </p>
                        @endif
                    </div>
                </div>
            @endif

            <!-- Read Statistics -->
            <div class="bg-white rounded-lg shadow-md">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">Read Statistics</h2>
                </div>
                <div class="px-6 py-4">
                    <div class="flex items-center justify-between mb-4">
                        <span class="text-gray-600">Total Reads</span>
                        <span class="text-2xl font-bold text-blue-600">{{ $notice->reads->count() }}</span>
                    </div>
                    @if($notice->reads->count() > 0)
                        <div class="border-t border-gray-200 pt-4">
                            <h4 class="text-sm font-medium text-gray-700 mb-3">Recent Readers</h4>
                            <div class="space-y-2">
                                @foreach($notice->reads->take(5) as $read)
                                    <div class="flex items-center justify-between text-sm">
                                        <span class="text-gray-900">{{ $read->user->name ?? 'Unknown' }}</span>
                                        <span class="text-gray-500">{{ \Carbon\Carbon::parse($read->read_at)->format('M d, h:i A') }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <p class="text-gray-500 text-sm">No one has read this notice yet.</p>
                    @endif
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
            <!-- Status Card -->
            <div class="bg-white rounded-lg shadow-md">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">Status</h2>
                </div>
                <div class="px-6 py-4">
                    @php
                        $statusColors = [
                            'draft' => 'bg-gray-100 text-gray-800',
                            'pending' => 'bg-yellow-100 text-yellow-800',
                            'published' => 'bg-green-100 text-green-800',
                            'rejected' => 'bg-red-100 text-red-800',
                            'expired' => 'bg-gray-100 text-gray-800',
                        ];
                    @endphp
                    <span class="px-4 py-2 text-sm font-semibold rounded-full {{ $statusColors[$notice->status] ?? 'bg-gray-100 text-gray-800' }}">
                        {{ ucfirst($notice->status) }}
                    </span>
                </div>
            </div>

            <!-- Notice Details -->
            <div class="bg-white rounded-lg shadow-md">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">Details</h2>
                </div>
                <div class="px-6 py-4">
                    <dl class="space-y-4">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Type</dt>
                            <dd class="mt-1">
                                @php
                                    $typeColors = [
                                        'general' => 'bg-blue-100 text-blue-800',
                                        'urgent' => 'bg-red-100 text-red-800',
                                        'maintenance' => 'bg-orange-100 text-orange-800',
                                        'event' => 'bg-purple-100 text-purple-800',
                                        'meeting' => 'bg-green-100 text-green-800',
                                    ];
                                @endphp
                                <span class="px-2 py-1 text-xs font-semibold rounded-full {{ $typeColors[$notice->type] ?? 'bg-gray-100 text-gray-800' }}">
                                    {{ ucfirst($notice->type) }}
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Society</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $notice->society->name ?? 'N/A' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Created By</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $notice->createdBy->name ?? 'Unknown' }}</dd>
                        </div>
                        @if($notice->approvedBy && $notice->status === 'published')
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Approved By</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $notice->approvedBy->name }}</dd>
                            </div>
                        @endif
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Publish Date</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $notice->publish_date ? $notice->publish_date->format('M d, Y') : 'Not set' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Expiry Date</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $notice->expiry_date ? $notice->expiry_date->format('M d, Y') : 'No expiry' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Created At</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $notice->created_at->format('M d, Y h:i A') }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            <!-- Notification Settings -->
            <div class="bg-white rounded-lg shadow-md">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">Notifications</h2>
                </div>
                <div class="px-6 py-4">
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-600">Email Notification</span>
                            @if($notice->send_email)
                                <span class="text-green-600"><i class="fas fa-check-circle"></i> Enabled</span>
                            @else
                                <span class="text-gray-400"><i class="fas fa-times-circle"></i> Disabled</span>
                            @endif
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-600">SMS Notification</span>
                            @if($notice->send_sms)
                                <span class="text-green-600"><i class="fas fa-check-circle"></i> Enabled</span>
                            @else
                                <span class="text-gray-400"><i class="fas fa-times-circle"></i> Disabled</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            @can('manage_notices')
            <div class="bg-white rounded-lg shadow-md">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">Actions</h2>
                </div>
                <div class="px-6 py-4 space-y-3">
                    <a href="{{ route('notices.edit', $notice) }}" 
                       class="w-full bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg font-medium transition-colors inline-flex items-center justify-center">
                        <i class="fas fa-edit mr-2"></i>Edit Notice
                    </a>
                    <form action="{{ route('notices.destroy', $notice) }}" method="POST" class="w-full"
                          onsubmit="return confirm('Are you sure you want to delete this notice?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="w-full bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg font-medium transition-colors inline-flex items-center justify-center">
                            <i class="fas fa-trash mr-2"></i>Delete
                        </button>
                    </form>
                </div>
            </div>
            @endcan
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div id="rejectModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-lg bg-white">
        <div class="mt-3">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Reject Notice</h3>
            <form action="{{ route('notices.reject', $notice) }}" method="POST">
                @csrf
                <div class="mb-4">
                    <label for="rejection_reason" class="block text-sm font-medium text-gray-700 mb-2">
                        Reason for Rejection <span class="text-red-500">*</span>
                    </label>
                    <textarea name="rejection_reason" id="rejection_reason" rows="4" required
                              placeholder="Please provide a reason for rejection..."
                              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500"></textarea>
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="document.getElementById('rejectModal').classList.add('hidden')"
                            class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">
                        Reject Notice
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
