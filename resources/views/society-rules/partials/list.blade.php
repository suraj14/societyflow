{{-- Reader list shared by /society-rules and /guard/rules. --}}
<form method="GET" class="mb-4">
    {{-- A search belongs to the Documents section; keep the page on it. --}}
    <input type="hidden" name="tab" value="documents">
    <div class="relative">
        <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Search rules and documents"
               class="w-full pl-10 pr-4 py-3 bg-white border border-gray-200 rounded-2xl text-sm shadow-sm focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
    </div>
</form>

@if ($total === 0)
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 px-6 py-14 text-center">
        <div class="w-14 h-14 mx-auto rounded-2xl bg-purple-50 text-purple-600 flex items-center justify-center mb-4">
            <i class="fas fa-book-open text-2xl"></i>
        </div>
        @if (request()->filled('q'))
            <p class="font-semibold text-gray-900">Nothing matches "{{ request('q') }}"</p>
            <a href="{{ route($routePrefix . '.index', ['tab' => 'documents']) }}" class="text-sm text-purple-600 mt-2 inline-block">Show all documents</a>
        @else
            <p class="font-semibold text-gray-900">No documents published yet</p>
            <p class="text-sm text-gray-500 mt-1">When the society office publishes rules, they'll appear here.</p>
        @endif
    </div>
@else
    <div class="space-y-5">
        @foreach ($grouped as $group)
            <section>
                <h2 class="text-xs font-semibold uppercase tracking-wider text-gray-500 mb-2 px-1">{{ $group['label'] }}</h2>
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 divide-y divide-gray-100 overflow-hidden">
                    @foreach ($group['documents'] as $doc)
                        <a href="{{ route($routePrefix . '.show', $doc->id) }}" class="flex items-center gap-4 px-4 py-3.5 hover:bg-purple-50/50 transition">
                            <div class="w-11 h-11 shrink-0 rounded-xl bg-gradient-to-br from-purple-500 to-indigo-600 text-white flex items-center justify-center">
                                <i class="fas fa-file-lines"></i>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="font-semibold text-gray-900 truncate">{{ $doc->title }}</p>
                                <p class="text-xs text-gray-500 truncate">
                                    {{ $doc->version }}
                                    @if ($doc->effective_from) &middot; from {{ $doc->effective_from->format('d M Y') }} @endif
                                    @if ($doc->description) &middot; {{ \Illuminate\Support\Str::limit($doc->description, 60) }} @endif
                                </p>
                            </div>
                            @if ($doc->requires_acknowledgement && !in_array($doc->id, $acknowledged, true))
                                <span class="shrink-0 px-2 py-0.5 rounded-full bg-amber-50 text-amber-700 ring-1 ring-amber-200 text-[11px] font-semibold">Please read</span>
                            @endif
                            <i class="fas fa-chevron-right text-gray-300 text-sm"></i>
                        </a>
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>
@endif
