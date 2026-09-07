@extends('layouts.app')

@section('title', 'My Visitors')
@section('page-title', 'My Visitors')

@section('content')
<div class="p-6">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <p class="text-gray-500">Manage visitors to your villa</p>
        </div>
        <a href="{{ route('villa-owner.visitors.create') }}" class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
            <i class="fas fa-plus mr-2"></i> Add Visitor
        </a>
    </div>

    <!-- Visitors List -->
    <div class="bg-white rounded-xl border border-gray-200">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Visitor</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Purpose</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Visit Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($visitors as $visitor)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 bg-gray-100 rounded-full flex items-center justify-center">
                                        <i class="fas fa-user text-gray-500"></i>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm font-medium text-gray-800">{{ $visitor->visitor_name }}</p>
                                        <p class="text-xs text-gray-500">{{ $visitor->visitor_phone }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">{{ $visitor->purpose }}</td>
                            <td class="px-6 py-4 text-sm text-gray-600">
                                {{ \Carbon\Carbon::parse($visitor->visit_date)->format('d M Y') }}
                                @if($visitor->expected_time)
                                    <br><span class="text-xs text-gray-400">{{ $visitor->expected_time }}</span>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-2 py-1 text-xs rounded-full 
                                    {{ $visitor->status === 'checked_in' ? 'bg-green-100 text-green-700' : '' }}
                                    {{ $visitor->status === 'checked_out' ? 'bg-gray-100 text-gray-700' : '' }}
                                    {{ $visitor->status === 'expected' ? 'bg-yellow-100 text-yellow-700' : '' }}
                                    {{ $visitor->status === 'cancelled' ? 'bg-red-100 text-red-700' : '' }}">
                                    {{ ucfirst(str_replace('_', ' ', $visitor->status)) }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <a href="{{ route('villa-owner.visitors.show', $visitor) }}" class="text-green-600 hover:text-green-800">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                                <i class="fas fa-user-friends text-4xl text-gray-300 mb-3"></i>
                                <p>No visitors yet</p>
                                <a href="{{ route('villa-owner.visitors.create') }}" class="text-green-600 hover:underline mt-2 inline-block">Add your first visitor</a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($visitors->hasPages())
            <div class="px-6 py-4 border-t border-gray-200">
                {{ $visitors->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
