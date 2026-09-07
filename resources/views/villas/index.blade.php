@extends('layouts.app')

@section('title', 'Villas')
@section('page-title', 'Villas')

@section('content')
<div class="p-6">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <p class="text-gray-500">Manage villas in your society</p>
        </div>
        <a href="{{ route('villas.create') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
            <i class="fas fa-plus mr-2"></i> Add Villa
        </a>
    </div>

    @if(session('success'))
        <div class="mb-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">
            {{ session('error') }}
        </div>
    @endif

    <!-- Villas Table -->
    <div class="bg-white rounded-xl border border-gray-200">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Villa</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Area</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Owner</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Details</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($villas as $villa)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                                        <i class="fas fa-house-user text-green-600"></i>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm font-medium text-gray-800">{{ $villa->villa_name ?? $villa->flat_number }}</p>
                                        <p class="text-xs text-gray-500">{{ $villa->flat_number }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">
                                {{ $villa->villaArea->name ?? '-' }}
                            </td>
                            <td class="px-6 py-4">
                                @if($villa->ownerModel)
                                    <p class="text-sm font-medium text-gray-800">{{ $villa->ownerModel->name }}</p>
                                    <p class="text-xs text-gray-500">{{ $villa->ownerModel->phone ?? $villa->ownerModel->email }}</p>
                                @else
                                    <span class="text-sm text-gray-400">Not Assigned</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">
                                <div class="flex items-center space-x-3">
                                    @if($villa->bedrooms)
                                        <span><i class="fas fa-bed text-gray-400 mr-1"></i>{{ $villa->bedrooms }}</span>
                                    @endif
                                    @if($villa->bathrooms)
                                        <span><i class="fas fa-bath text-gray-400 mr-1"></i>{{ $villa->bathrooms }}</span>
                                    @endif
                                    @if($villa->plot_area)
                                        <span>{{ number_format($villa->plot_area) }} sq.ft</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-2 py-1 text-xs rounded-full 
                                    {{ $villa->status === 'occupied' ? 'bg-red-100 text-red-700' : '' }}
                                    {{ $villa->status === 'vacant' ? 'bg-green-100 text-green-700' : '' }}
                                    {{ $villa->status === 'on_rent' ? 'bg-blue-100 text-blue-700' : '' }}
                                    {{ $villa->status === 'under_maintenance' ? 'bg-orange-100 text-orange-700' : '' }}
                                    {{ $villa->status === 'maintenance' ? 'bg-yellow-100 text-yellow-700' : '' }}">
                                    {{ ucfirst(str_replace('_', ' ', $villa->status)) }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center space-x-2">
                                    <a href="{{ route('villas.show', $villa) }}" class="text-blue-600 hover:text-blue-800" title="View">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="{{ route('villas.edit', $villa) }}" class="text-yellow-600 hover:text-yellow-800" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <form action="{{ route('villas.destroy', $villa) }}" method="POST" class="inline"
                                          onsubmit="return confirm('Are you sure you want to delete this villa?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:text-red-800" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                                <i class="fas fa-house-user text-4xl text-gray-300 mb-3"></i>
                                <p>No villas found</p>
                                <a href="{{ route('villas.create') }}" class="text-blue-600 hover:underline mt-2 inline-block">Add your first villa</a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($villas->hasPages())
            <div class="px-6 py-4 border-t border-gray-200">
                {{ $villas->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
