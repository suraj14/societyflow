@extends('layouts.app')

@section('title', 'New Facility Booking')
@section('page-title', 'New Facility Booking')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="flex items-center mb-6">
        <a href="{{ route('facility-bookings.index') }}" class="text-gray-600 hover:text-gray-900 mr-4">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div>
            <h1 class="text-3xl font-bold text-gray-900">New Facility Booking</h1>
            <p class="text-gray-600 mt-1">Book a facility for your event</p>
        </div>
    </div>

    @if(session('error'))
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-lg">
            <div class="flex items-center">
                <i class="fas fa-exclamation-circle mr-3"></i>
                <p class="font-medium">{{ session('error') }}</p>
            </div>
        </div>
    @endif

    <div class="max-w-3xl bg-white rounded-lg shadow-md">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Booking Details</h2>
        </div>
        
        <form action="{{ route('facility-bookings.store') }}" method="POST" class="px-6 py-6" data-no-ajax>
            @csrf
            
            <div class="space-y-6">
                <!-- Booked By (Auto-filled, read-only) -->
                <div>
                    <label for="booked_by" class="block text-sm font-medium text-gray-700 mb-2">
                        Booked By
                    </label>
                    <input type="text" id="booked_by" readonly
                           value="{{ auth()->user()->name }}"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-50 text-gray-600">
                </div>

                <!-- Facility Selection -->
                <div>
                    <label for="facility_id" class="block text-sm font-medium text-gray-700 mb-2">
                        Facility <span class="text-red-500">*</span>
                    </label>
                    <select name="facility_id" id="facility_id" required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('facility_id') border-red-500 @enderror">
                        <option value="">Select Facility</option>
                        @foreach($facilities as $facility)
                            <option value="{{ $facility->id }}" {{ old('facility_id') == $facility->id ? 'selected' : '' }}
                                    data-charge="{{ $facility->booking_charge }}">
                                {{ $facility->name }} (₹{{ number_format($facility->booking_charge, 2) }})
                            </option>
                        @endforeach
                    </select>
                    @error('facility_id')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Property Selection (Role-based) -->
                @if($propertyType && $properties->count() > 0)
                    <!-- Pre-populated for Owners/Tenants -->
                    <div>
                        <label for="flat_id" class="block text-sm font-medium text-gray-700 mb-2">
                            {{ ucfirst($propertyType) }} <span class="text-red-500">*</span>
                        </label>
                        <select name="flat_id" id="flat_id" required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('flat_id') border-red-500 @enderror">
                            <option value="">Select {{ ucfirst($propertyType) }}</option>
                            @foreach($properties as $property)
                                <option value="{{ $property['id'] }}" {{ old('flat_id') == $property['id'] ? 'selected' : '' }}>
                                    {{ $property['display_name'] }}
                                </option>
                            @endforeach
                        </select>
                        @error('flat_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    
                    <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        const flatSelect = document.getElementById('flat_id');
                        if (flatSelect && flatSelect.value === '') {
                            // Auto-select first property if only one exists
                            const options = Array.from(flatSelect.options).filter(opt => opt.value !== '');
                            if (options.length === 1) {
                                flatSelect.value = options[0].value;
                            }
                        }
                    });
                    </script>
                @elseif($propertyType && auth()->user()->hasAnyRole(['Tenant', 'Resident']))
                    <!-- Tenant/Resident with no properties assigned -->
                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                        <div class="flex items-start">
                            <i class="fas fa-exclamation-triangle text-yellow-600 mt-1 mr-3"></i>
                            <div>
                                <h4 class="text-sm font-medium text-yellow-900 mb-1">No Properties Assigned</h4>
                                <p class="text-sm text-yellow-700">
                                    You don't have any properties assigned to your account. Please contact the administrator to assign a property to you.
                                </p>
                            </div>
                        </div>
                    </div>
                    <input type="hidden" name="flat_id" id="flat_id" value="">
                @else
                    <!-- Admin can select property type and property -->
                    <input type="hidden" name="flat_id" id="flat_id" value="">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="property_type" class="block text-sm font-medium text-gray-700 mb-2">
                                Property Type <span class="text-red-500">*</span>
                            </label>
                            <select name="property_type" id="property_type" required
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('property_type') border-red-500 @enderror">
                                <option value="">Select Property Type</option>
                                <option value="apartment" {{ old('property_type') == 'apartment' ? 'selected' : '' }}>Apartment</option>
                                <option value="villa" {{ old('property_type') == 'villa' ? 'selected' : '' }}>Villa</option>
                            </select>
                            @error('property_type')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <!-- Apartment Selection Fields (for Admin) -->
                    <div id="apartment_fields" class="space-y-6" style="display: none;">
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                            <div>
                                <label for="building_id" class="block text-sm font-medium text-gray-700 mb-2">
                                    Select Tower <span class="text-red-500">*</span>
                                </label>
                                <select name="building_id" id="building_id"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <option value="">Select Tower</option>
                                    @if(isset($buildings))
                                        @foreach($buildings as $building)
                                            <option value="{{ $building->id }}" {{ old('building_id') == $building->id ? 'selected' : '' }}>
                                                {{ $building->name }}
                                            </option>
                                        @endforeach
                                    @endif
                                </select>
                            </div>

                            <div>
                                <label for="floor" class="block text-sm font-medium text-gray-700 mb-2">
                                    Select Floor <span class="text-red-500">*</span>
                                </label>
                                <select name="floor" id="floor"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <option value="">Select Floor</option>
                                </select>
                            </div>

                            <div>
                                <label for="apartment_flat_id" class="block text-sm font-medium text-gray-700 mb-2">
                                    Flat No. <span class="text-red-500">*</span>
                                </label>
                                <select name="apartment_flat_id" id="apartment_flat_id"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <option value="">Select Flat</option>
                                </select>
                            </div>

                        </div>
                    </div>

                    <!-- Villa Selection Fields (for Admin) -->
                    <div id="villa_fields" class="space-y-6" style="display: none;">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="villa_area_id" class="block text-sm font-medium text-gray-700 mb-2">
                                    Villa Area <span class="text-red-500">*</span>
                                </label>
                                <select name="villa_area_id" id="villa_area_id"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <option value="">Select Villa Area</option>
                                    @if(isset($villaAreas))
                                        @foreach($villaAreas as $area)
                                            <option value="{{ $area->id }}" {{ old('villa_area_id') == $area->id ? 'selected' : '' }}>
                                                {{ $area->name }}
                                            </option>
                                        @endforeach
                                    @endif
                                </select>
                            </div>

                            <div>
                                <label for="villa_flat_id" class="block text-sm font-medium text-gray-700 mb-2">
                                    Villa No. <span class="text-red-500">*</span>
                                </label>
                                <select name="villa_flat_id" id="villa_flat_id"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <option value="">Select Villa</option>
                                </select>
                            </div>
                        </div>
                    </div>
                @endif

                <!-- Booking Date & Time -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label for="booking_date" class="block text-sm font-medium text-gray-700 mb-2">
                            Booking Date <span class="text-red-500">*</span>
                        </label>
                        <input type="date" name="booking_date" id="booking_date" required
                               value="{{ old('booking_date', date('Y-m-d')) }}"
                               min="{{ date('Y-m-d') }}"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('booking_date') border-red-500 @enderror">
                        @error('booking_date')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="start_time" class="block text-sm font-medium text-gray-700 mb-2">
                            Start Time <span class="text-red-500">*</span>
                        </label>
                        <input type="time" name="start_time" id="start_time" required
                               value="{{ old('start_time', '09:00') }}"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('start_time') border-red-500 @enderror">
                        @error('start_time')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="end_time" class="block text-sm font-medium text-gray-700 mb-2">
                            End Time <span class="text-red-500">*</span>
                        </label>
                        <input type="time" name="end_time" id="end_time" required
                               value="{{ old('end_time', '12:00') }}"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('end_time') border-red-500 @enderror">
                        @error('end_time')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <!-- Purpose & Guests -->
                <div>
                    <label for="purpose" class="block text-sm font-medium text-gray-700 mb-2">
                        Purpose of Booking
                    </label>
                    <textarea name="purpose" id="purpose" rows="3"
                              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('purpose') border-red-500 @enderror"
                              placeholder="e.g., Birthday party, Family gathering, etc.">{{ old('purpose') }}</textarea>
                    @error('purpose')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="expected_guests" class="block text-sm font-medium text-gray-700 mb-2">
                        Expected Number of Guests
                    </label>
                    <input type="number" name="expected_guests" id="expected_guests" min="0"
                           value="{{ old('expected_guests', 0) }}"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('expected_guests') border-red-500 @enderror">
                    @error('expected_guests')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Info Box -->
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <div class="flex items-start">
                        <i class="fas fa-info-circle text-blue-600 mt-1 mr-3"></i>
                        <div>
                            <h4 class="text-sm font-medium text-blue-900 mb-1">Booking Information</h4>
                            <p class="text-sm text-blue-700">
                                Your booking request will be submitted for admin approval. You will be notified once it's approved or rejected.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="mt-8 flex justify-end space-x-3 pt-6 border-t border-gray-200">
                <a href="{{ route('facility-bookings.index') }}" 
                   class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 font-medium transition-colors">
                    Cancel
                </a>
                <button type="submit" 
                        @if($propertyType && auth()->user()->hasAnyRole(['Tenant', 'Resident']) && $properties->count() == 0)
                            disabled
                            class="px-6 py-2 bg-gray-400 text-white rounded-lg font-medium cursor-not-allowed"
                        @else
                            class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition-colors"
                        @endif>
                    Submit Booking Request
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const propertyTypeSelect = document.getElementById('property_type');
    const flatIdSelect = document.getElementById('flat_id');
    const apartmentFields = document.getElementById('apartment_fields');
    const villaFields = document.getElementById('villa_fields');
    
    if (propertyTypeSelect) {
        propertyTypeSelect.addEventListener('change', function() {
            const selectedType = this.value;
            
            if (selectedType === 'apartment') {
                apartmentFields.style.display = 'block';
                villaFields.style.display = 'none';
                if (flatIdSelect) flatIdSelect.innerHTML = '<option value="">Select Property</option>';
            } else if (selectedType === 'villa') {
                apartmentFields.style.display = 'none';
                villaFields.style.display = 'block';
                if (flatIdSelect) flatIdSelect.innerHTML = '<option value="">Select Property</option>';
            } else {
                apartmentFields.style.display = 'none';
                villaFields.style.display = 'none';
                if (flatIdSelect) flatIdSelect.innerHTML = '<option value="">Select Property</option>';
            }
        });

        const buildingSelect = document.getElementById('building_id');
        if (buildingSelect) {
            buildingSelect.addEventListener('change', function() {
                const buildingId = this.value;
                const floorSelect = document.getElementById('floor');
                const apartmentFlatSelect = document.getElementById('apartment_flat_id');
                
                if (floorSelect) floorSelect.innerHTML = '<option value="">Select Floor</option>';
                if (apartmentFlatSelect) apartmentFlatSelect.innerHTML = '<option value="">Select Flat</option>';
                if (flatIdSelect) flatIdSelect.value = '';

                if (buildingId) {
                    const url = `{{ route('api.facility-bookings.floors') }}?building_id=${buildingId}`;
                    fetch(url, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        }
                    })
                        .then(response => response.json())
                        .then(floors => {
                            if (Array.isArray(floors) && floors.length > 0) {
                                floors.forEach(floor => {
                                    const option = document.createElement('option');
                                    option.value = floor;
                                    option.textContent = `Floor ${floor}`;
                                    if (floorSelect) floorSelect.appendChild(option);
                                });
                            }
                        })
                        .catch(error => console.error('Error fetching floors:', error));
                }
            });
        }

        const floorSelect = document.getElementById('floor');
        if (floorSelect) {
            floorSelect.addEventListener('change', function() {
                const buildingSelect = document.getElementById('building_id');
                const buildingId = buildingSelect ? buildingSelect.value : null;
                const floor = this.value;
                const apartmentFlatSelect = document.getElementById('apartment_flat_id');
                
                if (apartmentFlatSelect) apartmentFlatSelect.innerHTML = '<option value="">Select Flat</option>';
                if (flatIdSelect) flatIdSelect.value = '';

                if (buildingId && floor) {
                    const url = `{{ route('api.facility-bookings.flats') }}?building_id=${buildingId}&floor=${floor}`;
                    fetch(url, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        }
                    })
                        .then(response => response.json())
                        .then(flats => {
                            if (Array.isArray(flats) && flats.length > 0) {
                                flats.forEach(flat => {
                                    const option = document.createElement('option');
                                    option.value = flat.id;
                                    option.textContent = flat.display_name;
                                    if (apartmentFlatSelect) apartmentFlatSelect.appendChild(option);
                                });
                            }
                        })
                        .catch(error => console.error('Error fetching flats:', error));
                }
            });
        }

        const apartmentFlatSelect = document.getElementById('apartment_flat_id');
        if (apartmentFlatSelect) {
            apartmentFlatSelect.addEventListener('change', function() {
                if (flatIdSelect) flatIdSelect.value = this.value;
            });
        }

        const villaAreaSelect = document.getElementById('villa_area_id');
        if (villaAreaSelect) {
            villaAreaSelect.addEventListener('change', function() {
                const villaAreaId = this.value;
                const villaFlatSelect = document.getElementById('villa_flat_id');
                
                if (villaFlatSelect) villaFlatSelect.innerHTML = '<option value="">Select Villa</option>';
                if (flatIdSelect) flatIdSelect.value = '';

                if (villaAreaId) {
                    const url = `{{ route('api.facility-bookings.villas') }}?villa_area_id=${villaAreaId}`;
                    fetch(url, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        }
                    })
                        .then(response => response.json())
                        .then(villas => {
                            if (Array.isArray(villas) && villas.length > 0) {
                                villas.forEach(villa => {
                                    const option = document.createElement('option');
                                    option.value = villa.id;
                                    option.textContent = villa.display_name;
                                    if (villaFlatSelect) villaFlatSelect.appendChild(option);
                                });
                            }
                        })
                        .catch(error => console.error('Error fetching villas:', error));
                }
            });
        }

        const villaFlatSelect = document.getElementById('villa_flat_id');
        if (villaFlatSelect) {
            villaFlatSelect.addEventListener('change', function() {
                if (flatIdSelect) flatIdSelect.value = this.value;
            });
        }

        const oldPropertyType = '{{ old('property_type') }}';
        if (oldPropertyType) {
            propertyTypeSelect.value = oldPropertyType;
            propertyTypeSelect.dispatchEvent(new Event('change'));
        }
    }
});
</script>
@endsection
