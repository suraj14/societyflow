@extends('layouts.app')

@section('content')
@php $field = 'w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-blue-500'; @endphp
<div class="container mx-auto px-4 py-8 max-w-xl">
    <h1 class="text-2xl font-bold text-gray-900 mb-6">Assign Guard to a Shift</h1>

    @if ($errors->any())
        <div class="mb-6 rounded-lg bg-red-50 border border-red-200 text-red-800 px-4 py-3" role="alert">
            <p class="font-medium">The guard was not assigned. Please fix:</p>
            <ul class="list-disc ml-5 mt-1 text-sm">@foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.shift-assignments.store') }}" class="bg-white rounded-lg shadow p-6 space-y-5">
        @csrf
        <div>
            <label for="sa-guard" class="block text-sm font-medium text-gray-700 mb-1">Guard</label>
            <select id="sa-guard" name="guard_user_id" required class="{{ $field }} {{ $errors->has('guard_user_id') ? 'border-red-400' : 'border-gray-300' }}">
                <option value="">Select a guard</option>
                @foreach ($guards as $guard)
                    <option value="{{ $guard->id }}" @selected(old('guard_user_id') == $guard->id)>{{ $guard->name }}</option>
                @endforeach
            </select>
            @error('guard_user_id')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
            @if ($guards->isEmpty())<p class="text-sm text-amber-700 mt-1">There is no active guard in this society yet.</p>@endif
        </div>
        <div>
            <label for="sa-shift" class="block text-sm font-medium text-gray-700 mb-1">Shift</label>
            <select id="sa-shift" name="shift_id" required class="{{ $field }} {{ $errors->has('shift_id') ? 'border-red-400' : 'border-gray-300' }}">
                <option value="">Select a shift</option>
                @foreach ($shifts as $shift)
                    <option value="{{ $shift->id }}" @selected(old('shift_id') == $shift->id)>
                        {{ $shift->name }} ({{ \Illuminate\Support\Carbon::parse($shift->start_time)->format('h:i A') }}–{{ \Illuminate\Support\Carbon::parse($shift->end_time)->format('h:i A') }})
                    </option>
                @endforeach
            </select>
            @error('shift_id')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
            @if ($shifts->isEmpty())<p class="text-sm text-amber-700 mt-1">There is no active shift yet. <a href="{{ route('admin.shifts.create') }}" class="underline">Create one</a>.</p>@endif
        </div>
        <div>
            <label for="sa-gate" class="block text-sm font-medium text-gray-700 mb-1">Gate</label>
            <select id="sa-gate" name="gate_id" required class="{{ $field }} {{ $errors->has('gate_id') ? 'border-red-400' : 'border-gray-300' }}">
                <option value="">Select a gate</option>
                @foreach ($gates as $gate)
                    <option value="{{ $gate->id }}" @selected(old('gate_id') == $gate->id)>{{ $gate->name }}</option>
                @endforeach
            </select>
            @error('gate_id')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
            @if ($gates->isEmpty())<p class="text-sm text-amber-700 mt-1">There is no active gate yet. <a href="{{ route('admin.gates.create') }}" class="underline">Create one</a>.</p>@endif
        </div>
        <div>
            <label for="sa-date" class="block text-sm font-medium text-gray-700 mb-1">Date</label>
            <input id="sa-date" type="date" name="date" required value="{{ old('date') }}"
                class="{{ $field }} {{ $errors->has('date') ? 'border-red-400' : 'border-gray-300' }}">
            @error('date')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
            <p class="text-xs text-gray-500 mt-1">Today or a later date. An overnight shift belongs to the day it starts.</p>
        </div>
        <div class="flex justify-end gap-3">
            <a href="{{ route('admin.shift-assignments.index') }}" class="px-5 py-2.5 text-gray-600">Cancel</a>
            <button type="submit" class="bg-blue-600 text-white px-6 py-2.5 rounded-lg font-medium hover:bg-blue-700">Assign</button>
        </div>
    </form>
</div>
@endsection
