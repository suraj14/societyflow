{{-- Message Templates -> Email. Email keeps its own editor (subject, body, variables); this lists the templates and links to it. --}}
@php
    $sfInput = 'px-3 py-2 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-purple-500';
@endphp

<form method="GET" action="{{ route('admin.message-templates.index') }}" class="flex flex-col sm:flex-row sm:items-center gap-2" role="search">
    <input type="hidden" name="channel" value="email">
    <label for="mt-email-q" class="sr-only">Search templates</label>
    <input id="mt-email-q" type="search" name="q" value="{{ $filters['q'] }}" placeholder="Search name or subject" class="{{ $sfInput }} sm:w-64">
    <button type="submit" class="px-4 py-2 rounded-xl border border-gray-300 text-gray-700 text-sm font-semibold hover:bg-gray-50">Search</button>
    @if ($filters['q'] !== '')
        <a href="{{ route('admin.message-templates.index', ['channel' => 'email']) }}" class="text-sm text-purple-700 hover:underline">Clear</a>
    @endif
    <a href="{{ route('admin.email-template-mappings.index') }}" class="sm:ml-auto text-sm font-semibold text-purple-700 hover:underline">Notification mappings <i class="fas fa-arrow-right text-xs"></i></a>
</form>

@if ($emailTemplates->isEmpty())
    <div class="bg-white rounded-2xl border border-gray-200 px-6 py-12 text-center">
        <p class="text-gray-600">{{ $filters['q'] !== '' ? 'No email templates match this search.' : 'No email templates yet.' }}</p>
    </div>
@else
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4" id="email-templates">
        @foreach ($emailTemplates as $e)
            @php $active = $e->status === 'active'; @endphp
            <article class="bg-white rounded-2xl border border-gray-200 p-5 flex flex-col gap-3 {{ $active ? '' : 'opacity-80' }}" data-template-id="{{ $e->id }}">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h3 class="font-semibold text-gray-900 break-words">{{ $e->name }}</h3>
                        <div class="mt-1.5 flex flex-wrap gap-1.5 text-xs font-semibold">
                            <span class="px-2 py-0.5 rounded-full bg-blue-50 text-blue-700">{{ $e->trigger_event ? ($eventLabels[$e->trigger_event] ?? $e->trigger_event) : 'Not mapped to a notification' }}</span>
                            <span class="px-2 py-0.5 rounded-full {{ $e->society_id ? 'bg-indigo-50 text-indigo-700' : 'bg-gray-100 text-gray-600' }}">{{ $e->society_id ? 'Your template' : 'System template' }}</span>
                        </div>
                    </div>
                    <span class="shrink-0 inline-flex items-center gap-1.5 text-xs font-semibold {{ $active ? 'text-green-700' : 'text-gray-500' }}">
                        <span class="w-1.5 h-1.5 rounded-full {{ $active ? 'bg-green-500' : 'bg-gray-400' }}"></span>{{ $active ? 'Active' : 'Inactive' }}
                    </span>
                </div>

                <p class="text-sm text-gray-800 bg-gray-50 rounded-xl px-3 py-2.5 break-words"><span class="text-xs uppercase tracking-wide text-gray-400 block">Subject</span>{{ $e->subject }}</p>

                <div class="flex flex-wrap items-center justify-between gap-2 text-xs text-gray-500">
                    <span>{{ count((array) $e->variables) }} {{ \Illuminate\Support\Str::plural('variable', count((array) $e->variables)) }}</span>
                    <a href="{{ $e->society_id ? route('admin.email-templates.edit', $e) : route('admin.email-templates.show', $e) }}" class="font-semibold text-purple-700 hover:underline">{{ $e->society_id ? 'Edit' : 'View' }}</a>
                </div>
            </article>
        @endforeach
    </div>
@endif
