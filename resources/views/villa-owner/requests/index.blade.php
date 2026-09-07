@extends('layouts.app')

@section('title', 'My Requests')
@section('page-title', 'My Requests')

@section('content')
<div class="p-6">
    <div class="flex items-center justify-between mb-6">
        <p class="text-gray-500">Track your maintenance and service requests</p>
        <a href="{{ route('villa-owner.requests.create') }}" class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
            <i class="fas fa-plus mr-2"></i> Raise Request
        </a>
    </div>

    <!-- Requests List -->
    <div class="bg-white rounded-xl border border-gray-200">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Request</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Priority</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($requests as $request)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4">
                                <p class="text-sm font-medium text-gray-800">{{ $request->title }}</p>
                                <p class="text-xs text-gray-500">{{ Str::limit($request->description, 50) }}</p>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">
                                {{ $request->category->name ?? 'General' }}
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-2 py-1 text-xs rounded-full 
                                    {{ $request->priority === 'urgent' ? 'bg-red-100 text-red-700' : '' }}
                                    {{ $request->priority === 'high' ? 'bg-orange-100 text-orange-700' : '' }}
                                    {{ $request->priority === 'medium' ? 'bg-yellow-100 text-yellow-700' : '' }}
                                    {{ $request->priority === 'low' ? 'bg-gray-100 text-gray-700' : '' }}">
                                    {{ ucfirst($request->priority) }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-2 py-1 text-xs rounded-full 
                                    {{ $request->status === 'open' ? 'bg-yellow-100 text-yellow-700' : '' }}
                                    {{ $request->status === 'in_progress' ? 'bg-blue-100 text-blue-700' : '' }}
                                    {{ $request->status === 'resolved' ? 'bg-green-100 text-green-700' : '' }}
                                    {{ $request->status === 'closed' ? 'bg-gray-100 text-gray-700' : '' }}">
                                    {{ ucfirst(str_replace('_', ' ', $request->status)) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">
                                {{ \Carbon\Carbon::parse($request->created_at)->format('d M Y') }}
                            </td>
                            <td class="px-6 py-4">
                                <a href="{{ route('villa-owner.requests.show', $request) }}" class="text-green-600 hover:text-green-800">
                                    <i class="fas fa-eye"></i> View
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                                <i class="fas fa-tools text-4xl text-gray-300 mb-3"></i>
                                <p>No requests yet</p>
                                <a href="{{ route('villa-owner.requests.create') }}" class="text-green-600 hover:underline mt-2 inline-block">Raise your first request</a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($requests->hasPages())
            <div class="px-6 py-4 border-t border-gray-200">
                {{ $requests->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
