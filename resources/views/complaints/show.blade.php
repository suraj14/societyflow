@extends('layouts.app')

@section('title', 'Ticket #' . $complaint->complaint_number)
@section('page-title', 'Ticket Details')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Main Content -->
        <div class="lg:col-span-2">
            <!-- Ticket Header -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden mb-6">
                <div class="bg-gradient-to-r from-blue-600 to-indigo-600 px-6 py-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-2xl font-bold text-white">Ticket #{{ $complaint->complaint_number }}</h1>
                            <p class="text-blue-100 mt-1">{{ $complaint->title }}</p>
                        </div>
                        <div class="flex items-center space-x-2">
                            <span class="px-3 py-1 text-xs font-semibold rounded-full 
                                {{ $complaint->status === 'open' ? 'bg-red-100 text-red-800' : 
                                   ($complaint->status === 'in_progress' ? 'bg-yellow-100 text-yellow-800' : 
                                   ($complaint->status === 'resolved' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800')) }}">
                                {{ ucfirst(str_replace('_', ' ', $complaint->status)) }}
                            </span>
                            <span class="px-3 py-1 text-xs font-semibold rounded-full 
                                {{ $complaint->priority === 'urgent' ? 'bg-red-100 text-red-800' : 
                                   ($complaint->priority === 'high' ? 'bg-orange-100 text-orange-800' : 
                                   ($complaint->priority === 'medium' ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800')) }}">
                                {{ ucfirst($complaint->priority) }}
                            </span>
                        </div>
                    </div>
                </div>
                
                <!-- Original Complaint -->
                <div class="p-6">
                    <div class="flex items-start space-x-4">
                        <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-user text-blue-600"></i>
                        </div>
                        <div class="flex-1">
                            <div class="flex items-center space-x-2 mb-2">
                                <span class="font-semibold text-gray-900">{{ $complaint->createdBy->name }}</span>
                                <span class="text-sm text-gray-500">{{ $complaint->created_at->diffForHumans() }}</span>
                            </div>
                            <div class="bg-gray-50 rounded-lg p-4">
                                <p class="text-gray-800 whitespace-pre-wrap">{{ $complaint->description }}</p>
                                
                                @php
                                    $attachments = $complaint->attachments;
                                    if (is_string($attachments)) {
                                        $attachments = json_decode($attachments, true) ?? [];
                                    } elseif (!is_array($attachments)) {
                                        $attachments = [];
                                    }
                                @endphp
                                @if(!empty($attachments))
                                    <div class="mt-4">
                                        <p class="text-sm font-medium text-gray-700 mb-2">Attachments:</p>
                                        <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
                                            @foreach($attachments as $attachment)
                                                @if($attachment)
                                                    <div class="relative">
                                                        <img src="{{ asset('storage/' . $attachment) }}" 
                                                             alt="Attachment" 
                                                             class="w-full h-20 object-cover rounded-lg cursor-pointer hover:opacity-75"
                                                             onclick="openImageModal('{{ asset('storage/' . $attachment) }}')">
                                                    </div>
                                                @endif
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Updates/Replies -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden mb-6">
                <div class="bg-gradient-to-r from-green-500 to-teal-600 px-6 py-4">
                    <h2 class="text-xl font-bold text-white flex items-center">
                        <i class="fas fa-comments mr-3"></i>
                        Updates & Replies
                    </h2>
                </div>
                
                <div class="p-6">
                    @forelse($complaint->updates()->with('user')->latest()->get() as $update)
                        <div class="flex items-start space-x-4 mb-6 {{ !$loop->last ? 'border-b border-gray-200 pb-6' : '' }}">
                            <div class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0
                                {{ $update->type === 'comment' ? 'bg-blue-100' : 
                                   ($update->type === 'status_change' ? 'bg-green-100' : 'bg-purple-100') }}">
                                <i class="fas {{ $update->type === 'comment' ? 'fa-comment' : 
                                               ($update->type === 'status_change' ? 'fa-cog' : 'fa-user-tag') }} 
                                         {{ $update->type === 'comment' ? 'text-blue-600' : 
                                            ($update->type === 'status_change' ? 'text-green-600' : 'text-purple-600') }}"></i>
                            </div>
                            <div class="flex-1">
                                <div class="flex items-center space-x-2 mb-2">
                                    <span class="font-semibold text-gray-900">{{ $update->user->name }}</span>
                                    <span class="text-sm text-gray-500">{{ $update->created_at->diffForHumans() }}</span>
                                    @if($update->type !== 'comment')
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full 
                                            {{ $update->type === 'status_change' ? 'bg-green-100 text-green-800' : 'bg-purple-100 text-purple-800' }}">
                                            {{ ucfirst(str_replace('_', ' ', $update->type)) }}
                                        </span>
                                    @endif
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4">
                                    <p class="text-gray-800 whitespace-pre-wrap">{{ $update->message }}</p>
                                    
                                    @if($update->attachments && (is_array($update->attachments) ? count($update->attachments) > 0 : !empty($update->attachments)))
                                        <div class="mt-3">
                                            <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
                                                @php
                                                    $updateAttachmentArray = is_array($update->attachments) ? $update->attachments : json_decode($update->attachments, true) ?? [];
                                                @endphp
                                                @foreach($updateAttachmentArray as $attachment)
                                                    <div class="relative">
                                                        <img src="{{ asset('storage/' . $attachment) }}" 
                                                             alt="Attachment" 
                                                             class="w-full h-16 object-cover rounded-lg cursor-pointer hover:opacity-75"
                                                             onclick="openImageModal('{{ asset('storage/' . $attachment) }}')">
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-8 text-gray-500">
                            <i class="fas fa-comments text-4xl mb-3 text-gray-300"></i>
                            <p>No updates yet</p>
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- Reply Form -->
            @if(auth()->user()->hasRole(['Admin', 'Super Admin', 'Staff']) || $complaint->created_by === auth()->id())
                <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                    <div class="bg-gradient-to-r from-purple-500 to-pink-600 px-6 py-4">
                        <h3 class="text-lg font-bold text-white flex items-center">
                            <i class="fas fa-reply mr-3"></i>
                            Add Reply
                        </h3>
                    </div>
                    
                    <div class="p-6">
                        <form action="{{ route('complaints.add-update', $complaint) }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            
                            <div class="mb-4">
                                <label for="message" class="block text-sm font-medium text-gray-700 mb-2">Message</label>
                                <textarea name="message" id="message" rows="4" 
                                          class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" 
                                          placeholder="Type your reply here..."
                                          required></textarea>
                            </div>

                            <div class="mb-4">
                                <label for="images" class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="fas fa-paperclip mr-1"></i>
                                    Attach Files (Optional)
                                </label>
                                <input type="file" name="images[]" id="images" multiple accept="image/*"
                                       class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <p class="text-xs text-gray-500 mt-1">You can upload up to 3 images (max 2MB each)</p>
                            </div>

                            <input type="hidden" name="type" value="comment">

                            <div class="flex justify-end">
                                <button type="submit" 
                                        class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-medium transition-colors inline-flex items-center">
                                    <i class="fas fa-paper-plane mr-2"></i>
                                    Submit Reply
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            @endif
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
            <!-- Ticket Details -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                    <i class="fas fa-info-circle mr-2 text-blue-600"></i>
                    Details
                </h3>
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-500">Type</label>
                        <p class="text-sm text-gray-900">{{ $complaint->category->name ?? 'N/A' }}</p>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-500">Agent</label>
                        <p class="text-sm text-gray-900">{{ $complaint->assignedTo->name ?? 'Unassigned' }}</p>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-500">Status</label>
                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full 
                            {{ $complaint->status === 'open' ? 'bg-red-100 text-red-800' : 
                               ($complaint->status === 'in_progress' ? 'bg-yellow-100 text-yellow-800' : 
                               ($complaint->status === 'resolved' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800')) }}">
                            {{ ucfirst(str_replace('_', ' ', $complaint->status)) }}
                        </span>
                    </div>

                    @if($complaint->flat)
                        <div>
                            <label class="block text-sm font-medium text-gray-500">Unit</label>
                            <p class="text-sm text-gray-900">
                                {{ $complaint->flat->building->name ?? '' }} - {{ $complaint->flat->flat_number ?? '' }}
                            </p>
                        </div>
                    @endif

                    <div>
                        <label class="block text-sm font-medium text-gray-500">Created</label>
                        <p class="text-sm text-gray-900">{{ $complaint->created_at->format('M d, Y h:i A') }}</p>
                    </div>

                    @if($complaint->resolved_at)
                        <div>
                            <label class="block text-sm font-medium text-gray-500">Resolved</label>
                            <p class="text-sm text-gray-900">{{ $complaint->resolved_at->format('M d, Y h:i A') }}</p>
                        </div>
                    @endif
                </div>

                @if(auth()->user()->hasRole(['Admin', 'Super Admin']))
                    <div class="mt-6 pt-6 border-t border-gray-200">
                        <button onclick="openStatusModal()" 
                                class="w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium transition-colors">
                            Update Status
                        </button>
                    </div>
                @endif
            </div>

            <!-- Quick Actions -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                    <i class="fas fa-bolt mr-2 text-yellow-500"></i>
                    Quick Actions
                </h3>
                
                <div class="space-y-3">
                    <a href="{{ route('complaints.index') }}" 
                       class="flex items-center p-3 bg-gray-50 hover:bg-gray-100 rounded-lg transition-colors">
                        <i class="fas fa-arrow-left text-gray-600 mr-3"></i>
                        <span class="font-medium text-gray-700">Back to Tickets</span>
                    </a>
                    
                    @if($complaint->created_by === auth()->id() && $complaint->status !== 'closed')
                        <a href="{{ route('complaints.edit', $complaint) }}" 
                           class="flex items-center p-3 bg-blue-50 hover:bg-blue-100 rounded-lg transition-colors">
                            <i class="fas fa-edit text-blue-600 mr-3"></i>
                            <span class="font-medium text-blue-700">Edit Ticket</span>
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Status Update Modal -->
@if(auth()->user()->hasRole(['Admin', 'Super Admin']))
<div id="statusModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Update Ticket Status</h3>
                <form action="{{ route('complaints.add-update', $complaint) }}" method="POST">
                    @csrf
                    
                    <div class="mb-4">
                        <label for="status" class="block text-sm font-medium text-gray-700 mb-2">New Status</label>
                        <select name="status" id="status" 
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="open" {{ $complaint->status === 'open' ? 'selected' : '' }}>Open</option>
                            <option value="in_progress" {{ $complaint->status === 'in_progress' ? 'selected' : '' }}>In Progress</option>
                            <option value="resolved" {{ $complaint->status === 'resolved' ? 'selected' : '' }}>Resolved</option>
                            <option value="closed" {{ $complaint->status === 'closed' ? 'selected' : '' }}>Closed</option>
                        </select>
                    </div>
                    
                    <div class="mb-4">
                        <label for="status_message" class="block text-sm font-medium text-gray-700 mb-2">Update Message</label>
                        <textarea name="message" id="status_message" rows="3" 
                                  class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" 
                                  placeholder="Describe the status change..."
                                  required></textarea>
                    </div>
                    
                    <input type="hidden" name="type" value="status_change">
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeStatusModal()" 
                                class="px-4 py-2 text-gray-600 hover:text-gray-800 transition-colors">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium transition-colors">
                            Update Status
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endif

<!-- Image Modal -->
<div id="imageModal" class="fixed inset-0 bg-black bg-opacity-75 hidden z-50" onclick="closeImageModal()">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="relative max-w-4xl max-h-full">
            <img id="modalImage" src="" alt="Full size image" class="max-w-full max-h-full object-contain">
            <button onclick="closeImageModal()" 
                    class="absolute top-4 right-4 text-white bg-black bg-opacity-50 rounded-full w-10 h-10 flex items-center justify-center hover:bg-opacity-75">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
</div>

<script>
function openStatusModal() {
    document.getElementById('statusModal').classList.remove('hidden');
}

function closeStatusModal() {
    document.getElementById('statusModal').classList.add('hidden');
}

function openImageModal(imageSrc) {
    document.getElementById('modalImage').src = imageSrc;
    document.getElementById('imageModal').classList.remove('hidden');
}

function closeImageModal() {
    document.getElementById('imageModal').classList.add('hidden');
}

// Close modals on escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeStatusModal();
        closeImageModal();
    }
});
</script>
@endsection