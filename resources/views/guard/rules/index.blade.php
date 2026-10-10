@extends('layouts.guard')

@section('title', 'Society Info')
@section('page-title', 'Society Info')

@section('content')
<div class="px-4 py-4">
    @include('society-rules.partials.info')
</div>
@endsection
