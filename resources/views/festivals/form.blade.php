@extends('layouts.app')

@section('title', $festival->exists ? 'Edit Festival' : 'New Festival')
@section('page-title', $festival->exists ? 'Edit Festival' : 'New Festival')

@section('content')
@php
    $field = 'w-full px-3 py-2 border border-gray-300 rounded-lg text-sm';
    $v = fn ($k, $d = '') => old($k, $festival->{$k} ?? $d);
@endphp
<div class="container mx-auto px-4 py-6 max-w-3xl">
    <h1 class="text-2xl font-bold text-gray-900 mb-1">{{ $festival->exists ? 'Edit ' . $festival->name : 'New Festival or Celebration' }}</h1>
    <p class="text-gray-600 mb-6">Contributions are voluntary. Any budget or goal here is a plan, never an amount residents owe.</p>

    @if($errors->any())
        <div class="mb-4 p-3 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm">
            <ul class="list-disc ml-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ $festival->exists ? route('festivals.update', $festival) : route('festivals.store') }}" class="bg-white rounded-lg shadow-md p-5 space-y-4">
        @csrf
        @if($festival->exists) @method('PUT') @endif
        <div><label class="block text-sm font-medium text-gray-700 mb-1">Name *</label><input name="name" required value="{{ $v('name') }}" class="{{ $field }}" placeholder="Navratri Celebration"></div>
        <div><label class="block text-sm font-medium text-gray-700 mb-1">Description</label><textarea name="description" rows="3" class="{{ $field }}">{{ $v('description') }}</textarea></div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Date *</label><input type="date" name="festival_date" required value="{{ old('festival_date', optional($festival->festival_date)->format('Y-m-d')) }}" class="{{ $field }}"></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Last day (if more than one)</label><input type="date" name="end_date" value="{{ old('end_date', optional($festival->end_date)->format('Y-m-d')) }}" class="{{ $field }}"></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Venue</label><input name="venue" value="{{ $v('venue') }}" class="{{ $field }}"></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Expected attendance</label><input type="number" min="0" name="expected_attendance" value="{{ $v('expected_attendance') }}" class="{{ $field }}"></div>
            <div class="sm:col-span-2"><label class="block text-sm font-medium text-gray-700 mb-1">Organizer</label>
                <select name="organizer_id" class="{{ $field }}"><option value="">None</option>
                    @foreach($users as $u)<option value="{{ $u->id }}" {{ (string) $v('organizer_id') === (string) $u->id ? 'selected' : '' }}>{{ $u->name }}</option>@endforeach
                </select></div>
        </div>

        <h2 class="font-semibold text-gray-900 pt-2">Budget &amp; goal (editable at any time)</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Estimated budget (₹)</label><input type="number" step="0.01" min="0" name="estimated_budget" value="{{ $v('estimated_budget') }}" class="{{ $field }}"></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Approved budget (₹)</label><input type="number" step="0.01" min="0" name="approved_budget" value="{{ $v('approved_budget') }}" class="{{ $field }}"></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Voluntary contribution goal (₹)</label><input type="number" step="0.01" min="0" name="contribution_target" value="{{ $v('contribution_target') }}" class="{{ $field }}"><p class="text-xs text-gray-500 mt-1">A hope, not a due. No one is charged or chased for it.</p></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Committee approval limit (₹)</label><input type="number" step="0.01" min="0" name="approval_limit" value="{{ $v('approval_limit') }}" class="{{ $field }}"><p class="text-xs text-gray-500 mt-1">Committee members may approve up to this; above it (or if blank) only an Admin can.</p></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Verified opening balance (₹)</label><input type="number" step="0.01" min="0" name="opening_balance" value="{{ $v('opening_balance', 0) }}" class="{{ $field }}"></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Where the opening balance comes from</label><input name="opening_balance_note" value="{{ $v('opening_balance_note') }}" class="{{ $field }}" placeholder="Required if there is an opening balance"></div>
        </div>
        <div><label class="block text-sm font-medium text-gray-700 mb-1">Arrangements &amp; timings</label><textarea name="arrangements" rows="3" class="{{ $field }}">{{ $v('arrangements') }}</textarea></div>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="separate_approver" value="1" {{ old('separate_approver', $festival->separate_approver ?? true) ? 'checked' : '' }}> The person who entered an expense cannot approve it</label>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="allow_anonymous" value="1" {{ old('allow_anonymous', $festival->allow_anonymous) ? 'checked' : '' }}> Allow anonymous contributions (the office still sees who gave)</label>

        <div class="flex gap-3 pt-2">
            <button class="bg-purple-700 hover:bg-purple-800 text-white px-5 py-2.5 rounded-lg font-medium">{{ $festival->exists ? 'Save changes' : 'Create draft' }}</button>
            <a href="{{ $festival->exists ? route('festivals.show', $festival) : route('festivals.index') }}" class="px-5 py-2.5 border border-gray-300 rounded-lg text-gray-700">Cancel</a>
        </div>
    </form>
</div>
@endsection