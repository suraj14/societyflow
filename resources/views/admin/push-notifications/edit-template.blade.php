@extends('layouts.app')

@section('title', 'Edit Push Template')

@section('content')
<div class="min-h-screen bg-gradient-to-br from-slate-50 to-blue-50 py-8">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="mb-6">
            <a href="{{ route('admin.push-notifications.templates') }}" class="text-blue-600 hover:text-blue-800 text-sm">
                <i class="fas fa-arrow-left mr-1"></i> Back to Templates
            </a>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 bg-gradient-to-r from-purple-50 to-pink-50 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900 flex items-center">
                    <i class="fas fa-edit text-purple-600 mr-2"></i>
                    Edit Template
                </h2>
                <p class="text-sm text-gray-600 mt-1">
                    Trigger: <code class="text-purple-700 bg-purple-100 px-2 py-0.5 rounded">{{ $template->trigger_action }}</code>
                    · Scope: {{ $template->society_id ? ($template->society->name ?? 'Society #' . $template->society_id) : 'Global' }}
                </p>
            </div>

            <form action="{{ route('admin.push-notifications.templates.update', $template) }}" method="POST" class="p-6">
                @csrf
                @method('PUT')

                @if($errors->any())
                    <div class="mb-6 bg-red-50 border-l-4 border-red-500 p-4 rounded-r-lg">
                        <ul class="text-sm text-red-700 list-disc list-inside">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="space-y-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Title *</label>
                        <input type="text" name="title" value="{{ old('title', $template->title) }}" required maxlength="255"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Body *</label>
                        <textarea name="body" rows="4" required
                                  class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">{{ old('body', $template->body) }}</textarea>
                        <p class="text-xs text-gray-500 mt-1">
                            Placeholders like <code>@{{visitor_name}}</code> or <code>@{{society_name}}</code> are replaced when the notification is sent.
                        </p>
                    </div>

                    <div class="grid md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Icon URL</label>
                            <input type="text" name="icon" value="{{ old('icon', $template->icon) }}"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Badge URL</label>
                            <input type="text" name="badge" value="{{ old('badge', $template->badge) }}"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Click action (URL opened on tap)</label>
                        <input type="text" name="click_action" value="{{ old('click_action', $template->click_action) }}"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                    </div>

                    <div>
                        {{-- Unchecked checkboxes are not submitted; the hidden 0 satisfies
                             the controller's `required|boolean` rule and the checkbox
                             overrides it with 1 when ticked. --}}
                        <input type="hidden" name="is_active" value="0">
                        <label class="flex items-center cursor-pointer">
                            <input type="checkbox" name="is_active" value="1" {{ old('is_active', $template->is_active) ? 'checked' : '' }}
                                   class="w-5 h-5 text-purple-600 rounded focus:ring-2 focus:ring-purple-500">
                            <span class="ml-3 text-gray-700 font-medium">Active</span>
                        </label>
                        <p class="text-sm text-gray-500 mt-1 ml-8">Inactive templates are skipped; no notification is sent for the trigger.</p>
                    </div>
                </div>

                <div class="flex justify-end space-x-3 border-t border-gray-200 mt-8 pt-6">
                    <a href="{{ route('admin.push-notifications.templates') }}"
                       class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">Cancel</a>
                    <button type="submit"
                            class="px-4 py-2 text-sm font-medium text-white bg-purple-600 border border-transparent rounded-lg hover:bg-purple-700">
                        <i class="fas fa-save mr-2"></i>Save Template
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
