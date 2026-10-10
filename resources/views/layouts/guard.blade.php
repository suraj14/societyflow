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

    {{-- No sidebar, no society administration: the gate only (brief §8). --}}
    <header class="bg-purple-700 text-white sticky top-0 z-20 shadow">
        <div class="px-4 py-3 flex items-center justify-between">
            <div class="min-w-0">
                <p class="text-xs text-purple-200 leading-tight">{{ auth()->user()?->society?->name ?? 'SocietyFlow' }}</p>
                <h1 class="text-lg font-bold leading-tight truncate">@yield('page-title', 'Gate')</h1>
            </div>
            <div class="flex items-center gap-2">
            @include('partials.notification-bell', ['bellButtonClass' => 'tap-target text-white bg-purple-800/60 hover:bg-purple-800'])
            <a href="{{ route('guard.rules.index') }}" aria-label="Society Info"
               class="tap-target px-3 rounded-lg {{ request()->routeIs('guard.rules.*') ? 'bg-white text-purple-700' : 'bg-purple-800/60 hover:bg-purple-800' }} text-sm font-medium flex items-center gap-2">
                <i class="fas fa-circle-info"></i>
                <span class="hidden sm:inline">Society Info</span>
            </a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="tap-target px-3 rounded-lg bg-purple-800/60 hover:bg-purple-800 text-sm font-medium flex items-center gap-2">
                    <i class="fas fa-right-from-bracket"></i>
                    <span class="hidden sm:inline">Logout</span>
                </button>
            </form>
            </div>
        </div>
    </header>

    @if(session('success'))
        <div class="mx-4 mt-3 p-3 rounded-lg bg-green-100 border border-green-300 text-green-800 text-sm">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="mx-4 mt-3 p-3 rounded-lg bg-red-100 border border-red-300 text-red-800 text-sm">
            {{ session('error') }}
        </div>
    @endif

    <main class="pb-24">
        @yield('content')
    </main>

    {{-- Thumb-reachable navigation: the things a guard does. Scan first -
         "scan, verify, move on" is the primary action, not a Register
         Visitor form (brief: THE GUARD SHOULD SCAN, VERIFY AND MOVE ON). --}}
    <nav class="fixed bottom-0 inset-x-0 bg-white border-t border-gray-200 z-20">
        <div class="grid grid-cols-4">
            @php
                $sfNav = [
                    ['route' => 'guard.dashboard',        'icon' => 'fa-door-open',   'label' => 'Gate'],
                    ['route' => 'guard.scan',             'icon' => 'fa-qrcode',      'label' => 'Scan QR'],
                    ['route' => 'guard.visitors.create',  'icon' => 'fa-user-plus',   'label' => 'New Visitor'],
                    ['route' => 'guard.history',          'icon' => 'fa-clock-rotate-left', 'label' => 'History'],
                ];
            @endphp
            @foreach($sfNav as $sfItem)
                @php $sfActive = request()->routeIs($sfItem['route']); @endphp
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
