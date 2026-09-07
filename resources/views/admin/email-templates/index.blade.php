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
                        Email Templates
                    </h1>
                    <p class="text-gray-600 mt-2">Manage and customize email templates for your society communications</p>
                </div>
                <div class="flex space-x-3">
                    <a href="{{ route('admin.email-templates.create') }}" 
                       class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-purple-600 to-blue-600 text-white font-medium rounded-lg hover:from-purple-700 hover:to-blue-700 transition-all duration-200 shadow-lg hover:shadow-xl transform hover:-translate-y-0.5">
                        <i class="fas fa-plus mr-2"></i>
                        Create Template
                    </a>
                    @if($templates->count() == 0)
                    <form method="POST" action="{{ route('admin.email-templates.initialize') }}" class="inline">
                        @csrf
                        <button type="submit" 
                                class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-green-500 to-emerald-600 text-white font-medium rounded-lg hover:from-green-600 hover:to-emerald-700 transition-all duration-200 shadow-lg hover:shadow-xl">
                            <i class="fas fa-magic mr-2"></i>
                            Initialize Default Templates
                        </button>
                    </form>
                    @endif
                </div>
            </div>
        </div>

        <!-- Success/Error Messages -->
        @if(session('success'))
            <div class="mb-6 bg-green-50 border-l-4 border-green-500 p-4 rounded-r-lg shadow-sm">
                <div class="flex items-center">
                    <i class="fas fa-check-circle text-green-500 mr-3 text-lg"></i>
                    <p class="text-green-800 font-medium">{{ session('success') }}</p>
                </div>
            </div>
        @endif

        @if(session('error'))
            <div class="mb-6 bg-red-50 border-l-4 border-red-500 p-4 rounded-r-lg shadow-sm">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle text-red-500 mr-3 text-lg"></i>
                    <p class="text-red-800 font-medium">{{ session('error') }}</p>
                </div>
            </div>
        @endif

        @if($templates->count() > 0)
            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-gradient-to-r from-blue-500 to-indigo-600 rounded-lg flex items-center justify-center">
                            <i class="fas fa-envelope text-white text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Total Templates</p>
                            <p class="text-2xl font-bold text-gray-900">{{ $templates->total() }}</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-gradient-to-r from-green-500 to-emerald-600 rounded-lg flex items-center justify-center">
                            <i class="fas fa-check-circle text-white text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Active Templates</p>
                            <p class="text-2xl font-bold text-gray-900">{{ $templates->where('status', 'active')->count() }}</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-gradient-to-r from-purple-500 to-pink-600 rounded-lg flex items-center justify-center">
                            <i class="fas fa-cog text-white text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">System Templates</p>
                            <p class="text-2xl font-bold text-gray-900">{{ $templates->where('type', 'system')->count() }}</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-gradient-to-r from-orange-500 to-red-600 rounded-lg flex items-center justify-center">
                            <i class="fas fa-star text-white text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Custom Templates</p>
                            <p class="text-2xl font-bold text-gray-900">{{ $templates->where('type', 'custom')->count() }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Templates Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                @foreach($templates as $template)
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 hover:shadow-lg transition-all duration-200 transform hover:-translate-y-1 overflow-hidden">
                    <!-- Template Header -->
                    <div class="p-6 border-b border-gray-100">
                        <div class="flex items-start justify-between mb-3">
                            <div class="flex-1">
                                <h3 class="text-lg font-semibold text-gray-900 mb-1">{{ $template->name }}</h3>
                                <p class="text-sm text-gray-600 line-clamp-2">{{ Str::limit($template->subject, 60) }}</p>
                            </div>
                            <div class="flex flex-col items-end space-y-2 ml-4">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $template->status === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    <div class="w-1.5 h-1.5 rounded-full {{ $template->status === 'active' ? 'bg-green-500' : 'bg-red-500' }} mr-1"></div>
                                    {{ ucfirst($template->status) }}
                                </span>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $template->type === 'system' ? 'bg-blue-100 text-blue-800' : 'bg-purple-100 text-purple-800' }}">
                                    <i class="fas fa-{{ $template->type === 'system' ? 'cog' : 'star' }} mr-1"></i>
                                    {{ ucfirst($template->type) }}
                                </span>
                            </div>
                        </div>
                        
                        <div class="flex items-center text-xs text-gray-500 space-x-4">
                            <span class="flex items-center">
                                <i class="fas fa-code mr-1"></i>
                                <code class="bg-gray-100 px-1 rounded">{{ $template->slug }}</code>
                            </span>
                            <span class="flex items-center">
                                <i class="fas fa-calendar mr-1"></i>
                                {{ $template->updated_at->format('M d, Y') }}
                            </span>
                        </div>
                    </div>

                    <!-- Template Actions -->
                    <div class="p-4 bg-gray-50">
                        <div class="flex items-center justify-between">
                            <div class="flex space-x-2">
                                <a href="{{ route('admin.email-templates.show', $template) }}" 
                                   class="inline-flex items-center px-3 py-1.5 bg-blue-100 text-blue-700 text-xs font-medium rounded-lg hover:bg-blue-200 transition-colors">
                                    <i class="fas fa-eye mr-1"></i>
                                    View
                                </a>
                                <a href="{{ route('admin.email-templates.edit', $template) }}" 
                                   class="inline-flex items-center px-3 py-1.5 bg-purple-100 text-purple-700 text-xs font-medium rounded-lg hover:bg-purple-200 transition-colors">
                                    <i class="fas fa-edit mr-1"></i>
                                    Edit
                                </a>
                            </div>
                            
                            @if($template->type !== 'system')
                            <form method="POST" action="{{ route('admin.email-templates.destroy', $template) }}" 
                                  class="inline" onsubmit="return confirm('Are you sure you want to delete this template?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" 
                                        class="inline-flex items-center px-3 py-1.5 bg-red-100 text-red-700 text-xs font-medium rounded-lg hover:bg-red-200 transition-colors">
                                    <i class="fas fa-trash mr-1"></i>
                                    Delete
                                </button>
                            </form>
                            @else
                            <span class="inline-flex items-center px-3 py-1.5 bg-gray-100 text-gray-500 text-xs font-medium rounded-lg">
                                <i class="fas fa-lock mr-1"></i>
                                Protected
                            </span>
                            @endif
                        </div>
                    </div>
                </div>
                @endforeach
            </div>

            <!-- Pagination -->
            @if($templates->hasPages())
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <div class="flex items-center justify-between">
                    <div class="text-sm text-gray-700">
                        Showing {{ $templates->firstItem() }} to {{ $templates->lastItem() }} of {{ $templates->total() }} templates
                    </div>
                    <div class="flex items-center space-x-2">
                        {{ $templates->links() }}
                    </div>
                </div>
            </div>
            @endif

        @else
            <!-- Empty State -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-12 text-center">
                <div class="w-24 h-24 bg-gradient-to-r from-purple-100 to-blue-100 rounded-full flex items-center justify-center mx-auto mb-6">
                    <i class="fas fa-envelope fa-3x text-purple-500"></i>
                </div>
                <h3 class="text-2xl font-bold text-gray-900 mb-4">No Email Templates Found</h3>
                <p class="text-gray-600 mb-8 max-w-md mx-auto">Get started by creating your first email template or initializing our comprehensive set of default templates.</p>
                
                <div class="flex flex-col sm:flex-row items-center justify-center space-y-3 sm:space-y-0 sm:space-x-4">
                    <a href="{{ route('admin.email-templates.create') }}" 
                       class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-purple-600 to-blue-600 text-white font-medium rounded-lg hover:from-purple-700 hover:to-blue-700 transition-all duration-200 shadow-lg hover:shadow-xl">
                        <i class="fas fa-plus mr-2"></i>
                        Create Your First Template
                    </a>
                    <form method="POST" action="{{ route('admin.email-templates.initialize') }}" class="inline">
                        @csrf
                        <button type="submit" 
                                class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-green-500 to-emerald-600 text-white font-medium rounded-lg hover:from-green-600 hover:to-emerald-700 transition-all duration-200 shadow-lg hover:shadow-xl">
                            <i class="fas fa-magic mr-2"></i>
                            Initialize Default Templates
                        </button>
                    </form>
                </div>
                
                <!-- Features Preview -->
                <div class="mt-12 grid grid-cols-1 md:grid-cols-3 gap-6 text-left">
                    <div class="p-6 bg-gradient-to-br from-blue-50 to-indigo-50 rounded-lg">
                        <div class="w-10 h-10 bg-blue-500 rounded-lg flex items-center justify-center mb-4">
                            <i class="fas fa-magic text-white"></i>
                        </div>
                        <h4 class="font-semibold text-gray-900 mb-2">26 Pre-built Templates</h4>
                        <p class="text-sm text-gray-600">Professional templates for bills, notices, events, complaints, and more.</p>
                    </div>
                    
                    <div class="p-6 bg-gradient-to-br from-purple-50 to-pink-50 rounded-lg">
                        <div class="w-10 h-10 bg-purple-500 rounded-lg flex items-center justify-center mb-4">
                            <i class="fas fa-code text-white"></i>
                        </div>
                        <h4 class="font-semibold text-gray-900 mb-2">Dynamic Variables</h4>
                        <p class="text-sm text-gray-600">Use variables like user names, amounts, and dates for personalized emails.</p>
                    </div>
                    
                    <div class="p-6 bg-gradient-to-br from-green-50 to-emerald-50 rounded-lg">
                        <div class="w-10 h-10 bg-green-500 rounded-lg flex items-center justify-center mb-4">
                            <i class="fas fa-eye text-white"></i>
                        </div>
                        <h4 class="font-semibold text-gray-900 mb-2">Live Preview & Testing</h4>
                        <p class="text-sm text-gray-600">Preview templates and send test emails before deployment.</p>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>

<!-- Enhanced JavaScript -->
<script>
// Add smooth animations and interactions
document.addEventListener('DOMContentLoaded', function() {
    // Add hover effects to template cards
    const templateCards = document.querySelectorAll('.bg-white.rounded-xl.shadow-sm');
    templateCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-4px)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });
    
    // Add loading states to buttons
    const buttons = document.querySelectorAll('button[type="submit"]');
    buttons.forEach(button => {
        button.addEventListener('click', function() {
            const originalText = this.innerHTML;
            this.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processing...';
            this.disabled = true;
            
            // Re-enable after 3 seconds (fallback)
            setTimeout(() => {
                this.innerHTML = originalText;
                this.disabled = false;
            }, 3000);
        });
    });
});

// Toast notification for actions
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