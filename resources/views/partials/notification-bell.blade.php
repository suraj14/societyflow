{{-- Header bell: the logged-in user's own in-app notifications (NotificationController). --}}
<div class="relative" x-data="sfBell(@js(route('notifications.feed')), @js(route('notifications.read-all')), @js(url('notifications')))" x-init="load()" @keydown.escape.window="open = false">
    <button type="button" @click="toggle()" aria-label="Notifications" :aria-expanded="open"
            class="relative p-2 {{ $bellButtonClass ?? 'text-gray-500 hover:text-gray-700 hover:bg-gray-100' }} rounded-lg transition-colors">
        <i class="fas fa-bell text-lg"></i>
        <span x-show="unread > 0" x-cloak x-text="unread > 9 ? '9+' : unread"
              class="absolute -top-0.5 -right-0.5 min-w-[18px] h-[18px] px-1 rounded-full bg-red-500 text-white text-[10px] font-bold leading-[18px] text-center ring-2 ring-white"></span>
    </button>

    <div x-show="open" x-cloak @click.outside="open = false" x-transition.origin.top.right
         class="absolute right-0 mt-2 w-[22rem] max-w-[calc(100vw-1rem)] bg-white rounded-2xl shadow-xl border border-gray-200 z-50 overflow-hidden text-left">
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100">
            <p class="font-semibold text-gray-900">Notifications</p>
            <button type="button" x-show="unread > 0" @click="readAll()" class="text-xs font-medium text-purple-600 hover:text-purple-800">Mark all as read</button>
        </div>
        <div class="max-h-96 overflow-y-auto">
            <template x-if="loading && !items.length"><p class="px-4 py-6 text-sm text-gray-500 text-center">Loading&hellip;</p></template>
            <template x-if="!loading && !items.length">
                <div class="px-4 py-10 text-center">
                    <i class="fas fa-bell-slash text-2xl text-gray-300"></i>
                    <p class="text-sm text-gray-500 mt-2">You're all caught up.</p>
                </div>
            </template>
            <template x-for="n in items" :key="n.id">
                <button type="button" @click="openItem(n)" class="w-full flex gap-3 px-4 py-3 text-left hover:bg-gray-50 border-b border-gray-50" :class="n.read ? '' : 'bg-purple-50/40'">
                    <span class="w-9 h-9 shrink-0 rounded-xl flex items-center justify-center" :class="n.read ? 'bg-gray-100 text-gray-500' : 'bg-purple-100 text-purple-600'"><i class="fas" :class="n.icon"></i></span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-sm text-gray-900" :class="n.read ? '' : 'font-semibold'" x-text="n.title"></span>
                        <span class="block text-xs text-gray-600 truncate" x-text="n.body"></span>
                        <span class="block text-[11px] text-gray-400 mt-0.5" x-text="n.time_ago"></span>
                    </span>
                    <span x-show="!n.read" class="w-2 h-2 rounded-full bg-purple-600 mt-1.5 shrink-0"></span>
                </button>
            </template>
        </div>
        <a href="{{ route('notifications.index') }}" class="block px-4 py-2.5 text-center text-sm font-medium text-purple-600 hover:bg-purple-50 border-t border-gray-100">View all</a>
    </div>
</div>

@once
@push('scripts')
<script>
function sfBell(feedUrl, readAllUrl, base) {
    const headers = () => ({ 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' });
    return {
        open: false, loading: false, unread: 0, items: [],
        async load() {
            this.loading = true;
            try {
                const r = await fetch(feedUrl, { headers: headers(), credentials: 'same-origin' });
                if (r.ok) { const d = await r.json(); this.unread = d.unread_count; this.items = d.items; }
            } catch (e) { /* the bell is optional - never break the page */ }
            this.loading = false;
        },
        toggle() { this.open = !this.open; if (this.open) this.load(); },
        async readAll() {
            await fetch(readAllUrl, { method: 'POST', headers: headers(), credentials: 'same-origin' });
            this.items.forEach(n => n.read = true); this.unread = 0;
        },
        async openItem(n) {
            if (!n.read) {
                await fetch(base + '/' + n.id + '/read', { method: 'POST', headers: headers(), credentials: 'same-origin' }).catch(() => {});
                n.read = true; this.unread = Math.max(0, this.unread - 1);
            }
            if (n.url) window.location.href = n.url;
        },
    };
}
</script>
@endpush
@endonce
