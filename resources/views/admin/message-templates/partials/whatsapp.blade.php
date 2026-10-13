{{-- Message Templates -> WhatsApp: templates typed in by hand (copied from the provider portal), one card each. Nothing is
     fetched from the provider here. --}}
@php
    $sfInput = 'px-3 py-2 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-purple-500';
    $hasFilter = $filters['q'] !== '' || $filters['purpose'] !== 'all' || $filters['status'] !== 'all';
@endphp

{{-- Filters --}}
<form method="GET" action="{{ route('admin.message-templates.index') }}" class="flex flex-col sm:flex-row sm:items-center gap-2" role="search">
    <input type="hidden" name="channel" value="whatsapp">
    <label for="mt-q" class="sr-only">Search templates</label>
    <input id="mt-q" type="search" name="q" value="{{ $filters['q'] }}" placeholder="Search name or text" class="{{ $sfInput }} sm:w-64">
    <label for="mt-purpose" class="sr-only">Type</label>
    <select id="mt-purpose" name="purpose" class="{{ $sfInput }}" onchange="this.form.submit()">
        <option value="all">All types</option>
        @foreach ($purposeLabels as $key => $label)
            <option value="{{ $key }}" @selected($filters['purpose'] === $key)>{{ $label }}</option>
        @endforeach
    </select>
    <label for="mt-status" class="sr-only">Status</label>
    <select id="mt-status" name="status" class="{{ $sfInput }}" onchange="this.form.submit()">
        <option value="all">Active and inactive</option>
        <option value="active" @selected($filters['status'] === 'active')>Active</option>
        <option value="inactive" @selected($filters['status'] === 'inactive')>Inactive</option>
    </select>
    <button type="submit" class="px-4 py-2 rounded-xl border border-gray-300 text-gray-700 text-sm font-semibold hover:bg-gray-50">Search</button>
    @if ($hasFilter)
        <a href="{{ route('admin.message-templates.index', ['channel' => 'whatsapp']) }}" class="text-sm text-purple-700 hover:underline">Clear</a>
    @endif
</form>

<p class="text-sm text-gray-600">Copy an approved template from your Fast2SMS WhatsApp account and paste it into <strong>New Template</strong>. The wording must match the approved template exactly.</p>

{{-- One short line for every notification that has no active template --}}
@if ($unmappedWhatsApp)
    <p class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-2.5 text-sm text-amber-900" role="status">
        <i class="fas fa-triangle-exclamation mr-1"></i> No active WhatsApp template for: {{ implode(', ', $unmappedWhatsApp) }}. Those notifications are not sent on WhatsApp.
    </p>
@endif

@if ($templates->isEmpty())
    <div class="bg-white rounded-2xl border border-gray-200 px-6 py-12 text-center">
        <p class="text-gray-600">{{ $hasFilter ? 'No WhatsApp templates match this filter.' : 'No WhatsApp templates yet.' }}</p>
        @unless ($hasFilter)
            <a href="{{ route('admin.message-templates.whatsapp.create') }}" class="inline-flex items-center gap-2 mt-4 px-4 py-2.5 rounded-xl bg-purple-600 text-white text-sm font-semibold hover:bg-purple-700"><i class="fas fa-plus text-xs"></i> New Template</a>
        @endunless
    </div>
@else
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4" id="whatsapp-templates">
        @foreach ($templates as $t)
            <article class="bg-white rounded-2xl border border-gray-200 p-5 flex flex-col gap-3 {{ $t->is_active ? '' : 'opacity-80' }}" data-template-id="{{ $t->id }}" data-purpose="{{ $t->purpose }}">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h3 class="font-semibold text-gray-900 break-words">{{ $t->displayName() }}</h3>
                        <div class="mt-1.5 flex flex-wrap gap-1.5 text-xs font-semibold">
                            <span class="px-2 py-0.5 rounded-full bg-purple-50 text-purple-700">{{ $t->purposeLabel() }}</span>
                            @if ($t->language)<span class="px-2 py-0.5 rounded-full bg-gray-100 text-gray-600">{{ $t->language }}</span>@endif
                            @if ($t->message_id)
                                <span class="px-2 py-0.5 rounded-full bg-green-50 text-green-700">Message ID: {{ $t->message_id }}</span>
                            @else
                                <span class="px-2 py-0.5 rounded-full bg-amber-50 text-amber-800" title="Sent by template name and language instead of the Fast2SMS Message ID.">No Message ID</span>
                            @endif
                            @if ($t->isVerified())
                                <span class="px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-700" title="The provider reported this template as approved">Provider approved</span>
                            @else
                                <span class="px-2 py-0.5 rounded-full bg-gray-100 text-gray-500" title="Saved here only. WhatsApp approves templates; SocietyFlow cannot confirm it.">Not verified</span>
                            @endif
                        </div>
                    </div>
                    <span class="shrink-0 inline-flex items-center gap-1.5 text-xs font-semibold {{ $t->is_active ? 'text-green-700' : 'text-gray-500' }}">
                        <span class="w-1.5 h-1.5 rounded-full {{ $t->is_active ? 'bg-green-500' : 'bg-gray-400' }}"></span>{{ $t->is_active ? 'Active' : 'Inactive' }}
                    </span>
                </div>

                <dl class="grid grid-cols-[auto,1fr] gap-x-3 gap-y-1 text-xs">
                    <dt class="text-gray-500">Approved name</dt><dd class="font-mono text-gray-800 break-all">{{ $t->name }}</dd>
                    <dt class="text-gray-500">Recipient</dt><dd class="text-gray-800">{{ \App\Support\WhatsAppPurposes::recipient($t->purpose) }}</dd>
                </dl>

                <p class="text-sm text-gray-800 bg-gray-50 rounded-xl px-3 py-2.5 whitespace-pre-line break-words">{{ $t->body_text ?: '(no text)' }}</p>

                <div class="flex flex-wrap items-center justify-between gap-2 text-xs text-gray-500">
                    <span>{{ mb_strlen((string) $t->body_text) }}/1024 characters &middot; {{ $t->var_count }} {{ \Illuminate\Support\Str::plural('variable', $t->var_count) }}</span>
                    <span class="flex items-center gap-3">
                        <a href="{{ route('admin.message-templates.whatsapp.edit', $t) }}" class="font-semibold text-purple-700 hover:underline">Edit</a>
                        <form method="POST" action="{{ route('admin.message-templates.whatsapp.toggle', $t) }}" class="inline">@csrf
                            <button type="submit" class="font-semibold text-gray-700 hover:underline">{{ $t->is_active ? 'Switch off' : 'Make active' }}</button>
                        </form>
                        <form method="POST" action="{{ route('admin.message-templates.whatsapp.destroy', $t) }}" class="inline" onsubmit="return confirm('Delete this template?')">@csrf @method('DELETE')
                            <button type="submit" class="font-semibold text-red-600 hover:underline">Delete</button>
                        </form>
                    </span>
                </div>
            </article>
        @endforeach
    </div>
@endif
