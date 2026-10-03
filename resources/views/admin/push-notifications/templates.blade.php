@extends('layouts.app')

@section('title', 'Push Notification Templates')

@section('content')
<div class="min-h-screen bg-gradient-to-br from-slate-50 to-blue-50 py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-8 flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 flex items-center">
                    <div class="w-10 h-10 bg-gradient-to-r from-purple-500 to-pink-600 rounded-lg flex items-center justify-center mr-3">
                        <i class="fas fa-file-alt text-white"></i>
                    </div>
                    Push Notification Templates
                </h1>
                <p class="text-gray-600 mt-2">Title and body sent for each trigger. Global templates apply to every society unless overridden.</p>
            </div>
            <a href="{{ route('admin.push-notifications.settings') }}"
               class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-gray-700 bg-white hover:bg-gray-50">
                <i class="fas fa-cog mr-2"></i>Settings
            </a>
        </div>


        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            @if($templates->count() > 0)
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Trigger</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Body</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Scope</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($templates as $template)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <code class="text-sm text-purple-700 bg-purple-50 px-2 py-1 rounded">{{ $template->trigger_action }}</code>
                                    </td>
                                    <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ $template->title }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-600 max-w-md">{{ Str::limit($template->body, 90) }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        @if($template->society_id)
                                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                                                {{ $template->society->name ?? 'Society #' . $template->society_id }}
                                            </span>
                                        @else
                                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-700">Global</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if($template->is_active)
                                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Active</span>
                                        @else
                                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-600">Inactive</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                        <a href="{{ route('admin.push-notifications.templates.edit', $template) }}"
                                           class="inline-flex items-center px-3 py-1.5 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                                            <i class="fas fa-edit mr-2"></i>Edit
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if($templates->hasPages())
                    <div class="px-6 py-4 border-t border-gray-200">
                        {{ $templates->links() }}
                    </div>
                @endif
            @else
                <div class="p-12 text-center">
                    <div class="inline-flex items-center justify-center w-16 h-16 bg-gray-100 rounded-full mb-4">
                        <i class="fas fa-file-alt text-gray-400 text-2xl"></i>
                    </div>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">No push templates yet</h3>
                    <p class="text-gray-500">Templates are created when push notifications are configured for a society.</p>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
