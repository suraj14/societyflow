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

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-5">
            <p class="text-gray-500 text-sm font-medium">Maintenance Billed</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">₹{{ number_format($data['billed'], 2) }}</p>
            <p class="text-xs text-gray-500 mt-1">Bills dated {{ $period->label() }}</p>
        </div>
        <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-5">
            <p class="text-gray-500 text-sm font-medium">Maintenance Collected</p>
            <p class="text-2xl font-bold text-green-700 mt-1">₹{{ number_format($data['total_maintenance'], 2) }}</p>
            <p class="text-xs text-gray-500 mt-1">{{ $data['count'] }} {{ \Illuminate\Support\Str::plural('payment', $data['count']) }} received in {{ $period->label() }}</p>
        </div>
        <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-5">
            <p class="text-gray-500 text-sm font-medium">Outstanding on these bills</p>
            <p class="text-2xl font-bold text-red-700 mt-1">₹{{ number_format($data['outstanding'], 2) }}</p>
            <p class="text-xs text-gray-500 mt-1">Still unpaid on bills dated {{ $period->label() }}</p>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-base font-semibold text-gray-900">Maintenance Payments &middot; {{ $period->label() }}</h2>
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
                            <td class="px-6 py-3" colspan="4">Total collected</td>
                            <td class="px-6 py-3 text-right">₹{{ number_format($data['total_maintenance'], 2) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
