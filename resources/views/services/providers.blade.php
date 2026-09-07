@extends('layouts.app')

@section('title', 'Service Providers')
@section('page-title', 'Service Providers')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="flex justify-between items-center mb-6">
        <div class="flex items-center">
            <a href="{{ route('services.index') }}" 
               class="text-gray-600 hover:text-gray-900 mr-4 transition-colors">
                <i class="fas fa-arrow-left text-xl"></i>
            </a>
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Service Providers</h1>
                <p class="text-gray-600 mt-1">Browse available service providers</p>
            </div>
        </div>
        @can('create', App\Models\ServiceProvider::class)
        <a href="{{ route('service-providers.create') }}" 
           class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-medium transition-colors inline-flex items-center">
            <i class="fas fa-plus mr-2"></i>
            Add Service Provider
        </a>
        @endcan
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label for="search" class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                <input type="text" name="search" id="search" value="{{ request('search') }}"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                       placeholder="Name or phone...">
            </div>
            <div>
                <label for="service_id" class="block text-sm font-medium text-gray-700 mb-2">Service Type</label>
                <select name="service_id" id="service_id" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">All Services</option>
                    @foreach($services as $service)
                        <option value="{{ $service->id }}" {{ request('service_id') == $service->id ? 'selected' : '' }}>
                            {{ $service->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="status" class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                <select name="status" id="status" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">All Status</option>
                    <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Active</option>
                    <option value="inactive" {{ request('status') == 'inactive' ? 'selected' : '' }}>Inactive</option>
                </select>
            </div>
            <div class="flex items-end">
                <button type="submit" 
                        class="w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium transition-colors">
                    <i class="fas fa-search mr-2"></i>Filter
                </button>
            </div>
        </form>
    </div>

    <!-- Providers Grid -->
    @if($providers->count() > 0)
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-6">
            @foreach($providers as $provider)
            <div class="bg-white rounded-lg shadow-md hover:shadow-lg transition-shadow">
                <div class="p-6">
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mr-4">
                            <i class="{{ $provider->service->icon_class }} text-blue-600 text-xl"></i>
                        </div>
                        <div class="flex-1">
                            <h3 class="font-semibold text-gray-900">{{ $provider->name }}</h3>
                            <p class="text-sm text-gray-600">{{ $provider->service->name }}</p>
                        </div>
                        <div class="flex flex-col items-end space-y-1">
                            {!! $provider->availability_badge !!}
                            {!! $provider->status_badge !!}
                        </div>
                    </div>

                    <div class="space-y-2 mb-4">
                        <div class="flex items-center text-sm text-gray-600">
                            <i class="fas fa-phone w-4 mr-2"></i>
                            <span>{{ $provider->contact_number }}</span>
                        </div>
                        @if($provider->is_daily_help)
                        <div class="flex items-center text-sm text-blue-600">
                            <i class="fas fa-calendar-day w-4 mr-2"></i>
                            <span>Daily Help Service</span>
                        </div>
                        @endif
                        @if(auth()->user()->hasAnyRole(['Admin', 'Super Admin']) && $provider->price)
                        <div class="flex items-center text-sm text-green-600 font-medium">
                            <i class="fas fa-rupee-sign w-4 mr-2"></i>
                            <span>{{ $provider->formatted_price }}</span>
                        </div>
                        @endif
                    </div>

                    <div class="flex justify-between items-center">
                        <div class="flex space-x-2">
                            <a href="{{ route('service-providers.show', $provider) }}" 
                               class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                                View Details
                            </a>
                            @can('update', $provider)
                            <span class="text-gray-300">|</span>
                            <a href="{{ route('service-providers.edit', $provider) }}" 
                               class="text-indigo-600 hover:text-indigo-800 text-sm font-medium">
                                Edit
                            </a>
                            @endcan
                        </div>

                        @if($provider->service->requires_attendance && (auth()->user()->hasAnyRole(['Admin', 'Staff']) || auth()->user()->isOwner()))
                            @if($provider->isClockedInToday())
                                <button onclick="openClockOutModal({{ $provider->id }}, '{{ $provider->name }}')"
                                        class="text-xs bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded transition-colors">
                                    Clock Out
                                </button>
                            @elseif(!$provider->hasAttendanceToday())
                                <button onclick="openClockInModal({{ $provider->id }}, '{{ $provider->name }}')"
                                        class="text-xs bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded transition-colors">
                                    Clock In
                                </button>
                            @else
                                <span class="text-xs text-gray-500 px-3 py-1">Completed</span>
                            @endif
                        @endif
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        <!-- Pagination -->
        <div class="bg-white rounded-lg shadow-md p-4">
            {{ $providers->withQueryString()->links() }}
        </div>
    @else
        <!-- Empty State -->
        <div class="bg-white rounded-lg shadow-md">
            <div class="text-center py-12">
                <div class="inline-flex items-center justify-center w-16 h-16 bg-gray-100 rounded-full mb-4">
                    <i class="fas fa-concierge-bell text-gray-400 text-2xl"></i>
                </div>
                <h3 class="text-lg font-medium text-gray-900 mb-2">No service providers found</h3>
                <p class="text-gray-500 mb-6">
                    @if(request()->hasAny(['search', 'service_id', 'status']))
                        Try adjusting your filters to see more results.
                    @else
                        Get started by adding your first service provider.
                    @endif
                </p>
                @if(request()->hasAny(['search', 'service_id', 'status']))
                    <a href="{{ route('services.providers') }}" 
                       class="inline-flex items-center text-blue-600 hover:text-blue-800 font-medium mr-4">
                        <i class="fas fa-times mr-2"></i>
                        Clear Filters
                    </a>
                @endif
                @can('create', App\Models\ServiceProvider::class)
                <a href="{{ route('service-providers.create') }}" 
                   class="inline-flex items-center bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-medium transition-colors">
                    <i class="fas fa-plus mr-2"></i>
                    Add Service Provider
                </a>
                @endcan
            </div>
        </div>
    @endif
</div>

<!-- Clock-In Modal -->
@if(auth()->user()->hasAnyRole(['Admin', 'Staff']) || auth()->user()->isOwner())
<div id="clockInModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
            <form action="{{ route('services.clock-in') }}" method="POST">
                @csrf
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Mark Entry</h3>
                    <input type="hidden" name="service_provider_id" id="clockInProviderId">
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Service Provider</label>
                        <p id="clockInProviderName" class="text-gray-900 font-medium"></p>
                    </div>

                    <div class="mb-4">
                        <label for="clockInNotes" class="block text-sm font-medium text-gray-700 mb-2">Notes (Optional)</label>
                        <textarea name="notes" id="clockInNotes" rows="3" 
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                  placeholder="Add any notes..."></textarea>
                    </div>
                </div>

                <div class="bg-gray-50 px-6 py-3 flex justify-end space-x-3 rounded-b-lg">
                    <button type="button" onclick="closeClockInModal()" 
                            class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg font-medium transition-colors">
                        Mark Entry
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Clock-Out Modal -->
<div id="clockOutModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
            <form action="{{ route('services.clock-out') }}" method="POST">
                @csrf
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Mark Exit</h3>
                    <input type="hidden" name="service_provider_id" id="clockOutProviderId">
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Service Provider</label>
                        <p id="clockOutProviderName" class="text-gray-900 font-medium"></p>
                    </div>

                    <div class="mb-4">
                        <label for="clockOutNotes" class="block text-sm font-medium text-gray-700 mb-2">Notes (Optional)</label>
                        <textarea name="notes" id="clockOutNotes" rows="3" 
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                  placeholder="Add any notes..."></textarea>
                    </div>
                </div>

                <div class="bg-gray-50 px-6 py-3 flex justify-end space-x-3 rounded-b-lg">
                    <button type="button" onclick="closeClockOutModal()" 
                            class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg font-medium transition-colors">
                        Mark Exit
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openClockInModal(providerId, providerName) {
    document.getElementById('clockInProviderId').value = providerId;
    document.getElementById('clockInProviderName').textContent = providerName;
    document.getElementById('clockInModal').classList.remove('hidden');
}

function closeClockInModal() {
    document.getElementById('clockInModal').classList.add('hidden');
    document.getElementById('clockInNotes').value = '';
}

function openClockOutModal(providerId, providerName) {
    document.getElementById('clockOutProviderId').value = providerId;
    document.getElementById('clockOutProviderName').textContent = providerName;
    document.getElementById('clockOutModal').classList.remove('hidden');
}

function closeClockOutModal() {
    document.getElementById('clockOutModal').classList.add('hidden');
    document.getElementById('clockOutNotes').value = '';
}

// Close modals when clicking outside
document.getElementById('clockInModal').addEventListener('click', function(e) {
    if (e.target === this) closeClockInModal();
});

document.getElementById('clockOutModal').addEventListener('click', function(e) {
    if (e.target === this) closeClockOutModal();
});
</script>
@endif
@endsection