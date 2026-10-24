@extends('layouts.app')

@section('title', 'Financial Report')
@section('page-title', 'Financial Report')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-900">Financial Report</h1>
        <p class="text-gray-600 mt-1">
            @if($report && $report['mode'] === 'property')
                Your bills, payments and property charges for the selected period.
            @else
                Track society income, common expenses, collections and outstanding dues for the selected period.
            @endif
        </p>
    </div>

    @if($errors->any())
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-lg">
            <ul class="list-disc list-inside text-sm">
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    @include('reports._period-filter', [
        'filterRoute'   => route('reports.financial'),
        'downloadRoute' => route('reports.download-financial'),
    ])

    @php $label = $period->label(); @endphp

    @if(!$report)
        <div class="bg-white rounded-lg shadow-md p-10 text-center text-gray-500">
            <i class="fas fa-building text-3xl mb-2 block opacity-50"></i>
            No society selected. Super Admin: use "Login as Admin" on a society to view its financial report.
        </div>

    @elseif($report['mode'] === 'society')
        @php
            $o = $report['overview'];
            $ex = $report['excluded'];
            $fac = $report['facility'];
            $bill = $report['billing'];
            $money = fn ($v) => '₹' . number_format($v, 2);
            $notReceivedTotal = collect($ex['not_received'])->sum('amount');
            $notReceivedCount = collect($ex['not_received'])->sum('count');
        @endphp

        <p class="text-sm text-gray-600 bg-blue-50 border border-blue-100 rounded-lg px-4 py-3 mb-4">
            <strong>How to read this page.</strong> <em>Billed</em> and <em>approved</em> are what is owed to the society - not money in hand.
            <em>Collections</em> are only payments that actually arrived (a successful payment, or a facility payment the office recorded) in {{ $label }}.
            Owner rent belongs to the owner and is never society income. Money the records mention but nothing proves, and money never received, is listed separately below and is not counted.
        </p>

        @php $fd = $report['future_dated']; @endphp
        @if($fd['payments']['count'] || $fd['expenses']['count'])
            <p class="text-sm text-amber-900 bg-amber-50 border border-amber-200 rounded-lg px-4 py-3 mb-4">
                <strong>Dated after today ({{ \Carbon\Carbon::parse($report['as_of'])->format('d M Y') }}).</strong>
                {{ $fd['payments']['count'] }} receipt(s) totalling {{ $money($fd['payments']['amount']) }} and {{ $fd['expenses']['count'] }} expense(s) totalling {{ $money($fd['expenses']['amount']) }}
                carry a date that has not arrived. They are counted in the period of their own date, not moved; check the date if one was mistyped.
            </p>
        @endif

        {{-- A. OVERVIEW --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
            <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-5">
                <p class="text-gray-500 text-sm font-medium">Actual Collections</p>
                <p class="text-2xl font-bold text-green-700 mt-1">{{ $money($o['total_income']) }}</p>
                <p class="text-xs text-gray-500 mt-1">Maintenance {{ $money($o['maintenance_collected']) }} &middot; Facility {{ $money($o['facility_collected']) }} &middot; Other {{ $money($o['other_income'] - $o['facility_collected']) }}</p>
            </div>
            <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-5">
                <p class="text-gray-500 text-sm font-medium">Society Expenses Paid</p>
                <p class="text-2xl font-bold text-red-700 mt-1">{{ $money($o['total_society_expenses']) }}</p>
                <p class="text-xs text-gray-500 mt-1">{{ $report['expenses']['count'] }} approved {{ \Illuminate\Support\Str::plural('expense', $report['expenses']['count']) }}, by expense date</p>
            </div>
            <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-5">
                <p class="text-gray-500 text-sm font-medium">Net Cash Movement</p>
                <p class="text-2xl font-bold mt-1 {{ $o['net_position'] >= 0 ? 'text-gray-900' : 'text-red-700' }}">{{ $o['net_position'] < 0 ? '−' : '' }}{{ $money(abs($o['net_position'])) }}</p>
                <p class="text-xs text-gray-500 mt-1">Collections − expenses, {{ $label }}</p>
            </div>
            <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-5">
                <p class="text-gray-500 text-sm font-medium">Outstanding Maintenance</p>
                <p class="text-2xl font-bold text-orange-700 mt-1">{{ $money($o['outstanding_dues']) }}</p>
                <p class="text-xs text-gray-500 mt-1">Owed on all maintenance bills to date</p>
            </div>
        </div>
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-5">
                <p class="text-gray-500 text-sm font-medium">Maintenance Billed</p>
                <p class="text-xl font-bold text-gray-900 mt-1">{{ $money($o['maintenance_billed']) }}</p>
                <p class="text-xs text-gray-500 mt-1">{{ $bill['bills_count'] }} {{ \Illuminate\Support\Str::plural('bill', $bill['bills_count']) }} dated {{ $label }} &middot; a receivable, not cash</p>
            </div>
            <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-5">
                <p class="text-gray-500 text-sm font-medium">Facility Payments Awaited</p>
                <p class="text-xl font-bold text-amber-700 mt-1">{{ $money($o['facility_receivable']) }}</p>
                <p class="text-xs text-gray-500 mt-1">{{ $fac['receivable']['count'] }} approved {{ \Illuminate\Support\Str::plural('booking', $fac['receivable']['count']) }} with no payment recorded &middot; not income</p>
            </div>
            <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-5">
                <p class="text-gray-500 text-sm font-medium">Held for Owners (Rent)</p>
                <p class="text-xl font-bold text-gray-700 mt-1">{{ $money($o['rent_collected']) }}</p>
                <p class="text-xs text-gray-500 mt-1">Owners' money - excluded from society income and expenses</p>
            </div>
            <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-5">
                <p class="text-gray-500 text-sm font-medium">Not Counted as Cash</p>
                <p class="text-xl font-bold text-gray-700 mt-1">{{ $money($ex['rent_unverified'] + $ex['other_unverified'] + $notReceivedTotal) }}</p>
                <p class="text-xs text-gray-500 mt-1">No evidence of receipt {{ $money($ex['rent_unverified'] + $ex['other_unverified']) }} &middot; pending / failed / cancelled {{ $money($notReceivedTotal) }}</p>
            </div>
        </div>

        @if(!$report['has_data'])
            <div class="bg-white rounded-lg shadow-md p-10 text-center text-gray-500 mb-6">
                <i class="fas fa-inbox text-3xl mb-2 block opacity-50"></i>
                <p class="font-medium text-gray-700">No financial data available for {{ $label }}.</p>
                <p class="text-sm mt-1">No transactions recorded for this period.</p>
            </div>
        @else

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            {{-- B. COLLECTIONS --}}
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-base font-semibold text-gray-900">Collections received &middot; {{ $label }}</h2>
                    <p class="text-xs text-gray-500">Cash that arrived: successful payments with evidence of receipt, and facility payments the office recorded (net of refunds).</p>
                </div>
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-gray-500 uppercase text-xs">
                        <tr><th class="px-6 py-3">Source</th><th class="px-6 py-3 text-right">Transactions</th><th class="px-6 py-3 text-right">Amount</th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($report['income']['rows'] as $row)
                            <tr><td class="px-6 py-3 text-gray-900">{{ $row['source'] }}</td><td class="px-6 py-3 text-right text-gray-600">{{ $row['count'] }}</td><td class="px-6 py-3 text-right font-medium">{{ $money($row['amount']) }}</td></tr>
                        @empty
                            <tr><td colspan="3" class="px-6 py-6 text-center text-gray-500">No collections recorded for {{ $label }}.</td></tr>
                        @endforelse
                        <tr class="bg-gray-100 font-bold border-t-2 border-gray-300">
                            <td class="px-6 py-3">Total collections</td><td class="px-6 py-3 text-right">{{ $report['income']['count'] }}</td><td class="px-6 py-3 text-right">{{ $money($report['income']['total']) }}</td>
                        </tr>
                    </tbody>
                </table>
                @if(count($report['income']['transactions']) > 0)
                    <details class="border-t border-gray-200 px-6 py-3 text-sm">
                        <summary class="cursor-pointer text-purple-700 font-medium">Open the {{ count($report['income']['transactions']) }} transactions behind this total</summary>
                        <table class="w-full mt-3 text-xs">
                            <thead class="text-left text-gray-500 uppercase"><tr><th class="py-2">Date</th><th>Property</th><th>For</th><th>Method</th><th>Reference</th><th class="text-right">Amount</th></tr></thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($report['income']['transactions'] as $t)
                                    <tr><td class="py-2 whitespace-nowrap">{{ $t['date'] }}</td><td>{{ $t['property'] }}</td><td>{{ $t['source'] }}</td><td>{{ ucfirst($t['method']) ?: '—' }}</td><td>{{ $t['reference'] ?: '—' }}</td><td class="text-right font-medium {{ $t['amount'] < 0 ? 'text-red-700' : '' }}">{{ $money($t['amount']) }}</td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    </details>
                @endif
            </div>

            {{-- C. SOCIETY EXPENSES --}}
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-base font-semibold text-gray-900">Society Expenses &middot; {{ $label }}</h2>
                    <p class="text-xs text-gray-500">Common expenses (security, water, electricity, gardening, repairs ...) approved and dated in this period. Property-level expenses are never included.</p>
                </div>
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-gray-500 uppercase text-xs">
                        <tr><th class="px-6 py-3">Category</th><th class="px-6 py-3 text-right">Transactions</th><th class="px-6 py-3 text-right">Amount</th><th class="px-6 py-3 text-right">% of Total</th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($report['expenses']['rows'] as $row)
                            <tr>
                                <td class="px-6 py-3 text-gray-900">{{ $row['category'] }}</td>
                                <td class="px-6 py-3 text-right text-gray-600">{{ $row['count'] }}</td>
                                <td class="px-6 py-3 text-right font-medium">{{ $money($row['amount']) }}</td>
                                <td class="px-6 py-3 text-right text-gray-600">{{ $row['percent'] }}%</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-6 py-6 text-center text-gray-500">No society/common expenses recorded for this period.</td></tr>
                        @endforelse
                        <tr class="bg-gray-100 font-bold border-t-2 border-gray-300">
                            <td class="px-6 py-3">Total Society Expenses</td><td class="px-6 py-3 text-right">{{ $report['expenses']['count'] }}</td><td class="px-6 py-3 text-right">{{ $money($report['expenses']['total']) }}</td><td class="px-6 py-3 text-right">{{ $report['expenses']['total'] > 0 ? '100%' : '—' }}</td>
                        </tr>
                    </tbody>
                </table>
                @if(count($report['expenses']['transactions']) > 0)
                    <details class="border-t border-gray-200 px-6 py-3 text-sm">
                        <summary class="cursor-pointer text-purple-700 font-medium">Open the {{ count($report['expenses']['transactions']) }} expenses behind this total</summary>
                        <table class="w-full mt-3 text-xs">
                            <thead class="text-left text-gray-500 uppercase"><tr><th class="py-2">Date</th><th>Expense</th><th>Category</th><th>Method</th><th class="text-right">Amount</th></tr></thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($report['expenses']['transactions'] as $t)
                                    <tr><td class="py-2 whitespace-nowrap">{{ $t['date'] }}</td><td>{{ $t['title'] }}</td><td>{{ $t['category'] }}</td><td>{{ ucfirst($t['method']) ?: '—' }}</td><td class="text-right font-medium">{{ $money($t['amount']) }}</td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    </details>
                @endif
            </div>
        </div>

        {{-- D. BILLING vs CASH --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-base font-semibold text-gray-900">Maintenance billed &middot; {{ $label }}</h2>
                    <p class="text-xs text-gray-500">Bills dated in this period ({{ $bill['bills_count'] }} {{ \Illuminate\Support\Str::plural('bill', $bill['bills_count']) }}). Rent bills are not society billing and are excluded.</p>
                </div>
                <table class="w-full text-sm">
                    <tbody class="divide-y divide-gray-100">
                        <tr><td class="px-6 py-3 text-gray-700">Total billed</td><td class="px-6 py-3 text-right font-medium">{{ $money($bill['billed']) }}</td></tr>
                        <tr><td class="px-6 py-3 text-gray-700">Paid against these bills (any date)</td><td class="px-6 py-3 text-right font-medium text-green-700">{{ $money($bill['collected']) }}</td></tr>
                        @if($bill['collected_without_evidence'] > 0)
                            <tr class="text-xs text-amber-800 bg-amber-50"><td class="px-6 py-2 pl-10">of which settled with <strong>no evidence of receipt</strong> (method "unrecorded")</td><td class="px-6 py-2 text-right">{{ $money($bill['collected_without_evidence']) }}</td></tr>
                        @endif
                        <tr><td class="px-6 py-3 text-gray-700">Still outstanding on these bills</td><td class="px-6 py-3 text-right font-medium text-red-700">{{ $money($bill['outstanding']) }}</td></tr>
                        @if(count($bill['charges']) > 1)
                            <tr><td class="px-6 pt-4 pb-1 text-xs uppercase text-gray-500" colspan="2">Billed charges breakdown</td></tr>
                            @foreach($bill['charges'] as $charge)
                                <tr><td class="px-6 py-2 text-gray-600 pl-10">{{ $charge['label'] }}</td><td class="px-6 py-2 text-right text-gray-700">{{ $money($charge['amount']) }}</td></tr>
                            @endforeach
                        @endif
                    </tbody>
                </table>
            </div>

            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-base font-semibold text-gray-900">Maintenance cash received in {{ $label }}</h2>
                    <p class="text-xs text-gray-500">Where this period's maintenance cash was allocated. A payment received now for an older bill is this period's cash but that older bill's receivable.</p>
                </div>
                <table class="w-full text-sm">
                    <tbody class="divide-y divide-gray-100">
                        <tr><td class="px-6 py-3 text-gray-700">Against bills dated {{ $label }}</td><td class="px-6 py-3 text-right font-medium">{{ $money($bill['cash_in_period']['this_period']) }}</td></tr>
                        <tr><td class="px-6 py-3 text-gray-700">Against earlier bills (arrears)</td><td class="px-6 py-3 text-right font-medium">{{ $money($bill['cash_in_period']['earlier']) }}</td></tr>
                        @if($bill['cash_in_period']['later'] > 0)
                            <tr><td class="px-6 py-3 text-gray-700">Against later bills (paid in advance)</td><td class="px-6 py-3 text-right font-medium">{{ $money($bill['cash_in_period']['later']) }}</td></tr>
                        @endif
                        @if($bill['cash_in_period']['unallocated'] > 0)
                            <tr><td class="px-6 py-3 text-gray-700">Not linked to any bill</td><td class="px-6 py-3 text-right font-medium">{{ $money($bill['cash_in_period']['unallocated']) }}</td></tr>
                        @endif
                        <tr class="font-semibold bg-gray-50"><td class="px-6 py-3 text-gray-900">Maintenance cash received</td><td class="px-6 py-3 text-right">{{ $money($bill['cash_in_period']['total']) }}</td></tr>
                        <tr><td class="px-6 py-3 text-gray-700">Outstanding on all maintenance bills to date</td><td class="px-6 py-3 text-right font-medium text-red-700">{{ $money($o['outstanding_dues']) }}</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- E. FACILITY BOOKINGS --}}
        @if($fac['collected']['count'] + $fac['refunded']['count'] + $fac['receivable']['count'] > 0 || $fac['undated_paid'] > 0)
        <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-base font-semibold text-gray-900">Facility Bookings &middot; {{ $label }}</h2>
                <p class="text-xs text-gray-500">A booking is income only when the office has recorded the payment. Approved bookings still waiting for payment are receivables.</p>
            </div>
            <table class="w-full text-sm">
                <tbody class="divide-y divide-gray-100">
                    <tr><td class="px-6 py-3 text-gray-700">Payments received ({{ $fac['collected']['count'] }})</td><td class="px-6 py-3 text-right font-medium text-green-700">{{ $money($fac['collected']['amount']) }}</td></tr>
                    <tr><td class="px-6 py-3 text-gray-700">Refunds paid back ({{ $fac['refunded']['count'] }})</td><td class="px-6 py-3 text-right font-medium text-red-700">−{{ $money($fac['refunded']['amount']) }}</td></tr>
                    <tr class="font-semibold bg-gray-50"><td class="px-6 py-3 text-gray-900">Facility income, net</td><td class="px-6 py-3 text-right">{{ $money($o['facility_collected']) }}</td></tr>
                    <tr><td class="px-6 py-3 text-gray-700">Approved / completed bookings with no payment recorded ({{ $fac['receivable']['count'] }}) - <span class="text-gray-500">receivable, not income</span></td><td class="px-6 py-3 text-right font-medium text-amber-700">{{ $money($fac['receivable']['amount']) }}</td></tr>
                    @if($fac['undated_paid'] > 0)
                        <tr class="text-xs text-amber-800 bg-amber-50"><td class="px-6 py-2" colspan="2">{{ $fac['undated_paid'] }} booking(s) are marked paid / refunded with no date of receipt, so they cannot be placed in any period and are not counted.</td></tr>
                    @endif
                </tbody>
            </table>
            @if($fac['receivable']['count'] > 0)
                <details class="border-t border-gray-200 px-6 py-3 text-sm">
                    <summary class="cursor-pointer text-purple-700 font-medium">Open the {{ $fac['receivable']['count'] }} bookings waiting for payment</summary>
                    <table class="w-full mt-3 text-xs">
                        <thead class="text-left text-gray-500 uppercase"><tr><th class="py-2">Booking</th><th>Facility</th><th>Property</th><th>Date</th><th>Status</th><th class="text-right">Amount</th></tr></thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($fac['receivable']['rows'] as $t)
                                <tr><td class="py-2">{{ $t['booking'] }}</td><td>{{ $t['facility'] }}</td><td>{{ $t['property'] }}</td><td>{{ $t['date'] }}</td><td>{{ ucfirst($t['status']) }}</td><td class="text-right font-medium">{{ $money($t['amount']) }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </details>
            @endif
        </div>
        @endif

        {{-- F. NOT COUNTED + OWNER RENT --}}
        @if($ex['rent_verified'] + $ex['rent_unverified'] + $ex['other_unverified'] + $notReceivedTotal > 0)
        <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-base font-semibold text-gray-900">Excluded from society income &middot; {{ $label }}</h2>
                <p class="text-xs text-gray-500">Shown so nothing is hidden - none of this is in the totals above.</p>
            </div>
            <table class="w-full text-sm">
                <tbody class="divide-y divide-gray-100">
                    <tr><td class="px-6 py-3 text-gray-700">Owner rent received through the society <span class="text-gray-500">(belongs to the owners)</span></td><td class="px-6 py-3 text-right">{{ $money($ex['rent_verified']) }}</td></tr>
                    <tr><td class="px-6 py-3 text-gray-700">Rent recorded as paid with <strong>no evidence of receipt</strong> (method "unrecorded")</td><td class="px-6 py-3 text-right">{{ $money($ex['rent_unverified']) }}</td></tr>
                    <tr><td class="px-6 py-3 text-gray-700">Other payments recorded as paid with <strong>no evidence of receipt</strong></td><td class="px-6 py-3 text-right">{{ $money($ex['other_unverified']) }}</td></tr>
                    @foreach(['pending' => 'Pending (not yet verified by the office)', 'failed' => 'Failed', 'cancelled' => 'Cancelled'] as $st => $label2)
                        @if($ex['not_received'][$st]['count'] > 0)
                            <tr><td class="px-6 py-3 text-gray-700">{{ $label2 }} payments ({{ $ex['not_received'][$st]['count'] }})</td><td class="px-6 py-3 text-right">{{ $money($ex['not_received'][$st]['amount']) }}</td></tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
            @if(count($report['rent_owners']) > 0)
                <div class="border-t border-gray-200 px-6 py-3">
                    <p class="text-xs uppercase text-gray-500 mb-2">Rent by owner (held for them - the records show no payout to owners)</p>
                    <table class="w-full text-xs">
                        <thead class="text-left text-gray-500 uppercase"><tr><th class="py-1">Owner</th><th>Property</th><th class="text-right">With evidence</th><th class="text-right">No evidence</th></tr></thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($report['rent_owners'] as $r)
                                <tr><td class="py-2">{{ $r['owner'] }}</td><td>{{ $r['property'] }}</td><td class="text-right">{{ $money($r['verified']) }}</td><td class="text-right">{{ $money($r['unverified']) }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
            @if(count($ex['transactions']) > 0)
                <details class="border-t border-gray-200 px-6 py-3 text-sm">
                    <summary class="cursor-pointer text-purple-700 font-medium">Open the {{ count($ex['transactions']) }} excluded transactions</summary>
                    <table class="w-full mt-3 text-xs">
                        <thead class="text-left text-gray-500 uppercase"><tr><th class="py-2">Date</th><th>Property</th><th>Why excluded</th><th>Method</th><th>Reference</th><th class="text-right">Amount</th></tr></thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($ex['transactions'] as $t)
                                <tr><td class="py-2 whitespace-nowrap">{{ $t['date'] }}</td><td>{{ $t['property'] }}</td><td>{{ $t['source'] }}</td><td>{{ ucfirst($t['method']) ?: '—' }}</td><td>{{ $t['reference'] ?: '—' }}</td><td class="text-right">{{ $money($t['amount']) }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </details>
            @endif
        </div>
        @endif

        {{-- G. PROPERTY DETAILS --}}
        @if(count($report['properties']) > 0)
        <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-base font-semibold text-gray-900">Property-wise Maintenance &middot; {{ $label }}</h2>
                <p class="text-xs text-gray-500">Per property, for maintenance bills dated in this period.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-gray-500 uppercase text-xs">
                        <tr><th class="px-6 py-3">Property</th><th class="px-6 py-3 text-right">Billed</th><th class="px-6 py-3 text-right">Paid</th><th class="px-6 py-3 text-right">Due</th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($report['properties'] as $row)
                            <tr>
                                <td class="px-6 py-3 text-gray-900">{{ $row['property'] }}</td>
                                <td class="px-6 py-3 text-right">{{ $money($row['billed']) }}</td>
                                <td class="px-6 py-3 text-right text-green-700">{{ $money($row['paid']) }}</td>
                                <td class="px-6 py-3 text-right {{ $row['due'] > 0 ? 'text-red-700 font-medium' : 'text-gray-500' }}">{{ $money($row['due']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        {{-- H. THE YEAR, MONTH BY MONTH (annual report only) --}}
        @if($report['months'])
        <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-base font-semibold text-gray-900">{{ $label }} month by month</h2>
                <p class="text-xs text-gray-500">The twelve months add up to the annual figures above, to the paisa. Months after {{ \Carbon\Carbon::parse($report['as_of'])->format('d M Y') }} have not happened yet and are marked <em>future</em>.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-gray-500 uppercase text-xs">
                        <tr><th class="px-6 py-3">Month</th><th class="px-6 py-3 text-right">Billed</th><th class="px-6 py-3 text-right">Collections</th><th class="px-6 py-3 text-right">Expenses</th><th class="px-6 py-3 text-right">Net</th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($report['months'] as $m)
                            <tr class="{{ $m['future'] && !$m['has_data'] ? 'text-gray-400 bg-gray-50' : '' }}">
                                <td class="px-6 py-2 {{ $m['future'] && !$m['has_data'] ? '' : 'text-gray-900' }}">{{ $m['label'] }}
                                    @if($m['future'])<span class="ml-1 text-xs px-1.5 py-0.5 rounded bg-gray-200 text-gray-600">future{{ $m['has_data'] ? ' - has dated records' : ' - no data yet' }}</span>
                                    @elseif($m['current'])<span class="ml-1 text-xs px-1.5 py-0.5 rounded bg-blue-100 text-blue-700">in progress</span>@endif
                                </td>
                                <td class="px-6 py-2 text-right text-gray-600">{{ $money($m['billed']) }}</td>
                                <td class="px-6 py-2 text-right text-green-700">{{ $money($m['income']) }}</td>
                                <td class="px-6 py-2 text-right text-red-700">{{ $money($m['expenses']) }}</td>
                                <td class="px-6 py-2 text-right font-medium {{ $m['net'] < 0 ? 'text-red-700' : '' }}">{{ $m['net'] < 0 ? '−' : '' }}{{ $money(abs($m['net'])) }}</td>
                            </tr>
                        @endforeach
                        <tr class="bg-gray-100 font-bold border-t-2 border-gray-300">
                            <td class="px-6 py-3">Year total</td>
                            <td class="px-6 py-3 text-right">{{ $money(collect($report['months'])->sum('billed')) }}</td>
                            <td class="px-6 py-3 text-right">{{ $money(collect($report['months'])->sum('income')) }}</td>
                            <td class="px-6 py-3 text-right">{{ $money(collect($report['months'])->sum('expenses')) }}</td>
                            <td class="px-6 py-3 text-right">{{ $money(collect($report['months'])->sum('net')) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        @endif {{-- has_data --}}

    @else
        {{-- Resident view: own units only --}}
        @php $o = $report['overview']; @endphp
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-5"><p class="text-gray-500 text-sm font-medium">Billed</p><p class="text-2xl font-bold text-gray-900 mt-1">₹{{ number_format($o['total_billed'], 2) }}</p><p class="text-xs text-gray-500 mt-1">Bills dated {{ $label }}</p></div>
            <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-5"><p class="text-gray-500 text-sm font-medium">Paid</p><p class="text-2xl font-bold text-green-700 mt-1">₹{{ number_format($o['total_paid'], 2) }}</p><p class="text-xs text-gray-500 mt-1">Payments in {{ $label }}</p></div>
            <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-5"><p class="text-gray-500 text-sm font-medium">Due on these bills</p><p class="text-2xl font-bold text-red-700 mt-1">₹{{ number_format($o['period_due'], 2) }}</p></div>
            <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-5"><p class="text-gray-500 text-sm font-medium">Total Outstanding</p><p class="text-2xl font-bold text-orange-700 mt-1">₹{{ number_format($o['outstanding_dues'], 2) }}</p><p class="text-xs text-gray-500 mt-1">All your bills to date</p></div>
        </div>

        @if(!$report['has_data'])
            <div class="bg-white rounded-lg shadow-md p-10 text-center text-gray-500">
                <i class="fas fa-inbox text-3xl mb-2 block opacity-50"></i>
                <p class="font-medium text-gray-700">No financial data available for {{ $label }}.</p>
                <p class="text-sm mt-1">No transactions recorded for this period.</p>
            </div>
        @else
            <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
                <div class="px-6 py-4 border-b border-gray-200"><h2 class="text-base font-semibold text-gray-900">Bills &middot; {{ $label }}</h2></div>
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-gray-500 uppercase text-xs"><tr><th class="px-6 py-3">Date</th><th class="px-6 py-3">Property</th><th class="px-6 py-3">Bill</th><th class="px-6 py-3">Type</th><th class="px-6 py-3 text-right">Billed</th><th class="px-6 py-3 text-right">Paid</th><th class="px-6 py-3 text-right">Due</th></tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($report['bills']['rows'] as $row)
                            <tr><td class="px-6 py-3">{{ $row['date'] }}</td><td class="px-6 py-3">{{ $row['property'] }}</td><td class="px-6 py-3">{{ $row['number'] }}</td><td class="px-6 py-3">{{ $row['type'] }}</td><td class="px-6 py-3 text-right">₹{{ number_format($row['billed'], 2) }}</td><td class="px-6 py-3 text-right text-green-700">₹{{ number_format($row['paid'], 2) }}</td><td class="px-6 py-3 text-right {{ $row['due'] > 0 ? 'text-red-700' : '' }}">₹{{ number_format($row['due'], 2) }}</td></tr>
                        @empty
                            <tr><td colspan="7" class="px-6 py-6 text-center text-gray-500">No bills dated {{ $label }}.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
                <div class="px-6 py-4 border-b border-gray-200"><h2 class="text-base font-semibold text-gray-900">Payments &middot; {{ $label }}</h2></div>
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-gray-500 uppercase text-xs"><tr><th class="px-6 py-3">Date</th><th class="px-6 py-3">Property</th><th class="px-6 py-3">For</th><th class="px-6 py-3">Receipt</th><th class="px-6 py-3 text-right">Amount</th></tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($report['payments']['rows'] as $row)
                            <tr><td class="px-6 py-3">{{ $row['date'] }}</td><td class="px-6 py-3">{{ $row['property'] }}</td><td class="px-6 py-3">{{ $row['type'] }}</td><td class="px-6 py-3">{{ $row['receipt'] ?: '—' }}</td><td class="px-6 py-3 text-right font-medium">₹{{ number_format($row['amount'], 2) }}</td></tr>
                        @empty
                            <tr><td colspan="5" class="px-6 py-6 text-center text-gray-500">No payments in {{ $label }}.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if(count($report['utility_bills']['rows']) > 0)
            <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
                <div class="px-6 py-4 border-b border-gray-200"><h2 class="text-base font-semibold text-gray-900">Utility Bills &middot; {{ $label }}</h2></div>
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-gray-500 uppercase text-xs"><tr><th class="px-6 py-3">Date</th><th class="px-6 py-3">Property</th><th class="px-6 py-3">Type</th><th class="px-6 py-3">Status</th><th class="px-6 py-3 text-right">Amount</th></tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($report['utility_bills']['rows'] as $row)
                            <tr><td class="px-6 py-3">{{ $row['date'] }}</td><td class="px-6 py-3">{{ $row['property'] }}</td><td class="px-6 py-3">{{ $row['type'] }}</td><td class="px-6 py-3">{{ $row['status'] }}</td><td class="px-6 py-3 text-right">₹{{ number_format($row['amount'], 2) }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif

        @endif
    @endif
</div>
@endsection
