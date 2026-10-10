@extends('layouts.app')

@section('title', $document->title)
@section('page-title', 'Society Rules')

@section('content')
<div class="container mx-auto px-4 py-6 sm:py-8 max-w-4xl">
    <a href="{{ route('society-rules.index') }}" class="text-sm text-purple-600 hover:text-purple-800"><i class="fas fa-arrow-left mr-1"></i> Society Rules</a>


    <div class="mt-3">
        @include('society-rules.partials.reader')
    </div>
</div>
@endsection
