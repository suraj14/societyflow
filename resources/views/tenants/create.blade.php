@extends('layouts.app')

@section('title', 'Create Tenant')
@section('page-title', 'Create Tenant')

@section('content')
<div class="container mx-auto px-4 py-6 max-w-2xl">
    <div class="bg-white rounded-lg shadow p-6">
        <h1 class="text-2xl font-bold text-gray-900 mb-6">Create New Tenant</h1>

        <form action="{{ route('tenants.store') }}" method="POST" class="space-y-6">
            @csrf

            <div class="grid grid-cols-2 gap-6">
                <x-form-field label="Name" name="name" type="text" required />
                <x-form-field label="Email" name="email" type="email" required />
            </div>

            <div class="grid grid-cols-2 gap-6">
                <x-form-field label="Phone" name="phone" type="tel" required />
                <x-form-field label="Monthly Rent" name="monthly_rent" type="number" step="0.01" required />
            </div>

            <div class="grid grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Owner <span class="text-red-500">*</span></label>
                    <select name="owner_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                        <option value="">Select Owner</option>
                        @foreach($owners as $owner)
                            <option value="{{ $owner->id }}">{{ $owner->name }}</option>
                        @endforeach
                    </select>
                    @error('owner_id')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Property <span class="text-red-500">*</span></label>
                    <select name="flat_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                        <option value="">Select Property</option>
                        @foreach($flats as $flat)
                            <option value="{{ $flat->id }}">{{ $flat->building->name ?? 'N/A' }} - {{ $flat->flat_number }}</option>
                        @endforeach
                    </select>
                    @error('flat_id')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="grid grid-cols-2 gap-6">
                <x-form-field label="Move-in Date" name="move_in_date" type="date" required />
                <x-form-field label="Security Deposit" name="security_deposit" type="number" step="0.01" />
            </div>

            <div class="flex justify-end space-x-4">
                <a href="{{ route('tenants.index') }}" class="px-6 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 transition-colors">
                    Cancel
                </a>
                <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                    Create Tenant
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
