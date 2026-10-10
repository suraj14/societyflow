@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8 max-w-xl">
    <h1 class="text-2xl font-bold text-gray-900 mb-6">Assign Guard to a Shift</h1>

    @if ($errors->any())
        <div class="mb-6 rounded-lg bg-red-50 border border-red-200 text-red-800 px-4 py-3">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('admin.shift-assignments.store') }}" class="bg-white rounded-lg shadow p-6 space-y-5">
        @csrf
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Guard</label>
            <select name="guard_user_id" required class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                <option value="">Select a guard</option>
                @foreach ($guards as $guard)
                    <option value="{{ $guard->id }}" @selected(old('guard_user_id') == $guard->id)>{{ $guard->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Shift</label>
            <select name="shift_id" required class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                <option value="">Select a shift</option>
                @foreach ($shifts as $shift)
                    <option value="{{ $shift->id }}" @selected(old('shift_id') == $shift->id)>
                        {{ $shift->name }} ({{ \Illuminate\Support\Carbon::parse($shift->start_time)->format('h:i A') }}–{{ \Illuminate\Support\Carbon::parse($shift->end_time)->format('h:i A') }})
                    </option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Gate</label>
            <select name="gate_id" required class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                <option value="">Select a gate</option>
                @foreach ($gates as $gate)
                    <option value="{{ $gate->id }}" @selected(old('gate_id') == $gate->id)>{{ $gate->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Date</label>
            <input type="date" name="date" required value="{{ old('date') }}"
                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
        </div>
        <div class="flex justify-end gap-3">
            <a href="{{ route('admin.shift-assignments.index') }}" class="px-5 py-2.5 text-gray-600">Cancel</a>
            <button type="submit" class="bg-blue-600 text-white px-6 py-2.5 rounded-lg font-medium hover:bg-blue-700">Assign</button>
        </div>
    </form>
</div>
@endsection
