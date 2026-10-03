@extends('layouts.app')

@section('title', 'Property & Occupancy')
@section('page-title', 'Property & Occupancy')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Property &amp; Occupancy</h1>
            <p class="text-gray-600 mt-1">Who lives where, and where to change it.</p>
        </div>
    </div>

    <!-- Society-wide numbers -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
        <div class="bg-white rounded-lg shadow-md p-5">
            <p class="text-sm text-gray-500">Total Properties</p>
            <p class="text-2xl font-bold text-gray-900">{{ $stats['total'] }}</p>
        </div>
        <div class="bg-white rounded-lg shadow-md p-5">
            <p class="text-sm text-gray-500">Owner Occupied</p>
            <p class="text-2xl font-bold text-green-600">{{ $stats['owner_occupied'] }}</p>
        </div>
        <div class="bg-white rounded-lg shadow-md p-5">
            <p class="text-sm text-gray-500">Tenant Occupied (On Rent)</p>
            <p class="text-2xl font-bold text-blue-600">{{ $stats['tenant_occupied'] }}</p>
        </div>
        <div class="bg-white rounded-lg shadow-md p-5">
            <p class="text-sm text-gray-500">Vacant</p>
            <p class="text-2xl font-bold text-amber-600">{{ $stats['vacant'] }}</p>
        </div>
    </div>

    {{--
        One card per assignment workflow, shown only when the society's own
        configuration actually supports that property type - the same rule
        the rest of this pass applies to forms applies to navigation too.
        Each card links to its existing, tested screen rather than
        reimplementing it.
    --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        @if(!$society || $society->propertyType()->supportsApartments())
            <a href="{{ route('admin.apartment-assignments.index') }}"
               class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow block">
                <i class="fas fa-building text-purple-500 text-2xl mb-3"></i>
                <h2 class="text-lg font-semibold text-gray-900">Apartment Assignments</h2>
                <p class="text-gray-600 text-sm mt-1">Assign, reassign or unassign apartment owners.</p>
                <p class="text-sm text-gray-400 mt-3">{{ $stats['apartments'] }} apartments in this society</p>
            </a>
        @endif

        @if(!$society || $society->propertyType()->supportsVillas())
            <a href="{{ route('admin.villa-assignments.index') }}"
               class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow block">
                <i class="fas fa-house-user text-purple-500 text-2xl mb-3"></i>
                <h2 class="text-lg font-semibold text-gray-900">Villa Assignments</h2>
                <p class="text-gray-600 text-sm mt-1">Assign, reassign or unassign villa owners.</p>
                <p class="text-sm text-gray-400 mt-3">{{ $stats['villas'] }} villas in this society</p>
            </a>
        @endif

        <a href="{{ route('admin.tenant-assignments.index') }}"
           class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow block">
            <i class="fas fa-key text-purple-500 text-2xl mb-3"></i>
            <h2 class="text-lg font-semibold text-gray-900">Tenant Assignments</h2>
            <p class="text-gray-600 text-sm mt-1">Move tenants in, transfer or move them out.</p>
            <p class="text-sm text-gray-400 mt-3">{{ $stats['tenant_occupied'] }} properties currently on rent</p>
        </a>
    </div>

    <!-- Per-property detail: the operational source of truth for who lives
         where, read from the same tables every other screen uses. -->
    <div class="mt-8 bg-white rounded-lg shadow-md overflow-hidden">
        <div class="p-6 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Properties</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Property</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Owner</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Tenant</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Occupancy</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($properties as $flat)
                        @php
                            $row = $occupancy[$flat->id];
                            $activeTenant = $row['tenant'];
                            $resolvedOwner = $row['owner'];
                            $badgeClass = \App\Support\PropertyOccupancy::badgeClass($row['state']);
                            $occupancyLabel = \App\Support\PropertyOccupancy::label($row['state']);
                        @endphp
                        <tr>
                            <td class="px-4 py-2 text-sm font-medium text-gray-900">
                                {{ $flat->flat_number ?? $flat->villa_name }}
                                @if($flat->building)
                                    <span class="text-gray-500 text-xs">- {{ $flat->building->name }}</span>
                                @elseif($flat->villaArea)
                                    <span class="text-gray-500 text-xs">- {{ $flat->villaArea->name }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-sm text-gray-600">{{ ucfirst($flat->property_type) }}</td>
                            <td class="px-4 py-2 text-sm text-gray-900">{{ $resolvedOwner->name ?? '—' }}</td>
                            <td class="px-4 py-2 text-sm text-gray-900">{{ $activeTenant->name ?? '—' }}</td>
                            <td class="px-4 py-2">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $badgeClass }}">
                                    {{ $occupancyLabel }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-6 text-center text-sm text-gray-500">No properties found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($properties->hasPages())
            <div class="p-4 border-t border-gray-200">{{ $properties->links() }}</div>
        @endif
    </div>

    <div class="mt-8 bg-white rounded-lg shadow-md p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-2">Adding a new resident?</h2>
        <p class="text-gray-600 text-sm">
            Assigning a property during
            <a href="{{ route('owners.create') }}" class="text-blue-600 hover:underline">Add Owner</a>
            or
            <a href="{{ route('admin.tenants.create') }}" class="text-blue-600 hover:underline">Add Tenant</a>
            is the normal path and does not require a separate assignment step
            afterwards. Come back here to change, transfer or unassign a
            property later.
        </p>
    </div>
</div>
@endsection
