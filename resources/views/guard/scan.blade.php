@extends('layouts.guard')

@section('title', 'Scan')
@section('page-title', 'Scan QR')

@section('content')
<div class="p-4 space-y-5">

    {{--
        Camera scan when the browser supports it, with a manual-entry field
        always available underneath - a guard whose phone camera is blocked,
        dirty, or unsupported still has to be able to let someone in, and a
        USB/handheld QR scanner that "types" its result works through the
        same field with zero extra code.
    --}}
    <div class="bg-white rounded-xl shadow-sm p-4">
        <div id="scanner-wrap" class="hidden">
            <video id="scanner-video" class="w-full rounded-lg bg-black aspect-square object-cover"></video>
            <p class="text-xs text-gray-500 text-center mt-2">Point the camera at the QR code.</p>
        </div>
        <button id="start-camera" type="button"
                class="tap-target w-full bg-gray-900 text-white rounded-lg flex items-center justify-center gap-2 font-medium py-3">
            <i class="fas fa-camera"></i> Start Camera
        </button>
    </div>

    <form id="scan-form" action="{{ route('guard.scan.submit') }}" method="POST" class="bg-white rounded-xl shadow-sm p-4 space-y-3">
        @csrf
        <label class="block text-sm font-medium text-gray-700">Or enter the code manually</label>
        <input type="text" name="token" id="token-input" autocomplete="off" autocapitalize="off"
               class="tap-target w-full px-4 py-3 border border-gray-300 rounded-lg text-lg font-mono tracking-wide focus:ring-2 focus:ring-purple-500"
               placeholder="Paste or type code">
        <button type="submit" class="tap-target w-full bg-purple-700 hover:bg-purple-800 text-white rounded-lg font-semibold py-3">
            <i class="fas fa-check-circle mr-1"></i> Verify
        </button>
    </form>

    <div id="scan-result"></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js"></script>
<script>
(function () {
    const startBtn = document.getElementById('start-camera');
    const wrap = document.getElementById('scanner-wrap');
    const video = document.getElementById('scanner-video');
    const tokenInput = document.getElementById('token-input');
    const form = document.getElementById('scan-form');
    const result = document.getElementById('scan-result');
    let stream = null;
    let scanning = false;

    startBtn.addEventListener('click', async function () {
        if (!navigator.mediaDevices?.getUserMedia || !window.jsQR) {
            showResult(false, 'Camera scanning is not available on this device - use the code field below.');
            return;
        }
        try {
            stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
            video.srcObject = stream;
            video.setAttribute('playsinline', true);
            await video.play();
            wrap.classList.remove('hidden');
            startBtn.classList.add('hidden');
            scanning = true;
            requestAnimationFrame(tick);
        } catch (e) {
            showResult(false, 'Could not access the camera - use the code field below.');
        }
    });

    function tick() {
        if (!scanning) return;
        if (video.readyState === video.HAVE_ENOUGH_DATA) {
            const canvas = document.createElement('canvas');
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            const ctx = canvas.getContext('2d');
            ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
            const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
            const code = jsQR(imageData.data, canvas.width, canvas.height);
            if (code && code.data) {
                scanning = false;
                stopCamera();
                tokenInput.value = code.data.trim();
                form.requestSubmit();
                return;
            }
        }
        requestAnimationFrame(tick);
    }

    function stopCamera() {
        stream?.getTracks().forEach(t => t.stop());
        wrap.classList.add('hidden');
        startBtn.classList.remove('hidden');
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        const token = tokenInput.value.trim();
        if (!token) return;

        fetch(form.action, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ token }),
        })
            .then(r => r.json().then(data => ({ status: r.status, data })))
            .then(({ data }) => {
                showResult(data.success, data.message, data.person, data.category);
                tokenInput.value = '';
                if (data.stats) {
                    Object.entries(data.stats).forEach(([key, value]) => {
                        const el = document.querySelector(`[data-stat="${key}"]`);
                        if (el) el.textContent = value;
                    });
                }
            })
            .catch(() => showResult(false, 'Could not reach the server. Try again.'));
    });

    function showResult(success, message, person, category) {
        result.innerHTML = `
            <div class="rounded-xl p-5 text-center shadow-sm ${success ? 'bg-green-600 text-white' : 'bg-red-600 text-white'}">
                <i class="fas ${success ? 'fa-circle-check' : 'fa-circle-xmark'} text-4xl mb-2"></i>
                <p class="text-xl font-bold">${success ? 'ENTRY ALLOWED' : 'ACCESS DENIED'}</p>
                ${person ? `<p class="mt-1">${person}${category ? ' &middot; ' + category : ''}</p>` : ''}
                <p class="text-sm opacity-90 mt-1">${message}</p>
            </div>`;
    }
})();
</script>
@endsection
