@extends('layouts.app')

@section('title', 'Message Templates')
@section('page-title', 'Message Templates')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-purple-600">Dashboard</a>
    <span class="mx-2">/</span>
    <a href="{{ route('settings.index') }}" class="hover:text-purple-600">Settings</a>
    <span class="mx-2">/</span>
    <span class="text-gray-700">Message Templates</span>
@endsection

@section('content')
@php
    $tabs = ['sms' => 'SMS', 'whatsapp' => 'WhatsApp', 'email' => 'Email'];
    $btn = 'inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-gradient-to-r from-purple-600 to-indigo-600 text-white text-sm font-semibold shadow hover:from-purple-700 hover:to-indigo-700';
@endphp

<div class="max-w-6xl space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Message Templates</h2>
            <p class="text-sm text-gray-500 mt-1">The messages SocietyFlow sends, and which template each notification uses.</p>
        </div>
        <div class="shrink-0">
            @if ($channel === 'sms')
                <a href="{{ route('admin.message-templates.sms.create') }}" class="{{ $btn }}"><i class="fas fa-plus text-xs"></i> New Template</a>
            @elseif ($channel === 'whatsapp')
                <a href="{{ route('admin.message-templates.whatsapp.create') }}" class="{{ $btn }}"><i class="fas fa-plus text-xs"></i> New Template</a>
            @elseif ($channel === 'email')
                <a href="{{ route('admin.email-templates.create') }}" class="{{ $btn }}"><i class="fas fa-plus text-xs"></i> New Template</a>
            @endif
        </div>
    </div>

    <nav class="flex gap-1 border-b border-gray-200" aria-label="Channel" role="tablist">
        @foreach ($tabs as $key => $label)
            <a href="{{ route('admin.message-templates.index', ['channel' => $key]) }}" role="tab" @if ($channel === $key) aria-current="page" aria-selected="true" @endif
               class="px-4 py-2.5 text-sm font-semibold border-b-2 -mb-px {{ $channel === $key ? 'border-purple-600 text-purple-700' : 'border-transparent text-gray-500 hover:text-gray-800' }}">
                {{ $label }} <span class="ml-1 text-xs {{ $channel === $key ? 'text-purple-400' : 'text-gray-400' }}">{{ $counts[$key] ?? 0 }}</span>
            </a>
        @endforeach
    </nav>

    @if ($channel === 'sms')
        @include('admin.message-templates.partials.sms')
    @elseif ($channel === 'whatsapp')
        @include('admin.message-templates.partials.whatsapp')
    @else
        @include('admin.message-templates.partials.email')
    @endif
</div>
@endsection
