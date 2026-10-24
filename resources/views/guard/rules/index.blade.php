@extends('layouts.guard')

@section('title', 'Society Info')
@section('page-title', 'Society Info')

@section('content')
<div class="px-4 py-4 md:px-6 md:py-6 md:max-w-4xl">
    @include('society-rules.partials.info')
</div>
@endsection
