@extends('layouts.app')

@section('title', $society->name)
@section('page-title', $society->name)

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-purple-600">Dashboard</a>
    <span class="mx-2">/</span>
    <a href="{{ route('societies.index') }}" class="hover:text-purple-600">Societies</a>
    <span class="mx-2">/</span>
    <span class="text-gray-700">{{ $society->name }}</span>
@endsection

@section('content')
<!-- Page Header with Actions -->
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6">
    <div>
        <div class="flex items-center">
            @if($society->status === 'active')
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 mr-3">
                    <span class="w-1.5 h-1.5 mr-1.5 rounded-full bg-green-500"></span>
                    Active
                </span>
            @elseif($society->status === 'trial')
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 mr-3">
                    <span class="w-1.5 h-1.5 mr-1.5 rounded-full bg-blue-500"></span>
                    Trial
                </span>
            @else
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 mr-3">
                    <span class="w-1.5 h-1.5 mr-1.5 rounded-full bg-red-500"></span>
                    Inactive
                </span>
            @endif
        </div>
        <p class="text-gray-500 mt-1">Society ID: #{{ $society->id }}</p>
    </div>
    <div class="mt-4 sm:mt-0 flex items-center space-x-3">
        <a href="{{ route('societies.edit', $society) }}" 
           class="inline-flex items-center px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white text-sm font-medium rounded-lg transition-colors shadow-sm">
            <i class="fas fa-edit mr-2"></i>Edit Society
        </a>
        <form action="{{ route('societies.destroy', $society) }}" method="POST" class="inline-block"
              onsubmit="return confirm('Are you sure you want to delete this society?');">
            @csrf
            @method('DELETE')
            <button type="submit" class="inline-flex items-center px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-lg transition-colors shadow-sm">
                <i class="fas fa-trash mr-2"></i>Delete
            </button>
        </form>
    </div>
</div>

<!-- Statistics Cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
        <div class="flex items-center">
            <div class="flex-shrink-0 w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center">
                <i class="fas fa-building text-blue-600 text-lg"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-500">Buildings</p>
                <p class="text-2xl font-bold text-gray-900">{{ $stats['total_buildings'] }}</p>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
        <div class="flex items-center">
            <div class="flex-shrink-0 w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center">
                <i class="fas fa-home text-green-600 text-lg"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-500">Total Flats</p>
                <p class="text-2xl font-bold text-gray-900">{{ $stats['total_flats'] }}</p>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
        <div class="flex items-center">
            <div class="flex-shrink-0 w-12 h-12 bg-purple-100 rounded-xl flex items-center justify-center">
                <i class="fas fa-users text-purple-600 text-lg"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-500">Residents</p>
                <p class="text-2xl font-bold text-gray-900">{{ $stats['total_residents'] }}</p>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
        <div class="flex items-center">
            <div class="flex-shrink-0 w-12 h-12 bg-orange-100 rounded-xl flex items-center justify-center">
                <i class="fas fa-check-circle text-orange-600 text-lg"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-500">Occupied</p>
                <p class="text-2xl font-bold text-gray-900">{{ $stats['occupied_flats'] }}</p>
            </div>
        </div>
    </div>
</div>

<!-- Society Information -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Basic Information -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
            <h2 class="text-lg font-semibold text-gray-900">Basic Information</h2>
        </div>
        <div class="p-6 space-y-4">
            <div class="flex justify-between"><span class="text-sm text-gray-500">Society Name</span><span class="text-sm font-medium text-gray-900">{{ $society->name }}</span></div>
            <div class="flex justify-between"><span class="text-sm text-gray-500">Slug</span><span class="text-sm text-gray-900">{{ $society->slug }}</span></div>
            <div class="flex justify-between"><span class="text-sm text-gray-500">Created</span><span class="text-sm text-gray-900">{{ $society->created_at->format('M d, Y') }}</span></div>
            <div class="flex justify-between"><span class="text-sm text-gray-500">Updated</span><span class="text-sm text-gray-900">{{ $society->updated_at->format('M d, Y') }}</span></div>
        </div>
    </div>

    <!-- Contact Information -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
            <h2 class="text-lg font-semibold text-gray-900">Contact Information</h2>
        </div>
        <div class="p-6 space-y-4">
            <div><span class="text-sm text-gray-500 block mb-1">Address</span><span class="text-sm text-gray-900">{{ $society->address }}</span></div>
            <div class="grid grid-cols-2 gap-4">
                <div><span class="text-sm text-gray-500 block mb-1">City</span><span class="text-sm text-gray-900">{{ $society->city }}</span></div>
                <div><span class="text-sm text-gray-500 block mb-1">State</span><span class="text-sm text-gray-900">{{ $society->state }}</span></div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div><span class="text-sm text-gray-500 block mb-1">Country</span><span class="text-sm text-gray-900">{{ $society->country }}</span></div>
                <div><span class="text-sm text-gray-500 block mb-1">Pincode</span><span class="text-sm text-gray-900">{{ $society->pincode }}</span></div>
            </div>
            <div class="pt-2 border-t"><span class="text-sm text-gray-500 block mb-1">Phone</span><span class="text-sm text-gray-900"><i class="fas fa-phone text-gray-400 mr-2"></i>{{ $society->phone ?? 'N/A' }}</span></div>
            <div><span class="text-sm text-gray-500 block mb-1">Email</span><span class="text-sm text-gray-900"><i class="fas fa-envelope text-gray-400 mr-2"></i>{{ $society->email ?? 'N/A' }}</span></div>
        </div>
    </div>
</div>
@endsection
