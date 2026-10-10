{{--
    Connection status banner for one channel (email / SMS / WhatsApp).
    $configured: required fields are saved. $status: ChannelStatus::get() -
    the last REAL provider check, cleared whenever the settings change.
--}}
@php $sfAgo = isset($status['at']) ? \Illuminate\Support\Carbon::parse($status['at'])->diffForHumans() : null; @endphp

@if (!$configured)
    <div class="rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 flex items-start gap-3">
        <i class="fas fa-triangle-exclamation text-amber-500 mt-0.5"></i>
        <div>
            <p class="font-semibold text-amber-900">{{ $notConfiguredLabel }}</p>
            <p class="text-sm text-amber-800">{{ $notConfiguredHint ?? 'Fill in the details below and save.' }}</p>
        </div>
    </div>
@elseif ($status && $status['ok'])
    <div class="rounded-2xl border border-green-200 bg-green-50 px-5 py-4 flex items-start gap-3">
        <i class="fas fa-circle-check text-green-600 mt-0.5"></i>
        <div>
            <p class="font-semibold text-green-900">{{ $okLabel }}</p>
            <p class="text-sm text-green-800">{{ $status['summary'] }} &middot; {{ $sfAgo }}</p>
        </div>
    </div>
@elseif ($status)
    <div class="rounded-2xl border border-red-200 bg-red-50 px-5 py-4 flex items-start gap-3">
        <i class="fas fa-circle-xmark text-red-600 mt-0.5"></i>
        <div class="min-w-0">
            <p class="font-semibold text-red-900">{{ $status['summary'] }}</p>
            <p class="text-sm text-red-800">Checked {{ $sfAgo }}. Fix the settings below, save, and test again.</p>
            @if (!empty($status['detail']))
                <details class="mt-2 text-xs text-red-800">
                    <summary class="cursor-pointer select-none">View technical details</summary>
                    <p class="mt-1 font-mono break-words">{{ $status['detail'] }}</p>
                </details>
            @endif
        </div>
    </div>
@else
    <div class="rounded-2xl border border-blue-200 bg-blue-50 px-5 py-4 flex items-start gap-3">
        <i class="fas fa-circle-check text-blue-600 mt-0.5"></i>
        <div>
            <p class="font-semibold text-blue-900">{{ $savedLabel }}</p>
            <p class="text-sm text-blue-800">{{ $savedHint }}</p>
        </div>
    </div>
@endif
