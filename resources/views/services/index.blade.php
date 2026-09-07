@extends('layouts.app')

@section('title', 'Services')
@section('page-title', 'Services')

@section('content')
<div class="container mx-auto px-4 py-6">
    <!-- Header -->
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Services</h1>
            <p class="text-gray-600 mt-1">Manage society services and providers</p>
        </div>
        @can('create', App\Models\ServiceProvider::class)
        <div class="flex space-x-3">
            <a href="{{ route('service-providers.create') }}" 
               class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium transition-colors inline-flex items-center">
                <i class="fas fa-plus mr-2"></i>
                Add Provider
            </a>
            <a href="{{ route('service-providers.index') }}" 
               class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg font-medium transition-colors inline-flex items-center">
                <i class="fas fa-list mr-2"></i>
                Manage All
            </a>
        </div>
        @endcan
    </div>

    @if(session('success'))
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-lg" role="alert">
            <div class="flex items-center">
                <i class="fas fa-check-circle mr-3"></i>
                <p class="font-medium">{{ session('success') }}</p>
            </div>
        </div>
    @endif

    @if(session('error'))
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-lg" role="alert">
            <div class="flex items-center">
                <i class="fas fa-exclamation-circle mr-3"></i>
                <p class="font-medium">{{ session('error') }}</p>
            </div>
        </div>
    @endif

    <!-- Service Categories Grid -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <h2 class="text-xl font-semibold text-gray-900 mb-4">Service Categories</h2>
        
        <!-- Service Icons Grid -->
        <div class="grid grid-cols-6 md:grid-cols-10 lg:grid-cols-15 gap-4 mb-6">
            @php
                $allServices = collect([
                    ['name' => 'Maid', 'icon' => 'fas fa-broom', 'active' => true],
                    ['name' => 'Full Time', 'icon' => 'fas fa-clock', 'active' => false],
                    ['name' => 'Cook', 'icon' => 'fas fa-utensils', 'active' => true],
                    ['name' => 'Driver', 'icon' => 'fas fa-car', 'active' => true],
                    ['name' => 'School Bus', 'icon' => 'fas fa-bus', 'active' => false],
                    ['name' => 'Doctor', 'icon' => 'fas fa-user-md', 'active' => false],
                    ['name' => 'Caretaker', 'icon' => 'fas fa-hands-helping', 'active' => false],
                    ['name' => 'Nanny', 'icon' => 'fas fa-baby', 'active' => false],
                    ['name' => 'Milkman', 'icon' => 'fas fa-glass-whiskey', 'active' => true],
                    ['name' => 'Newspaper', 'icon' => 'fas fa-newspaper', 'active' => false],
                    ['name' => 'Laundry', 'icon' => 'fas fa-tshirt', 'active' => false],
                    ['name' => 'Car Cleaner', 'icon' => 'fas fa-car-wash', 'active' => false],
                    ['name' => 'Tuition Teacher', 'icon' => 'fas fa-chalkboard-teacher', 'active' => false],
                    ['name' => 'Gym Instructor', 'icon' => 'fas fa-dumbbell', 'active' => false],
                    ['name' => 'Yoga Instructor', 'icon' => 'fas fa-spa', 'active' => false],
                    ['name' => 'Pet Walker', 'icon' => 'fas fa-dog', 'active' => false],
                    ['name' => 'Sports Trainer', 'icon' => 'fas fa-running', 'active' => false],
                    ['name' => 'House Keeper', 'icon' => 'fas fa-home', 'active' => false],
                    ['name' => 'Electrician', 'icon' => 'fas fa-bolt', 'active' => false],
                    ['name' => 'Plumber', 'icon' => 'fas fa-wrench', 'active' => false],
                    ['name' => 'Carpenter', 'icon' => 'fas fa-hammer', 'active' => false],
                    ['name' => 'Pest Control', 'icon' => 'fas fa-bug', 'active' => false],
                    ['name' => 'AC Service', 'icon' => 'fas fa-snowflake', 'active' => false],
                    ['name' => 'Blood Test', 'icon' => 'fas fa-vial', 'active' => false],
                    ['name' => 'Scrap Dealer', 'icon' => 'fas fa-recycle', 'active' => false],
                    ['name' => 'Internet Repair', 'icon' => 'fas fa-wifi', 'active' => false],
                    ['name' => 'Cable/TV', 'icon' => 'fas fa-tv', 'active' => false],
                    ['name' => 'Maid Pickup', 'icon' => 'fas fa-user-friends', 'active' => false],
                    ['name' => 'Staff', 'icon' => 'fas fa-users', 'active' => false],
                    ['name' => 'Other', 'icon' => 'fas fa-ellipsis-h', 'active' => false],
                ]);
                
                $currentFilter = request('service_filter', 'all');
            @endphp
            
            <!-- All Services Button -->
            <div class="flex flex-col items-center p-3 rounded-lg hover:bg-gray-50 cursor-pointer transition-colors {{ $currentFilter === 'all' ? 'bg-blue-50 border border-blue-200' : 'bg-gray-50' }}"
                 onclick="filterByService('all')">
                <div class="w-12 h-12 {{ $currentFilter === 'all' ? 'bg-blue-100' : 'bg-gray-100' }} rounded-lg flex items-center justify-center mb-2">
                    <i class="fas fa-th {{ $currentFilter === 'all' ? 'text-blue-600' : 'text-gray-600' }} text-lg"></i>
                </div>
                <span class="text-xs text-center {{ $currentFilter === 'all' ? 'text-blue-700 font-medium' : 'text-gray-700' }}">All</span>
            </div>
            
            @foreach($allServices as $service)
            <div class="flex flex-col items-center p-3 rounded-lg hover:bg-gray-50 cursor-pointer transition-colors {{ $currentFilter === $service['name'] ? 'bg-red-50 border border-red-200' : 'bg-gray-50' }}"
                 onclick="filterByService('{{ $service['name'] }}')">
                <div class="w-12 h-12 {{ $currentFilter === $service['name'] ? 'bg-red-100' : 'bg-gray-100' }} rounded-lg flex items-center justify-center mb-2">
                    <i class="{{ $service['icon'] }} {{ $currentFilter === $service['name'] ? 'text-red-600' : 'text-gray-600' }} text-lg"></i>
                </div>
                <span class="text-xs text-center {{ $currentFilter === $service['name'] ? 'text-red-700 font-medium' : 'text-gray-700' }}">{{ $service['name'] }}</span>
            </div>
            @endforeach
        </div>

        <!-- Search and Filters -->
        <div class="flex items-center space-x-4">
            <div class="flex-1">
                <div class="relative">
                    <input type="text" 
                           placeholder="Search service person name" 
                           class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                           id="searchInput">
                    <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                </div>
            </div>
            <button class="flex items-center px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                <i class="fas fa-filter mr-2"></i>
                FILTERS
            </button>
        </div>
    </div>

    <!-- Service Providers Table -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">CONTACT PERSON</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">SERVICE</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">CONTACT NUMBER</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">STATUS</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">DAILY HELP AVAILABILITY</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">PRICE</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">ACTIONS</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($providers as $provider)
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center mr-3">
                                    <span class="text-blue-600 font-semibold">{{ substr($provider->name, 0, 1) }}</span>
                                </div>
                                <div class="text-sm font-medium text-gray-900">{{ $provider->name }}</div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900">{{ $provider->service->name }}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900">{{ $provider->contact_number }}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full {{ $provider->availability === 'available' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                {{ ucfirst($provider->availability) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full {{ $provider->is_daily_help ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                {{ $provider->is_daily_help ? 'Yes' : 'No' }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @if($provider->price)
                                <div class="text-sm font-medium text-gray-900">₹{{ number_format($provider->price, 2) }}</div>
                                <div class="text-xs text-blue-600">{{ ucfirst(str_replace('_', ' ', $provider->price_type)) }}</div>
                            @else
                                <div class="text-sm text-gray-500">Not specified</div>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right">
                            <div class="flex justify-end space-x-2">
                                <!-- WhatsApp -->
                                <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $provider->contact_number) }}" 
                                   target="_blank" 
                                   class="text-green-600 hover:text-green-800 transition-colors" 
                                   title="WhatsApp">
                                    <i class="fab fa-whatsapp text-lg"></i>
                                </a>
                                <!-- Telegram -->
                                <button class="text-blue-600 hover:text-blue-800 transition-colors" title="Telegram">
                                    <i class="fab fa-telegram text-lg"></i>
                                </button>
                                <!-- Quick Edit -->
                                @can('update', $provider)
                                <button onclick="openUpdateModal({{ $provider->id }}, '{{ $provider->name }}', '{{ $provider->availability }}', '{{ $provider->price }}', '{{ $provider->price_type }}')"
                                        class="text-blue-500 hover:text-blue-700 transition-colors" 
                                        title="Quick Update">
                                    <i class="fas fa-edit text-lg"></i>
                                </button>
                                @endcan
                                <!-- Call -->
                                <a href="tel:{{ $provider->contact_number }}" 
                                   class="text-purple-600 hover:text-purple-800 transition-colors" 
                                   title="Call">
                                    <i class="fas fa-phone text-lg"></i>
                                </a>
                                <!-- SMS -->
                                <a href="sms:{{ $provider->contact_number }}" 
                                   class="text-gray-600 hover:text-gray-800 transition-colors" 
                                   title="SMS">
                                    <i class="fas fa-sms text-lg"></i>
                                </a>
                                <!-- Email -->
                                <button class="text-orange-600 hover:text-orange-800 transition-colors" title="Email">
                                    <i class="fas fa-envelope text-lg"></i>
                                </button>
                                <!-- More Options -->
                                <button class="text-red-600 hover:text-red-800 transition-colors" title="More">
                                    <i class="fas fa-ellipsis-v text-lg"></i>
                                </button>
                                <!-- Settings -->
                                @can('update', $provider)
                                <a href="{{ route('service-providers.edit', $provider) }}" 
                                   class="text-gray-600 hover:text-gray-800 transition-colors" 
                                   title="Full Edit">
                                    <i class="fas fa-cog text-lg"></i>
                                </a>
                                @endcan
                                <!-- Toggle Availability -->
                                @can('update', $provider)
                                <button onclick="toggleAvailability({{ $provider->id }}, '{{ $provider->availability }}')"
                                        class="text-{{ $provider->availability === 'available' ? 'green' : 'red' }}-600 hover:text-{{ $provider->availability === 'available' ? 'green' : 'red' }}-800 transition-colors" 
                                        title="Toggle Availability">
                                    <i class="fas fa-{{ $provider->availability === 'available' ? 'check-circle' : 'times-circle' }} text-lg"></i>
                                </button>
                                @endcan
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-6 py-12 text-center">
                            <div class="flex flex-col items-center">
                                <i class="fas fa-users text-gray-400 text-4xl mb-4"></i>
                                <h3 class="text-lg font-medium text-gray-900 mb-2">No Service Providers Found</h3>
                                <p class="text-gray-500 mb-4">
                                    @if(request('service_filter'))
                                        No providers found for "{{ request('service_filter') }}" service.
                                    @else
                                        No service providers have been added yet.
                                    @endif
                                </p>
                                @can('create', App\Models\ServiceProvider::class)
                                <a href="{{ route('service-providers.create') }}" 
                                   class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium transition-colors inline-flex items-center">
                                    <i class="fas fa-plus mr-2"></i>
                                    Add Service Provider
                                </a>
                                @endcan
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Quick Update Modal -->
<div id="updateModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
            <form id="updateForm" method="POST">
                @csrf
                @method('PATCH')
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Quick Update Service</h3>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Service Provider</label>
                        <p id="updateProviderName" class="text-gray-900 font-medium"></p>
                    </div>

                    <div class="mb-4">
                        <label for="updateAvailability" class="block text-sm font-medium text-gray-700 mb-2">Availability</label>
                        <select name="availability" id="updateAvailability" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="available">Available</option>
                            <option value="not_available">Not Available</option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label for="updatePrice" class="block text-sm font-medium text-gray-700 mb-2">Price</label>
                        <input type="number" name="price" id="updatePrice" step="0.01" min="0"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>

                    <div class="mb-4">
                        <label for="updatePriceType" class="block text-sm font-medium text-gray-700 mb-2">Price Type</label>
                        <select name="price_type" id="updatePriceType" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="per_day">Per Day</option>
                            <option value="per_month">Per Month</option>
                            <option value="per_visit">Per Visit</option>
                            <option value="per_hour">Per Hour</option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label for="updateNotes" class="block text-sm font-medium text-gray-700 mb-2">Notes (Optional)</label>
                        <textarea name="notes" id="updateNotes" rows="3" 
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                  placeholder="Add any notes..."></textarea>
                    </div>
                </div>

                <div class="bg-gray-50 px-6 py-3 flex justify-end space-x-3 rounded-b-lg">
                    <button type="button" onclick="closeUpdateModal()" 
                            class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition-colors">
                        Update Service
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function filterByService(serviceName) {
    // Update URL with service filter parameter
    const url = new URL(window.location);
    if (serviceName === 'all') {
        url.searchParams.delete('service_filter');
    } else {
        url.searchParams.set('service_filter', serviceName);
    }
    
    // Reload page with new filter
    window.location.href = url.toString();
}

function openUpdateModal(providerId, providerName, availability, price, priceType) {
    document.getElementById('updateForm').action = `/service-providers/${providerId}/quick-update`;
    document.getElementById('updateProviderName').textContent = providerName;
    document.getElementById('updateAvailability').value = availability;
    document.getElementById('updatePrice').value = price || '';
    document.getElementById('updatePriceType').value = priceType;
    document.getElementById('updateModal').classList.remove('hidden');
}

function closeUpdateModal() {
    document.getElementById('updateModal').classList.add('hidden');
    document.getElementById('updateNotes').value = '';
}

function toggleAvailability(providerId, currentAvailability) {
    const newAvailability = currentAvailability === 'available' ? 'not_available' : 'available';
    
    fetch(`/service-providers/${providerId}/toggle-availability`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({
            availability: newAvailability
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error updating availability');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error updating availability');
    });
}

// Search functionality
document.getElementById('searchInput').addEventListener('input', function(e) {
    const searchTerm = e.target.value.toLowerCase();
    const rows = document.querySelectorAll('tbody tr');
    
    rows.forEach(row => {
        const nameElement = row.querySelector('td:first-child .text-gray-900');
        const contactElement = row.querySelector('td:nth-child(3) .text-gray-900');
        
        if (nameElement && contactElement) {
            const name = nameElement.textContent.toLowerCase();
            const contact = contactElement.textContent.toLowerCase();
            
            if (name.includes(searchTerm) || contact.includes(searchTerm)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        }
    });
});

// Close modal when clicking outside
document.getElementById('updateModal').addEventListener('click', function(e) {
    if (e.target === this) closeUpdateModal();
});
</script>
@endsection