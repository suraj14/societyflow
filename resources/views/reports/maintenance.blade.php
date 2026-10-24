@extends('layouts.app')

@section('title', 'Maintenance Report')
@section('page-title', 'Maintenance Report')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-900">Maintenance Report</h1>
        <p class="text-gray-600 mt-1">Track maintenance billing and collections for the selected period.</p>
    </div>

    @if($errors->any())
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-lg">
            <ul class="list-disc list-inside text-sm">
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    @include('reports._period-filter', [
        'filterRoute'   => route('reports.maintenance'),
        'downloadRoute' => route('reports.download-maintenance'),
    ])

    @php $m = fn ($v) => '₹' . number_format($v, 2); @endphp
    <p class="text-sm text-gray-600 bg-blue-50 border border-blue-100 rounded-lg px-4 py-3 mb-4">
        <strong>Two different questions.</strong> <em>Billed</em> and <em>outstanding</em> are about the bills dated {{ $period->label() }}.
        <em>Received</em> is the cash that actually arrived in {{ $period->label() }}, for any bill. Money received now for an older bill is this period's cash but that older bill's receivable - it does not reduce what is owed on this period's bills.
    </p>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
        <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-5">
            <p class="text-gray-500 text-sm font-medium">Billed in {{ $period->label() }}</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ $m($data['billed']) }}</p>
            <p class="text-xs text-gray-500 mt-1">Maintenance bills dated {{ $period->label() }} &middot; a receivable, not cash</p>
        </div>
        <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-5">
            <p class="text-gray-500 text-sm font-medium">Received in {{ $period->label() }}</p>
            <p class="text-2xl font-bold text-green-700 mt-1">{{ $m($data['total_maintenance']) }}</p>
            <p class="text-xs text-gray-500 mt-1">{{ $data['count'] }} {{ \Illuminate\Support\Str::plural('payment', $data['count']) }} that arrived in {{ $period->label() }}, for any bill</p>
        </div>
        <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-5">
            <p class="text-gray-500 text-sm font-medium">Outstanding on these bills</p>
            <p class="text-2xl font-bold text-red-700 mt-1">{{ $m($data['outstanding']) }}</p>
            <p class="text-xs text-gray-500 mt-1">Still unpaid on the bills dated {{ $period->label() }}; paid so far {{ $m($data['collected_on_these_bills']) }}</p>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-base font-semibold text-gray-900">Where the {{ $period->label() }} cash went</h2>
        </div>
        <table class="w-full text-sm">
            <tbody class="divide-y divide-gray-100">
                <tr><td class="px-6 py-3 text-gray-700">Settled bills dated {{ $period->label() }}</td><td class="px-6 py-3 text-right font-medium">{{ $m($data['cash_against_this_period_bills']) }}</td></tr>
                <tr><td class="px-6 py-3 text-gray-700">Settled earlier bills (arrears)</td><td class="px-6 py-3 text-right font-medium">{{ $m($data['cash_against_earlier_bills']) }}</td></tr>
                @if($data['cash_against_later_bills'] > 0)<tr><td class="px-6 py-3 text-gray-700">Paid in advance for later bills</td><td class="px-6 py-3 text-right font-medium">{{ $m($data['cash_against_later_bills']) }}</td></tr>@endif
                @if($data['cash_unallocated'] > 0)<tr><td class="px-6 py-3 text-gray-700">Not linked to any bill</td><td class="px-6 py-3 text-right font-medium">{{ $m($data['cash_unallocated']) }}</td></tr>@endif
                <tr class="font-semibold bg-gray-50"><td class="px-6 py-3 text-gray-900">Received</td><td class="px-6 py-3 text-right">{{ $m($data['total_maintenance']) }}</td></tr>
                <tr><td class="px-6 py-3 text-gray-700">Outstanding on every maintenance bill to date</td><td class="px-6 py-3 text-right font-medium text-red-700">{{ $m($data['outstanding_all_time']) }}</td></tr>
            </tbody>
        </table>
    </div>

    @if($data['no_evidence']['count'] > 0 || $data['no_evidence_on_these_bills'] > 0)
        <div class="bg-amber-50 border border-amber-200 text-amber-900 rounded-lg px-4 py-3 mb-6 text-sm" role="status">
            <strong>{{ $m($data['no_evidence']['amount']) }} in {{ $data['no_evidence']['count'] }} {{ \Illuminate\Support\Str::plural('payment', $data['no_evidence']['count']) }} received in {{ $period->label() }} is recorded as paid with <u>no evidence of receipt</u> (method "unrecorded")</strong> and is not counted above. The payment records are kept; confirm how and when the money arrived, then record the method.
        </div>
    @endif
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-base font-semibold text-gray-900">Payments received &middot; {{ $period->label() }}</h2>
        </div>
        @if($data['payments']->isEmpty())
            <div class="p-10 text-center text-gray-500">
                <i class="fas fa-inbox text-3xl mb-2 block opacity-50"></i>
                No maintenance payments recorded for {{ $period->label() }}.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-gray-500 uppercase text-xs">
                        <tr>
                            <th class="px-6 py-3">Date</th>
                            <th class="px-6 py-3">Receipt</th>
                            <th class="px-6 py-3">Property</th>
                            <th class="px-6 py-3">Method</th>
                            <th class="px-6 py-3 text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($data['payments'] as $payment)
                            <tr>
                                <td class="px-6 py-3 whitespace-nowrap">{{ $payment->payment_date?->format('d M Y') }}</td>
                                <td class="px-6 py-3">{{ $payment->receipt_number ?: $payment->payment_id }}</td>
                                <td class="px-6 py-3">{{ $payment->flat?->property_type === 'villa' ? 'Villa ' . $payment->flat->flat_number : ($payment->flat?->flat_number ?? '—') }}</td>
                                <td class="px-6 py-3">{{ ucfirst($payment->payment_method) }}</td>
                                <td class="px-6 py-3 text-right font-semibold">₹{{ number_format($payment->amount, 2) }}</td>
                            </tr>
                        @endforeach
                        <tr class="bg-gray-100 font-bold border-t-2 border-gray-300">
                            <td class="px-6 py-3" colspan="4">Total received</td>
                            <td class="px-6 py-3 text-right">₹{{ number_format($data['total_maintenance'], 2) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
