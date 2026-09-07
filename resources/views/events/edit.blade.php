@extends('layouts.app')

@section('title', 'Edit Event')
@section('page-title', 'Edit Event')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="bg-white rounded-lg shadow">
        <!-- Header -->
        <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
            <h2 class="text-xl font-semibold text-gray-900">Edit Event</h2>
            <a href="{{ route('events.index') }}" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-xl"></i>
            </a>
        </div>

        <!-- Form -->
        <form method="POST" action="{{ route('events.update', $event) }}" class="p-6 space-y-6">
            @csrf
            @method('PUT')

            <!-- Event Name -->
            <div>
                <label for="event_name" class="block text-sm font-medium text-gray-700 mb-2">Event Name *</label>
                <input type="text" id="event_name" name="event_name" value="{{ old('event_name', $event->event_name) }}" 
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent @error('event_name') border-red-500 @enderror"
                       placeholder="Enter event name" required>
                @error('event_name')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Location -->
            <div>
                <label for="location" class="block text-sm font-medium text-gray-700 mb-2">Location *</label>
                <input type="text" id="location" name="location" value="{{ old('location', $event->location) }}" 
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent @error('location') border-red-500 @enderror"
                       placeholder="Enter event location" required>
                @error('location')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Description -->
            <div>
                <label for="description" class="block text-sm font-medium text-gray-700 mb-2">Description *</label>
                <textarea id="description" name="description" rows="4" 
                          class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent @error('description') border-red-500 @enderror"
                          placeholder="Enter event description" required>{{ old('description', $event->description) }}</textarea>
                @error('description')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Start Date and Time -->
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="start_date" class="block text-sm font-medium text-gray-700 mb-2">Start Date *</label>
                    <input type="date" id="start_date" name="start_date" value="{{ old('start_date', $event->start_date->format('Y-m-d')) }}" 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent @error('start_date') border-red-500 @enderror"
                           required>
                    @error('start_date')
                        <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="start_time" class="block text-sm font-medium text-gray-700 mb-2">Start Time *</label>
                    <input type="time" id="start_time" name="start_time" value="{{ old('start_time', $event->start_date->format('H:i')) }}" 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent @error('start_time') border-red-500 @enderror"
                           required>
                    @error('start_time')
                        <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <!-- End Date and Time -->
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="end_date" class="block text-sm font-medium text-gray-700 mb-2">End Date *</label>
                    <input type="date" id="end_date" name="end_date" value="{{ old('end_date', $event->end_date->format('Y-m-d')) }}" 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent @error('end_date') border-red-500 @enderror"
                           required>
                    @error('end_date')
                        <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="end_time" class="block text-sm font-medium text-gray-700 mb-2">End Time *</label>
                    <input type="time" id="end_time" name="end_time" value="{{ old('end_time', $event->end_date->format('H:i')) }}" 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent @error('end_time') border-red-500 @enderror"
                           required>
                    @error('end_time')
                        <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <!-- Status -->
            <div>
                <label for="status" class="block text-sm font-medium text-gray-700 mb-2">Status *</label>
                <select id="status" name="status" 
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent @error('status') border-red-500 @enderror"
                        required @if(!auth()->user()->hasRole('Admin')) disabled @endif>
                    <option value="pending" {{ old('status', $event->status) === 'pending' ? 'selected' : '' }}>Pending</option>
                    @if(auth()->user()->hasRole('Admin'))
                        @php
                            $canMarkCompleted = $event->end_date <= now();
                        @endphp
                        <option value="completed" {{ old('status', $event->status) === 'completed' ? 'selected' : '' }} @if(!$canMarkCompleted) disabled @endif>
                            Completed @if(!$canMarkCompleted)(Available after {{ $event->end_date->format('M d, Y H:i') }})@endif
                        </option>
                        <option value="cancelled" {{ old('status', $event->status) === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                    @endif
                </select>
                @if(!auth()->user()->hasRole('Admin'))
                    <input type="hidden" name="status" value="{{ $event->status }}">
                    <p class="text-sm text-gray-500 mt-1">Only Admin can change status</p>
                @else
                    @php
                        $canMarkCompleted = $event->end_date <= now();
                    @endphp
                    @if(!$canMarkCompleted)
                        <p class="text-sm text-blue-600 mt-2">
                            <i class="fas fa-info-circle mr-1"></i>
                            Event can be marked as "Completed" after {{ $event->end_date->format('M d, Y H:i') }}
                        </p>
                    @endif
                @endif
                @error('status')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Visibility Type -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-3">Visibility Type *</label>
                <div class="space-y-2">
                    <label class="flex items-center">
                        <input type="radio" name="is_role_based" value="1" {{ old('is_role_based', $event->is_role_based) == 1 ? 'checked' : '' }} 
                               class="rounded border-gray-300 text-purple-600 focus:ring-purple-500" onchange="updateVisibilityFields()">
                        <span class="ml-3 text-sm text-gray-700">Role Based</span>
                    </label>
                    <label class="flex items-center">
                        <input type="radio" name="is_role_based" value="0" {{ old('is_role_based', $event->is_role_based) == 0 ? 'checked' : '' }} 
                               class="rounded border-gray-300 text-purple-600 focus:ring-purple-500" onchange="updateVisibilityFields()">
                        <span class="ml-3 text-sm text-gray-700">User Based</span>
                    </label>
                </div>
            </div>

            <!-- Visible Roles -->
            <div id="rolesField" class="hidden">
                <label for="visible_roles" class="block text-sm font-medium text-gray-700 mb-2">Select Roles</label>
                <div class="space-y-2 max-h-48 overflow-y-auto border border-gray-300 rounded-lg p-3">
                    @foreach($roles as $role)
                        <label class="flex items-center">
                            <input type="checkbox" name="visible_roles[]" value="{{ $role->name }}" 
                                   {{ in_array($role->name, old('visible_roles', $event->visible_roles ?? [])) ? 'checked' : '' }}
                                   class="rounded border-gray-300 text-purple-600 focus:ring-purple-500">
                            <span class="ml-3 text-sm text-gray-700">{{ $role->name }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <!-- Visible Users -->
            <div id="usersField" class="hidden">
                <label for="visible_users" class="block text-sm font-medium text-gray-700 mb-2">Select Users</label>
                <div class="space-y-2 max-h-48 overflow-y-auto border border-gray-300 rounded-lg p-3">
                    @foreach($users as $user)
                        <label class="flex items-center">
                            <input type="checkbox" name="visible_users[]" value="{{ $user->id }}" 
                                   {{ in_array($user->id, old('visible_users', $event->visible_users ?? [])) ? 'checked' : '' }}
                                   class="rounded border-gray-300 text-purple-600 focus:ring-purple-500">
                            <span class="ml-3 text-sm text-gray-700">{{ $user->name }} ({{ $user->email }})</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <!-- Form Actions -->
            <div class="flex gap-3 pt-6 border-t border-gray-200">
                <button type="submit" class="flex-1 px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors font-medium">
                    <i class="fas fa-save mr-2"></i>
                    Update Event
                </button>
                <a href="{{ route('events.index') }}" class="flex-1 px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 transition-colors font-medium text-center">
                    <i class="fas fa-times mr-2"></i>
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
function updateVisibilityFields() {
    const isRoleBased = document.querySelector('input[name="is_role_based"]:checked').value === '1';
    document.getElementById('rolesField').classList.toggle('hidden', !isRoleBased);
    document.getElementById('usersField').classList.toggle('hidden', isRoleBased);
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', updateVisibilityFields);
</script>
@endpush
@endsection
