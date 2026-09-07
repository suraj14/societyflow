@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-gradient-to-br from-slate-50 to-blue-50 py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header Section -->
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 flex items-center">
                        <div class="w-10 h-10 bg-gradient-to-r from-purple-500 to-blue-600 rounded-lg flex items-center justify-center mr-3">
                            <i class="fas fa-envelope-open-text text-white"></i>
                        </div>
                        Create Email Template
                    </h1>
                    <p class="text-gray-600 mt-2">Design professional email templates for your society communications</p>
                </div>
                <a href="{{ route('admin.email-templates.index') }}" 
                   class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors shadow-sm">
                    <i class="fas fa-arrow-left mr-2"></i>
                    Back to Templates
                </a>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.email-templates.store') }}" class="space-y-8">
            @csrf
            
            <!-- Main Content Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                
                <!-- Left Column - Form Fields -->
                <div class="lg:col-span-2 space-y-6">
                    
                    <!-- Basic Information Card -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                        <div class="px-6 py-4 bg-gradient-to-r from-purple-50 to-blue-50 border-b border-gray-200">
                            <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                                <i class="fas fa-info-circle text-purple-600 mr-2"></i>
                                Basic Information
                            </h3>
                        </div>
                        <div class="p-6 space-y-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="space-y-2">
                                    <label for="name" class="block text-sm font-medium text-gray-700">
                                        Template Name <span class="text-red-500">*</span>
                                    </label>
                                    <div class="relative">
                                        <input type="text" 
                                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors @error('name') border-red-500 @enderror" 
                                               id="name" name="name" value="{{ old('name') }}" 
                                               placeholder="Enter template name" required>
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
                                        Template Slug <span class="text-red-500">*</span>
                                    </label>
                                    <div class="relative">
                                        <input type="text" 
                                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors @error('slug') border-red-500 @enderror" 
                                               id="slug" name="slug" value="{{ old('slug') }}" 
                                               placeholder="template-slug" required>
                                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                            <i class="fas fa-code text-gray-400"></i>
                                        </div>
                                    </div>
                                    <p class="text-xs text-gray-500">Used in code to reference this template</p>
                                    @error('slug')
                                        <p class="text-sm text-red-600 flex items-center mt-1">
                                            <i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}
                                        </p>
                                    @enderror
                                </div>
                            </div>

                            <div class="space-y-2">
                                <label for="subject" class="block text-sm font-medium text-gray-700">
                                    Email Subject <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <input type="text" 
                                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors @error('subject') border-red-500 @enderror" 
                                           id="subject" name="subject" value="{{ old('subject') }}" 
                                           placeholder="Enter email subject line" required>
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
                                    <label for="type" class="block text-sm font-medium text-gray-700">
                                        Template Type <span class="text-red-500">*</span>
                                    </label>
                                    <select class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors @error('type') border-red-500 @enderror" 
                                            id="type" name="type" required>
                                        <option value="system" {{ old('type') === 'system' ? 'selected' : '' }}>
                                            🔧 System Template
                                        </option>
                                        <option value="custom" {{ old('type') === 'custom' ? 'selected' : '' }}>
                                            ✨ Custom Template
                                        </option>
                                    </select>
                                    @error('type')
                                        <p class="text-sm text-red-600 flex items-center mt-1">
                                            <i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}
                                        </p>
                                    @enderror
                                </div>
                                
                                <div class="space-y-2">
                                    <label for="status" class="block text-sm font-medium text-gray-700">
                                        Status <span class="text-red-500">*</span>
                                    </label>
                                    <select class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors @error('status') border-red-500 @enderror" 
                                            id="status" name="status" required>
                                        <option value="active" {{ old('status') === 'active' ? 'selected' : '' }}>
                                            ✅ Active
                                        </option>
                                        <option value="inactive" {{ old('status') === 'inactive' ? 'selected' : '' }}>
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
                                    <label for="trigger_event" class="block text-sm font-medium text-gray-700">
                                        Email Event <span class="text-gray-500">(Optional)</span>
                                    </label>
                                    <select class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors @error('trigger_event') border-red-500 @enderror" 
                                            id="trigger_event" name="trigger_event">
                                        <option value="">-- Select Event --</option>
                                        <option value="user_registered" {{ old('trigger_event') === 'user_registered' ? 'selected' : '' }}>
                                            👤 User Registered
                                        </option>
                                        <option value="tenant_owner_added" {{ old('trigger_event') === 'tenant_owner_added' ? 'selected' : '' }}>
                                            👥 Tenant/Owner Added
                                        </option>
                                        <option value="notice_published" {{ old('trigger_event') === 'notice_published' ? 'selected' : '' }}>
                                            📢 Notice Published
                                        </option>
                                        <option value="bill_generated" {{ old('trigger_event') === 'bill_generated' ? 'selected' : '' }}>
                                            💰 Bill Generated
                                        </option>
                                        <option value="payment_success" {{ old('trigger_event') === 'payment_success' ? 'selected' : '' }}>
                                            ✅ Payment Success
                                        </option>
                                        <option value="payment_overdue" {{ old('trigger_event') === 'payment_overdue' ? 'selected' : '' }}>
                                            ⚠️ Payment Overdue
                                        </option>
                                        <option value="ticket_updated" {{ old('trigger_event') === 'ticket_updated' ? 'selected' : '' }}>
                                            🎫 Ticket Updated
                                        </option>
                                        <option value="visitor_approved" {{ old('trigger_event') === 'visitor_approved' ? 'selected' : '' }}>
                                            🚪 Visitor Approved
                                        </option>
                                        <option value="event_created" {{ old('trigger_event') === 'event_created' ? 'selected' : '' }}>
                                            🎉 Event Created
                                        </option>
                                        <option value="password_reset" {{ old('trigger_event') === 'password_reset' ? 'selected' : '' }}>
                                            🔐 Password Reset
                                        </option>
                                        <option value="report_generated" {{ old('trigger_event') === 'report_generated' ? 'selected' : '' }}>
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
                        <div class="px-6 py-4 bg-gradient-to-r from-blue-50 to-indigo-50 border-b border-gray-200">
                            <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                                <i class="fas fa-edit text-blue-600 mr-2"></i>
                                Email Content
                            </h3>
                        </div>
                        <div class="p-6">
                            <div class="space-y-2">
                                <label for="body" class="block text-sm font-medium text-gray-700">
                                    Email Body <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <textarea class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors @error('body') border-red-500 @enderror" 
                                              id="body" name="body" rows="12" 
                                              placeholder="Enter your email content here. HTML is supported." required>{{ old('body') }}</textarea>
                                </div>
                                <div class="flex items-center justify-between">
                                    <p class="text-xs text-gray-500 flex items-center">
                                        <i class="fas fa-code text-green-500 mr-1"></i>
                                        HTML supported. Use variables like @{{user_name}}, @{{society_name}}
                                    </p>
                                    <button type="button" onclick="insertVariable()" 
                                            class="text-xs text-purple-600 hover:text-purple-800 flex items-center">
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
                </div>

                <!-- Right Column - Variables Guide -->
                <div class="space-y-6">
                    
                    <!-- Variables Guide Card -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden sticky top-6">
                        <div class="px-6 py-4 bg-gradient-to-r from-green-50 to-emerald-50 border-b border-gray-200">
                            <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                                <i class="fas fa-magic text-green-600 mr-2"></i>
                                Available Variables
                            </h3>
                        </div>
                        <div class="p-6 space-y-6">
                            
                            <!-- Common Variables -->
                            <div>
                                <h4 class="text-sm font-semibold text-gray-800 mb-3 flex items-center">
                                    <div class="w-2 h-2 bg-blue-500 rounded-full mr-2"></div>
                                    Common Variables
                                </h4>
                                <div class="space-y-2">
                                    <div class="flex items-center justify-between p-2 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors cursor-pointer" onclick="copyVariable('user_name')">
                                        <code class="text-sm text-blue-600">@{{user_name}}</code>
                                        <span class="text-xs text-gray-500">User's name</span>
                                    </div>
                                    <div class="flex items-center justify-between p-2 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors cursor-pointer" onclick="copyVariable('society_name')">
                                        <code class="text-sm text-blue-600">@{{society_name}}</code>
                                        <span class="text-xs text-gray-500">Society name</span>
                                    </div>
                                    <div class="flex items-center justify-between p-2 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors cursor-pointer" onclick="copyVariable('society_email')">
                                        <code class="text-sm text-blue-600">@{{society_email}}</code>
                                        <span class="text-xs text-gray-500">Society email</span>
                                    </div>
                                    <div class="flex items-center justify-between p-2 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors cursor-pointer" onclick="copyVariable('society_phone')">
                                        <code class="text-sm text-blue-600">@{{society_phone}}</code>
                                        <span class="text-xs text-gray-500">Society phone</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Context Variables -->
                            <div>
                                <h4 class="text-sm font-semibold text-gray-800 mb-3 flex items-center">
                                    <div class="w-2 h-2 bg-purple-500 rounded-full mr-2"></div>
                                    Context Variables
                                </h4>
                                <div class="space-y-2">
                                    <div class="flex items-center justify-between p-2 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors cursor-pointer" onclick="copyVariable('amount')">
                                        <code class="text-sm text-purple-600">@{{amount}}</code>
                                        <span class="text-xs text-gray-500">Payment amount</span>
                                    </div>
                                    <div class="flex items-center justify-between p-2 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors cursor-pointer" onclick="copyVariable('due_date')">
                                        <code class="text-sm text-purple-600">@{{due_date}}</code>
                                        <span class="text-xs text-gray-500">Bill due date</span>
                                    </div>
                                    <div class="flex items-center justify-between p-2 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors cursor-pointer" onclick="copyVariable('view_url')">
                                        <code class="text-sm text-purple-600">@{{view_url}}</code>
                                        <span class="text-xs text-gray-500">View item link</span>
                                    </div>
                                    <div class="flex items-center justify-between p-2 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors cursor-pointer" onclick="copyVariable('login_url')">
                                        <code class="text-sm text-purple-600">@{{login_url}}</code>
                                        <span class="text-xs text-gray-500">Login page link</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Quick Tips -->
                            <div class="bg-gradient-to-r from-yellow-50 to-orange-50 border border-yellow-200 rounded-lg p-4">
                                <h4 class="text-sm font-semibold text-yellow-800 mb-2 flex items-center">
                                    <i class="fas fa-lightbulb text-yellow-600 mr-2"></i>
                                    Quick Tips
                                </h4>
                                <ul class="text-xs text-yellow-700 space-y-1">
                                    <li>• Click variables to copy them</li>
                                    <li>• Use HTML for rich formatting</li>
                                    <li>• Test your template before saving</li>
                                    <li>• Keep subject lines concise</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-4">
                        <button type="submit" 
                                class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-purple-600 to-blue-600 text-white font-medium rounded-lg hover:from-purple-700 hover:to-blue-700 transition-all duration-200 shadow-lg hover:shadow-xl transform hover:-translate-y-0.5">
                            <i class="fas fa-save mr-2"></i>
                            Create Template
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
// Auto-generate slug from name
document.getElementById('name').addEventListener('input', function() {
    const name = this.value;
    const slug = name.toLowerCase()
                     .replace(/[^a-z0-9\s-]/g, '')
                     .replace(/\s+/g, '-')
                     .replace(/-+/g, '-')
                     .trim('-');
    document.getElementById('slug').value = slug;
});

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
    const highlightedSubject = subject.replace(/\{\{(\w+)\}\}/g, '<span class="bg-yellow-200 px-1 rounded">$1</span>');
    const highlightedBody = body.replace(/\{\{(\w+)\}\}/g, '<span class="bg-yellow-200 px-1 rounded">$1</span>');
    
    modal.innerHTML = '<div class="bg-white rounded-xl max-w-4xl w-full max-h-[90vh] overflow-hidden">' +
        '<div class="flex items-center justify-between p-6 border-b border-gray-200">' +
        '<h3 class="text-lg font-semibold text-gray-900">Email Preview</h3>' +
        '<button onclick="this.closest(\'.fixed\').remove()" class="text-gray-400 hover:text-gray-600">' +
        '<i class="fas fa-times text-xl"></i>' +
        '</button>' +
        '</div>' +
        '<div class="p-6 overflow-y-auto max-h-[70vh]">' +
        '<div class="border border-gray-300 rounded-lg overflow-hidden">' +
        '<div class="bg-gray-100 p-4 border-b border-gray-300">' +
        '<div class="text-sm text-gray-600">Subject:</div>' +
        '<div class="font-medium">' + highlightedSubject + '</div>' +
        '</div>' +
        '<div class="p-6 bg-white">' +
        highlightedBody +
        '</div>' +
        '</div>' +
        '</div>' +
        '</div>';
    
    document.body.appendChild(modal);
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