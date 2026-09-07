@extends('layouts.app')

@section('title', 'Buildings')
@section('page-title', 'Buildings')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Buildings</h1>
            <p class="text-gray-600 mt-1">Manage all buildings in your society</p>
        </div>
        <a href="{{ route('buildings.create') }}" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors inline-flex items-center">
            <i class="fas fa-plus mr-2"></i>Add Building
        </a>
    </div>

    @if(session('success'))
        <x-alert type="success" autoDismiss="true">
            {{ session('success') }}
        </x-alert>
    @endif

    @if(session('error'))
        <x-alert type="error" autoDismiss="false">
            {{ session('error') }}
        </x-alert>
    @endif

    @if($buildings->isEmpty())
        <div class="bg-white rounded-lg shadow">
            <x-empty-state 
                icon="fas fa-building"
                title="No buildings found"
                description="Get started by adding your first building to the system."
                action="{{ route('buildings.create') }}"
                actionText="Add Your First Building" />
        </div>
    @else
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Building</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Floors</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Flats</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($buildings as $building)
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                            <i class="fas fa-building text-blue-600"></i>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900">{{ $building->name }}</div>
                                            @if($building->description)
                                                <div class="text-sm text-gray-500">{{ Str::limit($building->description, 50) }}</div>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $building->total_floors }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $building->total_flats }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <x-status-badge :status="$building->status" />
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <a href="{{ route('buildings.show', $building) }}" class="text-blue-600 hover:text-blue-900 mr-3" aria-label="View building">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="{{ route('buildings.edit', $building) }}" class="text-blue-600 hover:text-blue-900 mr-3" aria-label="Edit building">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <form method="POST" action="{{ route('buildings.destroy', $building) }}" class="inline" onsubmit="return confirm('Are you sure?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:text-red-900" aria-label="Delete building">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        @if($buildings->hasPages())
            <div class="mt-6">
                {{ $buildings->links() }}
            </div>
        @endif
    @endif
</div>
@endsection