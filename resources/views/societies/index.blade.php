@extends('layouts.app')

@section('title', 'Societies')
@section('page-title', 'Societies')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-purple-600">Dashboard</a>
    <span class="mx-2">/</span>
    <span class="text-gray-700">Societies</span>
@endsection

@section('content')
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6">
    <div>
        <p class="text-gray-600">Manage all societies in the system</p>
    </div>
    <div class="mt-4 sm:mt-0">
        <a href="{{ route('societies.create') }}" class="inline-flex items-center px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white text-sm font-medium rounded-lg transition-colors shadow-sm">
            <i class="fas fa-plus mr-2"></i>Add New Society
        </a>
    </div>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    @if($societies->count() > 0)
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Society</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Location</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Contact</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Status</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($societies as $society)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="h-10 w-10 bg-purple-100 rounded-lg flex items-center justify-center">
                                        <i class="fas fa-building text-purple-600"></i>
                                    </div>
                                    <div class="ml-4">
                                        <div class="text-sm font-medium text-gray-900">{{ $society->name }}</div>
                                        <div class="text-xs text-gray-500">ID: #{{ $society->id }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">{{ $society->city }}, {{ $society->state }}</div>
                                <div class="text-xs text-gray-500">{{ $society->country }} - {{ $society->pincode }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">{{ $society->phone ?? 'N/A' }}</div>
                                <div class="text-xs text-gray-500">{{ $society->email ?? 'N/A' }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($society->status === 'active')
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Active</span>
                                @elseif($society->status === 'trial')
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">Trial</span>
                                @else
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">Inactive</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right">
                                <div class="flex justify-end space-x-2">
                                    <a href="{{ route('societies.show', $society) }}" class="p-2 text-gray-500 hover:text-blue-600 rounded-lg"><i class="fas fa-eye"></i></a>
                                    <a href="{{ route('societies.edit', $society) }}" class="p-2 text-gray-500 hover:text-purple-600 rounded-lg"><i class="fas fa-edit"></i></a>
                                    <form action="{{ route('societies.destroy', $society) }}" method="POST" class="inline" onsubmit="return confirm('Delete?');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="p-2 text-gray-500 hover:text-red-600 rounded-lg"><i class="fas fa-trash"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if($societies->hasPages())
            <div class="bg-gray-50 px-6 py-3 border-t">{{ $societies->links() }}</div>
        @endif
    @else
        <div class="text-center py-16">
            <div class="w-16 h-16 bg-purple-100 rounded-full mx-auto mb-4 flex items-center justify-center">
                <i class="fas fa-building text-purple-600 text-2xl"></i>
            </div>
            <h3 class="text-lg font-medium text-gray-900 mb-2">No societies found</h3>
            <p class="text-gray-500 mb-6">Get started by creating your first society.</p>
            <a href="{{ route('societies.create') }}" class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg">
                <i class="fas fa-plus mr-2"></i>Create Society
            </a>
        </div>
    @endif
</div>
@endsection
