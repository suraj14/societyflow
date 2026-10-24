@extends('layouts.app')

@section('title', 'Festivals & Celebrations')
@section('page-title', 'Festivals & Celebrations')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Festivals &amp; Celebrations</h1>
            <p class="text-gray-600 mt-1">Community celebrations funded by voluntary contributions. Nobody is billed and nobody owes anything.</p>
        </div>
        @if($canCreate)
            <a href="{{ route('festivals.create') }}" class="bg-purple-700 hover:bg-purple-800 text-white px-5 py-2.5 rounded-lg font-medium inline-flex items-center">
                <i class="fas fa-plus mr-2"></i> New Festival
            </a>
        @endif
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
        @forelse($festivals as $f)
            @php $s = $summaries[$f->id]; @endphp
            <a href="{{ route('festivals.show', $f) }}" class="block bg-white rounded-lg border border-gray-200 shadow-sm p-5 hover:shadow-md transition-shadow">
                <div class="flex items-start justify-between gap-2">
                    <h2 class="text-lg font-semibold text-gray-900">{{ $f->name }}</h2>
                    <span class="text-xs px-2 py-1 rounded-full {{ $f->settlement_status === 'settled' ? 'bg-gray-100 text-gray-700' : ($f->status === 'published' ? 'bg-green-100 text-green-800' : ($f->status === 'cancelled' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800')) }}">
                        {{ $f->settlement_status === 'settled' ? 'Settled' : ucfirst($f->status) }}
                    </span>
                </div>
                <p class="text-sm text-gray-500 mt-1">{{ $f->festival_date->format('d M Y') }}@if($f->venue) · {{ $f->venue }}@endif</p>
                <div class="mt-4 text-sm">
                    <div class="flex justify-between"><span class="text-gray-500">Voluntary contributions received</span><span class="font-medium">₹{{ number_format($s['contributions_net'], 2) }}</span></div>
                    @if($s['target'] !== null)
                        <div class="flex justify-between"><span class="text-gray-500">Voluntary goal</span><span class="font-medium">₹{{ number_format($s['target'], 2) }}</span></div>
                    @endif
                    <div class="flex justify-between"><span class="text-gray-500">Contributors</span><span class="font-medium">{{ $s['contributors'] }}</span></div>
                </div>
            </a>
        @empty
            <div class="col-span-full bg-white rounded-lg border border-gray-200 p-10 text-center text-gray-500">No festivals yet.</div>
        @endforelse
    </div>
    <div class="mt-6">{{ $festivals->links() }}</div>
</div>
@endsection