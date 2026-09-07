@extends('layouts.app')

@section('title', 'Tenant Details')
@section('page-title', 'Tenant Details')

@section('content')
<div class="container mx-auto px-4 py-6 max-w-2xl">
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex justify-between items-start mb-6">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">{{ $tenant->name }}</h1>
                <p class="text-gray-600 mt-1">Tenant Information</p>
            </div>
            <div class="flex space-x-2">
                <a href="{{ route('tenants.edit', $tenant) }}" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                    <i class="fas fa-edit mr-2"></i>Edit
                </a>
                <form method="POST" action="{{ route('tenants.destroy', $tenant) }}" class="inline" onsubmit="return confirm('Are you sure?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors">
                        <i class="fas fa-trash mr-2"></i>Delete
                    </button>
                </form>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-6 mb-6">
            <div>
                <label class="block text-sm font-medium text-gray-500 mb-1">Email</label>
                <p class="text-lg text-gray-900">{{ $tenant->email }}</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-500 mb-1">Phone</label>
                <p class="text-lg text-gray-900">{{ $tenant->phone ?? 'N/A' }}</p>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-6 mb-6">
            <div>
                <label class="block text-sm font-medium text-gray-500 mb-1">Property</label>
                <p class="text-lg text-gray-900">
                    @if($tenant->flat)
                        {{ $tenant->flat->building->name ?? 'N/A' }} - {{ $tenant->flat->flat_number }}
                    @else
                        <span class="text-gray-500">Unassigned</span>
                    @endif
                </p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-500 mb-1">Owner</label>
                <p class="text-lg text-gray-900">{{ $tenant->owner->name ?? 'N/A' }}</p>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-6 mb-6">
            <div>
                <label class="block text-sm font-medium text-gray-500 mb-1">Monthly Rent</label>
                <p class="text-lg text-gray-900">₹{{ number_format($tenant->monthly_rent, 2) }}</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-500 mb-1">Security Deposit</label>
                <p class="text-lg text-gray-900">₹{{ number_format($tenant->security_deposit, 2) }}</p>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-6 mb-6">
            <div>
                <label class="block text-sm font-medium text-gray-500 mb-1">Move-in Date</label>
                <p class="text-lg text-gray-900">{{ $tenant->move_in_date->format('M d, Y') }}</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-500 mb-1">Status</label>
                <x-status-badge :status="$tenant->status" />
            </div>
        </div>

        <div class="flex justify-end">
            <a href="{{ route('tenants.index') }}" class="px-6 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 transition-colors">
                Back to Tenants
            </a>
        </div>
    </div>
</div>
@endsection
