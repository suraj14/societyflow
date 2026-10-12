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
    $chips = ['all' => 'All', 'sms' => 'SMS', 'whatsapp' => 'WhatsApp', 'email' => 'Email'];
    $statusBadge = [
        'configured' => ['Configured', 'bg-green-50 text-green-700'],
        'approved' => ['Approved', 'bg-green-50 text-green-700'],
        'not_approved' => ['Not approved', 'bg-gray-100 text-gray-500'],
        'missing' => ['Not set', 'bg-amber-50 text-amber-800'],
        'missing_key' => ['API key missing', 'bg-amber-50 text-amber-800'],
        'wrong_id' => ['Wrong ID type', 'bg-red-50 text-red-700'],
        'local' => ['Local', 'bg-indigo-50 text-indigo-700'],
    ];
    $channelStyle = ['sms' => 'bg-purple-50 text-purple-700', 'whatsapp' => 'bg-green-50 text-green-700', 'email' => 'bg-blue-50 text-blue-700'];
    $channelName = ['sms' => 'SMS', 'whatsapp' => 'WhatsApp', 'email' => 'Email'];
    $qs = fn (array $over) => route('admin.message-templates.index', array_filter(array_merge(['channel' => $channel === 'all' ? null : $channel, 'purpose' => $purpose === 'all' ? null : $purpose], $over), fn ($v) => $v !== null && $v !== 'all'));
@endphp

<div class="max-w-6xl space-y-6">
    <div>
        <h2 class="text-2xl font-bold text-gray-900">Message Templates</h2>
        <p class="text-sm text-gray-500 mt-1">Every message SocietyFlow can send, in one place. Provider-approved wording (SMS, WhatsApp) is fixed by the approval - you choose which approved template is used for which notification; you do not edit its text.</p>
    </div>

    {{-- Filters: channel chips + purpose --}}
    <div class="flex flex-col sm:flex-row sm:items-center gap-3">
        <nav class="flex flex-wrap gap-2" aria-label="Channel">
            @foreach ($chips as $key => $label)
                <a href="{{ $qs(['channel' => $key]) }}" @if($channel === $key) aria-current="page" @endif
                   class="px-3.5 py-1.5 rounded-full text-sm font-medium border {{ $channel === $key ? 'bg-purple-600 text-white border-purple-600' : 'bg-white text-gray-700 border-gray-200 hover:bg-gray-50' }}">
                    {{ $label }} <span class="{{ $channel === $key ? 'text-purple-100' : 'text-gray-400' }}">{{ $counts[$key] ?? 0 }}</span>
                </a>
            @endforeach
        </nav>
        <form method="GET" action="{{ route('admin.message-templates.index') }}" class="sm:ml-auto flex items-center gap-2">
            @if ($channel !== 'all') <input type="hidden" name="channel" value="{{ $channel }}"> @endif
            <label for="purpose" class="text-sm text-gray-600">Purpose</label>
            <select id="purpose" name="purpose" onchange="this.form.submit()" class="px-3 py-2 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500">
                @foreach ($purposeLabels as $key => $label)
                    <option value="{{ $key }}" @selected($purpose === $key)>{{ $label }}</option>
                @endforeach
            </select>
            <noscript><button class="px-3 py-2 rounded-xl border text-sm">Apply</button></noscript>
        </form>
    </div>

    @if (in_array($channel, ['all', 'whatsapp'], true) && $unmappedWhatsApp)
        <div class="rounded-2xl border border-amber-200 bg-amber-50 px-5 py-3 text-sm text-amber-900" role="status">
            <i class="fas fa-triangle-exclamation mr-1"></i> No WhatsApp template is chosen for: <strong>{{ implode(', ', $unmappedWhatsApp) }}</strong>. Those notifications are not sent on WhatsApp.
        </div>
    @endif

    {{-- The catalogue --}}
    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        @if ($rows->isEmpty())
            <p class="px-6 py-10 text-sm text-gray-500 text-center">No templates match this filter.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm" id="message-templates-table">
                    <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-2.5">Template</th><th class="px-3 py-2.5">Channel</th><th class="px-3 py-2.5">Used for</th>
                            <th class="px-3 py-2.5">Provider ID</th><th class="px-3 py-2.5">Status</th><th class="px-3 py-2.5">Variables</th>
                            <th class="px-3 py-2.5">On</th><th class="px-4 py-2.5"><span class="sr-only">Action</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($rows as $r)
                            @php $badge = $statusBadge[$r['status']] ?? [$r['status'], 'bg-gray-100 text-gray-600']; @endphp
                            <tr data-channel="{{ $r['channel'] }}" data-purpose="{{ $r['purpose'] }}">
                                <td class="px-4 py-3 max-w-xs">
                                    <p class="font-medium text-gray-900">{{ $r['name'] }}</p>
                                    @if (!empty($r['text'])) <p class="text-xs text-gray-500 line-clamp-2" title="{{ $r['text'] }}">{{ $r['text'] }}</p> @endif
                                </td>
                                <td class="px-3 py-3"><span class="px-2 py-0.5 rounded-full text-xs font-semibold {{ $channelStyle[$r['channel']] }}">{{ $channelName[$r['channel']] }}</span></td>
                                <td class="px-3 py-3 text-gray-700">{{ $r['usage'] }}</td>
                                <td class="px-3 py-3 text-gray-700">
                                    @if ($r['identifier'] !== '' && $r['identifier'] !== null)
                                        <span class="block text-[11px] uppercase text-gray-400">{{ $r['identifier_label'] }}</span><span class="font-mono text-xs break-all">{{ $r['identifier'] }}</span>
                                    @else <span class="text-gray-400">-</span> @endif
                                </td>
                                <td class="px-3 py-3">
                                    <span class="px-2 py-0.5 rounded-full text-xs font-semibold {{ $badge[1] }}">{{ $r['status_text'] ?? $badge[0] }}</span>
                                    @if (!empty($r['language'])) <span class="block text-xs text-gray-400 mt-0.5">{{ $r['language'] }}</span> @endif
                                </td>
                                <td class="px-3 py-3 text-gray-700">{{ $r['variables'] }}</td>
                                <td class="px-3 py-3">
                                    <span class="inline-flex items-center gap-1 text-xs font-medium {{ $r['enabled'] ? 'text-green-700' : 'text-gray-400' }}"><span class="w-1.5 h-1.5 rounded-full {{ $r['enabled'] ? 'bg-green-500' : 'bg-gray-300' }}"></span>{{ $r['enabled'] ? 'On' : 'Off' }}</span>
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    @if (!empty($r['action'])) <a href="{{ $r['action'][1] }}" class="text-purple-700 font-medium hover:underline">{{ $r['action'][0] }}</a> @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Where to act --}}
    @if (in_array($channel, ['all', 'sms'], true))
        <section aria-labelledby="sms-tools"><h3 id="sms-tools" class="sr-only">SMS templates</h3>
            @include('settings.partials.sms-templates')
        </section>
    @endif

    @if (in_array($channel, ['all', 'whatsapp'], true))
        <section aria-labelledby="wa-tools" class="space-y-3">
            <h3 id="wa-tools" class="font-semibold text-gray-900">WhatsApp: approved templates and what each notification uses</h3>
            @include('settings.partials.whatsapp-templates')
        </section>
        @include('settings.partials.whatsapp-js')
    @endif

    @if (in_array($channel, ['all', 'email'], true))
        <section class="bg-white rounded-2xl border border-gray-200 p-5 sm:p-6 flex flex-col sm:flex-row sm:items-center gap-3">
            <div class="flex-1">
                <h3 class="font-semibold text-gray-900">Email templates</h3>
                <p class="text-sm text-gray-500">Create or edit email wording, and choose which template each notification uses.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('admin.email-templates.index') }}" class="px-4 py-2 rounded-xl border border-gray-300 text-gray-700 text-sm font-semibold hover:bg-gray-50">All email templates</a>
                <a href="{{ route('admin.email-template-mappings.index') }}" class="px-4 py-2 rounded-xl border border-purple-300 text-purple-700 text-sm font-semibold hover:bg-purple-50">Notification mappings</a>
            </div>
        </section>
    @endif
</div>
@endsection
