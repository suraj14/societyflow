@extends('layouts.app')
@section('title', 'Settlement report')
@section('page-title', 'Settlement report')
@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="flex gap-2 mb-4">
        <a href="{{ route('festivals.show', $festival) }}" class="px-3 py-1.5 border border-gray-300 rounded-lg text-sm">Back</a>
        <a href="{{ route('festivals.report', [$festival, 'pdf']) }}" class="px-3 py-1.5 bg-purple-700 text-white rounded-lg text-sm">Download PDF</a>
        <a href="{{ route('festivals.report', [$festival, 'csv']) }}" class="px-3 py-1.5 border border-gray-300 rounded-lg text-sm">Download CSV</a>
    </div>
    <div class="bg-white rounded-lg shadow-md p-6 text-sm">
        @include('festivals._report_body')
    </div>
</div>
@endsection