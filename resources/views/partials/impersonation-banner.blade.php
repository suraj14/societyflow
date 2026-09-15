@if(session('impersonating'))
<div class="bg-amber-500 text-white text-sm px-4 py-2 flex items-center justify-between gap-3 relative z-50">
    <span>
        <i class="fas fa-user-secret mr-1"></i>
        You are logged in as <strong>{{ auth()->user()?->name }}</strong>
        @if(auth()->user()?->society)
            &mdash; <strong>{{ auth()->user()->society->name }}</strong>
        @endif
    </span>
    <form method="POST" action="{{ route('impersonation.stop') }}" class="shrink-0">
        @csrf
        <button type="submit" class="bg-white text-amber-700 font-semibold px-3 py-1 rounded hover:bg-amber-50">
            Return to Super Admin
        </button>
    </form>
</div>
@endif
