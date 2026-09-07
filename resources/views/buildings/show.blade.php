@extends('layouts.app')

@section('title', 'Building Details')
@section('page-title', $building->name)

@section('content')
<div class="p-6">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <a href="{{ route('buildings.index') }}" class="text-blue-600 hover:text-blue-800 text-sm">
                <i class="fas fa-arrow-left mr-1"></i> Back to Buildings
            </a>
        </div>
        <div class="flex items-center space-x-2">
            <a href="{{ route('buildings.edit', $building) }}" class="px-4 py-2 bg-yellow-500 text-white rounded-lg hover:bg-yellow-600 transition-colors">
                <i class="fas fa-edit mr-2"></i> Edit
            </a>
        </div>
    </div>

    <div class="grid lg:grid-cols-3 gap-6">
        <!-- Building Details -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-xl border border-gray-200">
                <div class="p-6 border-b border-gray-200 bg-gradient-to-r from-blue-500 to-blue-600 rounded-t-xl">
                    <div class="text-white">
                        <p class="text-blue-100 text-sm">Building</p>
                        <h2 class="text-2xl font-bold">{{ $building->name }}</h2>
                        <p class="text-blue-100 mt-1">{{ $building->society->name ?? 'Society' }}</p>
                    </div>
                </div>
                <div class="p-6 space-y-4">
                    <div>
                        <p class="text-sm text-gray-500">Total Floors</p>
                        <p class="font-medium text-gray-800">{{ $building->total_floors }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Total Flats</p>
                        <p class="font-medium text-gray-800">{{ $building->total_flats ?? $building->flats->count() }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Status</p>
                        <span class="px-2 py-1 text-xs rounded-full {{ $building->status === 'active' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700' }}">
                            {{ ucfirst($building->status) }}
                        </span>
                    </div>
                    @if($building->description)
                    <div>
                        <p class="text-sm text-gray-500">Description</p>
                        <p class="text-gray-700">{{ $building->description }}</p>
                    </div>
                    @endif
                </div>
            </div>

            <!-- Stats -->
            <div class="bg-white rounded-xl border border-gray-200 mt-6">
                <div class="p-4 border-b border-gray-200">
                    <h3 class="font-semibold text-gray-800">Occupancy Stats</h3>
                </div>
                <div class="p-6 space-y-4">
                    <div class="flex items-center justify-between">
                        <span class="text-gray-600">Total Flats</span>
                        <span class="font-bold text-gray-800">{{ $stats['total_flats'] }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-gray-600">Occupied</span>
                        <span class="font-bold text-green-600">{{ $stats['occupied_flats'] }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-gray-600">Vacant</span>
                        <span class="font-bold text-yellow-600">{{ $stats['vacant_flats'] }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-gray-600">Under Maintenance</span>
                        <span class="font-bold text-red-600">{{ $stats['maintenance_flats'] }}</span>
                    </div>
                    @if($stats['total_flats'] > 0)
                    <div class="pt-4 border-t border-gray-200">
                        <p class="text-sm text-gray-500 mb-2">Occupancy Rate</p>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-green-500 h-2 rounded-full" style="width: {{ ($stats['occupied_flats'] / $stats['total_flats']) * 100 }}%"></div>
                        </div>
                        <p class="text-sm text-gray-600 mt-1">{{ round(($stats['occupied_flats'] / $stats['total_flats']) * 100) }}% occupied</p>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Flats List -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-xl border border-gray-200">
                <div class="p-4 border-b border-gray-200 flex items-center justify-between">
                    <h3 class="font-semibold text-gray-800">Flats in this Building</h3>
                    <a href="{{ route('flats.create') }}?building_id={{ $building->id }}" class="text-blue-600 hover:text-blue-800 text-sm">
                        <i class="fas fa-plus mr-1"></i> Add Flat
                    </a>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Flat</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Floor</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Resident</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @forelse($building->flats as $flat)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4">
                                        <p class="text-sm font-medium text-gray-800">{{ $flat->flat_number }}</p>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-600">{{ $flat->floor }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-600">{{ $flat->type ?? '-' }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-600">
                                        {{ $flat->activeResident->name ?? '-' }}
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="px-2 py-1 text-xs rounded-full 
                                            {{ $flat->status === 'occupied' ? 'bg-green-100 text-green-700' : '' }}
                                            {{ $flat->status === 'vacant' ? 'bg-yellow-100 text-yellow-700' : '' }}
                                            {{ $flat->status === 'maintenance' ? 'bg-red-100 text-red-700' : '' }}">
                                            {{ ucfirst($flat->status) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <a href="{{ route('flats.show', $flat) }}" class="text-blue-600 hover:text-blue-800">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-8 text-center text-gray-500">
                                        No flats in this building yet
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
