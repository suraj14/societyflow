@extends('layouts.app')

@section('title', $villaArea->name)
@section('page-title', $villaArea->name)

@section('content')
<div class="p-6">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <a href="{{ route('villa-areas.index') }}" class="text-blue-600 hover:text-blue-800 text-sm">
                <i class="fas fa-arrow-left mr-1"></i> Back to Villa Areas
            </a>
        </div>
        <div class="flex items-center space-x-2">
            <a href="{{ route('villa-areas.edit', $villaArea) }}" class="px-4 py-2 bg-yellow-500 text-white rounded-lg hover:bg-yellow-600 transition-colors">
                <i class="fas fa-edit mr-2"></i> Edit
            </a>
        </div>
    </div>

    <div class="grid lg:grid-cols-3 gap-6">
        <!-- Villa Area Details -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-xl border border-gray-200">
                <div class="p-6 border-b border-gray-200">
                    <h3 class="font-semibold text-gray-800">Villa Area Details</h3>
                </div>
                <div class="p-6 space-y-4">
                    <div>
                        <p class="text-sm text-gray-500">Name</p>
                        <p class="font-medium text-gray-800">{{ $villaArea->name }}</p>
                    </div>
                    @if($villaArea->code)
                    <div>
                        <p class="text-sm text-gray-500">Code</p>
                        <p class="font-medium text-gray-800">{{ $villaArea->code }}</p>
                    </div>
                    @endif
                    <div>
                        <p class="text-sm text-gray-500">Society</p>
                        <p class="font-medium text-gray-800">{{ $villaArea->society->name ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Status</p>
                        <span class="px-2 py-1 text-xs rounded-full {{ $villaArea->status === 'active' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700' }}">
                            {{ ucfirst($villaArea->status) }}
                        </span>
                    </div>
                    @if($villaArea->description)
                    <div>
                        <p class="text-sm text-gray-500">Description</p>
                        <p class="text-gray-700">{{ $villaArea->description }}</p>
                    </div>
                    @endif
                </div>
            </div>

            <!-- Stats -->
            <div class="bg-white rounded-xl border border-gray-200 mt-6">
                <div class="p-6 border-b border-gray-200">
                    <h3 class="font-semibold text-gray-800">Statistics</h3>
                </div>
                <div class="p-6 space-y-4">
                    <div class="flex items-center justify-between">
                        <span class="text-gray-600">Total Villas</span>
                        <span class="font-bold text-gray-800">{{ $villaArea->villas->count() }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-gray-600">Occupied</span>
                        <span class="font-bold text-green-600">{{ $villaArea->getOccupiedVillasCount() }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-gray-600">Vacant</span>
                        <span class="font-bold text-yellow-600">{{ $villaArea->getVacantVillasCount() }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Villas List -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-xl border border-gray-200">
                <div class="p-4 border-b border-gray-200 flex items-center justify-between">
                    <h3 class="font-semibold text-gray-800">Villas in this Area</h3>
                    <a href="{{ route('villas.create') }}?villa_area_id={{ $villaArea->id }}" class="text-blue-600 hover:text-blue-800 text-sm">
                        <i class="fas fa-plus mr-1"></i> Add Villa
                    </a>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Villa</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Owner</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @forelse($villaArea->villas as $villa)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4">
                                        <p class="text-sm font-medium text-gray-800">{{ $villa->villa_name ?? $villa->flat_number }}</p>
                                        <p class="text-xs text-gray-500">{{ $villa->flat_number }}</p>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-600">
                                        {{ $villa->ownerUser->name ?? 'Not Assigned' }}
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="px-2 py-1 text-xs rounded-full {{ $villa->status === 'occupied' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700' }}">
                                            {{ ucfirst($villa->status) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <a href="{{ route('villas.show', $villa) }}" class="text-blue-600 hover:text-blue-800">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-6 py-8 text-center text-gray-500">
                                        No villas in this area yet
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
