{{--
    In-page PDF reader shared by residents and guards. Renders with PDF.js so
    it works on Android Chrome too, which cannot show a PDF inside an iframe
    and would otherwise force a download just to read the rules. "Open"
    hands the same authorised URL to the browser's own viewer (search, print).
--}}
@php $sfFileUrl = route($routePrefix . '.file', $document->id); @endphp

<div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 mb-4">
    <div class="min-w-0">
        <p class="text-xs font-semibold uppercase tracking-wider text-purple-600">{{ $document->category_label }}</p>
        <h1 class="text-xl sm:text-2xl font-bold text-gray-900">{{ $document->title }}</h1>
        <p class="text-sm text-gray-500">
            {{ $document->version }}
            @if ($document->effective_from) &middot; Effective {{ $document->effective_from->format('d M Y') }} @endif
            &middot; Published {{ $document->published_at?->format('d M Y') }}
        </p>
        @if ($document->description)
            <p class="text-sm text-gray-600 mt-2 whitespace-pre-line">{{ $document->description }}</p>
        @endif
    </div>
    <div class="flex gap-2 shrink-0">
        <a href="{{ $sfFileUrl }}" target="_blank" rel="noopener" class="px-3.5 py-2 rounded-xl border border-gray-200 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
            <i class="fas fa-up-right-from-square mr-1"></i> Open
        </a>
        <a href="{{ $sfFileUrl }}?download=1" class="px-3.5 py-2 rounded-xl border border-gray-200 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
            <i class="fas fa-download mr-1"></i> Download
        </a>
    </div>
</div>

@if ($document->requires_acknowledgement)
    <div class="mb-4 rounded-2xl border {{ $acknowledged ? 'border-green-200 bg-green-50' : 'border-amber-200 bg-amber-50' }} px-4 py-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        @if ($acknowledged)
            <p class="text-sm text-green-800"><i class="fas fa-circle-check mr-1"></i> You confirmed reading this version.</p>
        @else
            <p class="text-sm text-amber-800">The society office asks residents to confirm they have read this document.</p>
            <form method="POST" action="{{ route($routePrefix . '.acknowledge', $document->id) }}">
                @csrf
                <button class="w-full sm:w-auto px-4 py-2 rounded-xl bg-purple-600 text-white text-sm font-semibold hover:bg-purple-700">I have read this</button>
            </form>
        @endif
    </div>
@endif

<div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden" id="sf-pdf">
    <div class="flex items-center justify-between gap-2 px-3 py-2 border-b border-gray-100 bg-gray-50 sticky top-0 z-10">
        <span class="text-xs text-gray-500" id="sf-pdf-status">Loading&hellip;</span>
        <div class="flex items-center gap-1">
            <button type="button" id="sf-zoom-out" class="w-9 h-9 rounded-lg hover:bg-gray-200 text-gray-700" aria-label="Zoom out"><i class="fas fa-magnifying-glass-minus"></i></button>
            <span class="text-xs text-gray-600 w-12 text-center" id="sf-zoom-label">100%</span>
            <button type="button" id="sf-zoom-in" class="w-9 h-9 rounded-lg hover:bg-gray-200 text-gray-700" aria-label="Zoom in"><i class="fas fa-magnifying-glass-plus"></i></button>
        </div>
    </div>
    <div id="sf-pdf-pages" class="bg-gray-200 p-2 sm:p-4 overflow-auto max-h-[80vh] space-y-3"></div>
    <div id="sf-pdf-error" class="hidden p-6 text-center text-sm text-gray-600">
        This document could not be displayed here.
        <a href="{{ $sfFileUrl }}" target="_blank" rel="noopener" class="text-purple-600 font-medium">Open it directly</a>.
    </div>
</div>

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script>
(function () {
    var pagesEl = document.getElementById('sf-pdf-pages'),
        statusEl = document.getElementById('sf-pdf-status'),
        zoomLabel = document.getElementById('sf-zoom-label'),
        errorEl = document.getElementById('sf-pdf-error'),
        pdfDoc = null, zoom = 1, rendering = 0;

    function fail() { pagesEl.classList.add('hidden'); errorEl.classList.remove('hidden'); statusEl.textContent = ''; }
    if (!window.pdfjsLib) { fail(); return; }
    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

    function render() {
        var token = ++rendering;
        pagesEl.innerHTML = '';
        zoomLabel.textContent = Math.round(zoom * 100) + '%';
        var width = pagesEl.clientWidth - 16, dpr = window.devicePixelRatio || 1;
        var chain = Promise.resolve();
        for (var n = 1; n <= pdfDoc.numPages; n++) {
            (function (num) {
                chain = chain.then(function () {
                    if (token !== rendering) return;
                    return pdfDoc.getPage(num).then(function (page) {
                        var base = page.getViewport({ scale: 1 });
                        var scale = (width / base.width) * zoom;
                        var vp = page.getViewport({ scale: scale * dpr });
                        var canvas = document.createElement('canvas');
                        canvas.width = vp.width; canvas.height = vp.height;
                        canvas.style.width = (vp.width / dpr) + 'px';
                        canvas.className = 'mx-auto bg-white shadow rounded';
                        pagesEl.appendChild(canvas);
                        return page.render({ canvasContext: canvas.getContext('2d'), viewport: vp }).promise;
                    });
                });
            })(n);
        }
        chain.catch(fail);
    }

    pdfjsLib.getDocument({ url: @json($sfFileUrl), withCredentials: true }).promise.then(function (doc) {
        pdfDoc = doc;
        statusEl.textContent = doc.numPages + (doc.numPages === 1 ? ' page' : ' pages');
        render();
    }).catch(fail);

    document.getElementById('sf-zoom-in').addEventListener('click', function () { if (pdfDoc && zoom < 3) { zoom += 0.25; render(); } });
    document.getElementById('sf-zoom-out').addEventListener('click', function () { if (pdfDoc && zoom > 0.5) { zoom -= 0.25; render(); } });
})();
</script>
@endpush
