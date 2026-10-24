<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Gate') - {{ config('app.name', 'SocietyFlow') }}</title>

    <link rel="icon" type="image/x-icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='75' font-size='75' fill='%237c3aed'>S</text></svg>">

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <style>
        /* A guard works one-handed on a cheap phone. Nothing tappable is
           smaller than a fingertip, and the page never scrolls sideways. */
        .tap-target { min-height: 48px; }
        body { -webkit-tap-highlight-color: transparent; overflow-x: hidden; }
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-gray-100 antialiased">
@php
    $sfUser = auth()->user();
    $sfSociety = $sfUser?->society?->name ?? 'SocietyFlow';
    $sfAvatar = $sfUser?->avatar_web_url ?? 'https://ui-avatars.com/api/?name=' . urlencode($sfUser?->name ?? 'Guard') . '&background=667eea&color=fff';

    // The gate's pages - and nothing else: a guard has no society administration (brief §8).
    $sfNav = [
        ['route' => 'guard.dashboard',       'active' => 'guard.dashboard',  'icon' => 'fa-door-open',          'label' => 'Gate',        'tab' => true],
        ['route' => 'guard.scan',            'active' => 'guard.scan*',      'icon' => 'fa-qrcode',             'label' => 'Scan QR',     'tab' => true],
        ['route' => 'guard.visitors.create', 'active' => 'guard.visitors.*', 'icon' => 'fa-user-plus',          'label' => 'New Visitor', 'tab' => true],
        ['route' => 'guard.history',         'active' => 'guard.history',    'icon' => 'fa-clock-rotate-left',  'label' => 'History',     'tab' => true],
        ['route' => 'guard.shift.current',   'active' => 'guard.shift.*',    'icon' => 'fa-clock',              'label' => 'My Shift',    'tab' => false],
        ['route' => 'guard.rules.index',     'active' => 'guard.rules.*',    'icon' => 'fa-circle-info',        'label' => 'Society Info', 'tab' => false],
    ];
@endphp

{{-- On a laptop or desktop the gate looks like the rest of the web app: a white sidebar, a top bar and wide content.
     On a phone it stays the gate layout - purple bar, thumb-reachable tabs at the bottom. Same pages either way. --}}
<div class="md:flex md:h-screen md:overflow-hidden">

    <aside class="hidden md:flex md:flex-shrink-0">
        <div class="w-64 bg-white border-r border-gray-200 flex flex-col">
            <div class="h-16 flex items-center px-4 border-b border-gray-200">
                <a href="{{ route('guard.dashboard') }}" class="flex items-center min-w-0">
                    <span class="w-8 h-8 rounded-lg bg-purple-700 text-white flex items-center justify-center shrink-0">
                        <i class="fas fa-user-shield text-sm"></i>
                    </span>
                    <span class="ml-2.5 min-w-0">
                        <span class="block text-[15px] leading-tight font-bold text-gray-800 truncate">{{ $sfSociety }}</span>
                        <span class="block text-xs text-gray-500">Gate</span>
                    </span>
                </a>
            </div>

            <nav class="flex-1 overflow-y-auto py-2 px-2" aria-label="Gate navigation">
                <ul class="space-y-0.5">
                    @foreach($sfNav as $sfItem)
                        @php $sfActive = request()->routeIs($sfItem['active']); @endphp
                        <li>
                            <a href="{{ route($sfItem['route']) }}" @if($sfActive) aria-current="page" @endif
                               class="flex items-center gap-2.5 w-full h-10 px-2.5 rounded-lg text-[13px] font-medium transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-purple-400 {{ $sfActive ? 'bg-purple-50 text-purple-700 shadow-[inset_2px_0_0_#7c3aed]' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900' }}">
                                <i class="fas {{ $sfItem['icon'] }} w-[18px] text-center shrink-0"></i>
                                <span class="truncate">{{ $sfItem['label'] }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </nav>

            <div class="border-t border-gray-200 px-3 py-2.5">
                <div class="flex items-center">
                    <img class="h-8 w-8 rounded-full object-cover" src="{{ $sfAvatar }}" alt="">
                    <div class="ml-3 flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-900 truncate">{{ $sfUser?->name ?? 'Guard' }}</p>
                        <p class="text-xs text-gray-500 truncate">{{ $sfUser?->roles->first()->name ?? 'Guard' }}</p>
                    </div>
                    <form method="POST" action="{{ route('logout') }}" class="inline">
                        @csrf
                        <button type="submit" class="text-gray-400 hover:text-red-600 transition-colors" title="Logout" aria-label="Logout">
                            <i class="fas fa-sign-out-alt"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </aside>

    <div class="md:flex-1 md:flex md:flex-col md:overflow-hidden">

        <header class="bg-purple-700 text-white sticky top-0 z-20 shadow md:static md:shadow-none md:bg-white md:text-gray-800 md:border-b md:border-gray-200 md:flex-shrink-0">
            <div class="px-4 py-3 md:px-6 md:py-0 md:h-16 flex items-center justify-between">
                <div class="min-w-0">
                    <p class="text-xs text-purple-200 leading-tight md:hidden">{{ $sfSociety }}</p>
                    <h1 class="text-lg font-bold leading-tight truncate md:text-xl md:font-semibold">@yield('page-title', 'Gate')</h1>
                </div>
                <div class="flex items-center gap-2 md:gap-4">
                    <div class="hidden lg:flex items-center text-sm text-gray-500">
                        <i class="fas fa-calendar-alt mr-2"></i>
                        {{ now()->format('l, d M Y') }}
                    </div>
                    @include('partials.notification-bell', ['bellButtonClass' => 'tap-target text-white bg-purple-800/60 hover:bg-purple-800 md:text-gray-500 md:bg-transparent md:hover:bg-gray-100 md:hover:text-gray-700'])

                    {{-- Phone only: there is no sidebar, so Society Info and Logout live in the bar. --}}
                    <a href="{{ route('guard.rules.index') }}" aria-label="Society Info"
                       class="md:hidden tap-target px-3 rounded-lg {{ request()->routeIs('guard.rules.*') ? 'bg-white text-purple-700' : 'bg-purple-800/60 hover:bg-purple-800' }} text-sm font-medium flex items-center gap-2">
                        <i class="fas fa-circle-info"></i>
                        <span class="hidden sm:inline">Society Info</span>
                    </a>
                    <form method="POST" action="{{ route('logout') }}" class="md:hidden">
                        @csrf
                        <button type="submit" class="tap-target px-3 rounded-lg bg-purple-800/60 hover:bg-purple-800 text-sm font-medium flex items-center gap-2">
                            <i class="fas fa-right-from-bracket"></i>
                            <span class="hidden sm:inline">Logout</span>
                        </button>
                    </form>

                    {{-- Laptop / desktop: who is signed in, as on every other web page. --}}
                    <div class="hidden md:flex items-center gap-2">
                        <img class="h-8 w-8 rounded-full object-cover ring-2 ring-gray-200" src="{{ $sfAvatar }}" alt="">
                        <span class="text-sm font-medium text-gray-700">{{ $sfUser?->name ?? 'Guard' }}</span>
                    </div>
                </div>
            </div>
        </header>

        <main class="pb-24 md:pb-0 md:flex-1 md:overflow-y-auto">
            @if(session('success'))
                <div class="mx-4 mt-3 p-3 rounded-lg bg-green-100 border border-green-300 text-green-800 text-sm md:mx-6 md:mt-4">
                    {{ session('success') }}
                </div>
            @endif
            @if(session('error'))
                <div class="mx-4 mt-3 p-3 rounded-lg bg-red-100 border border-red-300 text-red-800 text-sm md:mx-6 md:mt-4">
                    {{ session('error') }}
                </div>
            @endif

            @yield('content')
        </main>
    </div>
</div>

{{-- Phone: thumb-reachable navigation for the things a guard does. Scan first -
     "scan, verify, move on" is the primary action, not a Register Visitor form
     (brief: THE GUARD SHOULD SCAN, VERIFY AND MOVE ON). --}}
<nav class="md:hidden fixed bottom-0 inset-x-0 bg-white border-t border-gray-200 z-20">
    <div class="grid grid-cols-4">
        @foreach(array_filter($sfNav, fn ($sfItem) => $sfItem['tab']) as $sfItem)
            @php $sfActive = request()->routeIs($sfItem['active']); @endphp
            <a href="{{ route($sfItem['route']) }}"
               class="tap-target flex flex-col items-center justify-center py-2 {{ $sfActive ? 'text-purple-700' : 'text-gray-500' }}">
                <i class="fas {{ $sfItem['icon'] }} text-lg"></i>
                <span class="text-[11px] mt-1 font-medium">{{ $sfItem['label'] }}</span>
            </a>
        @endforeach
    </div>
</nav>

    @stack('scripts')
    <script>
    // Double-submit guard (same as layouts/app.blade.php).
    document.addEventListener('submit', function (e) {
        var f = e.target;
        if (!(f instanceof HTMLFormElement) || e.defaultPrevented || f.hasAttribute('data-allow-resubmit')) return;
        if (f.dataset.sfSubmitted === '1') { e.preventDefault(); return; }
        f.dataset.sfSubmitted = '1';
        var buttons = f.querySelectorAll('button[type="submit"], input[type="submit"], button:not([type])');
        setTimeout(function () { buttons.forEach(function (b) { b.disabled = true; }); }, 0);
        setTimeout(function () { f.dataset.sfSubmitted = ''; buttons.forEach(function (b) { b.disabled = false; }); }, 8000);
    }, false);
    </script>
</body>
</html>
