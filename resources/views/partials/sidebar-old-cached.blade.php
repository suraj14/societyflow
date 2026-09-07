{{-- Sidebar Navigation Partial - Permission-Based Access --}}
@php
    $user = auth()->user();
    $isAdmin = $user && $user->hasAnyRole(['Super Admin', 'Admin']);
    $isSuperAdmin = $user && $user->hasRole('Super Admin');
    $isApartmentOwner = $user && $user->hasRole('Apartment Owner');
    $isVillaOwner = $user && $user->hasRole('Villa Owner');
    $isOwner = $user && $user->hasAnyRole(['Villa Owner', 'Apartment Owner']);
    $isTenant = $user && $user->hasRole('Tenant');
    $isStaff = $user && $user->hasRole('Staff');
    
    // Debug: Log current user role for troubleshooting
    // dd($user->roles->pluck('name'), $isTenant, $isAdmin); // Uncomment this line to debug
    
    // Determine unit menu label and route based on role
    $unitMenuLabel = $isApartmentOwner ? 'Apartment' : 'Villa';
    $unitMenuRoute = $isApartmentOwner ? 'my-apartment' : 'my-villa';
    $unitMenuIcon = $isApartmentOwner ? 'fa-door-open' : 'fa-house-user';
    
    // Menu active states for collapsible menus
    $villaMenuActive = request()->routeIs('villa-areas.*') || request()->routeIs('villas.*') || request()->routeIs('admin.villa-assignments.*');
    $apartmentMenuActive = request()->routeIs('buildings.*') || request()->routeIs('flats.*') || request()->routeIs('admin.apartment-assignments.*');
@endphp

<div class="h-full flex flex-col">
    <!-- Logo -->
    <div class="h-16 flex items-center px-6 border-b border-gray-200">
        <a href="{{ route('dashboard') }}" class="flex items-center">
            <div class="w-10 h-10 rounded-lg flex items-center justify-center overflow-hidden">
                <svg viewBox="0 0 100 100" class="w-full h-full">
                    <defs>
                        <linearGradient id="sidebarLogoGradient{{ $id ?? '' }}" x1="0%" y1="0%" x2="100%" y2="100%">
                            <stop offset="0%" style="stop-color:#667eea"/>
                            <stop offset="100%" style="stop-color:#764ba2"/>
                        </linearGradient>
                    </defs>
                    <rect x="0" y="0" width="100" height="100" rx="15" fill="url(#sidebarLogoGradient{{ $id ?? '' }})"/>
                    <rect x="12" y="22" width="28" height="58" fill="white" opacity="0.9"/>
                    <rect x="16" y="28" width="6" height="6" fill="url(#sidebarLogoGradient{{ $id ?? '' }})"/>
                    <rect x="24" y="28" width="6" height="6" fill="url(#sidebarLogoGradient{{ $id ?? '' }})"/>
                    <rect x="32" y="28" width="6" height="6" fill="url(#sidebarLogoGradient{{ $id ?? '' }})"/>
                    <rect x="16" y="38" width="6" height="6" fill="url(#sidebarLogoGradient{{ $id ?? '' }})"/>
                    <rect x="24" y="38" width="6" height="6" fill="url(#sidebarLogoGradient{{ $id ?? '' }})"/>
                    <rect x="32" y="38" width="6" height="6" fill="url(#sidebarLogoGradient{{ $id ?? '' }})"/>
                    <rect x="16" y="48" width="6" height="6" fill="url(#sidebarLogoGradient{{ $id ?? '' }})"/>
                    <rect x="24" y="48" width="6" height="6" fill="url(#sidebarLogoGradient{{ $id ?? '' }})"/>
                    <rect x="32" y="48" width="6" height="6" fill="url(#sidebarLogoGradient{{ $id ?? '' }})"/>
                    <polygon points="75,45 52,28 52,45 46,45 46,80 75,80 75,45" fill="white" opacity="0.9"/>
                    <polygon points="75,45 52,28 92,28 92,45" fill="white" opacity="0.7"/>
                    <rect x="58" y="55" width="10" height="10" fill="url(#sidebarLogoGradient{{ $id ?? '' }})"/>
                    <rect x="58" y="68" width="10" height="12" fill="url(#sidebarLogoGradient{{ $id ?? '' }})"/>
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
            <!-- Dashboard - All Users -->
            <a href="{{ route('dashboard') }}" 
               class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('dashboard') ? 'bg-purple-50 text-purple-600' : 'text-gray-700 hover:bg-gray-50' }}">
                <i class="fas fa-home w-5 text-center"></i>
                <span class="ml-3">Dashboard</span>
            </a>

            {{-- TENANT MENU - Right after Dashboard for Tenant users --}}
            @if($isTenant)
                <div class="tenant-menu-container">
                    <button type="button" 
                            class="tenant-menu-toggle w-full flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('tenant.*') ? 'bg-purple-50 text-purple-600' : 'text-gray-700 hover:bg-gray-50' }}"
                            onclick="toggleTenantMenu()">
                        <i class="fas fa-key w-5 text-center"></i>
                        <span class="ml-3 flex-1 text-left">Tenant</span>
                        <i class="fas fa-chevron-down tenant-menu-arrow transition-transform duration-200"></i>
                    </button>
                    
                    <div class="tenant-submenu {{ request()->routeIs('tenant.*') ? '' : 'hidden' }} ml-6 mt-1 space-y-1">
                        <!-- Tenant Dashboard -->
                        <a href="{{ route('tenant.dashboard') }}" 
                           class="flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('tenant.dashboard') ? 'bg-purple-50 text-purple-600' : 'text-gray-600 hover:bg-gray-50' }}">
                            <i class="fas fa-tachometer-alt w-4 text-center"></i>
                            <span class="ml-3">Tenant</span>
                        </a>

                        <!-- Tenant Rent -->
                        <a href="{{ route('tenant.rent') }}" 
                           class="flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('tenant.rent') ? 'bg-purple-50 text-purple-600' : 'text-gray-600 hover:bg-gray-50' }}">
                            <i class="fas fa-money-bill-wave w-4 text-center"></i>
                            <span class="ml-3">Rent</span>
                        </a>
                    </div>
                </div>
            @endif

            {{-- ADMIN SECTION - Full access for Super Admin & Admin --}}
            @if($isAdmin)
                <!-- Societies - Super Admin Only -->
                @if($isSuperAdmin)
                <a href="{{ route('societies.index') }}" 
                   class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('societies.*') ? 'bg-purple-50 text-purple-600' : 'text-gray-700 hover:bg-gray-50' }}">
                    <i class="fas fa-building w-5 text-center"></i>
                    <span class="ml-3">Societies</span>
                </a>
                @endif

                <!-- Apartments Parent Menu -->
                <div class="apartment-menu-container">
                    <button type="button" 
                            class="apartment-menu-toggle w-full flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors {{ $apartmentMenuActive ? 'bg-purple-50 text-purple-600' : 'text-gray-700 hover:bg-gray-50' }}"
                            onclick="toggleApartmentMenu()">
                        <i class="fas fa-door-open w-5 text-center"></i>
                        <span class="ml-3 flex-1 text-left">Apartments</span>
                        <i class="fas fa-chevron-down apartment-menu-arrow transition-transform duration-200"></i>
                    </button>
                    
                    <div class="apartment-submenu {{ $apartmentMenuActive ? '' : 'hidden' }} ml-6 mt-1 space-y-1">
                        <!-- Buildings -->
                        <a href="{{ route('buildings.index') }}" 
                           class="flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('buildings.*') ? 'bg-purple-50 text-purple-600' : 'text-gray-600 hover:bg-gray-50' }}">
                            <i class="fas fa-city w-4 text-center"></i>
                            <span class="ml-3">Buildings</span>
                        </a>

                        <!-- Apartments -->
                        <a href="{{ route('flats.index') }}" 
                           class="flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('flats.*') ? 'bg-purple-50 text-purple-600' : 'text-gray-600 hover:bg-gray-50' }}">
                            <i class="fas fa-door-open w-4 text-center"></i>
                            <span class="ml-3">Apartments</span>
                        </a>

                        <!-- Apartment Assignments -->
                        <a href="{{ route('admin.apartment-assignments.index') }}" 
                           class="flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('admin.apartment-assignments.*') ? 'bg-purple-50 text-purple-600' : 'text-gray-600 hover:bg-gray-50' }}">
                            <i class="fas fa-user-tag w-4 text-center"></i>
                            <span class="ml-3">Apartment Assignments</span>
                        </a>
                    </div>
                </div>

                <!-- Villas Parent Menu -->
                <div class="villa-menu-container">
                    <button type="button" 
                            class="villa-menu-toggle w-full flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors {{ $villaMenuActive ? 'bg-purple-50 text-purple-600' : 'text-gray-700 hover:bg-gray-50' }}"
                            onclick="toggleVillaMenu()">
                        <i class="fas fa-house-user w-5 text-center"></i>
                        <span class="ml-3 flex-1 text-left">Villas</span>
                        <i class="fas fa-chevron-down villa-menu-arrow transition-transform duration-200"></i>
                    </button>
                    
                    <div class="villa-submenu {{ $villaMenuActive ? '' : 'hidden' }} ml-6 mt-1 space-y-1">
                        <!-- Villa Areas -->
                        <a href="{{ route('villa-areas.index') }}" 
                           class="flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('villa-areas.*') ? 'bg-purple-50 text-purple-600' : 'text-gray-600 hover:bg-gray-50' }}">
                            <i class="fas fa-map-marked-alt w-4 text-center"></i>
                            <span class="ml-3">Villa Areas</span>
                        </a>

                        <!-- Villas -->
                        <a href="{{ route('villas.index') }}" 
                           class="flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('villas.*') ? 'bg-purple-50 text-purple-600' : 'text-gray-600 hover:bg-gray-50' }}">
                            <i class="fas fa-home w-4 text-center"></i>
                            <span class="ml-3">Villas</span>
                        </a>

                        <!-- Villa Assignments -->
                        <a href="{{ route('admin.villa-assignments.index') }}" 
                           class="flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('admin.villa-assignments.*') ? 'bg-purple-50 text-purple-600' : 'text-gray-600 hover:bg-gray-50' }}">
                            <i class="fas fa-user-tag w-4 text-center"></i>
                            <span class="ml-3">Villa Assignments</span>
                        </a>
                    </div>
                </div>

                <!-- Tenant Management - Admin Section -->
                @if($isAdmin)
                    @php
                        $tenantMenuActive = request()->routeIs('admin.tenants.*') || request()->routeIs('admin.rents.*') || request()->routeIs('admin.tenant-assignments.*');
                    @endphp
                    <div class="tenant-menu-container">
                        <button type="button" 
                                class="tenant-menu-toggle w-full flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors {{ $tenantMenuActive ? 'bg-purple-50 text-purple-600' : 'text-gray-700 hover:bg-gray-50' }}"
                                onclick="toggleTenantMenu()">
                            <i class="fas fa-key w-5 text-center"></i>
                            <span class="ml-3 flex-1 text-left">Tenant</span>
                            <i class="fas fa-chevron-down tenant-menu-arrow transition-transform duration-200"></i>
                        </button>
                        
                        <div class="tenant-submenu {{ $tenantMenuActive ? '' : 'hidden' }} ml-6 mt-1 space-y-1">
                            <!-- Admin Tenant Management -->
                            <a href="{{ route('admin.tenants.index') }}" 
                               class="flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('admin.tenants.*') ? 'bg-purple-50 text-purple-600' : 'text-gray-600 hover:bg-gray-50' }}">
                                <i class="fas fa-user w-4 text-center"></i>
                                <span class="ml-3">Tenant</span>
                            </a>

                            <!-- Admin Rent Management -->
                            <a href="{{ route('admin.rents.index') }}" 
                               class="flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('admin.rents.*') ? 'bg-purple-50 text-purple-600' : 'text-gray-600 hover:bg-gray-50' }}">
                                <i class="fas fa-money-bill-wave w-4 text-center"></i>
                                <span class="ml-3">Rent</span>
                            </a>

                            <!-- Admin Tenant Assignment Management -->
                            <a href="{{ route('admin.tenant-assignments.index') }}" 
                               class="flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('admin.tenant-assignments.*') ? 'bg-purple-50 text-purple-600' : 'text-gray-600 hover:bg-gray-50' }}">
                                <i class="fas fa-user-tag w-4 text-center"></i>
                                <span class="ml-3">Tenant Assignments</span>
                            </a>
                        </div>
                    </div>
                @endif

                <!-- Residents -->
                <a href="{{ route('residents.index') }}" 
                   class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('residents.*') ? 'bg-purple-50 text-purple-600' : 'text-gray-700 hover:bg-gray-50' }}">
                    <i class="fas fa-users w-5 text-center"></i>
                    <span class="ml-3">Residents</span>
                </a>

                <!-- Bills Management -->
                <a href="{{ route('payments.index') }}" 
                   class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('payments.*') ? 'bg-purple-50 text-purple-600' : 'text-gray-700 hover:bg-gray-50' }}">
                    <i class="fas fa-file-invoice-dollar w-5 text-center"></i>
                    <span class="ml-3">Bills</span>
                </a>

                <!-- Facilities Management -->
                <a href="{{ route('facilities.index') }}" 
                   class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('facilities.*') ? 'bg-purple-50 text-purple-600' : 'text-gray-700 hover:bg-gray-50' }}">
                    <i class="fas fa-swimming-pool w-5 text-center"></i>
                    <span class="ml-3">Facilities</span>
                </a>

                <!-- Service Providers -->
                <a href="{{ route('service-providers.index') }}" 
                   class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('service-providers.*') ? 'bg-purple-50 text-purple-600' : 'text-gray-700 hover:bg-gray-50' }}">
                    <i class="fas fa-briefcase w-5 text-center"></i>
                    <span class="ml-3">Service Providers</span>
                </a>
            @endif

            {{-- TICKETS - All users with permission --}}
            @if(!$isTenant)
                @can('view_all_tickets')
                <a href="{{ route('complaints.index') }}" 
                   class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('complaints.*') ? 'bg-purple-50 text-purple-600' : 'text-gray-700 hover:bg-gray-50' }}">
                    <i class="fas fa-ticket-alt w-5 text-center"></i>
                    <span class="ml-3">Tickets</span>
                </a>
                @elsecan('view_own_ticket')
                <a href="{{ route('complaints.index') }}" 
                   class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('complaints.*') ? 'bg-purple-50 text-purple-600' : 'text-gray-700 hover:bg-gray-50' }}">
                    <i class="fas fa-ticket-alt w-5 text-center"></i>
                    <span class="ml-3">Tickets</span>
                </a>
                @endcan
            @else
                {{-- TENANT TICKETS --}}
                @can('view_own_ticket')
                <a href="{{ route('complaints.index') }}" 
                   class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('complaints.*') ? 'bg-purple-50 text-purple-600' : 'text-gray-700 hover:bg-gray-50' }}">
                    <i class="fas fa-ticket-alt w-5 text-center"></i>
                    <span class="ml-3">Tickets</span>
                </a>
                @endcan
            @endif

            {{-- AMENITIES/FACILITIES BOOKING - Owners, Tenants, Staff --}}
            @if(!$isTenant)
                @can('book_facility')
                <a href="{{ route('facilities.index') }}" 
                   class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('facilities.*') || request()->routeIs('facility-bookings.*') ? 'bg-purple-50 text-purple-600' : 'text-gray-700 hover:bg-gray-50' }}">
                    <i class="fas fa-swimming-pool w-5 text-center"></i>
                    <span class="ml-3">Amenities</span>
                </a>
                @endcan
            @else
                {{-- TENANT AMENITIES --}}
                @can('book_facility')
                <a href="{{ route('facilities.index') }}" 
                   class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('facilities.*') || request()->routeIs('facility-bookings.*') ? 'bg-purple-50 text-purple-600' : 'text-gray-700 hover:bg-gray-50' }}">
                    <i class="fas fa-swimming-pool w-5 text-center"></i>
                    <span class="ml-3">Amenities</span>
                </a>
                @endcan
            @endif

            {{-- VISITORS - Admin, Owners, Staff --}}
            @if(!$isTenant)
                @can('view_all_visitors')
                <a href="{{ route('visitors.index') }}" 
                   class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('visitors.*') ? 'bg-purple-50 text-purple-600' : 'text-gray-700 hover:bg-gray-50' }}">
                    <i class="fas fa-user-friends w-5 text-center"></i>
                    <span class="ml-3">Visitors</span>
                </a>
                @elsecan('view_own_visitors')
                <a href="{{ route('visitors.index') }}" 
                   class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('visitors.*') ? 'bg-purple-50 text-purple-600' : 'text-gray-700 hover:bg-gray-50' }}">
                    <i class="fas fa-user-friends w-5 text-center"></i>
                    <span class="ml-3">Visitors</span>
                </a>
                @endcan
            @else
                {{-- TENANT VISITORS --}}
                @can('view_own_visitors')
                <a href="{{ route('visitors.index') }}" 
                   class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('visitors.*') ? 'bg-purple-50 text-purple-600' : 'text-gray-700 hover:bg-gray-50' }}">
                    <i class="fas fa-user-friends w-5 text-center"></i>
                    <span class="ml-3">Visitors</span>
                </a>
                @endcan
            @endif

            {{-- SERVICES - All users --}}
            @if(!$isTenant)
                @can('view_services')
                <a href="{{ route('services.index') }}" 
                   class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('services.*') ? 'bg-purple-50 text-purple-600' : 'text-gray-700 hover:bg-gray-50' }}">
                    <i class="fas fa-concierge-bell w-5 text-center"></i>
                    <span class="ml-3">Services</span>
                </a>
                @endcan
            @else
                {{-- TENANT SERVICES --}}
                @can('view_services')
                <a href="{{ route('services.index') }}" 
                   class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('services.*') ? 'bg-purple-50 text-purple-600' : 'text-gray-700 hover:bg-gray-50' }}">
                    <i class="fas fa-concierge-bell w-5 text-center"></i>
                    <span class="ml-3">Services</span>
                </a>
                @endcan
            @endif

            {{-- NOTICES - All users --}}
            @if(!$isTenant)
                @can('view_notices')
                <a href="{{ route('notices.index') }}" 
                   class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('notices.*') ? 'bg-purple-50 text-purple-600' : 'text-gray-700 hover:bg-gray-50' }}">
                    <i class="fas fa-bullhorn w-5 text-center"></i>
                    <span class="ml-3">Notices</span>
                </a>
                @endcan
            @else
                {{-- TENANT NOTICES --}}
                @can('view_notices')
                <a href="{{ route('notices.index') }}" 
                   class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('notices.*') ? 'bg-purple-50 text-purple-600' : 'text-gray-700 hover:bg-gray-50' }}">
                    <i class="fas fa-bullhorn w-5 text-center"></i>
                    <span class="ml-3">Notices</span>
                </a>
                @endcan
            @endif

            {{-- BILLS - Owners & Tenants --}}
            @if(!$isTenant)
                @can('view_own_bills')
                <a href="{{ route('payments.index') }}" 
                   class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('payments.*') ? 'bg-purple-50 text-purple-600' : 'text-gray-700 hover:bg-gray-50' }}">
                    <i class="fas fa-file-invoice-dollar w-5 text-center"></i>
                    <span class="ml-3">Bills</span>
                </a>
                @endcan
            @else
                {{-- TENANT BILLS --}}
                @can('view_own_bills')
                <a href="{{ route('payments.index') }}" 
                   class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('payments.*') ? 'bg-purple-50 text-purple-600' : 'text-gray-700 hover:bg-gray-50' }}">
                    <i class="fas fa-file-invoice-dollar w-5 text-center"></i>
                    <span class="ml-3">Bills</span>
                </a>
                @endcan
            @endif

            {{-- UNIT MENU - Apartment Owner or Villa Owner (SAME POSITION, DIFFERENT LABEL) --}}
            @if($isOwner && !$isTenant)
            <a href="{{ route($unitMenuRoute) }}" 
               class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs($unitMenuRoute) ? 'bg-purple-50 text-purple-600' : 'text-gray-700 hover:bg-gray-50' }}">
                <i class="fas {{ $unitMenuIcon }} w-5 text-center"></i>
                <span class="ml-3">{{ $unitMenuLabel }}</span>
            </a>
            @endif

            {{-- VILLA OWNER SPECIFIC MENUS --}}
            @if($isVillaOwner && !$isTenant)
                <!-- My Bookings -->
                <a href="{{ route('villa-owner.bookings') }}" 
                   class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('villa-owner.bookings*') ? 'bg-purple-50 text-purple-600' : 'text-gray-700 hover:bg-gray-50' }}">
                    <i class="fas fa-calendar-check w-5 text-center"></i>
                    <span class="ml-3">My Bookings</span>
                </a>

                <!-- Raise Request -->
                <a href="{{ route('villa-owner.requests') }}" 
                   class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('villa-owner.requests*') ? 'bg-purple-50 text-purple-600' : 'text-gray-700 hover:bg-gray-50' }}">
                    <i class="fas fa-tools w-5 text-center"></i>
                    <span class="ml-3">Raise Request</span>
                </a>
            @endif

            {{-- ADMIN ONLY SECTION --}}
            @if($isAdmin && !$isTenant)
                <!-- Divider -->
                <div class="my-4 border-t border-gray-200"></div>

                <!-- Reports -->
                @can('view_reports')
                <a href="#" 
                   class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors text-gray-700 hover:bg-gray-50">
                    <i class="fas fa-chart-bar w-5 text-center"></i>
                    <span class="ml-3">Reports</span>
                </a>
                @endcan

                {{-- SUPER ADMIN LINK --}}
                @if($isSuperAdmin)
                <a href="{{ route('super-admin.dashboard') }}" 
                   class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('super-admin.*') ? 'bg-purple-50 text-purple-600' : 'text-gray-700 hover:bg-gray-50' }}">
                    <i class="fas fa-user-shield w-5 text-center"></i>
                    <span class="ml-3">Super Admin</span>
                </a>
                @endif

                <!-- Settings -->
                @can('access_settings')
                <a href="{{ route('settings.index') }}" 
                   class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('settings.*') ? 'bg-purple-50 text-purple-600' : 'text-gray-700 hover:bg-gray-50' }}">
                    <i class="fas fa-cog w-5 text-center"></i>
                    <span class="ml-3">Settings</span>
                </a>
                @endcan
            @endif
        </div>
    </nav>

    <!-- User Profile -->
    <div class="border-t border-gray-200 p-4">
        <div class="flex items-center">
            <img class="h-10 w-10 rounded-full object-cover" 
                 src="https://ui-avatars.com/api/?name={{ urlencode(auth()->user()->name ?? 'User') }}&background=667eea&color=fff" 
                 alt="User">
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

<script>
function toggleVillaMenu() {
    const submenu = document.querySelector('.villa-submenu');
    const arrow = document.querySelector('.villa-menu-arrow');
    
    if (submenu.classList.contains('hidden')) {
        submenu.classList.remove('hidden');
        arrow.style.transform = 'rotate(180deg)';
    } else {
        submenu.classList.add('hidden');
        arrow.style.transform = 'rotate(0deg)';
    }
}

function toggleApartmentMenu() {
    const submenu = document.querySelector('.apartment-submenu');
    const arrow = document.querySelector('.apartment-menu-arrow');
    
    if (submenu.classList.contains('hidden')) {
        submenu.classList.remove('hidden');
        arrow.style.transform = 'rotate(180deg)';
    } else {
        submenu.classList.add('hidden');
        arrow.style.transform = 'rotate(0deg)';
    }
}

function toggleTenantMenu() {
    const submenu = document.querySelector('.tenant-submenu');
    const arrow = document.querySelector('.tenant-menu-arrow');
    
    if (submenu.classList.contains('hidden')) {
        submenu.classList.remove('hidden');
        arrow.style.transform = 'rotate(180deg)';
    } else {
        submenu.classList.add('hidden');
        arrow.style.transform = 'rotate(0deg)';
    }
}

// Auto-expand menus if any related route is active
document.addEventListener('DOMContentLoaded', function() {
    const villaMenuActive = {{ $villaMenuActive ? 'true' : 'false' }};
    if (villaMenuActive) {
        const submenu = document.querySelector('.villa-submenu');
        const arrow = document.querySelector('.villa-menu-arrow');
        if (submenu && arrow) {
            submenu.classList.remove('hidden');
            arrow.style.transform = 'rotate(180deg)';
        }
    }
    
    const apartmentMenuActive = {{ $apartmentMenuActive ? 'true' : 'false' }};
    if (apartmentMenuActive) {
        const submenu = document.querySelector('.apartment-submenu');
        const arrow = document.querySelector('.apartment-menu-arrow');
        if (submenu && arrow) {
            submenu.classList.remove('hidden');
            arrow.style.transform = 'rotate(180deg)';
        }
    }
    
    @if($isAdmin)
    const adminTenantMenuActive = {{ (request()->routeIs('admin.tenants.*') || request()->routeIs('admin.rents.*') || request()->routeIs('admin.tenant-assignments.*')) ? 'true' : 'false' }};
    if (adminTenantMenuActive) {
        const submenu = document.querySelector('.tenant-submenu');
        const arrow = document.querySelector('.tenant-menu-arrow');
        if (submenu && arrow) {
            submenu.classList.remove('hidden');
            arrow.style.transform = 'rotate(180deg)';
        }
    }
    @endif
    
    @if($isTenant)
    const tenantMenuActive = {{ request()->routeIs('tenant.*') ? 'true' : 'false' }};
    if (tenantMenuActive) {
        const submenu = document.querySelector('.tenant-submenu');
        const arrow = document.querySelector('.tenant-menu-arrow');
        if (submenu && arrow) {
            submenu.classList.remove('hidden');
            arrow.style.transform = 'rotate(180deg)';
        }
    }
    @endif
});
</script>
