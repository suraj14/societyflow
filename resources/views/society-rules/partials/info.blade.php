{{--
    Society Info: three sections on the one existing page (/society-rules and
    /guard/rules) - Society Details, Committee, Documents.

    Every panel is rendered by the server and only shown/hidden in the browser,
    so the Documents list and all its links are exactly what they were; the
    tab just decides which section is in view. ?tab=details|committee|documents
    deep-links a section.

    Needs: $details (array|null), $committee (array), $tab, $isManager,
           plus everything partials.list already needs.
--}}
@php
    $notAvailable = '<span class="text-gray-400">Not available</span>';
    $tabs = [
        'details'   => ['label' => 'Society Details', 'icon' => 'fa-building'],
        'committee' => ['label' => 'Committee',       'icon' => 'fa-users'],
        'documents' => ['label' => 'Documents',       'icon' => 'fa-book-open'],
    ];
@endphp

<div x-data="{ tab: '{{ $tab }}' }">
    <div role="tablist" aria-label="Society Info" class="grid grid-cols-3 gap-1 p-1 mb-5 bg-gray-100 rounded-2xl">
        @foreach ($tabs as $key => $t)
            <button type="button" role="tab" id="tab-{{ $key }}" aria-controls="panel-{{ $key }}"
                    :aria-selected="tab === '{{ $key }}' ? 'true' : 'false'"
                    @click="tab = '{{ $key }}'"
                    :class="tab === '{{ $key }}' ? 'bg-white text-purple-700 shadow-sm' : 'text-gray-600 hover:text-gray-900'"
                    class="flex flex-col sm:flex-row items-center justify-center gap-1 sm:gap-2 px-2 py-2.5 rounded-xl text-xs sm:text-sm font-semibold transition focus:outline-none focus:ring-2 focus:ring-purple-500">
                <i class="fas {{ $t['icon'] }}" aria-hidden="true"></i>
                <span>{{ $t['label'] }}</span>
            </button>
        @endforeach
    </div>

    {{-- ───────────── Society Details ───────────── --}}
    <section id="panel-details" role="tabpanel" aria-labelledby="tab-details" x-show="tab === 'details'" x-cloak>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            @if ($details)
                <dl class="divide-y divide-gray-100">
                    @foreach ([
                        'Society Name'        => $details['name'],
                        'Registration Number' => $details['registration_number'],
                        'Address'             => $details['address'],
                        'Society Type'        => $details['society_type'],
                        'Registered / Established' => $details['registered_on_label'],
                    ] as $label => $value)
                        <div class="px-4 py-3.5 sm:flex sm:gap-6">
                            <dt class="text-xs font-semibold uppercase tracking-wider text-gray-500 sm:w-56 shrink-0">{{ $label }}</dt>
                            <dd class="mt-1 sm:mt-0 text-sm text-gray-900 break-words">
                                @if ($value !== null && $value !== '') {{ $value }} @else {!! $notAvailable !!} @endif
                            </dd>
                        </div>
                    @endforeach
                </dl>
            @else
                <p class="px-4 py-10 text-center text-sm text-gray-500">Your account is not linked to a society yet.</p>
            @endif
        </div>
        @if ($isManager && auth()->user()->society_id && !auth()->user()->hasRole('Super Admin'))
            <a href="{{ route('societies.edit', auth()->user()->society_id) }}" class="mt-3 inline-flex items-center gap-2 text-sm font-medium text-purple-600 hover:text-purple-800">
                <i class="fas fa-pen" aria-hidden="true"></i> Edit society details
            </a>
        @endif
    </section>

    {{-- ───────────── Committee ───────────── --}}
    <section id="panel-committee" role="tabpanel" aria-labelledby="tab-committee" x-show="tab === 'committee'" x-cloak>
        @if (count($committee) === 0)
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 px-6 py-14 text-center">
                <div class="w-14 h-14 mx-auto rounded-2xl bg-purple-50 text-purple-600 flex items-center justify-center mb-4">
                    <i class="fas fa-users text-2xl" aria-hidden="true"></i>
                </div>
                <p class="font-semibold text-gray-900">No committee members listed yet</p>
                <p class="text-sm text-gray-500 mt-1">When the society office adds its office bearers, they will appear here.</p>
            </div>
        @else
            <ul class="grid gap-3 sm:grid-cols-2">
                @foreach ($committee as $member)
                    @php
                        $initials = collect(preg_split('/\s+/', trim($member['name'])))->filter()->take(2)
                            ->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('') ?: '?';
                    @endphp
                    <li class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 flex items-center gap-4" data-committee-member>
                        @if ($member['photo_web'])
                            <img src="{{ $member['photo_web'] }}" alt="Photo of {{ $member['name'] }}" class="w-14 h-14 rounded-full object-cover shrink-0">
                        @else
                            <div class="w-14 h-14 rounded-full bg-gradient-to-br from-purple-500 to-indigo-600 text-white flex items-center justify-center font-semibold shrink-0" aria-hidden="true">{{ $initials }}</div>
                        @endif
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-gray-900 truncate">{{ $member['name'] }}</p>
                            <p class="text-sm text-purple-700">{{ $member['designation'] }}</p>
                            @if ($member['can_call'])
                                <p class="text-sm text-gray-500 truncate">{{ $member['phone'] }}</p>
                            @endif
                        </div>
                        @if ($member['can_call'])
                            <a href="tel:{{ $member['phone'] }}" aria-label="Call {{ $member['name'] }}, {{ $member['designation'] }}"
                               class="shrink-0 inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-green-600 text-white text-sm font-semibold hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 min-h-[44px]">
                                <i class="fas fa-phone" aria-hidden="true"></i> Call
                            </a>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
        @if ($isManager)
            <p class="mt-3 text-sm text-gray-500">
                To add or change a member, set a <em>Committee designation</em> on their profile in
                <a href="{{ route('users.index') }}" class="text-purple-600 font-medium hover:text-purple-800">Users</a>.
            </p>
        @endif
    </section>

    {{-- ───────────── Documents (unchanged behaviour) ───────────── --}}
    <section id="panel-documents" role="tabpanel" aria-labelledby="tab-documents" x-show="tab === 'documents'" x-cloak>
        @if ($isManager)
            <a href="{{ route('admin.society-documents.index') }}" class="mb-4 inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-purple-600 text-white text-sm font-semibold hover:bg-purple-700">
                <i class="fas fa-folder-open" aria-hidden="true"></i> Manage documents
            </a>
        @endif
        @include('society-rules.partials.list')
    </section>
</div>
