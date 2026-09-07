@extends('layouts.app')

@section('title', 'Edit Resident')
@section('page-title', 'Edit Resident')

@section('content')
<div class="container mx-auto px-4 py-6">
    <!-- Header -->
    <div class="flex items-center mb-6">
        <a href="{{ route('residents.index') }}" 
           class="text-gray-600 hover:text-gray-900 mr-4">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Edit Resident</h1>
            <p class="text-gray-600 mt-1">Update resident information</p>
        </div>
    </div>

    <!-- Edit Form -->
    <div class="max-w-4xl bg-white rounded-lg shadow-md">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Resident Information</h2>
        </div>
        
        <form action="{{ route('residents.update', $resident) }}" method="POST" class="px-6 py-6">
            @csrf
            @method('PUT')
            
            <div class="space-y-6">
                <!-- Personal Information -->
                <div class="border-b border-gray-200 pb-6">
                    <h3 class="text-md font-semibold text-gray-900 mb-4">Personal Information</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
                                Full Name <span class="text-red-500">*</span>
                            </label>
                            <input type="text" 
                                   name="name" 
                                   id="name" 
                                   required
                                   value="{{ old('name', $resident->name) }}"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('name') border-red-500 @enderror">
                            @error('name')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                                Email <span class="text-red-500">*</span>
                            </label>
                            <input type="email" 
                                   name="email" 
                                   id="email" 
                                   required
                                   value="{{ old('email', $resident->email) }}"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('email') border-red-500 @enderror">
                            @error('email')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="phone" class="block text-sm font-medium text-gray-700 mb-2">
                                Phone <span class="text-red-500">*</span>
                            </label>
                            <input type="tel" 
                                   name="phone" 
                                   id="phone" 
                                   required
                                   value="{{ old('phone', $resident->phone) }}"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('phone') border-red-500 @enderror">
                            @error('phone')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <!-- Residence Information -->
                <div class="border-b border-gray-200 pb-6">
                    <h3 class="text-md font-semibold text-gray-900 mb-4">Residence Information</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="society_id" class="block text-sm font-medium text-gray-700 mb-2">
                                Society <span class="text-red-500">*</span>
                            </label>
                            <select name="society_id" 
                                    id="society_id" 
                                    required
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('society_id') border-red-500 @enderror">
                                <option value="">Select Society</option>
                                @foreach($societies as $society)
                                    <option value="{{ $society->id }}" {{ old('society_id', $resident->society_id) == $society->id ? 'selected' : '' }}>
                                        {{ $society->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('society_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="flat_id" class="block text-sm font-medium text-gray-700 mb-2">
                                Flat / Villa <span class="text-red-500">*</span>
                            </label>
                            <select name="flat_id" 
                                    id="flat_id" 
                                    required
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('flat_id') border-red-500 @enderror">
                                <option value="">Select Flat / Villa</option>
                                
                                @php
                                    $apartments = $flats->filter(fn($f) => $f->property_type !== 'villa');
                                    $villas = $flats->where('property_type', 'villa');
                                @endphp
                                
                                @if($apartments->count() > 0)
                                    <optgroup label="🏢 Apartments">
                                        @foreach($apartments as $flat)
                                            <option value="{{ $flat->id }}" {{ old('flat_id', $resident->flat_id) == $flat->id ? 'selected' : '' }}>
                                                {{ $flat->building->name ?? 'Building' }} - {{ $flat->flat_number }} ({{ $flat->type ?? 'Flat' }})
                                            </option>
                                        @endforeach
                                    </optgroup>
                                @endif
                                
                                @if($villas->count() > 0)
                                    <optgroup label="🏠 Villas">
                                        @foreach($villas as $villa)
                                            <option value="{{ $villa->id }}" {{ old('flat_id', $resident->flat_id) == $villa->id ? 'selected' : '' }}>
                                                {{ $villa->villaArea->name ?? 'Villa Area' }} - {{ $villa->villa_name ?? $villa->flat_number }}
                                            </option>
                                        @endforeach
                                    </optgroup>
                                @endif
                            </select>
                            @error('flat_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="move_in_date" class="block text-sm font-medium text-gray-700 mb-2">
                                Move In Date <span class="text-red-500">*</span>
                            </label>
                            <input type="date" 
                                   name="move_in_date" 
                                   id="move_in_date" 
                                   required
                                   value="{{ old('move_in_date', $resident->move_in_date ? \Carbon\Carbon::parse($resident->move_in_date)->format('Y-m-d') : '') }}"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('move_in_date') border-red-500 @enderror">
                            @error('move_in_date')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="status" class="block text-sm font-medium text-gray-700 mb-2">
                                Status <span class="text-red-500">*</span>
                            </label>
                            <select name="status" 
                                    id="status" 
                                    required
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('status') border-red-500 @enderror">
                                <option value="active" {{ old('status', $resident->status) == 'active' ? 'selected' : '' }}>Active</option>
                                <option value="inactive" {{ old('status', $resident->status) == 'inactive' ? 'selected' : '' }}>Inactive</option>
                            </select>
                            @error('status')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <!-- Financial Information -->
                <div>
                    <h3 class="text-md font-semibold text-gray-900 mb-4">Financial Information</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="monthly_rent" class="block text-sm font-medium text-gray-700 mb-2">
                                Monthly Rent (₹)
                            </label>
                            <input type="number" 
                                   name="monthly_rent" 
                                   id="monthly_rent" 
                                   step="0.01"
                                   min="0"
                                   value="{{ old('monthly_rent', $resident->monthly_rent) }}"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('monthly_rent') border-red-500 @enderror">
                            @error('monthly_rent')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="security_deposit" class="block text-sm font-medium text-gray-700 mb-2">
                                Security Deposit (₹)
                            </label>
                            <input type="number" 
                                   name="security_deposit" 
                                   id="security_deposit" 
                                   step="0.01"
                                   min="0"
                                   value="{{ old('security_deposit', $resident->security_deposit) }}"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('security_deposit') border-red-500 @enderror">
                            @error('security_deposit')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="mt-8 flex justify-end space-x-3 pt-6 border-t border-gray-200">
                <a href="{{ route('residents.index') }}" 
                   class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 font-medium transition-colors">
                    Cancel
                </a>
                <button type="submit" 
                        class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition-colors">
                    Update Resident
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
