<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Super Admin') - {{ config('app.name') }}</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='75' font-size='75' fill='%237c3aed'>S</text></svg>">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-gray-100">
    <div class="flex h-screen overflow-hidden" x-data="{ mobileMenuOpen: false }">
        
        <!-- Desktop Sidebar -->
        <aside class="hidden lg:flex lg:flex-shrink-0">
            <div class="w-64 bg-white border-r border-gray-200 flex flex-col">
                <!-- Logo -->
                <div class="h-16 flex items-center px-6 border-b border-gray-200">
                    <a href="{{ route('super-admin.dashboard') }}" class="flex items-center">
                        <div class="w-10 h-10 rounded-lg flex items-center justify-center overflow-hidden">
                            <svg viewBox="0 0 100 100" class="w-full h-full">
                                <defs>
                                    <linearGradient id="superAdminLogoGradient" x1="0%" y1="0%" x2="100%" y2="100%">
                                        <stop offset="0%" style="stop-color:#667eea"/>
                                        <stop offset="100%" style="stop-color:#764ba2"/>
                                    </linearGradient>
                                </defs>
                                <rect x="0" y="0" width="100" height="100" rx="15" fill="url(#superAdminLogoGradient)"/>
                                <rect x="12" y="22" width="28" height="58" fill="white" opacity="0.9"/>
                                <rect x="16" y="28" width="6" height="6" fill="url(#superAdminLogoGradient)"/>
                                <rect x="24" y="28" width="6" height="6" fill="url(#superAdminLogoGradient)"/>
                                <rect x="32" y="28" width="6" height="6" fill="url(#superAdminLogoGradient)"/>
                                <rect x="16" y="38" width="6" height="6" fill="url(#superAdminLogoGradient)"/>
                                <rect x="24" y="38" width="6" height="6" fill="url(#superAdminLogoGradient)"/>
                                <rect x="32" y="38" width="6" height="6" fill="url(#superAdminLogoGradient)"/>
                                <rect x="16" y="48" width="6" height="6" fill="url(#superAdminLogoGradient)"/>
                                <rect x="24" y="48" width="6" height="6" fill="url(#superAdminLogoGradient)"/>
                                <rect x="32" y="48" width="6" height="6" fill="url(#superAdminLogoGradient)"/>
                                <polygon points="75,45 52,28 52,45 46,45 46,80 75,80 75,45" fill="white" opacity="0.9"/>
                                <polygon points="75,45 52,28 92,28 92,45" fill="white" opacity="0.7"/>
                                <rect x="58" y="55" width="10" height="10" fill="url(#superAdminLogoGradient)"/>
                                <rect x="58" y="68" width="10" height="12" fill="url(#superAdminLogoGradient)"/>
                                <path d="M8,72 Q28,62 50,72 T92,72 L92,85 Q72,75 50,85 T8,85 Z" fill="white" opacity="0.3"/>
                            </svg>
                        </div>
                        <div class="ml-3">
                            <span class="text-lg font-bold text-gray-800">Society</span><span class="text-lg font-bold text-purple-600">Flow</span>
                        </div>
                    </a>
                </div>

                <!-- Navigation -->
                <nav class="flex-1 overflow-y-auto py-4 px-3">
                    <div class="space-y-1">
                        <a href="{{ route('super-admin.dashboard') }}" 
                           class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('super-admin.dashboard') ? 'bg-purple-50 text-purple-600' : 'text-gray-700 hover:bg-gray-50' }}">
                            <i class="fas fa-tachometer-alt w-5 text-center"></i>
                            <span class="ml-3">Dashboard</span>
                        </a>

                        <a href="{{ route('super-admin.societies.index') }}" 
                           class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('super-admin.societies.*') ? 'bg-purple-50 text-purple-600' : 'text-gray-700 hover:bg-gray-50' }}">
                            <i class="fas fa-building w-5 text-center"></i>
                            <span class="ml-3">Society</span>
                        </a>

                        <a href="{{ route('super-admin.packages.index') }}" 
                           class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('super-admin.packages.*') ? 'bg-purple-50 text-purple-600' : 'text-gray-700 hover:bg-gray-50' }}">
                            <i class="fas fa-box w-5 text-center"></i>
                            <span class="ml-3">Packages</span>
                        </a>

                        <a href="{{ route('super-admin.billing.index') }}" 
                           class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('super-admin.billing.*') ? 'bg-purple-50 text-purple-600' : 'text-gray-700 hover:bg-gray-50' }}">
                            <i class="fas fa-credit-card w-5 text-center"></i>
                            <span class="ml-3">Billing</span>
                        </a>

                        <a href="{{ route('super-admin.landing-site.index') }}" 
                           class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('super-admin.landing-site.*') ? 'bg-purple-50 text-purple-600' : 'text-gray-700 hover:bg-gray-50' }}">
                            <i class="fas fa-globe w-5 text-center"></i>
                            <span class="ml-3">Landing Site</span>
                        </a>
                    </div>
                </nav>

                <!-- User Profile in Sidebar -->
                <div class="border-t border-gray-200 p-4">
                    <div class="flex items-center">
                        <img class="h-10 w-10 rounded-full object-cover" 
                             src="https://ui-avatars.com/api/?name={{ urlencode(auth()->user()->name ?? 'Super Admin') }}&background=667eea&color=fff" 
                             alt="User">
                        <div class="ml-3 flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900 truncate">{{ auth()->user()->name ?? 'Super Admin' }}</p>
                            <p class="text-xs text-gray-500 truncate">{{ auth()->user()->email ?? '' }}</p>
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
        </aside>

        <!-- Mobile Sidebar -->
        <div x-show="mobileMenuOpen" x-cloak class="fixed inset-0 z-40 lg:hidden">
            <div class="fixed inset-0 bg-gray-600 bg-opacity-75" @click="mobileMenuOpen = false"></div>
            <aside class="fixed inset-y-0 left-0 w-64 bg-white z-50 flex flex-col">
                <div class="absolute top-0 right-0 -mr-12 pt-2">
                    <button @click="mobileMenuOpen = false" class="ml-1 flex items-center justify-center h-10 w-10 rounded-full">
                        <i class="fas fa-times text-white text-xl"></i>
                    </button>
                </div>
                <!-- Same sidebar content for mobile -->
                <div class="h-16 flex items-center px-6 border-b border-gray-200">
                    <div class="flex items-center">
                        <div class="w-10 h-10 rounded-lg bg-purple-600 flex items-center justify-center">
                            <i class="fas fa-building text-white"></i>
                        </div>
                        <div class="ml-3">
                            <span class="text-lg font-bold text-gray-800">Society</span><span class="text-lg font-bold text-purple-600">Flow</span>
                        </div>
                    </div>
                </div>
                <nav class="flex-1 overflow-y-auto py-4 px-3">
                    <div class="space-y-1">
                        <a href="{{ route('super-admin.dashboard') }}" class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg text-gray-700 hover:bg-gray-50">
                            <i class="fas fa-tachometer-alt w-5"></i><span class="ml-3">Dashboard</span>
                        </a>
                        <a href="{{ route('super-admin.societies.index') }}" class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg text-gray-700 hover:bg-gray-50">
                            <i class="fas fa-building w-5"></i><span class="ml-3">Society</span>
                        </a>
                        <a href="{{ route('super-admin.packages.index') }}" class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg text-gray-700 hover:bg-gray-50">
                            <i class="fas fa-box w-5"></i><span class="ml-3">Packages</span>
                        </a>
                        <a href="{{ route('super-admin.billing.index') }}" class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg text-gray-700 hover:bg-gray-50">
                            <i class="fas fa-credit-card w-5"></i><span class="ml-3">Billing</span>
                        </a>
                        <a href="{{ route('super-admin.landing-site.index') }}" class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg text-gray-700 hover:bg-gray-50">
                            <i class="fas fa-globe w-5"></i><span class="ml-3">Landing Site</span>
                        </a>
                    </div>
                </nav>
            </aside>
        </div>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Header -->
            <header class="bg-white border-b border-gray-200 flex-shrink-0">
                <div class="flex items-center justify-between h-16 px-4 sm:px-6">
                    <!-- Left: Mobile menu + Page title -->
                    <div class="flex items-center">
                        <button @click="mobileMenuOpen = true" class="lg:hidden p-2 rounded-md text-gray-500 hover:text-gray-900 hover:bg-gray-100">
                            <i class="fas fa-bars text-xl"></i>
                        </button>
                        <div class="ml-4 lg:ml-0">
                            <h1 class="text-xl font-semibold text-gray-800">@yield('page-title', 'Dashboard')</h1>
                        </div>
                    </div>

                    <!-- Right: Actions -->
                    <div class="flex items-center space-x-4">
                        <!-- Date -->
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
                                     src="https://ui-avatars.com/api/?name={{ urlencode(auth()->user()->name ?? 'Super Admin') }}&background=667eea&color=fff" 
                                     alt="User">
                                <span class="hidden sm:block text-sm font-medium text-gray-700">{{ auth()->user()->name ?? 'Super Admin' }}</span>
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
                                 class="absolute right-0 mt-2 w-56 bg-white rounded-lg shadow-lg py-1 z-50 border border-gray-200">
                                
                                <!-- User Info -->
                                <div class="px-4 py-3 border-b border-gray-100">
                                    <p class="text-sm font-medium text-gray-900">{{ auth()->user()->name ?? 'Super Admin' }}</p>
                                    <p class="text-xs text-gray-500 truncate">{{ auth()->user()->email ?? '' }}</p>
                                </div>
                                
                                <!-- Menu Items -->
                                <a href="{{ route('profile.show') }}" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                    <i class="fas fa-user w-4 mr-3 text-gray-400"></i>
                                    My Profile
                                </a>
                                
                                <div class="border-t border-gray-100 my-1"></div>
                                
                                <!-- Logout -->
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
            <main class="flex-1 overflow-y-auto bg-gray-100 p-6">
                @if(session('success'))
                    <div class="bg-green-50 border-l-4 border-green-500 p-4 rounded-r-lg mb-4">
                        <div class="flex items-center">
                            <i class="fas fa-check-circle text-green-500 mr-3"></i>
                            <p class="text-sm font-medium text-green-800">{{ session('success') }}</p>
                        </div>
                    </div>
                @endif

                @if(session('error'))
                    <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded-r-lg mb-4">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-circle text-red-500 mr-3"></i>
                            <p class="text-sm font-medium text-red-800">{{ session('error') }}</p>
                        </div>
                    </div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>

    @stack('scripts')
</body>
</html>
