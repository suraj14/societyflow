{{--
    Desktop sidebar navigation: grouped and compact.

    Visibility rules live in App\Support\SidebarMenu (carried over 1:1 from the previous flat sidebar in
    layouts/app.blade.php: same role checks, same society property-type switch, same routeIs patterns).
    Nothing here changes who can reach what - routes and middleware are untouched. A destination that two
    role blocks would both have shown (e.g. a user who is Accountant and Admin) now appears once.

    The mobile drawer (partials/mobile-menu) keeps its own, separate rules and is not changed by this partial.
--}}
@php
    // Who sees what is decided in App\Support\SidebarMenu (one place; the mobile drawer's extra rows use it too).
    $sfUser = auth()->user();
    $sfGroups = \App\Support\SidebarMenu::groups($sfUser);

    $sfActive = fn (array $item) => request()->routeIs(...$item['active']);
    $sfRow = 'group flex items-center gap-2.5 w-full h-10 px-2.5 rounded-lg text-[13px] font-medium transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-purple-400 focus-visible:ring-offset-1';
    $sfOn = 'bg-purple-50 text-purple-700 shadow-[inset_2px_0_0_#7c3aed]';
    $sfOff = 'text-gray-700 hover:bg-gray-50 hover:text-gray-900';
@endphp

<nav class="flex-1 overflow-y-auto py-2 px-2" aria-label="Main navigation">
    <ul class="space-y-0.5">
        {{-- Dashboard: always a direct link --}}
        <li>
            <a href="{{ route('dashboard') }}" @if(request()->routeIs('dashboard')) aria-current="page" @endif
               class="{{ $sfRow }} {{ request()->routeIs('dashboard') ? $sfOn : $sfOff }}">
                <i data-lucide="layout-dashboard" class="w-[18px] h-[18px] shrink-0"></i>
                <span class="truncate">Dashboard</span>
            </a>
        </li>

        @foreach ($sfGroups as $key => $group)
            @if (count($group['items']) === 1)
                @php $item = $group['items'][0]; @endphp
                <li class="mt-2">
                    <a href="{{ route($item['route']) }}" @if($sfActive($item)) aria-current="page" @endif
                       class="{{ $sfRow }} {{ $sfActive($item) ? $sfOn : $sfOff }}">
                        <i data-lucide="{{ $item['icon'] }}" class="w-[18px] h-[18px] shrink-0"></i>
                        <span class="truncate">{{ $item['label'] }}</span>
                    </a>
                </li>
            @else
                @php $hasActive = collect($group['items'])->contains(fn ($i) => $sfActive($i)); @endphp
                <li class="mt-2" x-data="{ open: {{ $hasActive ? 'true' : 'false' }} }">
                    <button type="button" @click="open = !open" :aria-expanded="open.toString()" aria-controls="sf-nav-{{ $key }}"
                            class="{{ $sfRow }} justify-between {{ $hasActive ? 'text-purple-700' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900' }}">
                        <span class="flex items-center gap-2.5 min-w-0">
                            <i data-lucide="{{ $group['icon'] }}" class="w-[18px] h-[18px] shrink-0"></i>
                            <span class="truncate">{{ $group['label'] }}</span>
                        </span>
                        <i data-lucide="chevron-down" class="w-4 h-4 shrink-0 text-gray-400 transition-transform" :class="{ 'rotate-180': open }"></i>
                    </button>
                    <ul id="sf-nav-{{ $key }}" x-show="open" x-collapse @unless($hasActive) x-cloak @endunless class="mt-0.5 ml-[18px] pl-2 border-l border-gray-200 space-y-0.5">
                        @foreach ($group['items'] as $item)
                            <li>
                                <a href="{{ route($item['route']) }}" @if($sfActive($item)) aria-current="page" @endif
                                   class="flex items-center gap-2 h-[38px] px-2.5 rounded-lg text-[13px] transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-purple-400 {{ $sfActive($item) ? 'bg-purple-50 text-purple-700 font-semibold shadow-[inset_2px_0_0_#7c3aed]' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">
                                    <i data-lucide="{{ $item['icon'] }}" class="w-4 h-4 shrink-0"></i>
                                    <span class="truncate">{{ $item['label'] }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </li>
            @endif
        @endforeach
    </ul>
</nav>
