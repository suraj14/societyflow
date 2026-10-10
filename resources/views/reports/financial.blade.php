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
        @php $o = $report['overview']; @endphp

        {{-- A. OVERVIEW --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-5">
                <p class="text-gray-500 text-sm font-medium">Total Income</p>
                <p class="text-2xl font-bold text-green-700 mt-1">₹{{ number_format($o['total_income'], 2) }}</p>
                <p class="text-xs text-gray-500 mt-1">Maintenance ₹{{ number_format($o['maintenance_collected'], 0) }} &middot; Other ₹{{ number_format($o['other_income'], 0) }}</p>
            </div>
            <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-5">
                <p class="text-gray-500 text-sm font-medium">Total Society Expenses</p>
                <p class="text-2xl font-bold text-red-700 mt-1">₹{{ number_format($o['total_society_expenses'], 2) }}</p>
                <p class="text-xs text-gray-500 mt-1">{{ $report['expenses']['count'] }} {{ \Illuminate\Support\Str::plural('transaction', $report['expenses']['count']) }}</p>
            </div>
            <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-5">
                <p class="text-gray-500 text-sm font-medium">Net Position</p>
                <p class="text-2xl font-bold mt-1 {{ $o['net_position'] >= 0 ? 'text-gray-900' : 'text-red-700' }}">{{ $o['net_position'] < 0 ? '−' : '' }}₹{{ number_format(abs($o['net_position']), 2) }}</p>
                <p class="text-xs text-gray-500 mt-1">Income − society expenses, {{ $label }}</p>
            </div>
            <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-5">
                <p class="text-gray-500 text-sm font-medium">Outstanding Dues</p>
                <p class="text-2xl font-bold text-orange-700 mt-1">₹{{ number_format($o['outstanding_dues'], 2) }}</p>
                <p class="text-xs text-gray-500 mt-1">Unpaid maintenance, all bills to date</p>
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
            {{-- B. INCOME --}}
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-base font-semibold text-gray-900">Income &middot; {{ $label }}</h2>
                    <p class="text-xs text-gray-500">Payments received (status: success) by what they paid for.</p>
                </div>
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-gray-500 uppercase text-xs">
                        <tr><th class="px-6 py-3">Source</th><th class="px-6 py-3 text-right">Transactions</th><th class="px-6 py-3 text-right">Amount</th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($report['income']['rows'] as $row)
                            <tr><td class="px-6 py-3 text-gray-900">{{ $row['source'] }}</td><td class="px-6 py-3 text-right text-gray-600">{{ $row['count'] }}</td><td class="px-6 py-3 text-right font-medium">₹{{ number_format($row['amount'], 2) }}</td></tr>
                        @empty
                            <tr><td colspan="3" class="px-6 py-6 text-center text-gray-500">No income recorded for {{ $label }}.</td></tr>
                        @endforelse
                        <tr class="bg-gray-100 font-bold border-t-2 border-gray-300">
                            <td class="px-6 py-3">Total Income</td><td class="px-6 py-3 text-right">{{ $report['income']['count'] }}</td><td class="px-6 py-3 text-right">₹{{ number_format($report['income']['total'], 2) }}</td>
                        </tr>
                        @if($o['rent_collected'] > 0)
                            <tr class="text-gray-500 text-xs"><td class="px-6 py-2" colspan="2">Rent collected on owners' behalf (not society income)</td><td class="px-6 py-2 text-right">₹{{ number_format($o['rent_collected'], 2) }}</td></tr>
                        @endif
                    </tbody>
                </table>
            </div>

            {{-- C. SOCIETY EXPENSES --}}
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-base font-semibold text-gray-900">Society Expenses &middot; {{ $label }}</h2>
                    <p class="text-xs text-gray-500">Where the society spent its money on common operations.</p>
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
                                <td class="px-6 py-3 text-right font-medium">₹{{ number_format($row['amount'], 2) }}</td>
                                <td class="px-6 py-3 text-right text-gray-600">{{ $row['percent'] }}%</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-6 py-6 text-center text-gray-500">No society/common expenses recorded for this period.</td></tr>
                        @endforelse
                        <tr class="bg-gray-100 font-bold border-t-2 border-gray-300">
                            <td class="px-6 py-3">Total Society Expenses</td><td class="px-6 py-3 text-right">{{ $report['expenses']['count'] }}</td><td class="px-6 py-3 text-right">₹{{ number_format($report['expenses']['total'], 2) }}</td><td class="px-6 py-3 text-right">{{ $report['expenses']['total'] > 0 ? '100%' : '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- D. COLLECTIONS & DUES --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-base font-semibold text-gray-900">Maintenance Billing &middot; {{ $label }}</h2>
                    <p class="text-xs text-gray-500">Maintenance bills dated in this period ({{ $report['billing']['bills_count'] }} {{ \Illuminate\Support\Str::plural('bill', $report['billing']['bills_count']) }}). Rent bills are not society billing and are excluded.</p>
                </div>
                <table class="w-full text-sm">
                    <tbody class="divide-y divide-gray-100">
                        <tr><td class="px-6 py-3 text-gray-700">Total Billed</td><td class="px-6 py-3 text-right font-medium">₹{{ number_format($report['billing']['billed'], 2) }}</td></tr>
                        <tr><td class="px-6 py-3 text-gray-700">Collected against these bills</td><td class="px-6 py-3 text-right font-medium text-green-700">₹{{ number_format($report['billing']['collected'], 2) }}</td></tr>
                        <tr><td class="px-6 py-3 text-gray-700">Outstanding on these bills</td><td class="px-6 py-3 text-right font-medium text-red-700">₹{{ number_format($report['billing']['outstanding'], 2) }}</td></tr>
                        @if(count($report['billing']['charges']) > 1)
                            <tr><td class="px-6 pt-4 pb-1 text-xs uppercase text-gray-500" colspan="2">Billed charges breakdown</td></tr>
                            @foreach($report['billing']['charges'] as $charge)
                                <tr><td class="px-6 py-2 text-gray-600 pl-10">{{ $charge['label'] }}</td><td class="px-6 py-2 text-right text-gray-700">₹{{ number_format($charge['amount'], 2) }}</td></tr>
                            @endforeach
                        @endif
                    </tbody>
                </table>
            </div>

            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-base font-semibold text-gray-900">Collections &amp; Dues</h2>
                    <p class="text-xs text-gray-500">What came in during {{ $label }}, and what the society is still owed.</p>
                </div>
                <table class="w-full text-sm">
                    <tbody class="divide-y divide-gray-100">
                        <tr><td class="px-6 py-3 text-gray-700">Maintenance collected in {{ $label }}</td><td class="px-6 py-3 text-right font-medium">₹{{ number_format($o['maintenance_collected'], 2) }}</td></tr>
                        <tr><td class="px-6 py-3 text-gray-700">Other collections in {{ $label }}</td><td class="px-6 py-3 text-right font-medium">₹{{ number_format($o['other_income'], 2) }}</td></tr>
                        <tr class="font-semibold"><td class="px-6 py-3 text-gray-900">Total collected</td><td class="px-6 py-3 text-right">₹{{ number_format($o['total_income'], 2) }}</td></tr>
                        <tr><td class="px-6 py-3 text-gray-700">Outstanding maintenance dues (all bills to date)</td><td class="px-6 py-3 text-right font-medium text-red-700">₹{{ number_format($o['outstanding_dues'], 2) }}</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- E. PROPERTY DETAILS --}}
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
                                <td class="px-6 py-3 text-right">₹{{ number_format($row['billed'], 2) }}</td>
                                <td class="px-6 py-3 text-right text-green-700">₹{{ number_format($row['paid'], 2) }}</td>
                                <td class="px-6 py-3 text-right {{ $row['due'] > 0 ? 'text-red-700 font-medium' : 'text-gray-500' }}">₹{{ number_format($row['due'], 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        {{-- PROPERTY EXPENSES - separate, never in society totals --}}

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
