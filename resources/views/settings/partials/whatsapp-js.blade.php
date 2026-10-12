{{-- Alpine component shared by Settings -> WhatsApp (connect / sync / test) and Message Templates (verify / assign). --}}
@once
@push('scripts')
<script>
function sfWhatsApp(templates, purposes) {
    const post = async (url, body) => {
        const r = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }, body: JSON.stringify(body || {}) });
        const data = await r.json().catch(() => ({}));
        if (r.status === 422) return { success: false, message: Object.values(data.errors || {}).flat()[0] || 'Please check the details.' };
        return data;
    };
    return {
        templates, purposes,
        busy: false, ok: false, skipped: false, msg: '', details: null, ref: '', numbers: [], numberId: '',
        panel: false, purpose: (purposes[0] || { key: '' }).key, templateId: '', vars: [],
        show(d) { this.ok = !!d.success; this.skipped = !!d.skipped; this.msg = d.message || 'Something went wrong.'; this.details = d.details || null; if (d.success && d.reload) setTimeout(() => location.reload(), 1000); },
        async call(url, body) { this.busy = true; const d = await post(url, body); this.show(d); this.busy = false; return d; },
        async connect(numberId) {
            const d = await this.call(@js(route('settings.whatsapp.connect')), numberId ? { phone_number_id: numberId } : {});
            this.numbers = d.choose ? d.numbers : [];
            if (d.choose && this.numbers.length) this.numberId = this.numbers[0].phone_number_id;
        },
        async verify() {
            const d = await this.call(@js(route('settings.whatsapp.verify-template')), { template_ref: this.ref });
            if (d.success && d.template) this.open(null, d.template.id);
        },
        async unassign(purpose) { if (confirm('Stop sending this notification on WhatsApp?')) await this.call(@js(route('settings.whatsapp.unassign')), { purpose }); },
        async assign() { await this.call(@js(route('settings.whatsapp.assign')), { purpose: this.purpose, template_id: this.templateId, variables: this.vars }); },
        open(purpose, templateId) {
            if (purpose) this.purpose = purpose;
            this.templateId = templateId || '';
            this.resetVars();
            this.panel = true;
            this.$nextTick(() => this.$refs.panel.scrollIntoView({ behavior: 'smooth', block: 'center' }));
        },
        tpl() { return this.templates.find(t => t.id === Number(this.templateId)) || null; },
        fields(asObject) {
            const f = (this.purposes.find(p => p.key === this.purpose) || { fields: {} }).fields;
            return asObject ? f : Object.keys(f);
        },
        resetVars() { const t = this.tpl(); const keys = this.fields(); this.vars = t ? Array.from({ length: t.vars }, (_, i) => keys[i] || keys[0]) : []; },
    };
}
</script>
@endpush
@endonce
