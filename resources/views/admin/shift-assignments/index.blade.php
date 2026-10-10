@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Guard Roster</h1>
            <p class="text-gray-600 mt-1">Who is assigned to which gate, on which shift.</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.gates.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Manage Gates</a>
            <a href="{{ route('admin.shifts.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Manage Shifts</a>
            <a href="{{ route('admin.shift-assignments.create') }}" class="bg-blue-600 text-white px-5 py-2.5 rounded-lg font-medium hover:bg-blue-700">
                <i class="fas fa-plus mr-1"></i> Assign Guard
            </a>
        </div>
    </div>


    <form method="GET" class="bg-white rounded-lg shadow p-4 mb-6 grid grid-cols-2 md:grid-cols-5 gap-3">
        <input type="date" name="date" value="{{ request('date', \App\Support\SocietyClock::today(auth()->user()->society_id)->toDateString()) }}"
            class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
        <select name="gate_id" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
            <option value="">All Gates</option>
            @foreach ($gates as $gate)
                <option value="{{ $gate->id }}" @selected(request('gate_id') == $gate->id)>{{ $gate->name }}</option>
            @endforeach
        </select>
        <select name="guard_user_id" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
            <option value="">All Guards</option>
            @foreach ($guards as $guard)
                <option value="{{ $guard->id }}" @selected(request('guard_user_id') == $guard->id)>{{ $guard->name }}</option>
            @endforeach
        </select>
        <select name="status" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
            <option value="">Any Status</option>
            @foreach (['scheduled', 'present', 'late', 'checked_out', 'absent', 'missed', 'replaced'] as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
            @endforeach
        </select>
        <button type="submit" class="bg-gray-100 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-200">Filter</button>
    </form>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        @if ($assignments->isEmpty())
            <div class="px-6 py-10 text-center text-gray-500">No assignments match these filters.</div>
        @else
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-gray-500">
                    <tr>
                        <th class="px-6 py-3">Date</th>
                        <th class="px-6 py-3">Guard</th>
                        <th class="px-6 py-3">Shift</th>
                        <th class="px-6 py-3">Gate</th>
                        <th class="px-6 py-3">Status</th>
                        <th class="px-6 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($assignments as $a)
                        <tr>
                            <td class="px-6 py-3">{{ $a->date->format('d M Y') }}</td>
                            <td class="px-6 py-3 font-medium text-gray-900">{{ $a->guardUser->name }}</td>
                            <td class="px-6 py-3">{{ $a->shift->name }}</td>
                            <td class="px-6 py-3">{{ $a->gate->name }}</td>
                            <td class="px-6 py-3">
                                <span class="px-2 py-0.5 rounded text-xs font-medium
                                    {{ in_array($a->status, ['present','checked_out']) ? 'bg-green-100 text-green-700' : ($a->status === 'late' ? 'bg-amber-100 text-amber-700' : (in_array($a->status, ['absent','missed']) ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-600')) }}">
                                    {{ ucfirst($a->status) }}
                                </span>
                            </td>
                            <td class="px-6 py-3 text-right">
                                @if ($a->status === 'scheduled' && !$a->checked_in_at)
                                    <div class="inline-flex items-center gap-3 mr-3">
                                        @if ($a->shift->windowStartingOn($a->date)[0]->lte(\App\Support\SocietyClock::now($a->society_id)))
                                            <form method="POST" action="{{ route('admin.shift-assignments.absent', $a) }}"
                                                onsubmit="return confirm('Mark this guard absent for this shift?');">
                                                @csrf
                                                <button type="submit" class="text-xs text-amber-700 hover:text-amber-900">Mark absent</button>
                                            </form>
                                        @endif
                                        <form method="POST" action="{{ route('admin.shift-assignments.destroy', $a) }}"
                                            onsubmit="return confirm('Remove this assignment from the roster?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-xs text-gray-500 hover:text-red-700">Remove</button>
                                        </form>
                                    </div>
                                @endif
                                @if ($a->isOpen())
                                    <form method="POST" action="{{ route('admin.shift-assignments.replace', $a) }}" class="inline-flex items-center gap-1"
                                        onsubmit="return confirm('Replace this guard for this shift?');">
                                        @csrf
                                        <select name="replacement_guard_id" required class="text-xs border border-gray-300 rounded px-1 py-1">
                                            <option value="">Replace with...</option>
                                            @foreach ($guards as $guard)
                                                @if ($guard->id !== $a->guard_user_id)
                                                    <option value="{{ $guard->id }}">{{ $guard->name }}</option>
                                                @endif
                                            @endforeach
                                        </select>
                                        <input type="text" name="reason" placeholder="Reason" required class="text-xs border border-gray-300 rounded px-1 py-1 w-24">
                                        <button type="submit" class="text-xs text-red-600 hover:text-red-800">Replace</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="px-6 py-4">{{ $assignments->links() }}</div>
        @endif
    </div>
</div>
@endsection
