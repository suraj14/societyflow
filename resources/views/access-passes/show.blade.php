@extends('layouts.app')

@section('title', 'Access Pass')
@section('page-title', 'Access Pass')

@section('content')
<div class="container mx-auto px-4 py-6 print:py-0">
    <div class="flex items-center mb-6 print:hidden">
        <a href="{{ route('access-passes.index') }}" class="text-gray-600 hover:text-gray-900 mr-4">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div>
            <h1 class="text-3xl font-bold text-gray-900">{{ $pass->person_name }}</h1>
            <p class="text-gray-600 mt-1">{{ $pass->pass_type === 'recurring_staff' ? 'Recurring Staff' : 'Temporary Service' }} - {{ $pass->category ?? 'No category' }}</p>
        </div>
    </div>


    <div class="max-w-md mx-auto bg-white rounded-lg shadow-md p-6 text-center print:shadow-none print:border" id="pass-card">
        {{-- Print-only header: the on-screen h1 above is hidden when printing. --}}
        <div class="hidden print:block mb-4">
            <p class="text-xs tracking-widest text-gray-500 uppercase">SocietyFlow Gate Pass</p>
            <h1 class="text-2xl font-bold text-gray-900">{{ $pass->person_name }}</h1>
            <p class="text-gray-600">{{ $pass->pass_type === 'recurring_staff' ? 'Recurring Staff' : 'Temporary Service' }} - {{ $pass->category ?? 'No category' }}</p>
        </div>

        @if($pass->status === 'revoked')
            {{--
                Revoking is a withdrawal, not a toggle: a revoked pass is
                never editable back into service (the server refuses it on
                every write path), so the only way forward is a new pass
                with its own token and its own audit trail.
            --}}
            <div class="bg-red-50 border border-red-200 text-red-700 rounded-lg p-4 mb-4">
                <p><i class="fas fa-ban mr-2"></i>This pass has been revoked and can no longer be scanned.</p>
                <a href="{{ route('access-passes.create') }}"
                   class="inline-block mt-3 px-4 py-2 bg-white border border-red-300 text-red-700 rounded-lg text-sm font-medium hover:bg-red-100 print:hidden">
                    <i class="fas fa-plus mr-1"></i> Create a new pass for {{ $pass->person_name }}
                </a>
            </div>
        @else
            {{--
                The QR is the primary, automatic credential - generated
                entirely client-side (the `qrcode` package, loaded from a
                CDN, no external QR *service*: the token never leaves this
                page). No "Generate QR" step exists because none is needed -
                store() already gave this pass a token, so a QR is always
                renderable the moment this page loads.
            --}}
            <div id="qr-code" class="flex justify-center items-center mb-3" style="min-height: 256px;">
                <div id="qr-loading" class="text-gray-400 text-sm">
                    <i class="fas fa-spinner fa-spin"></i> Generating QR…
                </div>
            </div>
            <p class="text-sm font-semibold text-gray-700">Scan at Gate</p>
            <p class="text-xs text-gray-500 mt-1 max-w-xs mx-auto">
                Show this QR at the gate. The guard can scan it to record entry automatically.
            </p>

            {{-- The long token is a fallback only - collapsed by default, never
                 the primary action. A guard with no working camera can still
                 type it into the scan screen's manual-entry field. --}}
            <details class="mt-4 text-left print:hidden">
                <summary class="text-xs text-gray-400 cursor-pointer hover:text-gray-600 select-none">
                    Camera not working? Show the code to enter manually
                </summary>
                <p class="font-mono text-xs text-gray-600 bg-gray-50 rounded-lg py-2 px-3 mt-2 break-all" data-token="{{ $pass->token }}" id="qr-token-text">
                    {{ $pass->token }}
                </p>
            </details>

            <div class="flex gap-2 mt-4 print:hidden">
                <button type="button" onclick="window.sfDownloadQr && window.sfDownloadQr()" class="flex-1 px-3 py-2 text-sm bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">
                    <i class="fas fa-download mr-1"></i> Download QR
                </button>
                <button type="button" onclick="window.print()" class="flex-1 px-3 py-2 text-sm bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">
                    <i class="fas fa-print mr-1"></i> Print Pass
                </button>
            </div>
        @endif

        <div class="text-left mt-6 space-y-2 text-sm text-gray-700 border-t pt-4">
            <p><strong>Access:</strong>
                @if($pass->flat)
                    {{ $pass->flat->property_type === 'villa' ? 'Villa ' . $pass->flat->flat_number : ($pass->flat->building->name ?? 'Building') . ' - ' . $pass->flat->flat_number }}
                @else
                    Entire Society
                @endif
            </p>
            @if($pass->isSocietyWide())
                <p><strong>Currently:</strong>
                    <span class="px-2 py-0.5 rounded-full text-xs font-semibold {{ $pass->currently_inside ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                        {{ $pass->currently_inside ? 'Inside' : 'Outside' }}
                    </span>
                    <span class="text-xs text-gray-500 print:hidden">(scan again at the gate to toggle)</span>
                </p>
            @endif
            @if($pass->pass_type === 'recurring_staff')
                <p><strong>Allowed Days:</strong>
                    @php $days = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat']; @endphp
                    {{ collect($pass->allowed_days ?? [])->map(fn($d) => $days[$d] ?? $d)->join(', ') ?: 'Every day' }}
                </p>
                <p><strong>Valid Until:</strong> {{ $pass->valid_until?->format('d M Y') ?? 'Indefinite' }}</p>
            @else
                <p><strong>Date:</strong> {{ $pass->valid_from->format('d M Y') }}</p>
            @endif
            @if($pass->start_time && $pass->end_time)
                <p><strong>Time Window:</strong> {{ \Illuminate\Support\Carbon::parse($pass->start_time)->format('h:i A') }} - {{ \Illuminate\Support\Carbon::parse($pass->end_time)->format('h:i A') }}</p>
            @endif
            <p><strong>Status:</strong>
                <span class="px-2 py-0.5 rounded-full text-xs font-semibold {{ $pass->status === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                    {{ ucfirst($pass->status) }}
                </span>
            </p>
        </div>

        @if($pass->status === 'active')
            {{--
                An expired pass is the common case this screen exists to
                repair, so it leads with "Extend" and a date already filled
                in - one field, one tap. The QR is untouched: the gate
                re-reads this row on every scan, so the same printed code
                starts working again the moment the date is saved.
            --}}
            @if($pass->isExpired())
                <div class="mt-6 pt-4 border-t print:hidden">
                    <div class="bg-amber-50 border border-amber-200 rounded-lg p-4">
                        <p class="text-sm text-amber-800 font-semibold mb-3">
                            <i class="fas fa-clock mr-1"></i>
                            Expired on {{ $pass->valid_until->format('d M Y') }}
                        </p>
                        <form action="{{ route('access-passes.extend', $pass) }}" method="POST" class="flex items-end gap-3">
                            @csrf
                            <div class="flex-1">
                                <label class="block text-xs font-medium text-gray-700 mb-1">Valid Until</label>
                                <input type="date" name="valid_until" required
                                       min="{{ now()->toDateString() }}"
                                       value="{{ now()->addMonth()->toDateString() }}"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
                            </div>
                            <button type="submit" class="px-5 py-2 bg-purple-600 text-white rounded-lg font-semibold hover:bg-purple-700 whitespace-nowrap">
                                <i class="fas fa-calendar-plus mr-1"></i> Extend Pass
                            </button>
                        </form>
                        <p class="text-xs text-amber-700 mt-2">
                            The existing QR code keeps working - nothing to reprint.
                        </p>
                    </div>
                </div>
            @endif

            <div class="flex flex-wrap gap-3 mt-6 pt-4 border-t print:hidden">
                <a href="{{ route('access-passes.edit', $pass) }}"
                   class="flex-1 text-center px-4 py-2 bg-purple-100 text-purple-800 rounded-lg font-medium hover:bg-purple-200">
                    <i class="fas fa-pen mr-1"></i> Edit Pass
                </a>

                @if($pass->isBlockedToday())
                    <form action="{{ route('access-passes.unblock-today', $pass) }}" method="POST" class="flex-1">
                        @csrf
                        <button type="submit" class="w-full px-4 py-2 bg-green-100 text-green-800 rounded-lg font-medium hover:bg-green-200">
                            <i class="fas fa-unlock mr-1"></i> Unblock Today
                        </button>
                    </form>
                @else
                    <form action="{{ route('access-passes.block-today', $pass) }}" method="POST" class="flex-1">
                        @csrf
                        <button type="submit" class="w-full px-4 py-2 bg-amber-100 text-amber-800 rounded-lg font-medium hover:bg-amber-200">
                            <i class="fas fa-ban mr-1"></i> Block Today
                        </button>
                    </form>
                @endif
                <form action="{{ route('access-passes.revoke', $pass) }}" method="POST" class="flex-1"
                      onsubmit="return confirm('Permanently revoke this pass? This cannot be undone.');">
                    @csrf
                    <button type="submit" class="w-full px-4 py-2 bg-red-100 text-red-800 rounded-lg font-medium hover:bg-red-200">
                        <i class="fas fa-trash mr-1"></i> Revoke
                    </button>
                </form>
            </div>
        @endif
    </div>
</div>

@if($pass->status !== 'revoked')
{{--
    Client-side QR rendering only - the token never leaves this page, and no
    external QR-*generation* website/API is contacted. The `qrcode` package
    (MIT, github.com/soldair/node-qrcode) runs entirely in the browser and
    draws directly to a <canvas>.

    Loaded via jsDelivr's `+esm` endpoint, not a `build/qrcode.min.js` path.
    That path was tried first and does NOT exist - confirmed by fetching it
    directly (404) and by browsing the published package on both jsDelivr
    and unpkg: this package ships no prebuilt browser bundle despite what
    its own package.json "files" field claims. `+esm` is jsDelivr's real,
    documented feature for exactly this case - it bundles any npm package
    (this one included) into a loadable ES module on the fly. Without this
    fix the QR canvas never rendered for anyone; only the fallback token
    ever appeared, which is the exact symptom that prompted looking here.
--}}
<script type="module">
    let sfQrCanvas = null;

    function showFallback(loading) {
        if (loading) {
            loading.innerHTML = '<i class="fas fa-triangle-exclamation text-amber-500"></i> '
                + 'QR could not be generated on this device. Use the manual code below.';
        }
    }

    // A module script is deferred by the spec (like a classic script with
    // the `defer` attribute) - it only runs once the document has been
    // fully parsed, which is always at or before DOMContentLoaded. The
    // elements below are already in the DOM by the time this line runs, so
    // no DOMContentLoaded listener is needed (or could introduce doubt
    // about firing order that a plain top-level run does not have).
    (async function () {
        const container = document.getElementById('qr-code');
        const loading = document.getElementById('qr-loading');
        const token = document.getElementById('qr-token-text')?.dataset.token;

        if (!token) {
            showFallback(loading);
            return;
        }

        let QRCode;
        try {
            ({ default: QRCode } = await import('https://cdn.jsdelivr.net/npm/qrcode@1.5.3/+esm'));
        } catch (e) {
            showFallback(loading);
            return;
        }

        // toCanvas(text, options, callback) - the callback's *second*
        // argument is the canvas QRCode itself created and drew to.
        QRCode.toCanvas(token, {
            width: 240,
            margin: 2,
            errorCorrectionLevel: 'M',
        }, function (err, canvas) {
            if (err || !canvas) {
                showFallback(loading);
                return;
            }
            if (loading) loading.remove();
            canvas.style.maxWidth = '100%';
            canvas.style.height = 'auto';
            container.appendChild(canvas);
            sfQrCanvas = canvas;
            window.sfDownloadQr = function () {
                if (!sfQrCanvas) return;
                const link = document.createElement('a');
                link.download = 'access-pass-{{ \Illuminate\Support\Str::slug($pass->person_name) }}.png';
                link.href = sfQrCanvas.toDataURL('image/png');
                link.click();
            };
        });
    })();
</script>
@endif
@endsection
