@extends('layouts.app')

@section('title', 'Edit Society')
@section('page-title', 'Edit Society')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-purple-600">Dashboard</a>
    <span class="mx-2">/</span>
    <a href="{{ route('societies.index') }}" class="hover:text-purple-600">Societies</a>
    <span class="mx-2">/</span>
    <span class="text-gray-700">Edit</span>
@endsection

@section('content')
<div class="max-w-3xl">
    <!-- Form Card -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
            <h2 class="text-lg font-semibold text-gray-900">Edit Society Information</h2>
            <p class="text-sm text-gray-500 mt-1">Update the society details below</p>
        </div>
        
        <form action="{{ route('societies.update', $society) }}" method="POST" class="p-6">
            @csrf
            @method('PUT')
            
            <div class="space-y-6">
                <!-- Society Name -->
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-1.5">
                        Society Name <span class="text-red-500">*</span>
                    </label>
                    <input type="text" 
                           name="name" 
                           id="name" 
                           required
                           value="{{ old('name', $society->name) }}"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors @error('name') border-red-500 @enderror"
                           placeholder="Enter society name">
                    @error('name')
                        <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Address -->
                <div>
                    <label for="address" class="block text-sm font-medium text-gray-700 mb-1.5">
                        Address <span class="text-red-500">*</span>
                    </label>
                    <textarea name="address" 
                              id="address" 
                              rows="3" 
                              required
                              class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors @error('address') border-red-500 @enderror"
                              placeholder="Enter complete address">{{ old('address', $society->address) }}</textarea>
                    @error('address')
                        <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- City, State, Pincode -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label for="city" class="block text-sm font-medium text-gray-700 mb-1.5">
                            City <span class="text-red-500">*</span>
                        </label>
                        <input type="text" 
                               name="city" 
                               id="city" 
                               required
                               value="{{ old('city', $society->city) }}"
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors @error('city') border-red-500 @enderror"
                               placeholder="City">
                        @error('city')
                            <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="state" class="block text-sm font-medium text-gray-700 mb-1.5">
                            State <span class="text-red-500">*</span>
                        </label>
                        <input type="text" 
                               name="state" 
                               id="state" 
                               required
                               value="{{ old('state', $society->state) }}"
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors @error('state') border-red-500 @enderror"
                               placeholder="State">
                        @error('state')
                            <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="pincode" class="block text-sm font-medium text-gray-700 mb-1.5">
                            Pincode <span class="text-red-500">*</span>
                        </label>
                        <input type="text" 
                               name="pincode" 
                               id="pincode" 
                               required
                               value="{{ old('pincode', $society->pincode) }}"
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors @error('pincode') border-red-500 @enderror"
                               placeholder="Pincode">
                        @error('pincode')
                            <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <!-- Country -->
                <div>
                    <label for="country" class="block text-sm font-medium text-gray-700 mb-1.5">
                        Country <span class="text-red-500">*</span>
                    </label>
                    <input type="text" 
                           name="country" 
                           id="country" 
                           required
                           value="{{ old('country', $society->country) }}"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors @error('country') border-red-500 @enderror"
                           placeholder="Country">
                    @error('country')
                        <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Contact Information -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="phone" class="block text-sm font-medium text-gray-700 mb-1.5">
                            Phone
                        </label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                                <i class="fas fa-phone"></i>
                            </span>
                            <input type="tel" 
                                   name="phone" 
                                   id="phone"
                                   value="{{ old('phone', $society->phone) }}"
                                   class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors @error('phone') border-red-500 @enderror"
                                   placeholder="Phone number">
                        </div>
                        @error('phone')
                            <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-1.5">
                            Email
                        </label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                                <i class="fas fa-envelope"></i>
                            </span>
                            <input type="email" 
                                   name="email" 
                                   id="email"
                                   value="{{ old('email', $society->email) }}"
                                   class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors @error('email') border-red-500 @enderror"
                                   placeholder="Email address">
                        </div>
                        @error('email')
                            <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="mt-8 flex items-center justify-end space-x-3 pt-6 border-t border-gray-200">
                <a href="{{ route('societies.index') }}" 
                   class="px-5 py-2.5 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 font-medium transition-colors">
                    Cancel
                </a>
                <button type="submit" 
                        class="px-5 py-2.5 bg-purple-600 hover:bg-purple-700 text-white rounded-lg font-medium transition-colors shadow-sm">
                    <i class="fas fa-save mr-2"></i>
                    Update Society
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
