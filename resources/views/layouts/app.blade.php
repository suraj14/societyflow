<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">

    <title>@yield('title', 'Dashboard') - {{ $themeSettings['app_name'] ?? 'SocietyFlow' }}</title>

    <!-- Favicon -->
    @php
        $favicon = \App\Models\SystemSetting::get('theme', 'society_favicon', null, auth()->user()?->society_id);
    @endphp
    @if($favicon)
        <link rel="icon" type="image/x-icon" href="{{ asset('storage/' . $favicon) }}">
    @else
        <link rel="icon" type="image/x-icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='75' font-size='75' fill='%237c3aed'>S</text></svg>">
    @endif

    <!-- Tailwind CSS via CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    
    <!-- Flatpickr Date Picker -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    
    <!-- Alpine.js Collapse Plugin - must load BEFORE Alpine core, or every
         x-collapse menu logs "can't use [x-collapse]" and loses its animation. -->
    <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/collapse@3.x.x/dist/cdn.min.js"></script>

    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://unpkg.com/lucide@0.454.0/dist/umd/lucide.min.js"></script>
    <script>document.addEventListener('DOMContentLoaded', function () { if (window.lucide) { lucide.createIcons(); } });</script>
    
    <style>
        [x-cloak] { display: none !important; }
        /* Custom scrollbar */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #f1f1f1; }
        ::-webkit-scrollbar-thumb { background: #c1c1c1; border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: #a1a1a1; }
        
        /* Dynamic Theme Styles */
        {!! $themeCss !!}
    </style>
    @stack('styles')
    <style>
        /* Focus indicators for accessibility */
        button:focus-visible, a:focus-visible, input:focus-visible, select:focus-visible, textarea:focus-visible {
            outline: 2px solid #3b82f6;
            outline-offset: 2px;
        }
        
        /* Auto-dismiss toast animations */
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        @keyframes slideOut {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(100%); opacity: 0; }
        }
        .toast-enter { animation: slideIn 0.3s ease-out; }
        .toast-exit { animation: slideOut 0.3s ease-in; }
    </style>
</head>
<body class="bg-gray-100 font-sans antialiased">
    @include('partials.impersonation-banner')
    <div class="flex h-screen overflow-hidden" x-data="{ sidebarOpen: true, mobileMenuOpen: false }">
        
        <!-- Desktop Sidebar -->
        <aside class="hidden md:flex md:flex-shrink-0">
            <div class="w-64 bg-white border-r border-gray-200 flex flex-col">
                @php
                    $user = auth()->user();
                    $isAdmin = $user && $user->hasAnyRole(['Admin', 'Society Admin']);
                    $isSuperAdmin = $user && $user->hasRole('Super Admin');
                    $isOwner = $user && $user->hasAnyRole(['Villa Owner', 'Apartment Owner', 'Owner']);
                    $isTenant = $user && $user->hasRole('Tenant');
                    $isStaff = $user && $user->hasRole('Staff');
                    $isAccountant = $user && $user->hasRole('Accountant');
                @endphp
                
                <div class="h-full flex flex-col">
                    <!-- Logo -->
                    <div class="h-14 flex items-center px-4 border-b border-gray-200">
                        <a href="{{ route('dashboard') }}" class="flex items-center">
                            @if($themeSettings['society_logo'] ?? null)
                                <div class="w-8 h-8 rounded-lg overflow-hidden logo-container">
                                    <img src="{{ asset('storage/' . $themeSettings['society_logo']) }}" 
                                         alt="{{ $themeSettings['app_name'] ?? 'SocietyFlow' }}" 
                                         class="w-full h-full object-cover logo-image"
                                         onerror="console.log('Logo failed to load:', this.src); this.style.display='none'; this.parentElement.nextElementSibling.style.display='flex';">
                                </div>
                                <div class="w-8 h-8 rounded-lg bg-primary items-center justify-center logo-fallback" style="display: none;">
                                    <span class="text-white font-bold text-base">
                                        @php
                                            $name = $themeSettings['app_name'] ?? 'SocietyFlow';
                                            $words = explode(' ', trim($name));
                                            $initials = '';
                                            
                                            if (count($words) >= 2) {
                                                // Multiple words: take first letter of each word (max 2)
                                                foreach($words as $word) {
                                                    if(strlen(trim($word)) > 0) {
                                                        $initials .= strtoupper(substr(trim($word), 0, 1));
                                                        if(strlen($initials) >= 2) break;
                                                    }
                                                }
                                            } else {
                                                // Single word: take first 2 characters
                                                $initials = strtoupper(substr(trim($name), 0, 2));
                                            }
                                            
                                            echo $initials ?: 'SF';
                                        @endphp
                                    </span>
                                </div>
                                <!-- Debug info (remove in production) -->
                                <div style="display: none;">
                                    Logo path: {{ $themeSettings['society_logo'] ?? 'not set' }}<br>
                                    Full URL: {{ asset('storage/' . ($themeSettings['society_logo'] ?? '')) }}
                                </div>
                            @else
                                <div class="w-8 h-8 rounded-lg bg-primary flex items-center justify-center">
                                    <span class="text-white font-bold text-base">
                                        @php
                                            $name = $themeSettings['app_name'] ?? 'SocietyFlow';
                                            $words = explode(' ', trim($name));
                                            $initials = '';
                                            
                                            if (count($words) >= 2) {
                                                // Multiple words: take first letter of each word (max 2)
                                                foreach($words as $word) {
                                                    if(strlen(trim($word)) > 0) {
                                                        $initials .= strtoupper(substr(trim($word), 0, 1));
                                                        if(strlen($initials) >= 2) break;
                                                    }
                                                }
                                            } else {
                                                // Single word: take first 2 characters
                                                $initials = strtoupper(substr(trim($name), 0, 2));
                                            }
                                            
                                            echo $initials ?: 'SF';
                                        @endphp
                                    </span>
                                </div>
                            @endif
                            <div class="ml-2.5 min-w-0">
                                <span class="text-[15px] leading-tight font-bold text-gray-800 line-clamp-2">{{ $themeSettings['app_name'] ?? 'SocietyFlow' }}</span>
                            </div>
                        </a>
                    </div>

                    <!-- Navigation: grouped, compact (see partials/sidebar-nav) -->
                    @include('partials.sidebar-nav')

                    <!-- User Profile -->
                    <div class="border-t border-gray-200 px-3 py-2.5">
                        <div class="flex items-center">
                            <img class="h-8 w-8 rounded-full object-cover" src="{{ auth()->user()->avatar_web_url ?? 'https://ui-avatars.com/api/?name=' . urlencode(auth()->user()->name ?? 'User') . '&background=667eea&color=fff' }}" alt="User">
                            <div class="ml-3 flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-900 truncate">{{ auth()->user()->name ?? 'User' }}</p>
                                <p class="text-xs text-gray-500 truncate">{{ auth()->user()->roles->first()->name ?? 'User' }}</p>
                            </div>
                            <form method="POST" action="{{ route('logout') }}" class="inline">
                                @csrf
                                <button type="submit" class="text-gray-400 hover:text-red-600 transition-colors" title="Logout">
                                    <i class="fas fa-sign-out-alt"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Mobile Sidebar Overlay -->
        <div x-show="mobileMenuOpen" 
             x-cloak
             class="fixed inset-0 z-40 md:hidden"
             x-transition:enter="transition-opacity ease-linear duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition-opacity ease-linear duration-300"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0">
            
            <!-- Backdrop -->
            <div class="fixed inset-0 bg-gray-600 bg-opacity-75" @click="mobileMenuOpen = false"></div>
            
            <!-- Mobile Sidebar -->
            <aside class="fixed inset-y-0 left-0 w-64 bg-white z-50 flex flex-col"
                   x-transition:enter="transition ease-in-out duration-300 transform"
                   x-transition:enter-start="-translate-x-full"
                   x-transition:enter-end="translate-x-0"
                   x-transition:leave="transition ease-in-out duration-300 transform"
                   x-transition:leave-start="translate-x-0"
                   x-transition:leave-end="-translate-x-full">
                
                <!-- Close Button -->
                <div class="absolute top-0 right-0 -mr-12 pt-2">
                    <button @click="mobileMenuOpen = false" 
                            class="ml-1 flex items-center justify-center h-10 w-10 rounded-full focus:outline-none focus:ring-2 focus:ring-inset focus:ring-white">
                        <i class="fas fa-times text-white text-xl"></i>
                    </button>
                </div>
                
                @include('partials.mobile-menu')
            </aside>
        </div>

        <!-- Main Content Area -->
        <div class="flex-1 flex flex-col overflow-hidden">
            
            <!-- Top Header -->
            <header class="bg-white border-b border-gray-200 flex-shrink-0">
                <div class="flex items-center justify-between h-16 px-4 sm:px-6">
                    <!-- Left: Mobile menu button + Page title -->
                    <div class="flex items-center">
                        <button @click="mobileMenuOpen = true" 
                                class="md:hidden p-2 rounded-md text-gray-500 hover:text-gray-900 hover:bg-gray-100 focus:outline-none">
                            <i class="fas fa-bars text-xl"></i>
                        </button>
                        <div class="ml-4 md:ml-0">
                            <h1 class="text-xl font-semibold text-gray-800">@yield('page-title', 'Dashboard')</h1>
                            @hasSection('breadcrumb')
                                <nav class="text-sm text-gray-500 mt-0.5">
                                    @yield('breadcrumb')
                                </nav>
                            @endif
                        </div>
                    </div>

                    <!-- Right: Actions -->
                    <div class="flex items-center space-x-4">
                        <!-- Date Display -->
                        <div class="hidden md:flex items-center text-sm text-gray-500">
                            <i class="fas fa-calendar-alt mr-2"></i>
                            {{ now()->format('l, d M Y') }}
                        </div>

                        <!-- Notifications (the user's own in-app notifications) -->
                        @include('partials.notification-bell')

                        <!-- User Dropdown -->
                        <div class="relative" x-data="{ open: false }">
                            <button @click="open = !open" 
                                    class="flex items-center space-x-2 p-1.5 rounded-lg hover:bg-gray-100 transition-colors">
                                <img class="h-8 w-8 rounded-full object-cover ring-2 ring-gray-200" 
                                     src="{{ auth()->user()->avatar_web_url ?? 'https://ui-avatars.com/api/?name=' . urlencode(auth()->user()->name ?? 'User') . '&background=667eea&color=fff' }}"
                                     alt="User">
                                <span class="hidden sm:block text-sm font-medium text-gray-700">{{ auth()->user()->name ?? 'User' }}</span>
                                <i class="fas fa-chevron-down text-xs text-gray-400"></i>
                            </button>

                            <div x-show="open" 
                                 @click.away="open = false"
                                 x-cloak
                                 x-transition:enter="transition ease-out duration-100"
                                 x-transition:enter-start="transform opacity-0 scale-95"
                                 x-transition:enter-end="transform opacity-100 scale-100"
                                 x-transition:leave="transition ease-in duration-75"
                                 x-transition:leave-start="transform opacity-100 scale-100"
                                 x-transition:leave-end="transform opacity-0 scale-95"
                                 class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg py-1 z-50 border border-gray-200">
                                <div class="px-4 py-2 border-b border-gray-100">
                                    <p class="text-sm font-medium text-gray-900">{{ auth()->user()->name ?? 'User' }}</p>
                                    <p class="text-xs text-gray-500 truncate">{{ auth()->user()->email ?? '' }}</p>
                                </div>
                                <a href="{{ route('profile.show') }}" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                    <i class="fas fa-user w-4 mr-3 text-gray-400"></i>
                                    Profile
                                </a>
                                @if($isAdmin || $isSuperAdmin)
                                <a href="{{ route('settings.index') }}" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                    <i class="fas fa-cog w-4 mr-3 text-gray-400"></i>
                                    Settings
                                </a>
                                @endif
                                <div class="border-t border-gray-100"></div>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="flex items-center w-full px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                        <i class="fas fa-sign-out-alt w-4 mr-3"></i>
                                        Logout
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Page Content -->
            <main class="flex-1 overflow-y-auto bg-gray-100">
                <!-- Flash Messages -->
                @if(session('success'))
                    <div class="mx-4 sm:mx-6 mt-4">
                        <div class="bg-green-50 border-l-4 border-green-500 p-4 rounded-r-lg" role="alert">
                            <div class="flex items-center">
                                <i class="fas fa-check-circle text-green-500 mr-3"></i>
                                <p class="text-sm font-medium text-green-800">{{ session('success') }}</p>
                            </div>
                        </div>
                    </div>
                @endif

                @if(session('error'))
                    <div class="mx-4 sm:mx-6 mt-4">
                        <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded-r-lg" role="alert">
                            <div class="flex items-center">
                                <i class="fas fa-exclamation-circle text-red-500 mr-3"></i>
                                <p class="text-sm font-medium text-red-800">{{ session('error') }}</p>
                            </div>
                        </div>
                    </div>
                @endif

                <!-- Main Content -->
                <div class="p-4 sm:p-6">
                    @yield('content')
                </div>
            </main>
        </div>
    </div>


    <!-- Global Form Handler - CRITICAL FOR FORM SUBMISSIONS -->
    <script src="{{ asset('js/global-form-handler.js') }}"></script>
    
    <!-- Compiled App JavaScript (Pure Laravel - No Build System) -->
    <script src="{{ asset('js/app.js') }}"></script>
    
    @stack('scripts')
    <script>
    // Double-submit guard for plain HTML forms: once a form is really being
    // submitted (not intercepted by its own JS), its submit buttons are
    // disabled and a second submit within a few seconds is dropped. Forms
    // that manage their own submission (AJAX) call preventDefault() first
    // and are left alone; add data-allow-resubmit to opt a form out.
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
    @include('partials.password-strength')
</body>
</html>

<!-- CSRF Token Setup for AJAX - Added for 419 Fix -->
<script>
    // Set CSRF token for all AJAX requests
    if (typeof $ !== 'undefined') {
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });
    }
    
    // Also set it for fetch API
    const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    if (token) {
        window.csrfToken = token;
    }
</script>

