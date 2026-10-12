{{-- SMS templates: connect to Fast2SMS, see the approved DLT templates, choose one for OTP and one for notifications.
     Used on the Message Templates page. Every choice is re-checked with Fast2SMS by the server (settings.sms.use-template);
     the OTP and notification choices are stored separately and never substitute for each other. --}}
@php $sfInput = 'w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-purple-500'; @endphp

<div class="bg-white rounded-2xl border border-gray-200 p-5 sm:p-6 space-y-4" x-data="sfSmsTemplates()">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h3 class="font-semibold text-gray-900">Choose approved SMS templates</h3>
            <p class="text-sm text-gray-500">Your DLT-approved templates from Fast2SMS. Pick one for login OTPs and one for notifications. Their wording is fixed by the approval and is not edited here.</p>
        </div>
        <button type="button" @click="connect()" :disabled="busy" class="px-4 py-2.5 rounded-xl bg-purple-600 text-white text-sm font-semibold hover:bg-purple-700 disabled:opacity-60 whitespace-nowrap">
            <i class="fas fa-rotate mr-1"></i> Fetch from Fast2SMS
        </button>
    </div>

    <form @submit.prevent="verify()" class="flex flex-col sm:flex-row gap-2">
        <input type="text" x-model="ref" placeholder="Already have a Message ID? Paste it here" class="flex-1 {{ $sfInput }}">
        <button type="submit" :disabled="busy || !ref" class="px-4 py-2.5 rounded-xl border border-purple-300 text-purple-700 text-sm font-semibold hover:bg-purple-50 disabled:opacity-60">Verify</button>
    </form>

    <div x-show="msg" x-cloak class="rounded-xl px-4 py-3 text-sm" role="status" :class="skipped ? 'bg-blue-50 text-blue-900' : (ok ? 'bg-green-50 text-green-900' : 'bg-red-50 text-red-900')">
        <p x-text="(skipped ? '' : (ok ? '✓ ' : '✕ ')) + msg"></p>
        <template x-if="details"><details class="mt-1 text-xs"><summary class="cursor-pointer">View technical details</summary><p class="font-mono break-words mt-1" x-text="details"></p></details></template>
    </div>

    <template x-if="templates.length">
        <ul class="divide-y divide-gray-100 border border-gray-200 rounded-xl max-h-96 overflow-y-auto">
            <template x-for="t in templates" :key="t.message_id">
                <li class="p-3 flex flex-col sm:flex-row sm:items-center gap-3" :class="highlight === t.message_id ? 'bg-purple-50' : ''">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm text-gray-900" x-text="t.text"></p>
                        <p class="text-xs text-gray-500 mt-0.5">Message ID <span class="font-mono" x-text="t.message_id"></span> &middot; <span x-text="t.sender_id"></span> &middot; <span x-text="t.var_count + ' variable(s)'"></span></p>
                    </div>
                    <div class="flex gap-2 shrink-0" x-show="t.var_count === 1">
                        <button type="button" @click="use('otp', t)" class="px-3 py-1.5 rounded-lg bg-purple-600 text-white text-xs font-semibold">Use for OTP</button>
                        <button type="button" @click="use('notification', t)" class="px-3 py-1.5 rounded-lg border border-purple-300 text-purple-700 text-xs font-semibold">Use for notifications</button>
                    </div>
                    <p class="text-xs text-gray-400 shrink-0" x-show="t.var_count !== 1">Needs exactly 1 variable</p>
                </li>
            </template>
        </ul>
    </template>
</div>

@once
@push('scripts')
<script>
function sfSmsTemplates() {
    const post = async (url, body) => {
        const r = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }, body: JSON.stringify(body || {}) });
        const data = await r.json().catch(() => ({}));
        if (r.status === 422) return { success: false, message: Object.values(data.errors || {}).flat()[0] || 'Please check the details.' };
        return data;
    };
    return {
        busy: false, ok: false, skipped: false, msg: '', details: null, ref: '', highlight: null, templates: [],
        show(d) { this.ok = !!d.success; this.skipped = !!d.skipped; this.msg = d.message || 'Something went wrong.'; this.details = d.details || null; if (d.templates) this.templates = d.templates; },
        async connect() { this.busy = true; this.show(await post(@js(route('settings.sms.connect')))); this.busy = false; },
        async verify() {
            this.busy = true;
            const d = await post(@js(route('settings.sms.verify')), { template_ref: this.ref });
            this.show(d);
            this.highlight = d.template ? d.template.message_id : null;
            this.busy = false;
        },
        async use(purpose, t) {
            this.busy = true;
            const d = await post(@js(route('settings.sms.use-template')), { purpose, message_id: t.message_id });
            this.show(d);
            this.busy = false;
            if (d.success) setTimeout(() => location.reload(), 900);
        },
    };
}
</script>
@endpush
@endonce
