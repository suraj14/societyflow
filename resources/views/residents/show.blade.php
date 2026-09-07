@extends('layouts.app')

@section('title', 'Resident Details')
@section('page-title', 'Resident Details')

@section('content')
<div class="container mx-auto px-4 py-6">
    <!-- Header -->
    <div class="flex justify-between items-center mb-6">
        <div class="flex items-center">
            <a href="{{ route('residents.index') }}" 
               class="text-gray-600 hover:text-gray-900 mr-4">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div>
                <h1 class="text-3xl font-bold text-gray-900">{{ $resident->name }}</h1>
                <p class="text-gray-600 mt-1">Resident Details</p>
            </div>
        </div>
        <div class="flex space-x-3">
            <a href="{{ route('residents.edit', $resident) }}" 
               class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-3 rounded-lg font-medium transition-colors inline-flex items-center">
                <i class="fas fa-edit mr-2"></i>
                Edit Resident
            </a>
            <form action="{{ route('residents.destroy', $resident) }}" 
                  method="POST" 
                  class="inline-block"
                  onsubmit="return confirm('Are you sure you want to delete this resident? This action cannot be undone.');">
                @csrf
                @method('DELETE')
                <button type="submit" 
                        class="bg-red-600 hover:bg-red-700 text-white px-6 py-3 rounded-lg font-medium transition-colors inline-flex items-center">
                    <i class="fas fa-trash mr-2"></i>
                    Delete
                </button>
            </form>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Profile Card -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="text-center">
                    <img class="h-32 w-32 rounded-full mx-auto object-cover mb-4" 
                         src="https://ui-avatars.com/api/?name={{ urlencode($resident->name) }}&size=128&background=0d47a1&color=fff" 
                         alt="{{ $resident->name }}">
                    <h2 class="text-xl font-bold text-gray-900">{{ $resident->name }}</h2>
                    <p class="text-gray-600">{{ ucfirst($resident->type) }}</p>
                    <div class="mt-4">
                        @if($resident->status === 'active')
                            <span class="px-3 py-1 inline-flex text-sm leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                Active
                            </span>
                        @else
                            <span class="px-3 py-1 inline-flex text-sm leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                Inactive
                            </span>
                        @endif
                    </div>
                </div>

                <div class="mt-6 border-t border-gray-200 pt-6">
                    <dl class="space-y-4">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Resident Code</dt>
                            <dd class="mt-1 text-sm text-gray-900 font-semibold">RES-{{ str_pad($resident->id, 5, '0', STR_PAD_LEFT) }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Member Since</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                {{ $resident->move_in_date ? \Carbon\Carbon::parse($resident->move_in_date)->format('M d, Y') : 'N/A' }}
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>

        <!-- Details Cards -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Contact Information -->
            <div class="bg-white rounded-lg shadow-md">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">Contact Information</h2>
                </div>
                <div class="px-6 py-4">
                    <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Email</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                <i class="fas fa-envelope text-gray-400 mr-2"></i>
                                {{ $resident->email }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Phone</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                <i class="fas fa-phone text-gray-400 mr-2"></i>
                                {{ $resident->phone }}
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>

            <!-- Residence Information -->
            <div class="bg-white rounded-lg shadow-md">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">Residence Information</h2>
                </div>
                <div class="px-6 py-4">
                    <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Society</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $resident->society->name }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Building</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $resident->flat->building->name ?? 'N/A' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Flat Number</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $resident->flat->flat_number ?? 'N/A' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Floor</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $resident->flat->floor ?? 'N/A' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Flat Type</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $resident->flat->type ?? 'N/A' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Resident Type</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ ucfirst($resident->type) }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            <!-- Financial Information -->
            <div class="bg-white rounded-lg shadow-md">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">Financial Information</h2>
                </div>
                <div class="px-6 py-4">
                    <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Monthly Rent</dt>
                            <dd class="mt-1 text-sm text-gray-900 font-semibold">
                                ₹{{ number_format($resident->monthly_rent ?? 0, 2) }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Security Deposit</dt>
                            <dd class="mt-1 text-sm text-gray-900 font-semibold">
                                ₹{{ number_format($resident->security_deposit ?? 0, 2) }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Move In Date</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                {{ $resident->move_in_date ? \Carbon\Carbon::parse($resident->move_in_date)->format('M d, Y') : 'N/A' }}
                            </dd>
                        </div>
                        @if($resident->move_out_date)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Move Out Date</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                {{ \Carbon\Carbon::parse($resident->move_out_date)->format('M d, Y') }}
                            </dd>
                        </div>
                        @endif
                    </dl>
                </div>
            </div>

            <!-- Timestamps -->
            <div class="bg-white rounded-lg shadow-md">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">Record Information</h2>
                </div>
                <div class="px-6 py-4">
                    <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Created At</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $resident->created_at->format('M d, Y h:i A') }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Last Updated</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $resident->updated_at->format('M d, Y h:i A') }}</dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
