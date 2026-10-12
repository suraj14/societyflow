{{--
    "Send a test" card shared by Email / SMS / WhatsApp. Posts the fields as
    JSON to $url, which answers {success, message, details?}. Success is only
    shown when the server says the provider accepted the message; the
    provider's own wording stays behind "View technical details".
--}}
<div class="rounded-2xl border border-green-200 bg-green-50/60 p-5 sm:p-6"
     x-data="sfChannelTest(@js($url))">
    <h3 class="font-semibold text-gray-900 flex items-center gap-2"><i class="fas {{ $icon }} text-green-600"></i> {{ $title }}</h3>
    <p class="text-sm text-gray-600 mt-1 mb-4">{{ $hint }}</p>

    <form @submit.prevent="send($el)" class="grid grid-cols-1 sm:grid-cols-3 gap-3 items-end" data-allow-resubmit>
        @foreach ($fields as $f)
            <div class="{{ ($f['wide'] ?? false) ? 'sm:col-span-3' : (count($fields) === 1 ? 'sm:col-span-2' : '') }}">
                <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ $f['label'] }}</label>
                @if (($f['type'] ?? 'text') === 'select')
                    <select name="{{ $f['name'] }}" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm bg-white focus:ring-2 focus:ring-green-500">
                        @foreach ($f['options'] as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                @elseif (($f['type'] ?? 'text') === 'textarea')
                    <div x-data="{ n: {{ mb_strlen($f['value'] ?? '') }} }">
                        <textarea name="{{ $f['name'] }}" rows="3" @if (!empty($f['maxlength'])) maxlength="{{ $f['maxlength'] }}" @endif
                                  @input="n = $event.target.value.length" @if ($f['required'] ?? true) required @endif
                                  class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm bg-white focus:ring-2 focus:ring-green-500">{{ $f['value'] ?? '' }}</textarea>
                        @if (!empty($f['counter']))<p class="text-xs text-gray-500 mt-1 text-right"><span x-text="n"></span>/{{ $f['counter'] }}</p>@endif
                    </div>
                @else
                    <input type="{{ $f['type'] ?? 'text' }}" name="{{ $f['name'] }}" value="{{ $f['value'] ?? '' }}" placeholder="{{ $f['placeholder'] ?? '' }}"
                           @if (!empty($f['maxlength'])) maxlength="{{ $f['maxlength'] }}" @endif
                           @if (!empty($f['inputmode'])) inputmode="{{ $f['inputmode'] }}" @endif
                           @if ($f['required'] ?? true) required @endif class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm bg-white focus:ring-2 focus:ring-green-500">
                @endif
            </div>
        @endforeach
        <button type="submit" :disabled="busy"
                class="px-5 py-2.5 rounded-xl bg-green-600 text-white text-sm font-semibold hover:bg-green-700 disabled:opacity-60 whitespace-nowrap">
            <span x-show="!busy">{{ $button }}</span>
            <span x-show="busy" x-cloak><i class="fas fa-spinner fa-spin mr-1"></i> Sending&hellip;</span>
        </button>
    </form>

    <div x-show="result" x-cloak class="mt-4 rounded-xl px-4 py-3 text-sm"
         :class="result && result.success ? 'bg-green-100 text-green-900' : 'bg-red-100 text-red-900'">
        <p class="font-medium" x-text="(result && result.success ? '✓ ' : '✕ ') + (result ? result.message : '')"></p>
        <template x-if="result && result.details">
            <details class="mt-1 text-xs">
                <summary class="cursor-pointer select-none">View technical details</summary>
                <p class="mt-1 font-mono break-words" x-text="result.details"></p>
            </details>
        </template>
        <template x-if="result && result.raw">
            <div class="mt-2">
                <p class="text-xs font-semibold">Fast2SMS response</p>
                <pre class="mt-1 text-xs font-mono whitespace-pre-wrap break-words bg-white/70 rounded-lg p-2" x-text="result.raw"></pre>
            </div>
        </template>
    </div>
</div>

@once
@push('scripts')
<script>
function sfChannelTest(url) {
    return {
        busy: false,
        result: null,
        async send(form) {
            this.busy = true;
            this.result = null;
            try {
                const body = Object.fromEntries(new FormData(form).entries());
                const r = await fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json',
                               'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                    body: JSON.stringify(body),
                });
                const data = await r.json().catch(() => ({}));
                if (r.status === 422) {
                    this.result = { success: false, message: Object.values(data.errors || {}).flat()[0] || 'Please check the details entered.' };
                } else {
                    this.result = { success: !!data.success, message: data.message || 'Something went wrong.', details: data.details || null, raw: data.raw || null };
                }
            } catch (e) {
                this.result = { success: false, message: 'Could not reach the server. Check your connection and try again.' };
            } finally {
                this.busy = false;
            }
        },
    };
}
</script>
@endpush
@endonce
