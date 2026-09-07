@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="mb-8">
        <a href="{{ route('admin.email-logs.index') }}" class="text-blue-600 hover:text-blue-900 mb-4 inline-block">← Back to Email Logs</a>
        <h1 class="text-3xl font-bold text-gray-900">Email Log Details</h1>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Main Content -->
        <div class="lg:col-span-2">
            <!-- Email Information -->
            <div class="bg-white rounded-lg shadow p-6 mb-6">
                <h2 class="text-xl font-bold text-gray-900 mb-4">Email Information</h2>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Recipient Email</label>
                        <p class="text-gray-900 mt-1">{{ $emailLog->recipient_email }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Recipient Name</label>
                        <p class="text-gray-900 mt-1">{{ $emailLog->recipient_name ?? '-' }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Trigger Action</label>
                        <p class="text-gray-900 mt-1">{{ str_replace('_', ' ', ucfirst($emailLog->trigger_action)) }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Status</label>
                        <p class="mt-1">
                            @if($emailLog->status === 'sent')
                                <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Sent</span>
                            @elseif($emailLog->status === 'pending')
                                <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">Pending</span>
                            @elseif($emailLog->status === 'failed')
                                <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Failed</span>
                            @else
                                <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">{{ ucfirst($emailLog->status) }}</span>
                            @endif
                        </p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Created At</label>
                        <p class="text-gray-900 mt-1">{{ $emailLog->created_at->format('M d, Y H:i:s') }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Sent At</label>
                        <p class="text-gray-900 mt-1">{{ $emailLog->sent_at ? $emailLog->sent_at->format('M d, Y H:i:s') : '-' }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Retry Count</label>
                        <p class="text-gray-900 mt-1">{{ $emailLog->retry_count }}/3</p>
                    </div>
                </div>
            </div>

            <!-- Template Information -->
            @if($emailLog->emailTemplate)
                <div class="bg-white rounded-lg shadow p-6 mb-6">
                    <h2 class="text-xl font-bold text-gray-900 mb-4">Template Information</h2>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Template Name</label>
                        <p class="text-gray-900 mt-1">{{ $emailLog->emailTemplate->name }}</p>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Subject</label>
                        <p class="text-gray-900 mt-1 bg-gray-50 p-3 rounded">{{ $emailLog->emailTemplate->subject }}</p>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Body Preview</label>
                        <div class="text-gray-900 mt-1 bg-gray-50 p-3 rounded max-h-64 overflow-y-auto">
                            {!! substr($emailLog->emailTemplate->body, 0, 500) !!}...
                        </div>
                    </div>
                </div>
            @endif

            <!-- Variables -->
            @if($emailLog->variables)
                <div class="bg-white rounded-lg shadow p-6 mb-6">
                    <h2 class="text-xl font-bold text-gray-900 mb-4">Variables Used</h2>
                    
                    <div class="bg-gray-50 p-4 rounded overflow-x-auto">
                        <pre class="text-sm text-gray-900">{{ json_encode($emailLog->variables, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                    </div>
                </div>
            @endif

            <!-- Error Message -->
            @if($emailLog->error_message)
                <div class="bg-white rounded-lg shadow p-6 mb-6 border-l-4 border-red-500">
                    <h2 class="text-xl font-bold text-red-900 mb-4">Error Details</h2>
                    <p class="text-red-700 font-mono text-sm">{{ $emailLog->error_message }}</p>
                </div>
            @endif
        </div>

        <!-- Sidebar -->
        <div class="lg:col-span-1">
            <!-- Actions -->
            <div class="bg-white rounded-lg shadow p-6 mb-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4">Actions</h3>
                
                @if($emailLog->status === 'failed' && $emailLog->retry_count < 3)
                    <form method="POST" action="{{ route('admin.email-logs.retry', $emailLog) }}" class="mb-3">
                        @csrf
                        <button type="submit" class="w-full bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition">
                            Retry Email
                        </button>
                    </form>
                @endif
                
                <a href="{{ route('admin.email-logs.index') }}" class="w-full block text-center bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition">
                    Back to Logs
                </a>
            </div>

            <!-- Summary -->
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4">Summary</h3>
                
                <div class="space-y-3">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Status:</span>
                        <span class="font-semibold">{{ ucfirst($emailLog->status) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Trigger:</span>
                        <span class="font-semibold">{{ str_replace('_', ' ', ucfirst($emailLog->trigger_action)) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Retries:</span>
                        <span class="font-semibold">{{ $emailLog->retry_count }}/3</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Age:</span>
                        <span class="font-semibold">{{ $emailLog->created_at->diffForHumans() }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
