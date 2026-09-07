@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-gradient-to-br from-slate-50 to-blue-50 py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header Section -->
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 flex items-center">
                        <div class="w-10 h-10 bg-gradient-to-r from-blue-500 to-indigo-600 rounded-lg flex items-center justify-center mr-3">
                            <i class="fas fa-edit text-white"></i>
                        </div>
                        Edit Email Template
                    </h1>
                    <p class="text-gray-600 mt-2">Modify "{{ $emailTemplate->name }}" template</p>
                </div>
                <div class="flex space-x-3">
                    <a href="{{ route('admin.email-templates.show', $emailTemplate) }}" 
                       class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors shadow-sm">
                        <i class="fas fa-eye mr-2"></i>
                        View Template
                    </a>
                    <a href="{{ route('admin.email-templates.index') }}" 
                       class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors shadow-sm">
                        <i class="fas fa-arrow-left mr-2"></i>
                        Back to Templates
                    </a>
                </div>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.email-templates.update', $emailTemplate) }}" class="space-y-8">
            @csrf
            @method('PUT')
            
            <!-- Main Content Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                
                <!-- Left Column - Form Fields -->
                <div class="lg:col-span-2 space-y-6">
                    
                    <!-- Template Info Card -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                        <div class="px-6 py-4 bg-gradient-to-r from-blue-50 to-indigo-50 border-b border-gray-200">
                            <div class="flex items-center justify-between">
                                <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                                    <i class="fas fa-info-circle text-blue-600 mr-2"></i>
                                    Template Information
                                </h3>
                                <div class="flex items-center space-x-2">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $emailTemplate->status === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                        {{ ucfirst($emailTemplate->status) }}
                                    </span>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $emailTemplate->type === 'system' ? 'bg-blue-100 text-blue-800' : 'bg-purple-100 text-purple-800' }}">
                                        {{ ucfirst($emailTemplate->type) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="p-6 space-y-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="space-y-2">
                                    <label for="name" class="block text-sm font-medium text-gray-700">
                                        Template Name <span class="text-red-500">*</span>
                                    </label>
                                    <div class="relative">
                                        <input type="text" 
                                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors @error('name') border-red-500 @enderror" 
                                               id="name" name="name" value="{{ old('name', $emailTemplate->name) }}" required>
                                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                            <i class="fas fa-tag text-gray-400"></i>
                                        </div>
                                    </div>
                                    @error('name')
                                        <p class="text-sm text-red-600 flex items-center mt-1">
                                            <i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}
                                        </p>
                                    @enderror
                                </div>
                                
                                <div class="space-y-2">
                                    <label for="slug" class="block text-sm font-medium text-gray-700">
                                        Template Slug
                                    </label>
                                    <div class="relative">
                                        <input type="text" 
                                               class="w-full px-4 py-3 border border-gray-300 rounded-lg bg-gray-50 text-gray-500" 
                                               id="slug" value="{{ $emailTemplate->slug }}" readonly>
                                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                            <i class="fas fa-lock text-gray-400"></i>
                                        </div>
                                    </div>
                                    <p class="text-xs text-gray-500">Slug cannot be changed after creation</p>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <label for="subject" class="block text-sm font-medium text-gray-700">
                                    Email Subject <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <input type="text" 
                                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors @error('subject') border-red-500 @enderror" 
                                           id="subject" name="subject" value="{{ old('subject', $emailTemplate->subject) }}" required>
                                    <div class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                        <i class="fas fa-envelope text-gray-400"></i>
                                    </div>
                                </div>
                                <p class="text-xs text-gray-500 flex items-center">
                                    <i class="fas fa-lightbulb text-yellow-500 mr-1"></i>
                                    Use variables like @{{society_name}}, @{{user_name}}
                                </p>
                                @error('subject')
                                    <p class="text-sm text-red-600 flex items-center mt-1">
                                        <i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}
                                    </p>
                                @enderror
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div class="space-y-2">
                                    <label for="status" class="block text-sm font-medium text-gray-700">
                                        Status <span class="text-red-500">*</span>
                                    </label>
                                    <select class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors @error('status') border-red-500 @enderror" 
                                            id="status" name="status" required>
                                        <option value="active" {{ old('status', $emailTemplate->status) === 'active' ? 'selected' : '' }}>
                                            ✅ Active
                                        </option>
                                        <option value="inactive" {{ old('status', $emailTemplate->status) === 'inactive' ? 'selected' : '' }}>
                                            ⏸️ Inactive
                                        </option>
                                    </select>
                                    @error('status')
                                        <p class="text-sm text-red-600 flex items-center mt-1">
                                            <i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}
                                        </p>
                                    @enderror
                                </div>
                                
                                <div class="space-y-2">
                                    <label class="block text-sm font-medium text-gray-700">Template Type</label>
                                    <div class="w-full px-4 py-3 border border-gray-300 rounded-lg bg-gray-50 text-gray-500 flex items-center">
                                        <i class="fas fa-{{ $emailTemplate->type === 'system' ? 'cog' : 'star' }} mr-2"></i>
                                        {{ ucfirst($emailTemplate->type) }} Template
                                    </div>
                                    <p class="text-xs text-gray-500">Template type cannot be changed</p>
                                </div>

                                <div class="space-y-2">
                                    <label for="trigger_event" class="block text-sm font-medium text-gray-700">
                                        Email Event <span class="text-gray-500">(Optional)</span>
                                    </label>
                                    <select class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors @error('trigger_event') border-red-500 @enderror" 
                                            id="trigger_event" name="trigger_event">
                                        <option value="">-- Select Event --</option>
                                        <option value="user_registered" {{ old('trigger_event', $emailTemplate->trigger_event) === 'user_registered' ? 'selected' : '' }}>
                                            👤 User Registered
                                        </option>
                                        <option value="tenant_owner_added" {{ old('trigger_event', $emailTemplate->trigger_event) === 'tenant_owner_added' ? 'selected' : '' }}>
                                            👥 Tenant/Owner Added
                                        </option>
                                        <option value="notice_published" {{ old('trigger_event', $emailTemplate->trigger_event) === 'notice_published' ? 'selected' : '' }}>
                                            📢 Notice Published
                                        </option>
                                        <option value="bill_generated" {{ old('trigger_event', $emailTemplate->trigger_event) === 'bill_generated' ? 'selected' : '' }}>
                                            💰 Bill Generated
                                        </option>
                                        <option value="payment_success" {{ old('trigger_event', $emailTemplate->trigger_event) === 'payment_success' ? 'selected' : '' }}>
                                            ✅ Payment Success
                                        </option>
                                        <option value="payment_overdue" {{ old('trigger_event', $emailTemplate->trigger_event) === 'payment_overdue' ? 'selected' : '' }}>
                                            ⚠️ Payment Overdue
                                        </option>
                                        <option value="ticket_updated" {{ old('trigger_event', $emailTemplate->trigger_event) === 'ticket_updated' ? 'selected' : '' }}>
                                            🎫 Ticket Updated
                                        </option>
                                        <option value="visitor_approved" {{ old('trigger_event', $emailTemplate->trigger_event) === 'visitor_approved' ? 'selected' : '' }}>
                                            🚪 Visitor Approved
                                        </option>
                                        <option value="event_created" {{ old('trigger_event', $emailTemplate->trigger_event) === 'event_created' ? 'selected' : '' }}>
                                            🎉 Event Created
                                        </option>
                                        <option value="password_reset" {{ old('trigger_event', $emailTemplate->trigger_event) === 'password_reset' ? 'selected' : '' }}>
                                            🔐 Password Reset
                                        </option>
                                        <option value="report_generated" {{ old('trigger_event', $emailTemplate->trigger_event) === 'report_generated' ? 'selected' : '' }}>
                                            📊 Report Generated
                                        </option>
                                    </select>
                                    <p class="text-xs text-gray-500">Map this template to an event for automatic sending</p>
                                    @error('trigger_event')
                                        <p class="text-sm text-red-600 flex items-center mt-1">
                                            <i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}
                                        </p>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Email Content Card -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                        <div class="px-6 py-4 bg-gradient-to-r from-green-50 to-emerald-50 border-b border-gray-200">
                            <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                                <i class="fas fa-edit text-green-600 mr-2"></i>
                                Email Content
                            </h3>
                        </div>
                        <div class="p-6">
                            <div class="space-y-2">
                                <label for="body" class="block text-sm font-medium text-gray-700">
                                    Email Body <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <textarea class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors @error('body') border-red-500 @enderror" 
                                              id="body" name="body" rows="15" required>{{ old('body', $emailTemplate->body) }}</textarea>
                                </div>
                                <div class="flex items-center justify-between">
                                    <p class="text-xs text-gray-500 flex items-center">
                                        <i class="fas fa-code text-green-500 mr-1"></i>
                                        HTML supported. Use variables like @{{user_name}}, @{{society_name}}
                                    </p>
                                    <button type="button" onclick="insertVariable()" 
                                            class="text-xs text-blue-600 hover:text-blue-800 flex items-center">
                                        <i class="fas fa-plus-circle mr-1"></i>Insert Variable
                                    </button>
                                </div>
                                @error('body')
                                    <p class="text-sm text-red-600 flex items-center mt-1">
                                        <i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}
                                    </p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <!-- Test Email Card -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                        <div class="px-6 py-4 bg-gradient-to-r from-orange-50 to-red-50 border-b border-gray-200">
                            <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                                <i class="fas fa-paper-plane text-orange-600 mr-2"></i>
                                Test Email Template
                            </h3>
                        </div>
                        <div class="p-6">
                            <div class="flex items-center space-x-4">
                                <div class="flex-1">
                                    <input type="email" 
                                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition-colors" 
                                           id="test_email" placeholder="Enter email address to test">
                                </div>
                                <button type="button" onclick="testTemplate()" 
                                        class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-orange-500 to-red-500 text-white font-medium rounded-lg hover:from-orange-600 hover:to-red-600 transition-all duration-200 shadow-lg hover:shadow-xl">
                                    <i class="fas fa-paper-plane mr-2"></i>
                                    Send Test
                                </button>
                            </div>
                            <p class="text-xs text-gray-500 mt-2">Send a test email to verify the template works correctly</p>
                        </div>
                    </div>
                </div>

                <!-- Right Column - Template Variables & Info -->
                <div class="space-y-6">
                    
                    @if($emailTemplate->variables)
                    <!-- Template Variables Card -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                        <div class="px-6 py-4 bg-gradient-to-r from-purple-50 to-pink-50 border-b border-gray-200">
                            <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                                <i class="fas fa-magic text-purple-600 mr-2"></i>
                                Template Variables
                            </h3>
                        </div>
                        <div class="p-6">
                            <div class="grid grid-cols-1 gap-2">
                                @foreach($emailTemplate->variables as $variable)
                                <div class="flex items-center justify-between p-3 bg-gradient-to-r from-purple-50 to-pink-50 rounded-lg hover:from-purple-100 hover:to-pink-100 transition-colors cursor-pointer" onclick="copyVariable('{{ $variable }}')">
                                    <code class="text-sm text-purple-600 font-medium">@{{ $variable }}</code>
                                    <i class="fas fa-copy text-gray-400 hover:text-purple-600 transition-colors"></i>
                                </div>
                                @endforeach
                            </div>
                            <p class="text-xs text-gray-500 mt-3 flex items-center">
                                <i class="fas fa-info-circle mr-1"></i>
                                Click any variable to copy it to your clipboard
                            </p>
                        </div>
                    </div>
                    @endif

                    <!-- Template Stats Card -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                        <div class="px-6 py-4 bg-gradient-to-r from-gray-50 to-slate-50 border-b border-gray-200">
                            <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                                <i class="fas fa-chart-bar text-gray-600 mr-2"></i>
                                Template Information
                            </h3>
                        </div>
                        <div class="p-6 space-y-4">
                            <div class="flex items-center justify-between py-2 border-b border-gray-100">
                                <span class="text-sm text-gray-600">Created</span>
                                <span class="text-sm font-medium text-gray-900">{{ $emailTemplate->created_at->format('M d, Y') }}</span>
                            </div>
                            <div class="flex items-center justify-between py-2 border-b border-gray-100">
                                <span class="text-sm text-gray-600">Last Updated</span>
                                <span class="text-sm font-medium text-gray-900">{{ $emailTemplate->updated_at->format('M d, Y') }}</span>
                            </div>
                            <div class="flex items-center justify-between py-2 border-b border-gray-100">
                                <span class="text-sm text-gray-600">Template ID</span>
                                <span class="text-sm font-medium text-gray-900">#{{ $emailTemplate->id }}</span>
                            </div>
                            <div class="flex items-center justify-between py-2">
                                <span class="text-sm text-gray-600">Variables Count</span>
                                <span class="text-sm font-medium text-gray-900">{{ $emailTemplate->variables ? count($emailTemplate->variables) : 0 }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions Card -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                        <div class="px-6 py-4 bg-gradient-to-r from-indigo-50 to-blue-50 border-b border-gray-200">
                            <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                                <i class="fas fa-bolt text-indigo-600 mr-2"></i>
                                Quick Actions
                            </h3>
                        </div>
                        <div class="p-6 space-y-3">
                            <button type="button" onclick="previewTemplate()" 
                                    class="w-full inline-flex items-center justify-center px-4 py-3 bg-gradient-to-r from-indigo-500 to-blue-500 text-white font-medium rounded-lg hover:from-indigo-600 hover:to-blue-600 transition-all duration-200">
                                <i class="fas fa-eye mr-2"></i>
                                Preview Template
                            </button>
                            <button type="button" onclick="duplicateTemplate()" 
                                    class="w-full inline-flex items-center justify-center px-4 py-3 bg-white border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 transition-colors">
                                <i class="fas fa-copy mr-2"></i>
                                Duplicate Template
                            </button>
                            <a href="{{ route('admin.email-templates.show', $emailTemplate) }}" 
                               class="w-full inline-flex items-center justify-center px-4 py-3 bg-white border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 transition-colors">
                                <i class="fas fa-external-link-alt mr-2"></i>
                                View Details
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-4">
                        <button type="submit" 
                                class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-blue-600 to-indigo-600 text-white font-medium rounded-lg hover:from-blue-700 hover:to-indigo-700 transition-all duration-200 shadow-lg hover:shadow-xl transform hover:-translate-y-0.5">
                            <i class="fas fa-save mr-2"></i>
                            Update Template
                        </button>
                        <button type="button" onclick="previewTemplate()" 
                                class="inline-flex items-center px-4 py-3 bg-white border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 transition-colors">
                            <i class="fas fa-eye mr-2"></i>
                            Preview
                        </button>
                    </div>
                    <a href="{{ route('admin.email-templates.index') }}" 
                       class="inline-flex items-center px-4 py-3 bg-gray-100 text-gray-700 font-medium rounded-lg hover:bg-gray-200 transition-colors">
                        <i class="fas fa-times mr-2"></i>
                        Cancel
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Enhanced JavaScript -->
<script>
// Copy variable to clipboard and insert into textarea
function copyVariable(variable) {
    const variableText = '{{' + variable + '}}';
    const textarea = document.getElementById('body');
    const cursorPos = textarea.selectionStart;
    const textBefore = textarea.value.substring(0, cursorPos);
    const textAfter = textarea.value.substring(cursorPos);
    
    textarea.value = textBefore + variableText + textAfter;
    textarea.focus();
    textarea.setSelectionRange(cursorPos + variableText.length, cursorPos + variableText.length);
    
    // Show success message
    showToast('Variable inserted successfully!', 'success');
}

// Insert variable helper
function insertVariable() {
    const variables = ['user_name', 'society_name', 'amount', 'due_date', 'view_url'];
    const variable = prompt('Enter variable name (e.g., user_name):');
    if (variable && variables.includes(variable)) {
        copyVariable(variable);
    } else if (variable) {
        copyVariable(variable); // Allow custom variables
    }
}

// Test template function
function testTemplate() {
    const email = document.getElementById('test_email').value;
    if (!email) {
        showToast('Please enter an email address', 'error');
        return;
    }

    const button = event.target.closest('button');
    const originalText = button.innerHTML;
    button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Sending...';
    button.disabled = true;

    fetch('{{ route("admin.email-templates.test", $emailTemplate) }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({ test_email: email })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Test email sent successfully!', 'success');
        } else {
            showToast('Failed to send test email: ' + data.message, 'error');
        }
    })
    .catch(error => {
        showToast('Error sending test email', 'error');
    })
    .finally(() => {
        button.innerHTML = originalText;
        button.disabled = false;
    });
}

// Preview template
function previewTemplate() {
    const subject = document.getElementById('subject').value;
    const body = document.getElementById('body').value;
    
    if (!subject || !body) {
        showToast('Please fill in subject and body to preview', 'error');
        return;
    }
    
    // Create preview modal
    const modal = document.createElement('div');
    modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4';
    
    // Replace variables with highlighted spans
    const highlightedSubject = subject.replace(/\{\{(\w+)\}\}/g, '<span class="bg-yellow-200 px-2 py-1 rounded text-sm">$1</span>');
    const highlightedBody = body.replace(/\{\{(\w+)\}\}/g, '<span class="bg-yellow-200 px-2 py-1 rounded text-sm">$1</span>');
    
    modal.innerHTML = '<div class="bg-white rounded-xl max-w-4xl w-full max-h-[90vh] overflow-hidden shadow-2xl">' +
        '<div class="flex items-center justify-between p-6 border-b border-gray-200 bg-gradient-to-r from-blue-50 to-indigo-50">' +
        '<h3 class="text-lg font-semibold text-gray-900 flex items-center">' +
        '<i class="fas fa-eye text-blue-600 mr-2"></i>Email Preview' +
        '</h3>' +
        '<button onclick="this.closest(\'.fixed\').remove()" class="text-gray-400 hover:text-gray-600 transition-colors">' +
        '<i class="fas fa-times text-xl"></i>' +
        '</button>' +
        '</div>' +
        '<div class="p-6 overflow-y-auto max-h-[70vh]">' +
        '<div class="border border-gray-300 rounded-lg overflow-hidden shadow-sm">' +
        '<div class="bg-gradient-to-r from-gray-50 to-slate-50 p-4 border-b border-gray-300">' +
        '<div class="text-sm text-gray-600 mb-1">Subject:</div>' +
        '<div class="font-medium text-lg">' + highlightedSubject + '</div>' +
        '</div>' +
        '<div class="p-6 bg-white min-h-[300px]">' +
        highlightedBody +
        '</div>' +
        '</div>' +
        '</div>' +
        '</div>';
    
    document.body.appendChild(modal);
}

// Duplicate template
function duplicateTemplate() {
    if (confirm('Create a copy of this template?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '{{ route("admin.email-templates.store") }}';
        
        const csrfToken = document.createElement('input');
        csrfToken.type = 'hidden';
        csrfToken.name = '_token';
        csrfToken.value = '{{ csrf_token() }}';
        form.appendChild(csrfToken);
        
        const name = document.createElement('input');
        name.type = 'hidden';
        name.name = 'name';
        name.value = '{{ $emailTemplate->name }} (Copy)';
        form.appendChild(name);
        
        const slug = document.createElement('input');
        slug.type = 'hidden';
        slug.name = 'slug';
        slug.value = '{{ $emailTemplate->slug }}-copy-' + Date.now();
        form.appendChild(slug);
        
        const subject = document.createElement('input');
        subject.type = 'hidden';
        subject.name = 'subject';
        subject.value = document.getElementById('subject').value;
        form.appendChild(subject);
        
        const body = document.createElement('textarea');
        body.name = 'body';
        body.style.display = 'none';
        body.value = document.getElementById('body').value;
        form.appendChild(body);
        
        const type = document.createElement('input');
        type.type = 'hidden';
        type.name = 'type';
        type.value = 'custom';
        form.appendChild(type);
        
        const status = document.createElement('input');
        status.type = 'hidden';
        status.name = 'status';
        status.value = 'inactive';
        form.appendChild(status);
        
        document.body.appendChild(form);
        form.submit();
    }
}

// Toast notification
function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    const bgColor = type === 'success' ? 'bg-green-500' : type === 'error' ? 'bg-red-500' : 'bg-blue-500';
    toast.className = `fixed top-4 right-4 ${bgColor} text-white px-6 py-3 rounded-lg shadow-lg z-50 transform translate-x-full transition-transform duration-300`;
    toast.innerHTML = `
        <div class="flex items-center">
            <i class="fas fa-${type === 'success' ? 'check' : type === 'error' ? 'exclamation-triangle' : 'info'} mr-2"></i>
            ${message}
        </div>
    `;
    document.body.appendChild(toast);
    
    setTimeout(() => toast.classList.remove('translate-x-full'), 100);
    setTimeout(() => {
        toast.classList.add('translate-x-full');
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// Character counter for textarea
document.getElementById('body').addEventListener('input', function() {
    const maxLength = 10000;
    const currentLength = this.value.length;
    const percentage = (currentLength / maxLength) * 100;
    
    // Add character counter if it doesn't exist
    let counter = document.getElementById('char-counter');
    if (!counter) {
        counter = document.createElement('div');
        counter.id = 'char-counter';
        counter.className = 'text-xs text-gray-500 mt-1 text-right';
        this.parentNode.appendChild(counter);
    }
    
    counter.textContent = `${currentLength} / ${maxLength} characters`;
    counter.className = `text-xs mt-1 text-right ${percentage > 90 ? 'text-red-500' : 'text-gray-500'}`;
});
</script>
@endsection