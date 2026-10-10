@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8 max-w-xl">
    <h1 class="text-2xl font-bold text-gray-900 mb-6">Edit Gate</h1>

    <form method="POST" action="{{ route('admin.gates.update', $gate) }}" class="bg-white rounded-lg shadow p-6 space-y-5">
        @csrf
        @method('PUT')
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
            <input type="text" name="name" required value="{{ old('name', $gate->name) }}"
                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
            @error('name') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
            <select name="type" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                @foreach (\App\Models\Gate::TYPES as $type)
                    <option value="{{ $type }}" @selected(old('type', $gate->type) === $type)>{{ ucfirst($type) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Description (optional)</label>
            <textarea name="description" rows="2" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">{{ old('description', $gate->description) }}</textarea>
        </div>
        <label class="flex items-center gap-2">
            <input type="checkbox" name="is_active" value="1" @checked($gate->is_active) class="rounded border-gray-300 text-blue-600">
            <span class="text-sm text-gray-700">Active</span>
        </label>
        <div class="flex justify-end gap-3">
            <a href="{{ route('admin.gates.index') }}" class="px-5 py-2.5 text-gray-600">Cancel</a>
            <button type="submit" class="bg-blue-600 text-white px-6 py-2.5 rounded-lg font-medium hover:bg-blue-700">Save</button>
        </div>
    </form>

    <form method="POST" action="{{ route('admin.gates.destroy', $gate) }}" class="mt-4" onsubmit="return confirm('Delete this gate?');">
        @csrf
        @method('DELETE')
        <button type="submit" class="text-red-600 text-sm hover:text-red-800">Delete Gate</button>
    </form>
</div>
@endsection
