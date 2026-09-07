@extends('layouts.app')

@section('title', 'Pending Notices')
@section('page-title', 'Pending Notices')

@section('content')
<div class="container mx-auto px-4 py-6">
    <!-- Header -->
    <div class="flex justify-between items-center mb-6">
        <div class="flex items-center">
            <a href="{{ route('notices.index') }}" class="text-gray-600 hover:text-gray-900 mr-4">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Pending Approval</h1>
                <p class="text-gray-600 mt-1">Review and approve notices submitted by residents</p>
            </div>
        </div>
    </div>

    <!-- Success/Error Messages -->
    @if(session('success'))
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-lg">
            <div class="flex items-center">
                <i class="fas fa-check-circle mr-3"></i>
                <p class="font-medium">{{ session('success') }}</p>
            </div>
        </div>
    @endif

    <!-- Pending Notices -->
    <div class="space-y-4">
        @forelse($notices as $notice)
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="p-6">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <div class="flex items-center space-x-3 mb-2">
                                @php
                                    $typeColors = [
                                        'general' => 'bg-blue-100 text-blue-800',
                                        'urgent' => 'bg-red-100 text-red-800',
                                        'maintenance' => 'bg-orange-100 text-orange-800',
                                        'event' => 'bg-purple-100 text-purple-800',
                                        'meeting' => 'bg-green-100 text-green-800',
                                    ];
                                    $priorityColors = [
                                        'low' => 'bg-green-100 text-green-800',
                                        'medium' => 'bg-yellow-100 text-yellow-800',
                                        'high' => 'bg-red-100 text-red-800',
                                    ];
                                @endphp
                                <span class="px-2 py-1 text-xs font-semibold rounded-full {{ $typeColors[$notice->type] ?? 'bg-gray-100 text-gray-800' }}">
                                    {{ ucfirst($notice->type) }}
                                </span>
                                <span class="px-2 py-1 text-xs font-semibold rounded-full {{ $priorityColors[$notice->priority] ?? 'bg-gray-100 text-gray-800' }}">
                                    {{ ucfirst($notice->priority) }} Priority
                                </span>
                            </div>
                            <h3 class="text-xl font-semibold text-gray-900 mb-2">{{ $notice->title }}</h3>
                            <p class="text-gray-600 mb-4">{{ Str::limit($notice->content, 200) }}</p>
                            
                            <div class="flex items-center text-sm text-gray-500 space-x-4">
                                <span><i class="fas fa-user mr-1"></i> {{ $notice->createdBy->name ?? 'Unknown' }}</span>
                                <span><i class="fas fa-building mr-1"></i> {{ $notice->society->name ?? 'N/A' }}</span>
                                <span><i class="fas fa-calendar mr-1"></i> {{ $notice->created_at->format('M d, Y h:i A') }}</span>
                            </div>
                        </div>

                        @if($notice->image)
                            <div class="ml-6 flex-shrink-0">
                                <img src="{{ asset('storage/' . $notice->image) }}" alt="{{ $notice->title }}" class="w-24 h-24 object-cover rounded-lg">
                            </div>
                        @endif
                    </div>

                    <div class="mt-6 pt-4 border-t border-gray-200 flex justify-between items-center">
                        <a href="{{ route('notices.show', $notice) }}" class="text-blue-600 hover:text-blue-800 font-medium">
                            <i class="fas fa-eye mr-1"></i> View Full Notice
                        </a>
                        
                        @if(auth()->user()->hasRole(['Admin', 'Super Admin']))
                        <div class="flex space-x-3">
                            <form action="{{ route('notices.approve', $notice) }}" method="POST" class="inline">
                                @csrf
                                <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors inline-flex items-center">
                                    <i class="fas fa-check mr-2"></i>Approve
                                </button>
                            </form>
                            <button onclick="openRejectModal({{ $notice->id }})" 
                                    class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors inline-flex items-center">
                                <i class="fas fa-times mr-2"></i>Reject
                            </button>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="bg-white rounded-lg shadow-md p-12 text-center">
                <div class="inline-flex items-center justify-center w-16 h-16 bg-green-100 rounded-full mb-4">
                    <i class="fas fa-check-circle text-green-600 text-2xl"></i>
                </div>
                <h3 class="text-lg font-medium text-gray-900 mb-2">All caught up!</h3>
                <p class="text-gray-500 mb-6">There are no notices pending approval.</p>
                <a href="{{ route('notices.index') }}" 
                   class="inline-flex items-center text-blue-600 hover:text-blue-800 font-medium">
                    <i class="fas fa-arrow-left mr-2"></i>
                    Back to Notice Board
                </a>
            </div>
        @endforelse
    </div>

    <!-- Pagination -->
    @if($notices->hasPages())
        <div class="mt-6">
            {{ $notices->links() }}
        </div>
    @endif
</div>

<!-- Reject Modal -->
<div id="rejectModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-lg bg-white">
        <div class="mt-3">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Reject Notice</h3>
            <form id="rejectForm" method="POST">
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
                    <button type="button" onclick="closeRejectModal()"
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

@push('scripts')
<script>
    function openRejectModal(noticeId) {
        document.getElementById('rejectForm').action = '/notices/' + noticeId + '/reject';
        document.getElementById('rejectModal').classList.remove('hidden');
    }
    
    function closeRejectModal() {
        document.getElementById('rejectModal').classList.add('hidden');
        document.getElementById('rejection_reason').value = '';
    }
</script>
@endpush
@endsection
