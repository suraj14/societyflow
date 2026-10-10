@extends('layouts.app')

@section('title', 'Society Info')
@section('page-title', 'Society Info')

@section('content')
<div class="container mx-auto px-4 py-6 sm:py-8 max-w-3xl">
    <div class="bg-gradient-to-r from-blue-600 via-purple-600 to-indigo-700 rounded-2xl shadow-lg p-6 mb-5 text-white">
        <h1 class="text-2xl font-bold">Society Info</h1>
        <p class="text-purple-100 text-sm mt-1">Your society's details, its committee, and the official rules and documents from the society office.</p>
    </div>

    @include('society-rules.partials.info')
</div>
@endsection
