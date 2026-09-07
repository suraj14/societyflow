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
    
    <!-- Mobile Sidebar - SAME AS DESKTOP WITH SUBMENUS -->
    <aside class="fixed inset-y-0 left-0 w-64 bg-white z-50 flex flex-col overflow-y-auto"
           x-transition:enter="transition ease-in-out duration-300 transform"
           x-transition:enter-start="-translate-x-full"
           x-transition:enter-end="translate-x-0"
           x-transition:leave="transition ease-in-out duration-300 transform"
           x-transition:leave-start="translate-x-0"
           x-transition:leave-end="-translate-x-full">
        
        <div class="h-full flex flex-col">
            <!-- Logo -->
            <div class="h-16 flex items-center px-6 border-b border-gray-200 sidebar-logo">
                <a href="{{ route('dashboard') }}" @click="mobileMenuOpen = false" class="flex items-center">
                    <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-purple-600 to-purple-800 flex items-center justify-center">
                        <span class="text-white font-bold text-lg">S</span>
                    </div>
                    <div class="ml-3">
                        <span class="text-lg font-bold text-gray-800">Society</span><span class="text-lg font-bold text-purple-600">Flow</span>
                    </div>
                </a>
            </div>

            <!-- Navigation - SAME AS DESKTOP SIDEBAR WITH SUBMENUS -->
            <nav class="flex-1 overflow-y-auto py-4 px-3 sidebar-nav">
                <div class="space-y-1">
                    @php
                        use App\Models\SystemSetting;
                        
                        $user = auth()->user();
                        $isAdmin = $user && $user->hasAnyRole(['Admin', 'Society Admin']);
                        $isSuperAdmin = $user && $user->hasRole('Super Admin');
                        $isOwner = $user && $user->hasAnyRole(['Villa Owner', 'Apartment Owner', 'Owner']);
                        $isTenant = $user && $user->hasRole('Tenant');
                        $isStaff = $user && $user->hasRole('Staff');
                        $isAccountant = $user && $user->hasRole('Accountant');
                        
                        $societyId = $user ? $user->society_id : null;
                        
                        $enableFacilities = SystemSetting::get('features', 'enable_facilities', true, $societyId);
                        $enableVisitors = SystemSetting::get('features', 'enable_visitors', true, $societyId);
                        $enableComplaints = SystemSetting::get('features', 'enable_complaints', true, $societyId);
                        $enableBills = SystemSetting::get('features', 'enable_bills', true, $societyId);
                        $enableServices = SystemSetting::get('features', 'enable_services', true, $societyId);
                        $enableNotices = SystemSetting::get('features', 'enable_notices', true, $societyId);
                        $enableReports = SystemSetting::get('features', 'enable_reports', true, $societyId);
                    @endphp

                    <!-- Dashboard - All Users -->
                    <a href="{{ route('dashboard') }}" @click="mobileMenuOpen = false"
                       class="nav-item flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('dashboard') ? 'bg-purple-50 text-purple-600 active' : 'text-gray-700 hover:bg-gray-50' }}">
                        <i class="fas fa-home w-5 text-center"></i>
                        <span class="ml-3">Dashboard</span>
                    </a>

                    {{-- STAFF SECTION - LIMITED ACCESS --}}
                    @if($isStaff)
                        {{-- Visitors - CORE FEATURE for Staff --}}
                        @if($enableVisitors)
                        <a href="{{ route('visitors.index') }}" @click="mobileMenuOpen = false"
                           class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('visitors.*') ? 'bg-purple-50 text-purple-600' : 'text-gray-700 hover:bg-gray-50' }}">
                            <i class="fas fa-user-friends w-5 text-center"></i>
                            <span class="ml-3">Visitors</span>
                        </a>
                        @endif

                        {{-- Tickets - View and Update Only --}}
                        @if($enableComplaints)
                        <a href="{{ route('complaints.index') }}" @click="mobileMenuOpen = false"
                           class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('complaints.*') ? 'bg-purple-50 text-purple-600' : 'text-gray-700 hover:bg-gray-50' }}">
                            <i class="fas fa-ticket-alt w-5 text-center"></i>
                            <span class="ml-3">Tickets</span>
                        </a>
                        @endif

                        {{-- Services - View Only --}}
                        @if($enableServices)
                        <a href="{{ route('services.index') }}" @click="mobileMenuOpen = false"
                           class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('services.*') ? 'bg-purple-50 text-purple-600' : 'text-gray-700 hover:bg-gray-50' }}">
                            <i class="fas fa-concierge-bell w-5 text-center"></i>
                            <span class="ml-3">Services</span>
                        </a>
                        @endif

                        {{-- Events - Read Only --}}
                        @if(SystemSetting::get('features', 'enable_events', false, $societyId))
                        <a href="{{ route('events.index') }}" @click="mobileMenuOpen = false"
                           class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('events.*') ? 'bg-purple-50 text-purple-600' : 'text-gray-700 hover:bg-gray-50' }}">
                            <i class="fas fa-calendar-alt w-5 text-center"></i>
                            <span class="ml-3">Events</span>
                        </a>
                        @endif

                        {{-- Notices - Read Only --}}
                        @if($enableNotices)
                        <a href="{{ route('notices.index') }}" @click="mobileMenuOpen = false"
                           class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('notices.*') ? 'bg-purple-50 text-purple-600' : 'text-gray-700 hover:bg-gray-50' }}">
                            <i class="fas fa-bullhorn w-5 text-center"></i>
                            <span class="ml-3">Notices</span>
                        </a>
                        @endif
                    @endif

                    {{-- NON-STAFF SECTIONS --}}
                    @if(!$isStaff)
                        {{-- ADMIN SECTION --}}
                        @if($isAdmin)
                            <!-- User Management -->
                            <a href="{{ route('users.index') }}" @click="mobileMenuOpen = false"
                               class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('users.*') ? 'bg-purple-50 text-purple-600' : 'text-gray-700 hover:bg-gray-50' }}">
                                <i class="fas fa-users w-5 text-center"></i>
                                <span class="ml-3">Users</span>
                            </a>

                            <!-- Owners -->
                            <a href="{{ route('owners.index') }}" @click="mobileMenuOpen = false"
                               class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('owners.*') ? 'bg-purple-50 text-purple-600' : 'text-gray-700 hover:bg-gray-50' }}">
                                <i class="fas fa-user-tie w-5 text-center"></i>
                                <span class="ml-3">Owners</span>
                            </a>

                            <!-- Apartments Collapsible -->
                            <div x-data="{ open: {{ request()->routeIs('buildings.*') || request()->routeIs('flats.*') ? 'true' : 'false' }} }">
                                <button @click="open = !open" class="w-full flex items-center justify-between px-3 py-2.5 text-sm font-medium rounded-lg transition-colors text-gray-700 hover:bg-gray-50">
                                    <div class="flex items-center">
                                        <i class="fas fa-building w-5 text-center"></i>
                                        <span class="ml-3">Apartments</span>
                                    </div>
                                    <i class="fas fa-chevron-down text-xs transition-transform" :class="{ 'rotate-180': open }"></i>
                                </button>
                                <div x-show="open" x-collapse class="ml-8 mt-1 space-y-1">
                                    <a href="{{ route('buildings.index') }}" @click="mobileMenuOpen = false" class="flex items-center px-3 py-2 text-sm rounded-lg transition-colors {{ request()->routeIs('buildings.*') ? 'bg-purple-50 text-purple-600' : 'text-gray-600 hover:bg-gray-50' }}">
                                        Buildings
                                    </a>
                                    <a href="{{ route('flats.index') }}" @click="mobileMenuOpen = false" class="flex items-center px-3 py-2 text-sm rounded-lg transition-colors {{ request()->routeIs('flats.*') ? 'bg-purple-50 text-purple-600' : 'text-gray-600 hover:bg-gray-50' }}">
                                        Apartments
                                    </a>
                                </div>
                            </div>

                            <!-- Villas Collapsible -->
                            <div x-data="{ open: {{ request()->routeIs('villa-areas.*') || request()->routeIs('villas.*') ? 'true' : 'false' }} }">
                                <button @click="open = !open" class="w-full flex items-center justify-between px-3 py-2.5 text-sm font-medium rounded-lg transition-colors text-gray-700 hover:bg-gray-50">
                                    <div class="flex items-center">
                                        <i class="fas fa-house-user w-5 text-center"></i>
                                        <span class="ml-3">Villas</span>
                                    </div>
                                    <i class="fas fa-chevron-down text-xs transition-transform" :class="{ 'rotate-180': open }"></i>
                                </button>
                                <div x-show="open" x-collapse class="ml-8 mt-1 space-y-1">
                                    <a href="{{ route('villa-areas.index') }}" @click="mobileMenuOpen = false" class="flex items-center px-3 py-2 text-sm rounded-lg transition-colors {{ request()->routeIs('villa-areas.*') ? 'bg-purple-50 text-purple-600' : 'text-gray-600 hover:bg-gray-50' }}">
                                        Villa Areas
                                    </a>
                                    <a href="{{ route('villas.index') }}" @click="mobileMenuOpen = false" class="flex items-center px-3 py-2 text-sm rounded-lg transition-colors {{ request()->routeIs('villas.*') ? 'bg-purple-50 text-purple-600' : 'text-gray-600 hover:bg-gray-50' }}">
                                        Villas
                                    </a>
                                </div>
                            </div>

                            <!-- Tenant Collapsible -->
                            <div x-data="{ open: {{ request()->routeIs('admin.tenants.*') || request()->routeIs('admin.rents.*') ? 'true' : 'false' }} }">
                                <button @click="open = !open" class="w-full flex items-center justify-between px-3 py-2.5 text-sm font-medium rounded-lg transition-colors text-gray-700 hover:bg-gray-50">
                                    <div class="flex items-center">
                                        <i class="fas fa-key w-5 text-center"></i>
                                        <span class="ml-3">Tenant</span>
                                    </div>
                                    <i class="fas fa-chevron-down text-xs transition-transform" :class="{ 'rotate-180': open }"></i>
                                </button>
                                <div x-show="open" x-collapse class="ml-8 mt-1 space-y-1">
                                    <a href="{{ route('admin.tenants.index') }}" @click="mobileMenuOpen = false" class="flex items-center px-3 py-2 text-sm rounded-lg transition-colors {{ request()->routeIs('admin.tenants.*') ? 'bg-purple-50 text-purple-600' : 'text-gray-600 hover:bg-gray-50' }}">
                                        Tenant
                                    </a>
                                    <a href="{{ route('admin.rents.index') }}" @click="mobileMenuOpen = false" class="flex items-center px-3 py-2 text-sm rounded-lg transition-colors {{ request()->routeIs('admin.rents.*') ? 'bg-purple-50 text-purple-600' : 'text-gray-600 hover:bg-gray-50' }}">
                                        Rent
                                    </a>
                                </div>
                            </div>
                        @endif

                        {{-- TICKETS --}}
                        @if(($isAdmin || $isOwner || $isTenant) && $enableComplaints)
                        <a href="{{ route('complaints.index') }}" @click="mobileMenuOpen = false"
                           class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('complaints.*') ? 'bg-purple-50 text-purple-600' : 'text-gray-700 hover:bg-gray-50' }}">
                            <i class="fas fa-ticket-alt w-5 text-center"></i>
                            <span class="ml-3">Tickets</span>
                        </a>
                        @endif

                        {{-- FACILITIES COLLAPSIBLE --}}
                        @if(($isAdmin || $isOwner || $isTenant) && $enableFacilities)
                        <div x-data="{ open: {{ request()->routeIs('facilities.*') || request()->routeIs('facility-bookings.*') ? 'true' : 'false' }} }">
                            <button @click="open = !open" class="w-full flex items-center justify-between px-3 py-2.5 text-sm font-medium rounded-lg transition-colors text-gray-700 hover:bg-gray-50">
                                <div class="flex items-center">
                                    <i class="fas fa-swimming-pool w-5 text-center"></i>
                                    <span class="ml-3">Facilities</span>
                                </div>
                                <i class="fas fa-chevron-down text-xs transition-transform" :class="{ 'rotate-180': open }"></i>
                            </button>
                            <div x-show="open" x-collapse class="ml-8 mt-1 space-y-1">
                                <a href="{{ route('facilities.index') }}" @click="mobileMenuOpen = false" class="flex items-center px-3 py-2 text-sm rounded-lg transition-colors {{ request()->routeIs('facilities.*') ? 'bg-purple-50 text-purple-600' : 'text-gray-600 hover:bg-gray-50' }}">
                                    <i class="fas fa-cogs w-4 text-center mr-2"></i>
                                    Facilities
                                </a>
                                <a href="{{ route('facility-bookings.index') }}" @click="mobileMenuOpen = false" class="flex items-center px-3 py-2 text-sm rounded-lg transition-colors {{ request()->routeIs('facility-bookings.*') ? 'bg-purple-50 text-purple-600' : 'text-gray-600 hover:bg-gray-50' }}">
                                    <i class="fas fa-calendar-check w-4 text-center mr-2"></i>
                                    Book Facilities
                                </a>
                            </div>
                        </div>
                        @endif

                        {{-- VISITORS --}}
                        @if(($isAdmin || $isOwner) && $enableVisitors)
                        <a href="{{ route('visitors.index') }}" @click="mobileMenuOpen = false"
                           class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('visitors.*') ? 'bg-purple-50 text-purple-600' : 'text-gray-700 hover:bg-gray-50' }}">
                            <i class="fas fa-user-friends w-5 text-center"></i>
                            <span class="ml-3">Visitors</span>
                        </a>
                        @endif

                        {{-- SERVICES --}}
                        @if(($isAdmin || $isOwner || $isTenant) && $enableServices)
                        <a href="{{ route('services.index') }}" @click="mobileMenuOpen = false"
                           class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('services.*') ? 'bg-purple-50 text-purple-600' : 'text-gray-700 hover:bg-gray-50' }}">
                            <i class="fas fa-concierge-bell w-5 text-center"></i>
                            <span class="ml-3">Services</span>
                        </a>
                        @endif

                        {{-- NOTICES --}}
                        @if(($isAdmin || $isOwner || $isTenant) && $enableNotices)
                        <a href="{{ route('notices.index') }}" @click="mobileMenuOpen = false"
                           class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('notices.*') ? 'bg-purple-50 text-purple-600' : 'text-gray-700 hover:bg-gray-50' }}">
                            <i class="fas fa-bullhorn w-5 text-center"></i>
                            <span class="ml-3">Notices</span>
                        </a>
                        @endif

                        {{-- BILLS - Admin, Owner, Tenant, Accountant (HIDDEN FOR STAFF) --}}
                        @if(($isAccountant || $isAdmin || $isOwner || $isTenant) && !$isStaff)
                        <a href="{{ route('payments.index') }}" @click="mobileMenuOpen = false"
                           class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('payments.*') || request()->routeIs('utility-bills.*') ? 'bg-purple-50 text-purple-600' : 'text-gray-700 hover:bg-gray-50' }}">
                            <i class="fas fa-file-invoice-dollar w-5 text-center"></i>
                            <span class="ml-3">Bills</span>
                        </a>
                        @endif

                        {{-- REPORTS COLLAPSIBLE - Admin, Tenant only (HIDDEN FOR STAFF) --}}
                        @if(($isAdmin || $isTenant) && !$isStaff)
                        <div x-data="{ open: {{ request()->routeIs('reports.*') ? 'true' : 'false' }} }">
                            <button @click="open = !open" class="w-full flex items-center justify-between px-3 py-2.5 text-sm font-medium rounded-lg transition-colors text-gray-700 hover:bg-gray-50">
                                <div class="flex items-center">
                                    <i class="fas fa-chart-bar w-5 text-center"></i>
                                    <span class="ml-3">Reports</span>
                                </div>
                                <i class="fas fa-chevron-down text-xs transition-transform" :class="{ 'rotate-180': open }"></i>
                            </button>
                            <div x-show="open" x-collapse class="ml-8 mt-1 space-y-1">
                                <a href="{{ route('reports.maintenance') }}" @click="mobileMenuOpen = false" class="flex items-center px-3 py-2 text-sm rounded-lg transition-colors {{ request()->routeIs('reports.maintenance') ? 'bg-purple-50 text-purple-600' : 'text-gray-600 hover:bg-gray-50' }}">
                                    <i class="fas fa-wrench w-4 text-center mr-2"></i>
                                    Maintenance Report
                                </a>
                                <a href="{{ route('reports.financial') }}" @click="mobileMenuOpen = false" class="flex items-center px-3 py-2 text-sm rounded-lg transition-colors {{ request()->routeIs('reports.financial') ? 'bg-purple-50 text-purple-600' : 'text-gray-600 hover:bg-gray-50' }}">
                                    <i class="fas fa-chart-line w-4 text-center mr-2"></i>
                                    Financial Report
                                </a>
                            </div>
                        </div>
                        @endif

                        {{-- EVENTS - All Roles except Staff (Staff has read-only access above) --}}
                        @if(SystemSetting::get('features', 'enable_events', false, $societyId))
                        <a href="{{ route('events.index') }}" @click="mobileMenuOpen = false"
                           class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('events.*') ? 'bg-purple-50 text-purple-600' : 'text-gray-700 hover:bg-gray-50' }}">
                            <i class="fas fa-calendar-alt w-5 text-center"></i>
                            <span class="ml-3">Events</span>
                        </a>
                        @endif

                        {{-- SETTINGS - At Bottom for Admin --}}
                        @if($isAdmin)
                        <a href="{{ route('settings.index') }}" @click="mobileMenuOpen = false"
                           class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('settings.*') ? 'bg-purple-50 text-purple-600' : 'text-gray-700 hover:bg-gray-50' }}">
                            <i class="fas fa-cog w-5 text-center"></i>
                            <span class="ml-3">Settings</span>
                        </a>
                        @endif
                    @endif
                </div>
            </nav>

            <!-- User Profile -->
            <div class="border-t border-gray-200 p-4">
                <a href="{{ route('profile.show') }}" @click="mobileMenuOpen = false" class="flex items-center hover:bg-gray-50 rounded-lg p-2 transition-colors mb-2">
                    <img class="h-10 w-10 rounded-full object-cover" 
                         src="https://ui-avatars.com/api/?name={{ urlencode(auth()->user()->name ?? 'User') }}&background=667eea&color=fff" 
                         alt="User">
                    <div class="ml-3 flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-900 truncate">{{ auth()->user()->name ?? 'User' }}</p>
                        <p class="text-xs text-gray-500 truncate">{{ auth()->user()->roles->first()->name ?? 'User' }}</p>
                    </div>
                </a>
                <div class="flex gap-2">
                    <a href="{{ route('profile.edit') }}" @click="mobileMenuOpen = false" class="flex-1 text-center text-gray-600 hover:text-purple-600 text-sm py-2 rounded transition-colors" title="Edit Profile">
                        <i class="fas fa-edit"></i>
                    </a>
                    <form method="POST" action="{{ route('logout') }}" class="flex-1">
                        @csrf
                        <button type="submit" class="w-full text-gray-600 hover:text-red-600 transition-colors text-sm py-2" title="Logout">
                            <i class="fas fa-sign-out-alt"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </aside>
</div>
