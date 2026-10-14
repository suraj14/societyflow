{{-- Message Templates -> WhatsApp: SocietyFlow's templates in five categories, whatever their provider status. Hand-typed
     templates and ones read by "Sync from Fast2SMS" appear the same way. Another product's templates sharing the Fast2SMS
     account are kept out of this list (see App\Support\WhatsAppTemplateScope) and reachable under "Other templates". --}}
@php
    $sfInput = 'px-3 py-2 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-purple-500';
    $showingOther = $filters['scope'] === 'other';
    $hasFilter = $filters['q'] !== '' || $filters['purpose'] !== 'all' || $filters['status'] !== 'all' || $filters['category'] !== 'all' || $filters['provider'] !== 'all';
    $groups = $templates->groupBy(fn ($t) => $t->scope_info['category'] ?? 'other');
    $providerChip = fn ($t) => match (strtoupper((string) $t->status)) {
        'APPROVED' => 'bg-green-50 text-green-700',
        'PENDING', 'IN_REVIEW', 'IN_APPEAL' => 'bg-amber-50 text-amber-800',
        'REJECTED', 'PAUSED', 'DISABLED', 'FLAGGED' => 'bg-red-50 text-red-700',
        default => 'bg-gray-100 text-gray-600',
    };
@endphp

{{-- What the last sync found that needs a look --}}
@foreach ((array) session('sync_warnings', []) as $warning)
    <p class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-2.5 text-sm text-amber-900" role="status"><i class="fas fa-triangle-exclamation mr-1"></i> {{ $warning }}</p>
@endforeach

{{-- Another product's template switched on for residents: never sent, but it should not stay switched on --}}
@if ($misrouted && !$showingOther)
    <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900" role="alert">
        <p class="font-semibold"><i class="fas fa-circle-exclamation mr-1"></i> BookMizo template(s) are switched on for resident notifications.</p>
        <ul class="list-disc ml-5 mt-1">
            @foreach ($misrouted as $m)<li>{{ $m['name'] }} &rarr; {{ $m['purpose'] }}</li>@endforeach
        </ul>
        <p class="mt-1">SocietyFlow refuses to send them, so those notifications are not delivered on WhatsApp. Switch them off under
            <a href="{{ route('admin.message-templates.index', ['channel' => 'whatsapp', 'scope' => 'other']) }}" class="underline font-medium">Other templates</a>, then switch on a SocietyFlow template.</p>
    </div>
@endif

@if ($showingOther)
    <p class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-700">
        These templates are on your Fast2SMS account but are not SocietyFlow's (booking, slot, refund, customer or staff messages). They are kept, never sent to residents and never deleted.
        <a href="{{ route('admin.message-templates.index', ['channel' => 'whatsapp']) }}" class="underline font-medium">&larr; Back to SocietyFlow templates</a>
    </p>
@endif

{{-- Filters --}}
<form method="GET" action="{{ route('admin.message-templates.index') }}" class="flex flex-col lg:flex-row lg:flex-wrap lg:items-center gap-2" role="search">
    <input type="hidden" name="channel" value="whatsapp">
    @if ($showingOther)<input type="hidden" name="scope" value="other">@endif
    <label for="mt-q" class="sr-only">Search templates</label>
    <input id="mt-q" type="search" name="q" value="{{ $filters['q'] }}" placeholder="Search name or text" class="{{ $sfInput }} lg:w-56">
    @unless ($showingOther)
        <label for="mt-category" class="sr-only">Category</label>
        <select id="mt-category" name="category" class="{{ $sfInput }}" onchange="this.form.submit()">
            <option value="all">All categories</option>
            @foreach ($categories as $key => $label)
                <option value="{{ $key }}" @selected($filters['category'] === $key)>{{ $label }}</option>
            @endforeach
        </select>
    @endunless
    <label for="mt-purpose" class="sr-only">Notification type</label>
    <select id="mt-purpose" name="purpose" class="{{ $sfInput }}" onchange="this.form.submit()">
        <option value="all">All notification types</option>
        @foreach ($purposeLabels as $key => $label)
            <option value="{{ $key }}" @selected($filters['purpose'] === $key)>{{ $label }}</option>
        @endforeach
    </select>
    <label for="mt-provider" class="sr-only">Provider status</label>
    <select id="mt-provider" name="provider" class="{{ $sfInput }}" onchange="this.form.submit()">
        <option value="all">Any provider status</option>
        <option value="approved" @selected($filters['provider'] === 'approved')>Approved</option>
        <option value="pending" @selected($filters['provider'] === 'pending')>Pending review</option>
        <option value="rejected" @selected($filters['provider'] === 'rejected')>Rejected / paused</option>
        <option value="unchecked" @selected($filters['provider'] === 'unchecked')>Not checked (typed in)</option>
    </select>
    <label for="mt-status" class="sr-only">In app</label>
    <select id="mt-status" name="status" class="{{ $sfInput }}" onchange="this.form.submit()">
        <option value="all">Active and inactive</option>
        <option value="active" @selected($filters['status'] === 'active')>Active in app</option>
        <option value="inactive" @selected($filters['status'] === 'inactive')>Inactive in app</option>
    </select>
    <button type="submit" class="px-4 py-2 rounded-xl border border-gray-300 text-gray-700 text-sm font-semibold hover:bg-gray-50">Search</button>
    @if ($hasFilter)
        <a href="{{ route('admin.message-templates.index', ['channel' => 'whatsapp'] + ($showingOther ? ['scope' => 'other'] : [])) }}" class="text-sm text-purple-700 hover:underline">Clear</a>
    @endif
</form>

<p class="text-sm text-gray-600">All your SocietyFlow templates are listed, approved or not. <strong>Provider</strong> is what Fast2SMS / WhatsApp says; <strong>In app</strong> is whether SocietyFlow uses it. Only an approved (or hand-typed) template is ever sent. <strong>Sync from Fast2SMS</strong> adds templates approved since the last sync &mdash; there is no automatic sync.</p>

{{-- One short line for every notification that has no active template --}}
@if ($unmappedWhatsApp && !$showingOther)
    <p class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-2.5 text-sm text-amber-900" role="status">
        <i class="fas fa-triangle-exclamation mr-1"></i> No active WhatsApp template for: {{ implode(', ', $unmappedWhatsApp) }}. Those notifications are not sent on WhatsApp.
    </p>
@endif

@if ($templates->isEmpty())
    <div class="bg-white rounded-2xl border border-gray-200 px-6 py-12 text-center">
        <p class="text-gray-600">{{ $hasFilter ? 'No WhatsApp templates match this filter.' : ($showingOther ? 'No other templates.' : 'No WhatsApp templates yet. Press "Sync from Fast2SMS" or add one.') }}</p>
    </div>
@else
    @foreach ($groups as $groupKey => $items)
        <section class="space-y-3" aria-labelledby="cat-{{ $groupKey }}">
            <h3 id="cat-{{ $groupKey }}" class="text-sm font-bold uppercase tracking-wide text-gray-500">
                {{ $categories[$groupKey] ?? ($showingOther ? 'Not SocietyFlow templates' : 'Not sorted yet') }} <span class="font-normal text-gray-400">&middot; {{ $items->count() }}</span>
            </h3>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4" @if ($loop->first) id="whatsapp-templates" @endif>
                @foreach ($items as $t)
                    @php $basis = $t->scope_info['basis']; @endphp
                    <article class="bg-white rounded-2xl border border-gray-200 p-5 flex flex-col gap-3 {{ $t->is_active ? '' : 'opacity-90' }}" data-template-id="{{ $t->id }}" data-purpose="{{ $t->purpose }}" data-category="{{ $t->scope_info['category'] }}" data-provider-status="{{ strtoupper((string) $t->status) }}">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h4 class="font-semibold text-gray-900 break-words">{{ $t->displayName() }}</h4>
                                <div class="mt-1.5 flex flex-wrap gap-1.5 text-xs font-semibold">
                                    <span class="px-2 py-0.5 rounded-full bg-purple-50 text-purple-700">{{ $t->purposeLabel() }}</span>
                                    @if ($t->language)<span class="px-2 py-0.5 rounded-full bg-gray-100 text-gray-600">{{ $t->language }}</span>@endif
                                    @if ($t->category)<span class="px-2 py-0.5 rounded-full bg-gray-100 text-gray-600" title="WhatsApp template category">{{ ucfirst(strtolower($t->category)) }}</span>@endif
                                    @if ($t->message_id)
                                        <span class="px-2 py-0.5 rounded-full bg-green-50 text-green-700">Message ID: {{ $t->message_id }}</span>
                                    @else
                                        <span class="px-2 py-0.5 rounded-full bg-amber-50 text-amber-800" title="Sent by template name and language instead of the Fast2SMS Message ID.">No Message ID</span>
                                    @endif
                                </div>
                            </div>
                            {{-- two separate facts: what the provider says, and whether the app uses it --}}
                            <div class="shrink-0 flex flex-col items-end gap-1 text-xs font-semibold">
                                <span class="px-2 py-0.5 rounded-full {{ $providerChip($t) }}" title="What Fast2SMS / WhatsApp reports. A typed-in template has never been checked.">Provider: {{ $t->providerStatusLabel() }}</span>
                                <span class="inline-flex items-center gap-1.5 {{ $t->is_active ? 'text-green-700' : 'text-gray-500' }}" title="Whether SocietyFlow sends this template">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $t->is_active ? 'bg-green-500' : 'bg-gray-400' }}"></span>In app: {{ $t->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </div>
                        </div>

                        <dl class="grid grid-cols-[auto,1fr] gap-x-3 gap-y-1 text-xs">
                            <dt class="text-gray-500">Approved name</dt><dd class="font-mono text-gray-800 break-all">{{ $t->name }}</dd>
                            <dt class="text-gray-500">Recipient</dt><dd class="text-gray-800">{{ \App\Support\WhatsAppPurposes::recipient($t->purpose) }}</dd>
                        </dl>

                        <p class="text-sm text-gray-800 bg-gray-50 rounded-xl px-3 py-2.5 whitespace-pre-line break-words">{{ $t->body_text ?: '(no text saved - sync or edit to fill it in)' }}</p>

                        {{-- what will be filled in, and how the finished message reads (example values - nothing is sent) --}}
                        @if ($t->purpose && $t->var_count > 0 && $t->body_text)
                            <div class="text-xs text-gray-600 space-y-1">
                                <p><span class="font-semibold text-gray-700">Fills:</span> {{ $t->mappingSummary() }}</p>
                                <p class="whitespace-pre-line break-words"><span class="font-semibold text-gray-700">Example:</span> {{ $t->preview() }}</p>
                            </div>
                        @endif

                        @if ($t->isKnownUnapproved())
                            <p class="text-xs text-amber-800 bg-amber-50 rounded-lg px-3 py-2"><i class="fas fa-ban mr-1"></i> Not approved at the provider, so it is never sent and cannot be switched on.</p>
                        @elseif (!$t->purpose && !$showingOther)
                            <p class="text-xs text-gray-600 bg-gray-50 rounded-lg px-3 py-2"><i class="fas fa-circle-info mr-1"></i> Not attached to a notification yet, so SocietyFlow does not use it. Edit it and choose what it is for (only the types listed under Notification Type have a sender).</p>
                        @endif
                        @if ($basis === 'wording')
                            <p class="text-xs text-gray-500"><i class="fas fa-tag mr-1"></i> Sorted into this category by its name and wording.</p>
                        @endif

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
        </section>
    @endforeach
@endif

@if (!$showingOther && $otherCount)
    <p class="text-sm text-gray-500">
        {{ $otherCount }} other template(s) on this account are not SocietyFlow's (BookMizo booking, slot, refund, customer or staff messages).
        <a href="{{ route('admin.message-templates.index', ['channel' => 'whatsapp', 'scope' => 'other']) }}" class="underline font-medium">Other templates</a>
    </p>
@endif
