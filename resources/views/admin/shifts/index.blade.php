@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Shifts</h1>
            <p class="text-gray-600 mt-1">Duty timings guards are rostered against.</p>
        </div>
        <a href="{{ route('admin.shifts.create') }}" class="bg-blue-600 text-white px-5 py-2.5 rounded-lg font-medium hover:bg-blue-700">
            <i class="fas fa-plus mr-1"></i> Add Shift
        </a>
    </div>


    <div class="bg-white rounded-lg shadow overflow-hidden">
        @if ($shifts->isEmpty())
            <div class="px-6 py-10 text-center text-gray-500">
                No shifts yet. Add a Morning, Evening or Night shift to get started.
            </div>
        @else
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-gray-500">
                    <tr>
                        <th class="px-6 py-3">Name</th>
                        <th class="px-6 py-3">Timing</th>
                        <th class="px-6 py-3">Grace</th>
                        <th class="px-6 py-3">Status</th>
                        <th class="px-6 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($shifts as $shift)
                        <tr>
                            <td class="px-6 py-3 font-medium text-gray-900">{{ $shift->name }}</td>
                            <td class="px-6 py-3 text-gray-600">
                                {{ \Illuminate\Support\Carbon::parse($shift->start_time)->format('h:i A') }}
                                – {{ \Illuminate\Support\Carbon::parse($shift->end_time)->format('h:i A') }}
                                @if ($shift->isOvernight())
                                    <span class="text-xs text-amber-600">(overnight)</span>
                                @endif
                            </td>
                            <td class="px-6 py-3 text-gray-600">{{ $shift->grace_period_minutes }} min</td>
                            <td class="px-6 py-3">
                                <span class="px-2 py-0.5 rounded text-xs font-medium {{ $shift->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }}">
                                    {{ $shift->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-6 py-3 text-right">
                                <a href="{{ route('admin.shifts.edit', $shift) }}" class="text-blue-600 hover:text-blue-800 text-xs font-medium">Edit</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
@endsection
