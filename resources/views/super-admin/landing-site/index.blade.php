@extends('layouts.super-admin')

@section('title', 'Landing Site Management')
@section('page-title', 'Landing Site Management')

@section('content')
<div x-data="{ activeTab: 'general' }">
    <!-- Tabs Navigation -->
    <div class="bg-white rounded-lg shadow mb-6">
        <div class="border-b border-gray-200">
            <nav class="flex flex-wrap -mb-px">
                <button @click="activeTab = 'general'" 
                        :class="activeTab === 'general' ? 'border-purple-500 text-purple-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                        class="py-4 px-6 border-b-2 font-medium text-sm transition-colors">
                    <i class="fas fa-cog mr-2"></i>General
                </button>
                <button @click="activeTab = 'header'" 
                        :class="activeTab === 'header' ? 'border-purple-500 text-purple-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                        class="py-4 px-6 border-b-2 font-medium text-sm transition-colors">
                    <i class="fas fa-heading mr-2"></i>Header
                </button>
                <button @click="activeTab = 'features'" 
                        :class="activeTab === 'features' ? 'border-purple-500 text-purple-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                        class="py-4 px-6 border-b-2 font-medium text-sm transition-colors">
                    <i class="fas fa-star mr-2"></i>Features
                </button>
                <button @click="activeTab = 'reviews'" 
                        :class="activeTab === 'reviews' ? 'border-purple-500 text-purple-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                        class="py-4 px-6 border-b-2 font-medium text-sm transition-colors">
                    <i class="fas fa-quote-left mr-2"></i>Reviews
                </button>
                <button @click="activeTab = 'faqs'" 
                        :class="activeTab === 'faqs' ? 'border-purple-500 text-purple-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                        class="py-4 px-6 border-b-2 font-medium text-sm transition-colors">
                    <i class="fas fa-question-circle mr-2"></i>FAQs
                </button>
                <button @click="activeTab = 'contact'" 
                        :class="activeTab === 'contact' ? 'border-purple-500 text-purple-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                        class="py-4 px-6 border-b-2 font-medium text-sm transition-colors">
                    <i class="fas fa-envelope mr-2"></i>Contact
                </button>
                <button @click="activeTab = 'pricing'" 
                        :class="activeTab === 'pricing' ? 'border-purple-500 text-purple-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                        class="py-4 px-6 border-b-2 font-medium text-sm transition-colors">
                    <i class="fas fa-tags mr-2"></i>Pricing
                </button>
            </nav>
        </div>
    </div>

    <!-- General Tab - Enable/Disable Landing Site -->
    <div x-show="activeTab === 'general'" x-cloak>
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Landing Site Status</h3>
            <form action="{{ route('super-admin.landing-site.toggle') }}" method="POST">
                @csrf
                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                    <div>
                        <h4 class="font-medium text-gray-800">Disable Landing Site</h4>
                        <p class="text-sm text-gray-600">When disabled, visitors will be redirected directly to the login page.</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="disable" value="1" class="sr-only peer" 
                               {{ ($settings['disable_landing_site'] ?? false) ? 'checked' : '' }}>
                        <div class="w-14 h-7 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-purple-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[4px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-6 after:w-6 after:transition-all peer-checked:bg-purple-600"></div>
                    </label>
                </div>
                <div class="mt-4">
                    <button type="submit" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors">
                        Save Changes
                    </button>
                </div>
            </form>
            
            <div class="mt-6 p-4 bg-blue-50 rounded-lg">
                <div class="flex items-start">
                    <i class="fas fa-info-circle text-blue-500 mt-1 mr-3"></i>
                    <div>
                        <h4 class="font-medium text-blue-800">Preview Landing Page</h4>
                        <p class="text-sm text-blue-600 mt-1">View how your landing page looks to visitors.</p>
                        <a href="{{ url('/') }}" target="_blank" class="inline-flex items-center mt-2 text-blue-600 hover:text-blue-800">
                            <i class="fas fa-external-link-alt mr-2"></i>Open Landing Page
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <!-- Header Tab -->
    <div x-show="activeTab === 'header'" x-cloak>
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Header & Hero Section</h3>
            <form action="{{ route('super-admin.landing-site.header') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Site Title</label>
                        <input type="text" name="site_title" value="{{ $settings['site_title'] ?? 'SocietyFlow' }}" 
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Site Tagline</label>
                        <input type="text" name="site_tagline" value="{{ $settings['site_tagline'] ?? 'Modern Society Management' }}" 
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Hero Title</label>
                        <input type="text" name="hero_title" value="{{ $settings['hero_title'] ?? 'Simplify Your Society Management' }}" 
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Hero Subtitle</label>
                        <textarea name="hero_subtitle" rows="2" 
                                  class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">{{ $settings['hero_subtitle'] ?? 'Complete solution for managing residential societies, apartments, and villas with ease.' }}</textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Button Text</label>
                        <input type="text" name="hero_button_text" value="{{ $settings['hero_button_text'] ?? 'Get Started' }}" 
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Button URL</label>
                        <input type="text" name="hero_button_url" value="{{ $settings['hero_button_url'] ?? '/register' }}" 
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Hero Image</label>
                        @if(isset($settings['hero_image']) && $settings['hero_image'])
                            <div class="mb-2">
                                <img src="{{ Storage::url($settings['hero_image']) }}" alt="Hero" class="h-32 rounded-lg">
                            </div>
                        @endif
                        <input type="file" name="hero_image" accept="image/*" 
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        <p class="text-sm text-gray-500 mt-1">Recommended size: 1200x600px. Max 2MB.</p>
                    </div>
                </div>
                <div class="mt-6">
                    <button type="submit" class="px-6 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors">
                        <i class="fas fa-save mr-2"></i>Save Header Settings
                    </button>
                </div>
            </form>
        </div>
    </div>


    <!-- Features Tab -->
    <div x-show="activeTab === 'features'" x-cloak>
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-800">Features</h3>
                <button @click="$refs.featureModal.showModal()" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors">
                    <i class="fas fa-plus mr-2"></i>Add Feature
                </button>
            </div>
            
            @if($features->count() > 0)
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Title</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach($features as $feature)
                        <tr>
                            <td class="px-4 py-3">
                                @if($feature->type === 'icon')
                                    <i class="{{ $feature->icon }} text-2xl text-purple-600"></i>
                                @else
                                    <img src="{{ Storage::url($feature->image) }}" alt="" class="h-10 w-10 rounded object-cover">
                                @endif
                            </td>
                            <td class="px-4 py-3 font-medium text-gray-800">{{ $feature->title }}</td>
                            <td class="px-4 py-3 text-gray-600 text-sm">{{ Str::limit($feature->description, 50) }}</td>
                            <td class="px-4 py-3">
                                <form action="{{ route('super-admin.landing-site.features.toggle', $feature) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit" class="px-2 py-1 text-xs rounded-full {{ $feature->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                        {{ $feature->is_active ? 'Active' : 'Inactive' }}
                                    </button>
                                </form>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center space-x-2">
                                    <button onclick="editFeature({{ $feature->id }}, '{{ $feature->type }}', '{{ $feature->icon }}', '{{ addslashes($feature->title) }}', '{{ addslashes($feature->description) }}')" 
                                            class="text-blue-600 hover:text-blue-800">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form action="{{ route('super-admin.landing-site.features.destroy', $feature) }}" method="POST" class="inline" onsubmit="return confirm('Delete this feature?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:text-red-800">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @else
            <div class="text-center py-8 text-gray-500">
                <i class="fas fa-star text-4xl mb-2"></i>
                <p>No features added yet. Click "Add Feature" to get started.</p>
            </div>
            @endif
        </div>

        <!-- Add Feature Modal -->
        <dialog x-ref="featureModal" class="rounded-lg shadow-xl p-0 w-full max-w-lg">
            <div class="p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-800">Add Feature</h3>
                    <button onclick="this.closest('dialog').close()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <form action="{{ route('super-admin.landing-site.features.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Type</label>
                            <select name="type" id="featureType" onchange="toggleFeatureType()" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                                <option value="icon">Icon</option>
                                <option value="image">Image</option>
                            </select>
                        </div>
                        <div id="iconField">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Icon Class (FontAwesome)</label>
                            <input type="text" name="icon" placeholder="fas fa-building" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                            <p class="text-xs text-gray-500 mt-1">Example: fas fa-building, fas fa-users, fas fa-shield-alt</p>
                        </div>
                        <div id="imageField" style="display: none;">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Image</label>
                            <input type="file" name="image" accept="image/*" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Title</label>
                            <input type="text" name="title" required class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                            <textarea name="description" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-lg"></textarea>
                        </div>
                    </div>
                    <div class="mt-6 flex justify-end space-x-3">
                        <button type="button" onclick="this.closest('dialog').close()" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700">Add Feature</button>
                    </div>
                </form>
            </div>
        </dialog>
    </div>


    <!-- Reviews Tab -->
    <div x-show="activeTab === 'reviews'" x-cloak>
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-800">Customer Reviews</h3>
                <button @click="$refs.reviewModal.showModal()" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors">
                    <i class="fas fa-plus mr-2"></i>Add Review
                </button>
            </div>
            
            @if($reviews->count() > 0)
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($reviews as $review)
                <div class="border rounded-lg p-4 {{ $review->is_active ? '' : 'opacity-50' }}">
                    <div class="flex items-center mb-3">
                        @if($review->avatar)
                            <img src="{{ Storage::url($review->avatar) }}" alt="" class="w-12 h-12 rounded-full object-cover">
                        @else
                            <div class="w-12 h-12 rounded-full bg-purple-100 flex items-center justify-center">
                                <span class="text-purple-600 font-semibold">{{ substr($review->name, 0, 1) }}</span>
                            </div>
                        @endif
                        <div class="ml-3">
                            <h4 class="font-medium text-gray-800">{{ $review->name }}</h4>
                            <p class="text-sm text-gray-500">{{ $review->designation }}{{ $review->company ? ' at ' . $review->company : '' }}</p>
                        </div>
                    </div>
                    <div class="flex mb-2">
                        @for($i = 1; $i <= 5; $i++)
                            <i class="fas fa-star {{ $i <= $review->rating ? 'text-yellow-400' : 'text-gray-300' }}"></i>
                        @endfor
                    </div>
                    <p class="text-gray-600 text-sm mb-3">{{ Str::limit($review->review, 100) }}</p>
                    <div class="flex items-center justify-between">
                        <form action="{{ route('super-admin.landing-site.reviews.toggle', $review) }}" method="POST">
                            @csrf
                            <button type="submit" class="text-xs {{ $review->is_active ? 'text-green-600' : 'text-gray-500' }}">
                                {{ $review->is_active ? 'Active' : 'Inactive' }}
                            </button>
                        </form>
                        <div class="flex space-x-2">
                            <button onclick="editReview({{ $review->toJson() }})" class="text-blue-600 hover:text-blue-800">
                                <i class="fas fa-edit"></i>
                            </button>
                            <form action="{{ route('super-admin.landing-site.reviews.destroy', $review) }}" method="POST" class="inline" onsubmit="return confirm('Delete this review?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-600 hover:text-red-800">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
            @else
            <div class="text-center py-8 text-gray-500">
                <i class="fas fa-quote-left text-4xl mb-2"></i>
                <p>No reviews added yet. Click "Add Review" to get started.</p>
            </div>
            @endif
        </div>

        <!-- Add Review Modal -->
        <dialog x-ref="reviewModal" class="rounded-lg shadow-xl p-0 w-full max-w-lg">
            <div class="p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-800">Add Review</h3>
                    <button onclick="this.closest('dialog').close()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <form action="{{ route('super-admin.landing-site.reviews.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Name</label>
                                <input type="text" name="name" required class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Rating</label>
                                <select name="rating" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                                    @for($i = 5; $i >= 1; $i--)
                                        <option value="{{ $i }}">{{ $i }} Star{{ $i > 1 ? 's' : '' }}</option>
                                    @endfor
                                </select>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Designation</label>
                                <input type="text" name="designation" placeholder="CEO, Manager, etc." class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Company</label>
                                <input type="text" name="company" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Avatar</label>
                            <input type="file" name="avatar" accept="image/*" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Review</label>
                            <textarea name="review" rows="4" required class="w-full px-4 py-2 border border-gray-300 rounded-lg"></textarea>
                        </div>
                    </div>
                    <div class="mt-6 flex justify-end space-x-3">
                        <button type="button" onclick="this.closest('dialog').close()" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700">Add Review</button>
                    </div>
                </form>
            </div>
        </dialog>
    </div>


    <!-- FAQs Tab -->
    <div x-show="activeTab === 'faqs'" x-cloak>
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-800">Frequently Asked Questions</h3>
                <button @click="$refs.faqModal.showModal()" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors">
                    <i class="fas fa-plus mr-2"></i>Add FAQ
                </button>
            </div>
            
            @if($faqs->count() > 0)
            <div class="space-y-3">
                @foreach($faqs as $faq)
                <div class="border rounded-lg {{ $faq->is_active ? '' : 'opacity-50' }}">
                    <div class="p-4">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <h4 class="font-medium text-gray-800">{{ $faq->question }}</h4>
                                <p class="text-gray-600 text-sm mt-2">{{ $faq->answer }}</p>
                            </div>
                            <div class="flex items-center space-x-2 ml-4">
                                <form action="{{ route('super-admin.landing-site.faqs.toggle', $faq) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="px-2 py-1 text-xs rounded-full {{ $faq->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                        {{ $faq->is_active ? 'Active' : 'Inactive' }}
                                    </button>
                                </form>
                                <button onclick="editFaq({{ $faq->id }}, '{{ addslashes($faq->question) }}', '{{ addslashes($faq->answer) }}')" class="text-blue-600 hover:text-blue-800">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <form action="{{ route('super-admin.landing-site.faqs.destroy', $faq) }}" method="POST" class="inline" onsubmit="return confirm('Delete this FAQ?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:text-red-800">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
            @else
            <div class="text-center py-8 text-gray-500">
                <i class="fas fa-question-circle text-4xl mb-2"></i>
                <p>No FAQs added yet. Click "Add FAQ" to get started.</p>
            </div>
            @endif
        </div>

        <!-- Add FAQ Modal -->
        <dialog x-ref="faqModal" class="rounded-lg shadow-xl p-0 w-full max-w-lg">
            <div class="p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-800">Add FAQ</h3>
                    <button onclick="this.closest('dialog').close()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <form action="{{ route('super-admin.landing-site.faqs.store') }}" method="POST">
                    @csrf
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Question</label>
                            <input type="text" name="question" required class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Answer</label>
                            <textarea name="answer" rows="4" required class="w-full px-4 py-2 border border-gray-300 rounded-lg"></textarea>
                        </div>
                    </div>
                    <div class="mt-6 flex justify-end space-x-3">
                        <button type="button" onclick="this.closest('dialog').close()" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700">Add FAQ</button>
                    </div>
                </form>
            </div>
        </dialog>
    </div>


    <!-- Contact Tab -->
    <div x-show="activeTab === 'contact'" x-cloak>
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Contact Settings</h3>
            <form action="{{ route('super-admin.landing-site.contact') }}" method="POST">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Contact Email</label>
                        <input type="email" name="contact_email" value="{{ $settings['contact_email'] ?? '' }}" 
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Contact Phone</label>
                        <input type="text" name="contact_phone" value="{{ $settings['contact_phone'] ?? '' }}" 
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Address</label>
                        <textarea name="contact_address" rows="2" 
                                  class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">{{ $settings['contact_address'] ?? '' }}</textarea>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Google Maps Embed Code</label>
                        <textarea name="contact_map_embed" rows="3" placeholder="Paste Google Maps iframe embed code here"
                                  class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent font-mono text-sm">{{ $settings['contact_map_embed'] ?? '' }}</textarea>
                    </div>
                </div>

                <h4 class="text-md font-semibold text-gray-800 mt-6 mb-4">Social Media Links</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fab fa-facebook text-blue-600 mr-2"></i>Facebook
                        </label>
                        <input type="url" name="social_facebook" value="{{ $settings['social_facebook'] ?? '' }}" 
                               placeholder="https://facebook.com/yourpage"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fab fa-twitter text-blue-400 mr-2"></i>Twitter
                        </label>
                        <input type="url" name="social_twitter" value="{{ $settings['social_twitter'] ?? '' }}" 
                               placeholder="https://twitter.com/yourhandle"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fab fa-instagram text-pink-600 mr-2"></i>Instagram
                        </label>
                        <input type="url" name="social_instagram" value="{{ $settings['social_instagram'] ?? '' }}" 
                               placeholder="https://instagram.com/yourhandle"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fab fa-linkedin text-blue-700 mr-2"></i>LinkedIn
                        </label>
                        <input type="url" name="social_linkedin" value="{{ $settings['social_linkedin'] ?? '' }}" 
                               placeholder="https://linkedin.com/company/yourcompany"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    </div>
                </div>

                <div class="mt-6">
                    <button type="submit" class="px-6 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors">
                        <i class="fas fa-save mr-2"></i>Save Contact Settings
                    </button>
                </div>
            </form>
        </div>
    </div>


    <!-- Pricing Tab -->
    <div x-show="activeTab === 'pricing'" x-cloak>
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-800">Pricing Plans</h3>
                <button @click="$refs.pricingModal.showModal()" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors">
                    <i class="fas fa-plus mr-2"></i>Add Plan
                </button>
            </div>
            
            @if($pricing->count() > 0)
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach($pricing as $plan)
                <div class="border rounded-lg {{ $plan->is_popular ? 'border-purple-500 ring-2 ring-purple-200' : '' }} {{ $plan->is_active ? '' : 'opacity-50' }}">
                    @if($plan->is_popular)
                        <div class="bg-purple-600 text-white text-center py-1 text-sm font-medium rounded-t-lg">Most Popular</div>
                    @endif
                    <div class="p-6">
                        <h4 class="text-xl font-bold text-gray-800">{{ $plan->name }}</h4>
                        <p class="text-gray-600 text-sm mt-1">{{ $plan->description }}</p>
                        <div class="mt-4">
                            <span class="text-3xl font-bold text-gray-800">${{ number_format($plan->monthly_price, 2) }}</span>
                            <span class="text-gray-500">/month</span>
                        </div>
                        <p class="text-sm text-gray-500">${{ number_format($plan->yearly_price, 2) }}/year</p>
                        
                        @if($plan->features)
                        <ul class="mt-4 space-y-2">
                            @foreach($plan->features as $feature)
                            <li class="flex items-center text-sm text-gray-600">
                                <i class="fas fa-check text-green-500 mr-2"></i>{{ $feature }}
                            </li>
                            @endforeach
                        </ul>
                        @endif
                        
                        <div class="mt-4 pt-4 border-t flex items-center justify-between">
                            <form action="{{ route('super-admin.landing-site.pricing.toggle', $plan) }}" method="POST">
                                @csrf
                                <button type="submit" class="text-xs {{ $plan->is_active ? 'text-green-600' : 'text-gray-500' }}">
                                    {{ $plan->is_active ? 'Active' : 'Inactive' }}
                                </button>
                            </form>
                            <div class="flex space-x-2">
                                <button onclick="editPricing({{ $plan->toJson() }})" class="text-blue-600 hover:text-blue-800">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <form action="{{ route('super-admin.landing-site.pricing.destroy', $plan) }}" method="POST" class="inline" onsubmit="return confirm('Delete this plan?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:text-red-800">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
            @else
            <div class="text-center py-8 text-gray-500">
                <i class="fas fa-tags text-4xl mb-2"></i>
                <p>No pricing plans added yet. Click "Add Plan" to get started.</p>
            </div>
            @endif
        </div>

        <!-- Add Pricing Modal -->
        <dialog x-ref="pricingModal" class="rounded-lg shadow-xl p-0 w-full max-w-lg">
            <div class="p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-800">Add Pricing Plan</h3>
                    <button onclick="this.closest('dialog').close()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <form action="{{ route('super-admin.landing-site.pricing.store') }}" method="POST">
                    @csrf
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Plan Name</label>
                            <input type="text" name="name" required class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                            <input type="text" name="description" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Monthly Price ($)</label>
                                <input type="number" name="monthly_price" step="0.01" min="0" required class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Yearly Price ($)</label>
                                <input type="number" name="yearly_price" step="0.01" min="0" required class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Features (one per line)</label>
                            <textarea name="features_text" rows="4" placeholder="Feature 1&#10;Feature 2&#10;Feature 3" 
                                      class="w-full px-4 py-2 border border-gray-300 rounded-lg" id="featuresText"></textarea>
                        </div>
                        <div class="flex items-center">
                            <input type="checkbox" name="is_popular" value="1" id="isPopular" class="rounded border-gray-300 text-purple-600 focus:ring-purple-500">
                            <label for="isPopular" class="ml-2 text-sm text-gray-700">Mark as Popular</label>
                        </div>
                    </div>
                    <div class="mt-6 flex justify-end space-x-3">
                        <button type="button" onclick="this.closest('dialog').close()" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700">Add Plan</button>
                    </div>
                </form>
            </div>
        </dialog>
    </div>
</div>

<style>
    [x-cloak] { display: none !important; }
    dialog::backdrop { background: rgba(0, 0, 0, 0.5); }
</style>

@push('scripts')
<script>
function toggleFeatureType() {
    const type = document.getElementById('featureType').value;
    document.getElementById('iconField').style.display = type === 'icon' ? 'block' : 'none';
    document.getElementById('imageField').style.display = type === 'image' ? 'block' : 'none';
}

function editFeature(id, type, icon, title, description) {
    // For simplicity, we'll use a prompt-based edit. In production, use a proper modal.
    alert('Edit feature functionality - implement with proper modal for production');
}

function editReview(review) {
    alert('Edit review functionality - implement with proper modal for production');
}

function editFaq(id, question, answer) {
    alert('Edit FAQ functionality - implement with proper modal for production');
}

function editPricing(plan) {
    alert('Edit pricing functionality - implement with proper modal for production');
}

// Convert features text to array before form submit
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', function(e) {
        const featuresText = this.querySelector('#featuresText');
        if (featuresText && featuresText.value) {
            const features = featuresText.value.split('\n').filter(f => f.trim());
            features.forEach((feature, index) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = `features[${index}]`;
                input.value = feature.trim();
                this.appendChild(input);
            });
        }
    });
});
</script>
@endpush
@endsection
