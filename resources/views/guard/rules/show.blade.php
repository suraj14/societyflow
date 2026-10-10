@extends('layouts.guard')

@section('title', $document->title)
@section('page-title', 'Gate Rules')

@section('content')
<div class="px-4 py-4">
    <a href="{{ route('guard.rules.index') }}" class="text-sm text-purple-700 font-medium"><i class="fas fa-arrow-left mr-1"></i> All rules</a>
    <div class="mt-3">
        @include('society-rules.partials.reader')
    </div>
</div>
@endsection
