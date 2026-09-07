@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-gradient-to-br from-slate-50 to-blue-50 py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header Section -->
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 flex items-center">
                        <div class="w-10 h-10 bg-gradient-to-r from-indigo-500 to-purple-600 rounded-lg flex items-center justify-center mr-3">
                            <i class="fas fa-envelope-open text-white"></i>
                        </div>
                        {{ $emailTemplate->name }}
                    </h1>
                    <p class="text-gray-600 mt-2">Template details and preview</p>
                </div>
                <div class="flex space-x-3">
                    <a href="{{ route('admin.email-templates.edit', $emailTemplate) }}" 
                       class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-blue-600 to-indigo-600 text-white font-medium rounded-lg hover:from-blue-700 hover:to-indigo-700 transition-all duration-200 shadow-lg hover:shadow-xl">
                        <i class="fas fa-edit mr-2"></i>
                        Edit Template
                    </a>
                    <a href="{{ route('admin.email-templates.index') }}" 
                       class="inline-flex items-center px-4 py-3 bg-white border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors shadow-sm">
                        <i class="fas fa-arrow-left mr-2"></i>
                        Back to Templates
                    </a>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            
            <!-- Main Content -->
            <div class="lg:col-span-2 space-y-6">
                
                <!-- Template Information Card -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="px-6 py-4 bg-gradient-to-r from-indigo-50 to-purple-50 border-b border-gray-200">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                                <i class="fas fa-info-circle text-indigo-600 mr-2"></i>
                                Template Information
                            </h3>
                            <div class="flex items-center space-x-2">
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium {{ $emailTemplate->status === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    <div class="w-2 h-2 rounded-full {{ $emailTemplate->status === 'active' ? 'bg-green-500' : 'bg-red-500' }} mr-2"></div>
                                    {{ ucfirst($emailTemplate->status) }}
                                </span>
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium {{ $emailTemplate->type === 'system' ? 'bg-blue-100 text-blue-800' : 'bg-purple-100 text-purple-800' }}">
                                    <i class="fas fa-{{ $emailTemplate->type === 'system' ? 'cog' : 'star' }} mr-1"></i>
                                    {{ ucfirst($emailTemplate->type) }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="space-y-4">
                                <div class="flex items-center justify-between py-3 border-b border-gray-100">
                                    <span class="text-sm font-medium text-gray-600">Template Name</span>
                                    <span class="text-sm text-gray-900 font-medium">{{ $emailTemplate->name }}</span>
                                </div>
                                <div class="flex items-center justify-between py-3 border-b border-gray-100">
                                    <span class="text-sm font-medium text-gray-600">Slug</span>
                                    <code class="text-sm bg-gray-100 px-2 py-1 rounded">{{ $emailTemplate->slug }}</code>
                                </div>
                                <div class="flex items-center justify-between py-3 border-b border-gray-100">
                                    <span class="text-sm font-medium text-gray-600">Template ID</span>
                                    <span class="text-sm text-gray-900 font-medium">#{{ $emailTemplate->id }}</span>
                                </div>
                            </div>
                            <div class="space-y-4">
                                <div class="flex items-center justify-between py-3 border-b border-gray-100">
                                    <span class="text-sm font-medium text-gray-600">Created</span>
                                    <span class="text-sm text-gray-900">{{ $emailTemplate->created_at->format('M d, Y H:i') }}</span>
                                </div>
                                <div class="flex items-center justify-between py-3 border-b border-gray-100">
                                    <span class="text-sm font-medium text-gray-600">Last Updated</span>
                                    <span class="text-sm text-gray-900">{{ $emailTemplate->updated_at->format('M d, Y H:i') }}</span>
                                </div>
                                <div class="flex items-center justify-between py-3 border-b border-gray-100">
                                    <span class="text-sm font-medium text-gray-600">Variables</span>
                                    <span class="text-sm text-gray-900 font-medium">{{ $emailTemplate->variables ? count($emailTemplate->variables) : 0 }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Email Subject Card -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="px-6 py-4 bg-gradient-to-r from-blue-50 to-cyan-50 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                            <i class="fas fa-envelope text-blue-600 mr-2"></i>
                            Email Subject
                        </h3>
                    </div>
                    <div class="p-6">
                        <div class="bg-gradient-to-r from-blue-50 to-cyan-50 border border-blue-200 rounded-lg p-4">
                            <div class="flex items-start space-x-3">
                                <div class="w-8 h-8 bg-blue-500 rounded-lg flex items-center justify-center flex-shrink-0">
                                    <i class="fas fa-quote-left text-white text-sm"></i>
                                </div>
                                <div class="flex-1">
                                    <p class="text-gray-900 font-medium text-lg">{{ $emailTemplate->subject }}</p>
                                    <p class="text-sm text-blue-600 mt-1">Subject line as it appears in recipient's inbox</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Email Content Card -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="px-6 py-4 bg-gradient-to-r from-green-50 to-emerald-50 border-b border-gray-200">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                                <i class="fas fa-file-alt text-green-600 mr-2"></i>
                                Email Content
                            </h3>
                            <div class="flex space-x-2">
                                <button onclick="showRawContent()" 
                                        class="px-3 py-1 text-xs bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">
                                    <i class="fas fa-code mr-1"></i>Raw
                                </button>
                                <button onclick="showPreview()" 
                                        class="px-3 py-1 text-xs bg-green-100 text-green-700 rounded-lg hover:bg-green-200 transition-colors">
                                    <i class="fas fa-eye mr-1"></i>Preview
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="p-6">
                        <!-- Raw Content View -->
                        <div id="raw-content" class="hidden">
                            <div class="bg-gray-900 rounded-lg p-4 overflow-x-auto">
                                <pre class="text-green-400 text-sm font-mono whitespace-pre-wrap">{{ $emailTemplate->body }}</pre>
                            </div>
                        </div>
                        
                        <!-- Preview Content View -->
                        <div id="preview-content">
                            <div class="border border-gray-300 rounded-lg overflow-hidden shadow-sm">
                                <div class="bg-gradient-to-r from-gray-50 to-slate-50 p-4 border-b border-gray-300">
                                    <div class="flex items-center space-x-4">
                                        <div class="w-10 h-10 bg-gradient-to-r from-blue-500 to-indigo-600 rounded-full flex items-center justify-center">
                                            <i class="fas fa-envelope text-white"></i>
                                        </div>
                                        <div>
                                            <div class="text-sm text-gray-600">From: {{ auth()->user()->society->name ?? 'Your Society' }}</div>
                                            <div class="text-sm text-gray-600">Subject: {{ $emailTemplate->subject }}</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="p-6 bg-white min-h-[300px] max-h-[600px] overflow-y-auto">
                                    {!! str_replace([
                                        '{{user_name}}', 
                                        '{{society_name}}', 
                                        '{{amount}}', 
                                        '{{due_date}}',
                                        '{{view_url}}',
                                        '{{login_url}}'
                                    ], [
                                        '<span class="bg-yellow-200 px-2 py-1 rounded text-sm font-medium">John Doe</span>', 
                                        '<span class="bg-yellow-200 px-2 py-1 rounded text-sm font-medium">Sample Society</span>', 
                                        '<span class="bg-yellow-200 px-2 py-1 rounded text-sm font-medium">$500.00</span>', 
                                        '<span class="bg-yellow-200 px-2 py-1 rounded text-sm font-medium">March 15, 2026</span>',
                                        '<span class="bg-yellow-200 px-2 py-1 rounded text-sm font-medium">#view-link</span>',
                                        '<span class="bg-yellow-200 px-2 py-1 rounded text-sm font-medium">#login-link</span>'
                                    ], $emailTemplate->body) !!}
                                </div>
                            </div>
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
                                    class="inline-flex items-center px-6 py-3 bg-orange-600 hover:bg-orange-700 text-white font-medium rounded-lg transition-all duration-200 shadow-lg hover:shadow-xl" style="background-color: #ea580c !important;">
                                <i class="fas fa-paper-plane mr-2"></i>
                                Send Test
                            </button>
                        </div>
                        <p class="text-xs text-gray-500 mt-3 flex items-center">
                            <i class="fas fa-info-circle mr-1"></i>
                            Send a test email to verify the template works correctly with sample data
                        </p>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="space-y-6">
                
                @if($emailTemplate->variables)
                <!-- Template Variables Card -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="px-6 py-4 bg-gradient-to-r from-purple-50 to-pink-50 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                            <i class="fas fa-magic text-purple-600 mr-2"></i>
                            Available Variables
                        </h3>
                    </div>
                    <div class="p-6">
                        <div class="space-y-2">
                            @foreach($emailTemplate->variables as $variable)
                            <div class="flex items-center justify-between p-3 bg-gradient-to-r from-purple-50 to-pink-50 rounded-lg hover:from-purple-100 hover:to-pink-100 transition-colors cursor-pointer" onclick="copyVariable('{{ $variable }}')">
                                <code class="text-sm text-purple-600 font-medium">@{{ $variable }}</code>
                                <i class="fas fa-copy text-gray-400 hover:text-purple-600 transition-colors"></i>
                            </div>
                            @endforeach
                        </div>
                        <p class="text-xs text-gray-500 mt-4 flex items-center">
                            <i class="fas fa-info-circle mr-1"></i>
                            Click any variable to copy it to your clipboard
                        </p>
                    </div>
                </div>
                @endif

                <!-- Quick Actions Card -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="px-6 py-4 bg-gradient-to-r from-indigo-50 to-blue-50 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                            <i class="fas fa-bolt text-indigo-600 mr-2"></i>
                            Quick Actions
                        </h3>
                    </div>
                    <div class="p-6 space-y-3">
                        <a href="{{ route('admin.email-templates.edit', $emailTemplate) }}" 
                           class="w-full inline-flex items-center justify-center px-4 py-3 bg-gradient-to-r from-blue-500 to-indigo-500 text-white font-medium rounded-lg hover:from-blue-600 hover:to-indigo-600 transition-all duration-200">
                            <i class="fas fa-edit mr-2"></i>
                            Edit Template
                        </a>
                        <button type="button" onclick="duplicateTemplate()" 
                                class="w-full inline-flex items-center justify-center px-4 py-3 bg-white border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 transition-colors">
                            <i class="fas fa-copy mr-2"></i>
                            Duplicate Template
                        </button>
                        <button type="button" onclick="exportTemplate()" 
                                class="w-full inline-flex items-center justify-center px-4 py-3 bg-white border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 transition-colors">
                            <i class="fas fa-download mr-2"></i>
                            Export Template
                        </button>
                    </div>
                </div>

                <!-- Template Stats Card -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="px-6 py-4 bg-gradient-to-r from-gray-50 to-slate-50 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                            <i class="fas fa-chart-bar text-gray-600 mr-2"></i>
                            Template Statistics
                        </h3>
                    </div>
                    <div class="p-6 space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-600">Character Count</span>
                            <span class="text-sm font-medium text-gray-900">{{ strlen($emailTemplate->body) }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-600">Word Count</span>
                            <span class="text-sm font-medium text-gray-900">{{ str_word_count(strip_tags($emailTemplate->body)) }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-600">Variables Used</span>
                            <span class="text-sm font-medium text-gray-900">{{ $emailTemplate->variables ? count($emailTemplate->variables) : 0 }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-600">HTML Content</span>
                            <span class="text-sm font-medium text-gray-900">{{ strip_tags($emailTemplate->body) !== $emailTemplate->body ? 'Yes' : 'No' }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Enhanced JavaScript -->
<script>
// Toggle between raw and preview content
function showRawContent() {
    document.getElementById('raw-content').classList.remove('hidden');
    document.getElementById('preview-content').classList.add('hidden');
}

function showPreview() {
    document.getElementById('preview-content').classList.remove('hidden');
    document.getElementById('raw-content').classList.add('hidden');
}

// Copy variable to clipboard
function copyVariable(variable) {
    const variableText = '{{' + variable + '}}';
    navigator.clipboard.writeText(variableText).then(() => {
        showToast('Variable copied to clipboard!', 'success');
    }).catch(() => {
        // Fallback for older browsers
        const textArea = document.createElement('textarea');
        textArea.value = variableText;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        showToast('Variable copied to clipboard!', 'success');
    });
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

// Duplicate template
function duplicateTemplate() {
    if (confirm('Create a copy of this template?')) {
        window.location.href = '{{ route("admin.email-templates.create") }}?duplicate={{ $emailTemplate->id }}';
    }
}

// Export template
function exportTemplate() {
    const templateData = {
        name: '{{ $emailTemplate->name }}',
        slug: '{{ $emailTemplate->slug }}',
        subject: '{{ $emailTemplate->subject }}',
        body: `{{ str_replace(['`', "\n", "\r"], ['\\`', '\\n', '\\r'], $emailTemplate->body) }}`,
        variables: @json($emailTemplate->variables),
        type: '{{ $emailTemplate->type }}',
        status: '{{ $emailTemplate->status }}'
    };
    
    const dataStr = JSON.stringify(templateData, null, 2);
    const dataBlob = new Blob([dataStr], {type: 'application/json'});
    const url = URL.createObjectURL(dataBlob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `${templateData.slug}-template.json`;
    link.click();
    URL.revokeObjectURL(url);
    
    showToast('Template exported successfully!', 'success');
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
</script>
@endsection