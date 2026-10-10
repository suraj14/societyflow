@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8 max-w-xl">
    <h1 class="text-2xl font-bold text-gray-900 mb-6">Edit Shift</h1>

    <form method="POST" action="{{ route('admin.shifts.update', $shift) }}" class="bg-white rounded-lg shadow p-6 space-y-5">
        @csrf
        @method('PUT')
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
            <input type="text" name="name" required value="{{ old('name', $shift->name) }}"
                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
            @error('name') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Start Time</label>
                <input type="time" name="start_time" required value="{{ old('start_time', \Illuminate\Support\Carbon::parse($shift->start_time)->format('H:i')) }}"
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">End Time</label>
                <input type="time" name="end_time" required value="{{ old('end_time', \Illuminate\Support\Carbon::parse($shift->end_time)->format('H:i')) }}"
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
            </div>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Grace Period (minutes)</label>
            <input type="number" name="grace_period_minutes" min="0" max="120" value="{{ old('grace_period_minutes', $shift->grace_period_minutes) }}"
                class="w-full md:w-40 px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
        </div>
        <label class="flex items-center gap-2">
            <input type="checkbox" name="is_active" value="1" @checked($shift->is_active) class="rounded border-gray-300 text-blue-600">
            <span class="text-sm text-gray-700">Active</span>
        </label>
        <div class="flex justify-end gap-3">
            <a href="{{ route('admin.shifts.index') }}" class="px-5 py-2.5 text-gray-600">Cancel</a>
            <button type="submit" class="bg-blue-600 text-white px-6 py-2.5 rounded-lg font-medium hover:bg-blue-700">Save</button>
        </div>
    </form>

    <form method="POST" action="{{ route('admin.shifts.destroy', $shift) }}" class="mt-4" onsubmit="return confirm('Delete this shift?');">
        @csrf
        @method('DELETE')
        <button type="submit" class="text-red-600 text-sm hover:text-red-800">Delete Shift</button>
    </form>
</div>
@endsection
