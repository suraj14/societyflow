@extends('layouts.app')

@section('title', 'Service Requests')
@section('page-title', 'Service Requests')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center">
            <a href="{{ route('services.index') }}"
               class="text-gray-600 hover:text-gray-900 mr-4 transition-colors">
                <i class="fas fa-arrow-left text-xl"></i>
            </a>
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Service Requests</h1>
                <p class="text-gray-600 mt-1">
                    {{ $isStaff ? 'Every request in your society' : 'Requests you have raised' }}
                </p>
            </div>
        </div>

        <form method="GET" class="flex items-center space-x-2">
            <label for="status" class="text-sm font-medium text-gray-700">Status:</label>
            <select name="status" id="status" onchange="this.form.submit()"
                    class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                <option value="">All</option>
                @foreach(\App\Models\ServiceRequest::STATUSES as $s)
                    <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst(str_replace('_', ' ', $s)) }}</option>
                @endforeach
            </select>
        </form>
    </div>


    <div class="bg-white shadow-md rounded-lg overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Service</th>
                    @if($isStaff)
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Requested By</th>
                    @endif
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Assigned To</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Requested</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($requests as $r)
                    @php
                        $statusColors = [
                            'pending'     => 'bg-amber-100 text-amber-800',
                            'assigned'    => 'bg-blue-100 text-blue-800',
                            'in_progress' => 'bg-indigo-100 text-indigo-800',
                            'completed'   => 'bg-green-100 text-green-800',
                            'cancelled'   => 'bg-gray-100 text-gray-600',
                        ];
                    @endphp
                    <tr>
                        <td class="px-4 py-3 text-sm text-gray-900">{{ $r->service->name ?? '—' }}</td>
                        @if($isStaff)
                            <td class="px-4 py-3 text-sm text-gray-700">{{ $r->user->name ?? '—' }}</td>
                        @endif
                        <td class="px-4 py-3 text-sm text-gray-700 max-w-xs truncate" title="{{ $r->description }}">{{ $r->description }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusColors[$r->status] ?? 'bg-gray-100 text-gray-800' }}">
                                {{ ucfirst(str_replace('_', ' ', $r->status)) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-700">{{ $r->assignedProvider->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-500">{{ $r->created_at->format('d M, h:i A') }}</td>
                        <td class="px-4 py-3 text-sm space-y-1">
                            @if($isStaff)
                                @if($r->status === 'pending')
                                    <form method="POST" action="{{ route('service-requests.assign', $r) }}" class="flex items-center gap-1">
                                        @csrf
                                        <select name="assigned_provider_id" required class="text-xs border border-gray-300 rounded px-1.5 py-1">
                                            <option value="">Assign to…</option>
                                            @foreach($providers->where('service_id', $r->service_id) as $p)
                                                <option value="{{ $p->id }}">{{ $p->name }}</option>
                                            @endforeach
                                        </select>
                                        <button type="submit" class="px-2 py-1 bg-blue-600 text-white rounded text-xs hover:bg-blue-700">Assign</button>
                                    </form>
                                @endif
                                @if($r->status === 'assigned')
                                    <form method="POST" action="{{ route('service-requests.status', $r) }}" class="inline">
                                        @csrf
                                        <input type="hidden" name="status" value="in_progress">
                                        <button type="submit" class="px-2 py-1 bg-indigo-600 text-white rounded text-xs hover:bg-indigo-700">Start</button>
                                    </form>
                                @endif
                                @if(in_array($r->status, ['assigned', 'in_progress']))
                                    <form method="POST" action="{{ route('service-requests.status', $r) }}" class="inline">
                                        @csrf
                                        <input type="hidden" name="status" value="completed">
                                        <button type="submit" class="px-2 py-1 bg-green-600 text-white rounded text-xs hover:bg-green-700">Complete</button>
                                    </form>
                                @endif
                            @endif
                            @if($r->isOpen() && (! $isStaff ? $r->user_id === auth()->id() : true))
                                <form method="POST" action="{{ route('service-requests.cancel', $r) }}" class="inline"
                                      onsubmit="return confirm('Cancel this service request?');">
                                    @csrf
                                    <input type="hidden" name="status" value="cancelled">
                                    <button type="submit" class="px-2 py-1 bg-red-50 text-red-700 border border-red-200 rounded text-xs hover:bg-red-100">Cancel</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $isStaff ? 7 : 6 }}" class="px-4 py-10 text-center text-gray-500">
                            No service requests yet.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $requests->links() }}</div>
</div>
@endsection
