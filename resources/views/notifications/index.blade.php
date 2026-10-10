@extends('layouts.app')

@section('title', 'Notifications')
@section('page-title', 'Notifications')

@section('content')
<div class="container mx-auto px-4 py-6 sm:py-8 max-w-3xl">
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-bold text-gray-900">Notifications</h1>
        @if (auth()->user()->unreadNotifications()->exists())
            <form method="POST" action="{{ route('notifications.read-all') }}">
                @csrf
                <button class="text-sm font-medium text-purple-600 hover:text-purple-800">Mark all as read</button>
            </form>
        @endif
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        @forelse ($notifications as $n)
            @php $d = \App\Http\Controllers\NotificationController::format($n); @endphp
            <form method="POST" action="{{ route('notifications.read', $n->id) }}" class="border-b border-gray-100 last:border-0">
                @csrf
                <button class="w-full flex gap-3 px-5 py-4 text-left hover:bg-gray-50 {{ $d['read'] ? '' : 'bg-purple-50/40' }}">
                    <span class="w-10 h-10 shrink-0 rounded-xl flex items-center justify-center {{ $d['read'] ? 'bg-gray-100 text-gray-500' : 'bg-purple-100 text-purple-600' }}"><i class="fas {{ $d['icon'] }}"></i></span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-sm text-gray-900 {{ $d['read'] ? '' : 'font-semibold' }}">{{ $d['title'] }}</span>
                        <span class="block text-sm text-gray-600">{{ $d['body'] }}</span>
                        <span class="block text-xs text-gray-400 mt-0.5">{{ $d['time_ago'] }}</span>
                    </span>
                    @unless ($d['read']) <span class="w-2 h-2 rounded-full bg-purple-600 mt-2 shrink-0"></span> @endunless
                </button>
            </form>
        @empty
            <div class="px-6 py-14 text-center">
                <i class="fas fa-bell-slash text-3xl text-gray-300"></i>
                <p class="font-semibold text-gray-900 mt-3">No notifications yet</p>
                <p class="text-sm text-gray-500 mt-1">Updates about your visitors, bills, complaints and society notices will appear here.</p>
            </div>
        @endforelse
    </div>
    <div class="mt-4">{{ $notifications->links() }}</div>
</div>
@endsection
