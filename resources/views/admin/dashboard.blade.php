@extends('layouts.app')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@section('content')
<div class="p-6">
    @php
        // Cards follow the society's configured property type. A villa
        // township has no buildings to count; an apartment society has no
        // villas. A mixed society sees both, split out, so the numbers stay
        // legible instead of collapsing into one opaque "units" figure.
        // Super Admin has no society of their own and sees everything.
        $sfShowApartments = $society === null || $society->supportsApartments();
        $sfShowVillas     = $society === null || $society->supportsVillas();
        $sfIsMixed        = $society === null || $society->isMixedProperty();
    @endphp

    @php
        // Compact property breakdown line under the Total Properties KPI -
        // same three counts the old three-card row showed, just not each
        // given a full card of its own.
        $sfPropertyParts = [];
        if ($sfShowApartments) { $sfPropertyParts[] = ($stats['total_buildings'] ?? 0) . ' Buildings'; }
        if ($sfIsMixed || !$sfShowVillas) { $sfPropertyParts[] = ($stats['total_apartments'] ?? 0) . ' Apartments'; }
        if ($sfIsMixed || $sfShowVillas) { $sfPropertyParts[] = ($stats['total_villas'] ?? 0) . ' Villas'; }
    @endphp

    @php
        // Exactly eight daily-management KPIs. Each is tagged data-kpi so the
        // dashboard test can count them; everything else on the page
        // (property overview, expenses, recent activity) is supporting
        // detail, not a KPI card.
        $sfCard = 'block bg-white rounded-lg border border-gray-200 shadow-sm hover:shadow-md transition-shadow p-5 h-full';
        $sfIcon = 'w-11 h-11 rounded-lg flex items-center justify-center shrink-0';
    @endphp

    <!-- Row 1: Property & People -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
        <a href="{{ route('admin.property-occupancy.index') }}" class="{{ $sfCard }}" data-kpi="total_properties">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-gray-500 text-sm font-medium">Total Properties</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2">{{ $stats['total_units'] ?? 0 }}</p>
                    @if(!empty($sfPropertyParts))<p class="text-xs text-gray-400 mt-1 truncate">{{ implode(' · ', $sfPropertyParts) }}</p>@endif
                </div>
                <div class="{{ $sfIcon }} bg-blue-100"><i class="fas fa-home text-blue-600"></i></div>
            </div>
        </a>
        <a href="{{ route('admin.property-occupancy.index') }}" class="{{ $sfCard }}" data-kpi="occupied_units">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-gray-500 text-sm font-medium">Occupied Units</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2">{{ $stats['occupied_units'] ?? 0 }}</p>
                    <p class="text-xs text-gray-400 mt-1">{{ $stats['owner_occupied_units'] ?? 0 }} owner · {{ $stats['tenant_occupied_units'] ?? 0 }} tenant</p>
                </div>
                <div class="{{ $sfIcon }} bg-green-100"><i class="fas fa-user-check text-green-600"></i></div>
            </div>
        </a>
        <a href="{{ route('admin.property-occupancy.index') }}" class="{{ $sfCard }}" data-kpi="vacant_units">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-gray-500 text-sm font-medium">Vacant Units</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2">{{ $stats['vacant_units'] ?? 0 }}</p>
                </div>
                <div class="{{ $sfIcon }} bg-gray-100"><i class="fas fa-door-open text-gray-600"></i></div>
            </div>
        </a>
        <a href="{{ route('owners.index') }}" class="{{ $sfCard }}" data-kpi="residents">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-gray-500 text-sm font-medium">Residents</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2">{{ $stats['total_residents'] ?? 0 }}</p>
                    <p class="text-xs text-gray-400 mt-1">{{ $stats['active_owners'] ?? 0 }} owners · {{ $stats['active_tenants'] ?? 0 }} tenants</p>
                </div>
                <div class="{{ $sfIcon }} bg-purple-100"><i class="fas fa-users text-purple-600"></i></div>
            </div>
        </a>
    </div>

    <!-- Row 2: Daily Operations & Finance -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <a href="{{ route('visitors.index') }}" class="{{ $sfCard }}" data-kpi="visitors_today">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-gray-500 text-sm font-medium">Visitors Today</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2">{{ $stats['visitors_today'] ?? 0 }}</p>
                </div>
                <div class="{{ $sfIcon }} bg-cyan-100"><i class="fas fa-id-badge text-cyan-600"></i></div>
            </div>
        </a>
        <a href="{{ route('complaints.index') }}" class="{{ $sfCard }}" data-kpi="open_tickets">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-gray-500 text-sm font-medium">Open Tickets</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2">{{ $stats['open_tickets'] ?? 0 }}</p>
                    <p class="text-xs mt-1 {{ ($stats['open_tickets'] ?? 0) > 0 ? 'text-red-500' : 'text-gray-400' }}">{{ ($stats['open_tickets'] ?? 0) > 0 ? 'Needs attention' : 'No open tickets' }}</p>
                </div>
                <div class="{{ $sfIcon }} bg-red-100"><i class="fas fa-ticket-alt text-red-600"></i></div>
            </div>
        </a>
        <a href="{{ route('payments.index') }}" class="{{ $sfCard }}" data-kpi="pending_dues">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-gray-500 text-sm font-medium">Pending Dues</p>
                    <p class="text-2xl font-bold text-gray-900 mt-2">₹{{ number_format($stats['pending_dues'] ?? 0) }}</p>
                    <p class="text-xs text-gray-400 mt-1">{{ $stats['pending_bills_count'] ?? 0 }} unpaid {{ \Illuminate\Support\Str::plural('bill', $stats['pending_bills_count'] ?? 0) }}</p>
                </div>
                <div class="{{ $sfIcon }} bg-orange-100"><i class="fas fa-file-invoice-dollar text-orange-600"></i></div>
            </div>
        </a>
        <a href="{{ route('reports.financial') }}" class="{{ $sfCard }}" data-kpi="monthly_revenue">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-gray-500 text-sm font-medium">Monthly Revenue</p>
                    <p class="text-2xl font-bold text-gray-900 mt-2">₹{{ number_format($stats['monthly_revenue'] ?? 0) }}</p>
                    <p class="text-xs text-gray-400 mt-1">Received in {{ \App\Support\SocietyClock::now()->format('F') }}</p>
                </div>
                <div class="{{ $sfIcon }} bg-emerald-100"><i class="fas fa-rupee-sign text-emerald-600"></i></div>
            </div>
        </a>
    </div>

    <!-- Property & finance overview (supporting detail, not KPI cards) -->
    <div class="bg-white rounded-lg border border-gray-200 shadow-sm px-5 py-3 mb-6 flex flex-wrap items-center gap-x-6 gap-y-2 text-sm text-gray-600">
        <span class="font-medium text-gray-900">Overview</span>
        @foreach($sfPropertyParts as $sfPart)<span>{{ $sfPart }}</span>@endforeach
        @php $sfNet = $stats['monthly_net'] ?? 0; @endphp
        <a href="{{ route('expenses.index') }}" class="hover:underline">Society expenses this month: ₹{{ number_format($stats['monthly_expenses'] ?? 0) }}</a>
        <span class="{{ $sfNet >= 0 ? 'text-green-700' : 'text-red-700' }}">Net: {{ $sfNet < 0 ? '−' : '' }}₹{{ number_format(abs($sfNet)) }}</span>
    </div>

    <!-- Row 3: Recent Activity -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <!-- Recent Tickets -->
        <div class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                <h2 class="text-base font-semibold text-gray-900">Recent Tickets</h2>
                <a href="{{ route('complaints.index') }}" class="text-primary text-sm font-medium hover:underline">View All</a>
            </div>
            <div class="divide-y divide-gray-100">
                @forelse($recentTickets ?? [] as $ticket)
                    <div class="px-6 py-3 hover:bg-gray-50 transition-colors">
                        <div class="flex items-center justify-between gap-3">
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-900 truncate">{{ $ticket->title ?? 'Complaint' }}</p>
                                <p class="text-xs text-gray-500 mt-1">{{ $ticket->created_at?->diffForHumans() ?? 'Recently' }}</p>
                            </div>
                            @php
                                $sfTicketBadge = match($ticket->status ?? 'open') {
                                    'resolved', 'closed' => 'bg-green-100 text-green-800',
                                    'in_progress' => 'bg-blue-100 text-blue-800',
                                    default => 'bg-red-100 text-red-800',
                                };
                            @endphp
                            <span class="px-2.5 py-1 text-xs font-semibold rounded-full shrink-0 {{ $sfTicketBadge }}">
                                {{ ucfirst(str_replace('_', ' ', $ticket->status ?? 'open')) }}
                            </span>
                        </div>
                    </div>
                @empty
                    <div class="px-6 py-8 text-center">
                        <p class="text-gray-400 text-sm">No open tickets</p>
                    </div>
                @endforelse
            </div>
        </div>

        <!-- Recent Visitors -->
        <div class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                <h2 class="text-base font-semibold text-gray-900">Recent Visitors</h2>
                <a href="{{ route('visitors.index') }}" class="text-primary text-sm font-medium hover:underline">View All</a>
            </div>
            <div class="divide-y divide-gray-100">
                @forelse($recentVisitors ?? [] as $visitor)
                    <div class="px-6 py-3 hover:bg-gray-50 transition-colors">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 bg-gray-100 rounded-full flex items-center justify-center shrink-0">
                                <i class="fas fa-user text-gray-500 text-sm"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-900 truncate">{{ $visitor->visitor_name ?? 'Visitor' }}</p>
                                <p class="text-xs text-gray-500">{{ $visitor->created_at?->format('M d, Y g:i A') ?? 'Recently' }}</p>
                            </div>
                            @if(!empty($visitor->entry_status))
                                <span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-700 shrink-0">
                                    {{ ucfirst($visitor->entry_status) }}
                                </span>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="px-6 py-8 text-center">
                        <p class="text-gray-400 text-sm">No visitors today</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Row 4: Latest Notices -->
    <div class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
            <h2 class="text-base font-semibold text-gray-900">Latest Notices</h2>
            @if(\Illuminate\Support\Facades\Route::has('notices.index'))
                <a href="{{ route('notices.index') }}" class="text-primary text-sm font-medium hover:underline">View All</a>
            @endif
        </div>
        <div class="divide-y divide-gray-100">
            @forelse(($notices ?? []) as $notice)
                <div class="px-6 py-3 flex items-start gap-3">
                    <div class="w-8 h-8 bg-amber-50 rounded-full flex items-center justify-center shrink-0 mt-0.5">
                        <i class="fas fa-bullhorn text-amber-600 text-sm"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <h4 class="text-sm font-medium text-gray-900">{{ $notice->title }}</h4>
                        <p class="text-sm text-gray-500 mt-0.5">{{ Str::limit($notice->content, 100) }}</p>
                        <p class="text-xs text-gray-400 mt-1">{{ $notice->created_at->diffForHumans() }}</p>
                    </div>
                </div>
            @empty
                <div class="px-6 py-8 text-center">
                    <p class="text-gray-400 text-sm">No recent notices</p>
                </div>
            @endforelse
        </div>
    </div>
</div>
@endsection
