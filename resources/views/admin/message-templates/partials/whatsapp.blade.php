{{-- Message Templates -> WhatsApp. The templates come from Fast2SMS (Refresh): their wording is fixed by the provider's
     approval, so you choose which approved template each notification uses - you do not edit its text. --}}
@php
    $sfWa = $settings['whatsapp'] ?? [];
    $sfWaSociety = auth()->user()->hasRole('Super Admin') ? null : auth()->user()->society_id;
    $sfWaTemplates = \App\Models\WhatsAppTemplate::bySociety($sfWaSociety)->orderByRaw("CASE WHEN upper(status) = 'APPROVED' THEN 0 ELSE 1 END")->orderBy('name')->get();
    $sfWaPurposes = \App\Support\WhatsAppPurposes::all();
    $sfWaByName = $sfWaTemplates->keyBy('name');
    $sfWaAssigned = [];
    foreach ($sfWaPurposes as $key => $p) {
        $name = (string) ($sfWa["whatsapp_{$key}_template_name"] ?? '');
        $sfWaAssigned[$key] = ['name' => $name, 'template' => $sfWaByName->get($name)];
    }
    $sfUsedFor = [];
    foreach ($sfWaAssigned as $key => $a) {
        if ($a['template']) { $sfUsedFor[$a['template']->id][] = $sfWaPurposes[$key]['label']; }
    }
    $sfInput = 'w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-purple-500';
    $sfJsTemplates = $sfWaTemplates->filter->isApproved()->map(fn ($t) => [
        'id' => $t->id, 'label' => $t->displayName(), 'language' => $t->language, 'vars' => $t->var_count, 'body' => $t->body_text,
    ])->values();
    $sfJsPurposes = collect($sfWaPurposes)->map(fn ($p, $k) => ['key' => $k, 'label' => $p['label'], 'fields' => $p['fields']])->values();
@endphp

<div class="space-y-6" x-data="sfWhatsApp(@js($sfJsTemplates), @js($sfJsPurposes))">
    <div class="flex flex-col sm:flex-row sm:items-center gap-3">
        <p class="flex-1 text-sm text-gray-600">Approved templates are read from your Fast2SMS WhatsApp account. Press Refresh after you add or change one there.</p>
        <button type="button" @click="call(@js(route('settings.whatsapp.sync')))" :disabled="busy" class="shrink-0 inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl border border-gray-300 text-gray-700 text-sm font-semibold hover:bg-gray-50 disabled:opacity-60"><i class="fas fa-rotate text-xs"></i> Refresh from Fast2SMS</button>
    </div>
    <div x-show="msg" x-cloak class="rounded-xl px-4 py-3 text-sm" role="status" :class="skipped ? 'bg-blue-50 text-blue-900' : (ok ? 'bg-green-50 text-green-900' : 'bg-red-50 text-red-900')">
        <p x-text="(skipped ? '' : (ok ? '✓ ' : '✕ ')) + msg"></p>
        <template x-if="details"><p class="font-mono text-xs break-words mt-1" x-text="details"></p></template>
    </div>

    @if ($unmappedWhatsApp)
        <p class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-2.5 text-sm text-amber-900" role="status">
            <i class="fas fa-triangle-exclamation mr-1"></i> No WhatsApp template is chosen for: <strong>{{ implode(', ', $unmappedWhatsApp) }}</strong>. Those notifications are not sent on WhatsApp.
        </p>
    @endif

    {{-- Which template each notification uses --}}
    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <div class="px-5 sm:px-6 py-4 border-b border-gray-100">
            <h3 class="font-semibold text-gray-900">Notifications on WhatsApp</h3>
            <p class="text-sm text-gray-500">Choose a template only for the messages you want on WhatsApp. The rest still go by email (and SMS, if on).</p>
        </div>
        <ul class="divide-y divide-gray-100">
            @foreach ($sfWaPurposes as $key => $p)
                @php $a = $sfWaAssigned[$key]; @endphp
                <li class="px-5 sm:px-6 py-3 flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-4">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-900">{{ $p['label'] }}</p>
                        <p class="text-xs text-gray-500">{{ $p['help'] }}</p>
                    </div>
                    <div class="text-sm sm:text-right">
                        @if ($a['template'])
                            <span class="inline-flex items-center gap-1 text-green-700"><i class="fas fa-circle-check"></i> {{ $a['template']->displayName() }}</span>
                        @elseif ($a['name'] !== '')
                            <span class="inline-flex items-center gap-1 text-amber-700" title="Not among your synced templates"><i class="fas fa-triangle-exclamation"></i> Needs a new template</span>
                        @else
                            <span class="text-gray-400">Not sent on WhatsApp</span>
                        @endif
                    </div>
                    <div class="flex gap-2 shrink-0">
                        <button type="button" @click="open(@js($key), {{ $a['template']->id ?? 'null' }})" class="px-3 py-1.5 rounded-lg border border-gray-300 text-gray-700 text-xs font-semibold hover:bg-gray-50">{{ $a['name'] !== '' ? 'Change' : 'Choose template' }}</button>
                        @if ($a['name'] !== '')
                            <button type="button" @click="unassign(@js($key))" class="px-3 py-1.5 rounded-lg text-red-600 text-xs font-semibold hover:bg-red-50">Turn off</button>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    </div>

    {{-- Choose template + variables --}}
    <div x-show="panel" x-cloak class="bg-white rounded-2xl border-2 border-green-300 p-5 sm:p-6 space-y-4" x-ref="panel">
        <div class="flex items-center justify-between">
            <h3 class="font-semibold text-gray-900">Use a template</h3>
            <button type="button" @click="panel = false" class="text-gray-400 hover:text-gray-600" aria-label="Close"><i class="fas fa-xmark"></i></button>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Send it for</label>
                <select x-model="purpose" @change="resetVars()" class="{{ $sfInput }}">
                    <template x-for="p in purposes" :key="p.key"><option :value="p.key" x-text="p.label"></option></template>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Approved template</label>
                <select x-model.number="templateId" @change="resetVars()" class="{{ $sfInput }}">
                    <option value="">Choose&hellip;</option>
                    <template x-for="t in templates" :key="t.id"><option :value="t.id" x-text="t.label + ' (' + t.vars + ' variable' + (t.vars === 1 ? '' : 's') + ')'"></option></template>
                </select>
            </div>
        </div>
        <template x-if="tpl()">
            <div class="space-y-3">
                <p class="text-sm bg-gray-50 rounded-xl p-3 text-gray-700 whitespace-pre-line" x-text="tpl().body || '(no body text)'"></p>
                <p x-show="tpl().vars > fields().length" class="text-sm text-red-700">This template needs <span x-text="tpl().vars"></span> values but this notification can fill only <span x-text="fields().length"></span>. Choose a simpler template.</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3" x-show="tpl().vars <= fields().length">
                    <template x-for="(v, i) in vars" :key="i">
                        <label class="text-sm">
                            <span class="block text-gray-700 mb-1">Fill <span class="font-mono" x-text="'{' + '{' + (i + 1) + '}' + '}'"></span> with</span>
                            <select x-model="vars[i]" class="{{ $sfInput }}">
                                <template x-for="(label, key) in fields(true)" :key="key"><option :value="key" x-text="label" :selected="key === vars[i]"></option></template>
                            </select>
                        </label>
                    </template>
                </div>
            </div>
        </template>
        <div class="flex justify-end gap-2">
            <button type="button" @click="panel = false" class="px-4 py-2 rounded-xl border border-gray-200 text-sm">Cancel</button>
            <button type="button" @click="assign()" :disabled="busy || !tpl() || tpl().vars > fields().length" class="px-5 py-2 rounded-xl bg-green-600 text-white text-sm font-semibold disabled:opacity-50">Use this template</button>
        </div>
    </div>

    {{-- The templates, as cards --}}
    @if ($sfWaTemplates->isEmpty())
        <div class="bg-white rounded-2xl border border-gray-200 px-6 py-12 text-center">
            <p class="text-gray-600">No WhatsApp templates yet.</p>
            <p class="text-sm text-gray-500 mt-1">Save your API key and Phone Number ID in <a href="{{ route('settings.index', ['tab' => 'whatsapp']) }}" class="underline">WhatsApp settings</a>, then press Refresh from Fast2SMS.</p>
        </div>
    @else
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4" id="whatsapp-templates">
            @foreach ($sfWaTemplates as $t)
                <article class="bg-white rounded-2xl border border-gray-200 p-5 flex flex-col gap-3 {{ $t->isApproved() ? '' : 'opacity-75' }}" data-template-id="{{ $t->id }}">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h3 class="font-semibold text-gray-900 break-words">{{ $t->displayName() }}</h3>
                            <div class="mt-1.5 flex flex-wrap gap-1.5 text-xs font-semibold">
                                <span class="px-2 py-0.5 rounded-full {{ $t->isApproved() ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-500' }}">{{ ucfirst(strtolower((string) $t->status)) }}</span>
                                @if ($t->message_id)
                                    <span class="px-2 py-0.5 rounded-full bg-green-50 text-green-700">WA: {{ $t->message_id }}</span>
                                @else
                                    <span class="px-2 py-0.5 rounded-full bg-amber-50 text-amber-800" title="Sending needs the Fast2SMS message ID. Refresh to fetch it.">No message ID - refresh</span>
                                @endif
                                @if ($t->language)<span class="px-2 py-0.5 rounded-full bg-gray-100 text-gray-600">{{ $t->language }}</span>@endif
                                @if ($t->category)<span class="px-2 py-0.5 rounded-full bg-gray-100 text-gray-600">{{ ucfirst(strtolower((string) $t->category)) }}</span>@endif
                                <span class="px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-700" title="Read from your Fast2SMS account">From Fast2SMS</span>
                            </div>
                        </div>
                        @if ($t->isApproved())
                            <button type="button" @click="open(null, {{ $t->id }})" class="shrink-0 px-3 py-1.5 rounded-lg bg-green-600 text-white text-xs font-semibold hover:bg-green-700">Use</button>
                        @endif
                    </div>

                    <p class="text-sm text-gray-800 bg-gray-50 rounded-xl px-3 py-2.5 whitespace-pre-line break-words">{{ $t->body_text ?: '(no body text)' }}</p>

                    <div class="flex flex-wrap items-center justify-between gap-2 text-xs text-gray-500">
                        <span>{{ $t->var_count }} {{ \Illuminate\Support\Str::plural('variable', $t->var_count) }}</span>
                        <span>Used for: <strong class="text-gray-700">{{ implode(', ', $sfUsedFor[$t->id] ?? []) ?: 'nothing yet' }}</strong></span>
                    </div>
                </article>
            @endforeach
        </div>
    @endif
</div>

@include('admin.message-templates.partials.whatsapp-js')
