@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8 max-w-xl">
    <h1 class="text-2xl font-bold text-gray-900 mb-6">My Shift</h1>


    @if (!$assignment)
        <div class="bg-white rounded-lg shadow p-6 text-center text-gray-500">
            You have no scheduled shift right now.
        </div>
    @else
        <div class="bg-white rounded-lg shadow p-6 space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Gate</p>
                    <p class="text-lg font-semibold text-gray-900">{{ $assignment->gate->name }}</p>
                </div>
                <div class="text-right">
                    <p class="text-sm text-gray-500">Shift</p>
                    <p class="text-lg font-semibold text-gray-900">{{ $assignment->shift->name }}</p>
                </div>
            </div>
            <div class="text-sm text-gray-500">
                {{ \Illuminate\Support\Carbon::parse($assignment->shift->start_time)->format('h:i A') }}
                – {{ \Illuminate\Support\Carbon::parse($assignment->shift->end_time)->format('h:i A') }}
            </div>
            <span class="inline-block px-3 py-1 rounded-full text-sm font-medium
                {{ in_array($assignment->status, ['present','checked_out']) ? 'bg-green-100 text-green-700' : ($assignment->status === 'late' ? 'bg-amber-100 text-amber-700' : 'bg-gray-100 text-gray-600') }}">
                {{ ucfirst($assignment->status) }}
            </span>

            <div class="flex gap-3 pt-4 border-t border-gray-100">
                @if (!$assignment->hasStarted())
                    <form method="POST" action="{{ route('guard.shift.start') }}">
                        @csrf
                        <button type="submit" class="bg-green-600 text-white px-6 py-3 rounded-lg font-medium hover:bg-green-700">Start Shift</button>
                    </form>
                @elseif (!$assignment->hasEnded())
                    <form method="POST" action="{{ route('guard.shift.end') }}">
                        @csrf
                        <button type="submit" class="bg-red-600 text-white px-6 py-3 rounded-lg font-medium hover:bg-red-700">End Shift</button>
                    </form>
                @endif
            </div>

            @if ($assignment->hasStarted() && !$assignment->hasEnded())
                <div class="pt-4 border-t border-gray-100">
                    <h2 class="text-sm font-semibold text-gray-700 mb-2">Handover Notes for the Next Guard</h2>
                    <form method="POST" action="{{ route('guard.shift.handover.start') }}" class="space-y-3">
                        @csrf
                        <textarea name="notes" rows="2" placeholder="Pending issues, important notes..."
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"></textarea>
                        <textarea name="pending_visitors_note" rows="1" placeholder="Pending/expected visitors"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"></textarea>
                        <textarea name="active_passes_note" rows="1" placeholder="Active access passes to watch"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"></textarea>
                        <button type="submit" class="bg-blue-600 text-white px-5 py-2 rounded-lg text-sm font-medium hover:bg-blue-700">Save Handover Notes</button>
                    </form>
                </div>
            @endif
        </div>
    @endif
</div>
@endsection
