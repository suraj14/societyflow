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
    
    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    
    <!-- Alpine.js Collapse Plugin -->
    <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/collapse@3.x.x/dist/cdn.min.js"></script>
    
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
                @endphp
                
                <div class="h-full flex flex-col">
                    <!-- Logo -->
                    <div class="h-16 flex items-center px-6 border-b border-gray-200">
                        <a href="{{ route('dashboard') }}" class="flex items-center">
                            @if($themeSettings['society_logo'] ?? null)
                                <div class="w-10 h-10 rounded-lg overflow-hidden logo-container">
                                    <img src="{{ asset('storage/' . $themeSettings['society_logo']) }}" 
                                         alt="{{ $themeSettings['app_name'] ?? 'SocietyFlow' }}" 
                                         class="w-full h-full object-cover logo-image"
                                         onerror="console.log('Logo failed to load:', this.src); this.style.display='none'; this.parentElement.nextElementSibling.style.display='flex';">
                                </div>
                                <div class="w-10 h-10 rounded-lg bg-primary items-center justify-center logo-fallback" style="display: none;">
                                    <span class="text-white font-bold text-xl">
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
                                <div class="w-10 h-10 rounded-lg bg-primary flex items-center justify-center">
                                    <span class="text-white font-bold text-xl">
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
                            <div class="ml-3">
                                <span class="text-lg font-bold text-gray-800">{{ $themeSettings['app_name'] ?? 'SocietyFlow' }}</span>
                            </div>
                        </a>
                    </div>

                    <!-- Navigation -->
                    <nav class="flex-1 overflow-y-auto py-4 px-3">
                        <div class="space-y-1">
                            <!-- Dashboard -->
                            <a href="{{ route('dashboard') }}" class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('dashboard') ? 'bg-purple-50 text-purple-600' : 'text-gray-700 hover:bg-gray-50' }}">
                                <i class="fas fa-home w-5 text-center"></i>
                                <span class="ml-3">Dashboard</span>
                            </a>

                            @if($isAdmin)
                                <!-- User Management -->
                                <a href="{{ route('users.index') }}" class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('users.*') ? 'bg-purple-50 text-purple-600' : 'text-gray-700 hover:bg-gray-50' }}">
                                    <i class="fas fa-users w-5 text-center"></i>
                                    <span class="ml-3">User</span>
                                </a>

                                <!-- Owners Management -->
                                <a href="{{ route('owners.index') }}" class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('owners.*') ? 'bg-purple-50 text-purple-600' : 'text-gray-700 hover:bg-gray-50' }}">
                                    <i class="fas fa-user-tie w-5 text-center"></i>
                                    <span class="ml-3">Owners</span>
                                </a>



                                <!-- Apartments Group -->
                                <div x-data="{ open: {{ request()->routeIs('buildings.*') || request()->routeIs('flats.*') ? 'true' : 'false' }} }">
                                    <button @click="open = !open" class="w-full flex items-center justify-between px-3 py-2.5 text-sm font-medium rounded-lg transition-colors text-gray-700 hover:bg-gray-50">
                                        <div class="flex items-center">
                                            <i class="fas fa-building w-5 text-center"></i>
                                            <span class="ml-3">Apartments</span>
                                        </div>
                                        <i class="fas fa-chevron-down text-xs transition-transform" :class="{ 'rotate-180': open }"></i>
                                    </button>
                                    <div x-show="open" x-collapse class="ml-8 mt-1 space-y-1">
                                        <a href="{{ route('buildings.index') }}" class="flex items-center px-3 py-2 text-sm rounded-lg transition-colors {{ request()->routeIs('buildings.*') ? 'bg-purple-50 text-purple-600' : 'text-gray-600 hover:bg-gray-50' }}">
                                            Buildings
                                        </a>
                                        <a href="{{ route('flats.index') }}" class="flex items-center px-3 py-2 text-sm rounded-lg transition-colors {{ request()->routeIs('flats.*') ? 'bg-purple-50 text-purple-600' : 'text-gray-600 hover:bg-gray-50' }}">
                                            Apartments
                                        </a>
                                    </div>
                                </div>

                                <!-- Villas Group -->
                                <div x-data="{ open: {{ request()->routeIs('villa-areas.*') || request()->routeIs('villas.*') ? 'true' : 'false' }} }">
                                    <button @click="open = !open" class="w-full flex items-center justify-between px-3 py-2.5 text-sm font-medium rounded-lg transition-colors text-gray-700 hover:bg-gray-50">
                                        <div class="flex items-center">
                                            <i class="fas fa-house-user w-5 text-center"></i>
                                            <span class="ml-3">Villas</span>
                                        </div>
                                        <i class="fas fa-chevron-down text-xs transition-transform" :class="{ 'rotate-180': open }"></i>
                                    </button>
                                    <div x-show="open" x-collapse class="ml-8 mt-1 space-y-1">
                                        <a href="{{ route('villa-areas.index') }}" class="flex items-center px-3 py-2 text-sm rounded-lg transition-colors {{ request()->routeIs('villa-areas.*') ? 'bg-purple-50 text-purple-600' : 'text-gray-600 hover:bg-gray-50' }}">
                                            Villa Areas
                                        </a>
                                        <a href="{{ route('villas.index') }}" class="flex items-center px-3 py-2 text-sm rounded-lg transition-colors {{ request()->routeIs('villas.*') ? 'bg-purple-50 text-purple-600' : 'text-gray-600 hover:bg-gray-50' }}">
                                            Villas
                                        </a>
                                    </div>
                                </div>

                                <!-- Tenant Group -->
                                <div x-data="{ open: {{ request()->routeIs('admin.tenants.*') || request()->routeIs('admin.rents.*') ? 'true' : 'false' }} }">
                                    <button @click="open = !open" class="w-full flex items-center justify-between px-3 py-2.5 text-sm font-medium rounded-lg transition-colors text-gray-700 hover:bg-gray-50">
                                        <div class="flex items-center">
                                            <i class="fas fa-key w-5 text-center"></i>
                                            <span class="ml-3">Tenant</span>
                                        </div>
                                        <i class="fas fa-chevron-down text-xs transition-transform" :class="{ 'rotate-180': open }"></i>
                                    </button>
                                    <div x-show="open" x-collapse class="ml-8 mt-1 space-y-1">
                                        <a href="{{ route('admin.tenants.index') }}" class="flex items-center px-3 py-2 text-sm rounded-lg transition-colors {{ request()->routeIs('admin.tenants.*') ? 'bg-purple-50 text-purple-600' : 'text-gray-600 hover:bg-gray-50' }}">
                                            Tenant
                                        </a>
                                        <a href="{{ route('admin.rents.index') }}" class="flex items-center px-3 py-2 text-sm rounded-lg transition-colors {{ request()->routeIs('admin.rents.*') ? 'bg-purple-50 text-purple-600' : 'text-gray-600 hover:bg-gray-50' }}">
                                            Rent
                                        </a>
                                    </div>
                                </div>
                            @endif

                            @if($isTenant)
                                <!-- Tenant Rent (for Tenant role only) -->
                                <a href="{{ route('tenant.rent') }}" class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('tenant.rent') ? 'bg-purple-50 text-purple-600' : 'text-gray-700 hover:bg-gray-50' }}">
                                    <i class="fas fa-money-bill-wave w-5 text-center"></i>
                                    <span class="ml-3">Rent</span>
                                </a>
                            @endif

                            @if($isAdmin || $isOwner || $isTenant || $isStaff)
                                <!-- Tickets -->
                                <a href="{{ route('complaints.index') }}" class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('complaints.*') ? 'bg-purple-50 text-purple-600' : 'text-gray-700 hover:bg-gray-50' }}">
                                    <i class="fas fa-ticket-alt w-5 text-center"></i>
                                    <span class="ml-3">Tickets</span>
                                </a>

{{-- Updated Facilities Menu v2.5 {{ now()->timestamp }} --}}
                                <!-- Facilities -->
                                <div x-data="{ open: {{ request()->routeIs('facilities.*') || request()->routeIs('facility-bookings.*') ? 'true' : 'false' }} }">
                                    <button @click="open = !open" class="w-full flex items-center justify-between px-3 py-2.5 text-sm font-medium rounded-lg transition-colors text-gray-700 hover:bg-gray-50">
                                        <div class="flex items-center">
                                            <i class="fas fa-swimming-pool w-5 text-center"></i>
                                            <span class="ml-3">Facilities</span>
                                        </div>
                                        <i class="fas fa-chevron-down text-xs transition-transform" :class="{ 'rotate-180': open }"></i>
                                    </button>
                                    <div x-show="open" x-collapse class="ml-8 mt-1 space-y-1">
                                        <a href="{{ route('facilities.index') }}" class="flex items-center px-3 py-2 text-sm rounded-lg transition-colors {{ request()->routeIs('facilities.*') ? 'bg-purple-50 text-purple-600' : 'text-gray-600 hover:bg-gray-50' }}">
                                            <i class="fas fa-cogs w-4 text-center mr-2"></i>
                                            Facilities
                                        </a>
                                        <a href="{{ route('facility-bookings.index') }}" class="flex items-center px-3 py-2 text-sm rounded-lg transition-colors {{ request()->routeIs('facility-bookings.*') ? 'bg-purple-50 text-purple-600' : 'text-gray-600 hover:bg-gray-50' }}">
                                            <i class="fas fa-calendar-check w-4 text-center mr-2"></i>
                                            Book Facilities
                                        </a>
                                    </div>
                                </div>

                                <!-- Visitors -->
                                <a href="{{ route('visitors.index') }}" class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('visitors.*') ? 'bg-purple-50 text-purple-600' : 'text-gray-700 hover:bg-gray-50' }}">
                                    <i class="fas fa-user-friends w-5 text-center"></i>
                                    <span class="ml-3">Visitors</span>
                                </a>

                                <!-- Services -->
                                <a href="{{ route('services.index') }}" class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('services.*') || request()->routeIs('service-providers.*') ? 'bg-purple-50 text-purple-600' : 'text-gray-700 hover:bg-gray-50' }}">
                                    <i class="fas fa-concierge-bell w-5 text-center"></i>
                                    <span class="ml-3">Services</span>
                                </a>

                                <!-- Notices -->
                                <a href="{{ route('notices.index') }}" class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('notices.*') ? 'bg-purple-50 text-purple-600' : 'text-gray-700 hover:bg-gray-50' }}">
                                    <i class="fas fa-bullhorn w-5 text-center"></i>
                                    <span class="ml-3">Notices</span>
                                </a>

                                <!-- Bills - HIDDEN FOR STAFF -->
                                @if(!$isStaff)
                                <a href="{{ route('payments.index') }}" class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('payments.*') ? 'bg-purple-50 text-purple-600' : 'text-gray-700 hover:bg-gray-50' }}">
                                    <i class="fas fa-file-invoice-dollar w-5 text-center"></i>
                                    <span class="ml-3">Bills</span>
                                </a>
                                @endif

                                <!-- Reports - HIDDEN FOR STAFF -->
                                @if(!$isStaff)
                                <div x-data="{ open: {{ request()->routeIs('reports.*') ? 'true' : 'false' }} }">
                                    <button @click="open = !open" class="w-full flex items-center justify-between px-3 py-2.5 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('reports.*') ? 'bg-purple-50 text-purple-600' : 'text-gray-700 hover:bg-gray-50' }}">
                                        <div class="flex items-center">
                                            <i class="fas fa-chart-bar w-5 text-center"></i>
                                            <span class="ml-3">Reports</span>
                                        </div>
                                        <i class="fas fa-chevron-down text-xs transition-transform" :class="{ 'rotate-180': open }"></i>
                                    </button>
                                    <div x-show="open" x-collapse class="ml-8 mt-1 space-y-1">
                                        <a href="{{ route('reports.maintenance') }}" class="flex items-center px-3 py-2 text-sm rounded-lg transition-colors {{ request()->routeIs('reports.maintenance') ? 'bg-purple-50 text-purple-600' : 'text-gray-600 hover:bg-gray-50' }}">
                                            <i class="fas fa-wrench w-4 text-center mr-2"></i>
                                            Maintenance Report
                                        </a>
                                        <a href="{{ route('reports.financial') }}" class="flex items-center px-3 py-2 text-sm rounded-lg transition-colors {{ request()->routeIs('reports.financial') ? 'bg-purple-50 text-purple-600' : 'text-gray-600 hover:bg-gray-50' }}">
                                            <i class="fas fa-chart-line w-4 text-center mr-2"></i>
                                            Financial Report
                                        </a>
                                    </div>
                                </div>
                                @endif

                                <!-- Events -->
                                <a href="{{ route('events.index') }}" class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('events.*') ? 'bg-purple-50 text-purple-600' : 'text-gray-700 hover:bg-gray-50' }}">
                                    <i class="fas fa-calendar-alt w-5 text-center"></i>
                                    <span class="ml-3">Events</span>
                                </a>
                            @endif

                            @if($isAdmin)
                                <div class="my-4 border-t border-gray-200"></div>
                                
                                <!-- Settings -->
                                <a href="{{ route('settings.index') }}" class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('settings.*') ? 'bg-purple-50 text-purple-600' : 'text-gray-700 hover:bg-gray-50' }}">
                                    <i class="fas fa-cog w-5 text-center"></i>
                                    <span class="ml-3">Settings</span>
                                </a>
                            @endif
                        </div>
                    </nav>

                    <!-- User Profile -->
                    <div class="border-t border-gray-200 p-4">
                        <div class="flex items-center">
                            <img class="h-10 w-10 rounded-full object-cover" src="https://ui-avatars.com/api/?name={{ urlencode(auth()->user()->name ?? 'User') }}&background=667eea&color=fff" alt="User">
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

                        <!-- Notifications -->
                        <button class="relative p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-lg transition-colors">
                            <i class="fas fa-bell text-lg"></i>
                            <span class="absolute top-1 right-1 block h-2 w-2 rounded-full bg-red-500 ring-2 ring-white"></span>
                        </button>

                        <!-- User Dropdown -->
                        <div class="relative" x-data="{ open: false }">
                            <button @click="open = !open" 
                                    class="flex items-center space-x-2 p-1.5 rounded-lg hover:bg-gray-100 transition-colors">
                                <img class="h-8 w-8 rounded-full object-cover ring-2 ring-gray-200" 
                                     src="https://ui-avatars.com/api/?name={{ urlencode(auth()->user()->name ?? 'User') }}&background=667eea&color=fff" 
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

    <!-- Laravel Session Messages (Hidden data for JavaScript) -->
    @if(session('success'))
        <div data-success-message="{{ session('success') }}" style="display: none;"></div>
    @endif
    
    @if(session('error'))
        <div data-error-message="{{ session('error') }}" style="display: none;"></div>
    @endif
    
    @if($errors->any())
        @foreach($errors->all() as $error)
            <div data-validation-error="{{ $error }}" style="display: none;"></div>
        @endforeach
    @endif

    <!-- Global Form Handler - CRITICAL FOR FORM SUBMISSIONS -->
    <script src="{{ asset('js/global-form-handler.js') }}"></script>
    
    <!-- Compiled App JavaScript (Pure Laravel - No Build System) -->
    <script src="{{ asset('js/app.js') }}"></script>
    
    <!-- Push Notifications -->
    <!-- Push Notifications DISABLED -->
    
    @stack('scripts')
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

