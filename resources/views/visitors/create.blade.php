@extends('layouts.app')

@section('title', 'Add Visitor')
@section('page-title', 'Add Visitor')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="flex items-center mb-6">
        <a href="{{ route('visitors.index') }}" class="text-gray-600 hover:text-gray-900 mr-4">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Add New Visitor</h1>
            <p class="text-gray-600 mt-1">Register a new visitor entry</p>
        </div>
    </div>

    <div class="max-w-3xl bg-white rounded-lg shadow-md">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Visitor Information</h2>
        </div>
        
        <form action="{{ route('visitors.store') }}" method="POST" enctype="multipart/form-data" class="px-6 py-6">
            @csrf
            
            <div class="space-y-6">
                <!-- Visitor Details -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="visitor_name" class="block text-sm font-medium text-gray-700 mb-2">
                            Visitor Name <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="visitor_name" id="visitor_name" required value="{{ old('visitor_name') }}"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('visitor_name') border-red-500 @enderror"
                               placeholder="Enter visitor name">
                        @error('visitor_name')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="visitor_phone" class="block text-sm font-medium text-gray-700 mb-2">
                            Mobile Number <span class="text-red-500">*</span>
                        </label>
                        <input type="tel" name="visitor_phone" id="visitor_phone" required value="{{ old('visitor_phone') }}"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('visitor_phone') border-red-500 @enderror"
                               placeholder="Enter mobile number">
                        @error('visitor_phone')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="visitor_type" class="block text-sm font-medium text-gray-700 mb-2">
                            Visitor Type <span class="text-red-500">*</span>
                        </label>
                        <select name="visitor_type" id="visitor_type" required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('visitor_type') border-red-500 @enderror">
                            <option value="">Select Type</option>
                            <option value="guest" {{ old('visitor_type') == 'guest' ? 'selected' : '' }}>Guest</option>
                            <option value="delivery" {{ old('visitor_type') == 'delivery' ? 'selected' : '' }}>Delivery</option>
                            <option value="cab" {{ old('visitor_type') == 'cab' ? 'selected' : '' }}>Cab</option>
                            <option value="service" {{ old('visitor_type') == 'service' ? 'selected' : '' }}>Service Staff</option>
                            <option value="other" {{ old('visitor_type') == 'other' ? 'selected' : '' }}>Other</option>
                        </select>
                        @error('visitor_type')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="property_type" class="block text-sm font-medium text-gray-700 mb-2">
                            Visiting Apartment / Villa <span class="text-red-500">*</span>
                        </label>
                        <select name="property_type" id="property_type" required onchange="handlePropertyTypeChange()"
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

                <!-- Apartment Selection -->
                <div id="apartment_section" style="display: none;">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <label for="tower_select" class="block text-sm font-medium text-gray-700 mb-2">
                                Select Tower <span class="text-red-500">*</span>
                            </label>
                            <select id="tower_select" onchange="updateFloorOptions()"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Select Tower</option>
                            </select>
                        </div>

                        <div>
                            <label for="floor_select" class="block text-sm font-medium text-gray-700 mb-2">
                                Select Floor <span class="text-red-500">*</span>
                            </label>
                            <select id="floor_select" onchange="updateFlatOptions()"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Select Floor</option>
                            </select>
                        </div>

                        <div>
                            <label for="flat_select" class="block text-sm font-medium text-gray-700 mb-2">
                                Flat No. <span class="text-red-500">*</span>
                            </label>
                            <select id="flat_select" onchange="updateFlatId()"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Select Flat</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Villa Selection -->
                <div id="villa_section" style="display: none;">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="villa_area_select" class="block text-sm font-medium text-gray-700 mb-2">
                                Villa Areas <span class="text-red-500">*</span>
                            </label>
                            <select id="villa_area_select" onchange="updateVillaOptions()"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Select Villa Area</option>
                            </select>
                        </div>

                        <div>
                            <label for="villa_select" class="block text-sm font-medium text-gray-700 mb-2">
                                Villa No. <span class="text-red-500">*</span>
                            </label>
                            <select id="villa_select" onchange="updateFlatId()"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Select Villa</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Hidden field to store the selected flat_id -->
                <input type="hidden" name="flat_id" id="flat_id" value="{{ old('flat_id') }}">
                @error('flat_id')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror

                <!-- Visit Details -->
                <div class="border-t border-gray-200 pt-6">
                    <h3 class="text-md font-semibold text-gray-900 mb-4">Visit Details</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="expected_entry_time" class="block text-sm font-medium text-gray-700 mb-2">
                                Date Of Visit <span class="text-red-500">*</span>
                            </label>
                            <input type="date" name="expected_entry_time" id="expected_entry_time" required
                                   value="{{ old('expected_entry_time', now()->format('Y-m-d')) }}"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('expected_entry_time') border-red-500 @enderror">
                            @error('expected_entry_time')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="in_time" class="block text-sm font-medium text-gray-700 mb-2">
                                In Time <span class="text-red-500">*</span>
                            </label>
                            <input type="time" name="in_time" id="in_time" required
                                   value="{{ old('in_time', now()->format('H:i')) }}"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('in_time') border-red-500 @enderror">
                            @error('in_time')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="expected_exit_time" class="block text-sm font-medium text-gray-700 mb-2">
                                Date Of Exit
                            </label>
                            <input type="date" name="expected_exit_time" id="expected_exit_time"
                                   value="{{ old('expected_exit_time') }}"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('expected_exit_time') border-red-500 @enderror">
                            @error('expected_exit_time')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="out_time" class="block text-sm font-medium text-gray-700 mb-2">
                                Out Time
                            </label>
                            <input type="time" name="out_time" id="out_time"
                                   value="{{ old('out_time') }}"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('out_time') border-red-500 @enderror">
                            @error('out_time')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="expected_count" class="block text-sm font-medium text-gray-700 mb-2">
                                Number of Visitors
                            </label>
                            <input type="number" name="expected_count" id="expected_count" min="1" max="50"
                                   value="{{ old('expected_count', 1) }}"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('expected_count') border-red-500 @enderror">
                            @error('expected_count')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="vehicle_number" class="block text-sm font-medium text-gray-700 mb-2">
                                Vehicle Number
                            </label>
                            <input type="text" name="vehicle_number" id="vehicle_number"
                                   value="{{ old('vehicle_number') }}"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('vehicle_number') border-red-500 @enderror"
                                   placeholder="e.g., MH12AB1234">
                            @error('vehicle_number')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <!-- Purpose and Photo Upload -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="purpose" class="block text-sm font-medium text-gray-700 mb-2">
                            Purpose of Visit
                        </label>
                        <textarea name="purpose" id="purpose" rows="3"
                                  class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('purpose') border-red-500 @enderror"
                                  placeholder="Enter purpose of visit...">{{ old('purpose') }}</textarea>
                        @error('purpose')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="photo" class="block text-sm font-medium text-gray-700 mb-2">
                            Upload Photo (Optional)
                        </label>
                        <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-lg hover:border-gray-400 transition-colors">
                            <div class="space-y-1 text-center">
                                <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                    <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                                <div class="flex text-sm text-gray-600">
                                    <label for="photo" class="relative cursor-pointer bg-white rounded-md font-medium text-blue-600 hover:text-blue-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-blue-500">
                                        <span>Upload Photo</span>
                                        <input id="photo" name="photo" type="file" accept="image/*" class="sr-only" onchange="previewPhoto(this)">
                                    </label>
                                    <p class="pl-1">or drag and drop</p>
                                </div>
                                <p class="text-xs text-gray-500">PNG, JPG, GIF up to 2MB</p>
                            </div>
                        </div>
                        <div id="photo-preview" class="mt-2 hidden">
                            <img id="preview-image" class="h-20 w-20 object-cover rounded-lg" src="" alt="Preview">
                        </div>
                        @error('photo')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="mt-8 flex justify-end space-x-3 pt-6 border-t border-gray-200">
                <a href="{{ route('visitors.index') }}" 
                   class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 font-medium transition-colors">
                    Cancel
                </a>
                <button type="submit" 
                        class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition-colors">
                    Register Visitor
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Fetch all flats data
let allFlats = [];

fetch('/api/flats')
    .then(response => {
        console.log('API Response status:', response.status);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        allFlats = data;
        console.log('Flats loaded:', allFlats.length);
        console.log('All flats data:', allFlats);
        
        // Debug villa data specifically
        const villaFlats = allFlats.filter(flat => flat.property_type === 'villa');
        console.log('Villa flats found:', villaFlats.length);
        console.log('Villa flats:', villaFlats);
        
        const villaFlatsWithAreas = villaFlats.filter(flat => flat.villa_area);
        console.log('Villa flats with areas:', villaFlatsWithAreas.length);
        console.log('Villa flats with areas:', villaFlatsWithAreas);
        
        if (villaFlatsWithAreas.length > 0) {
            console.log('Sample villa flat structure:', villaFlatsWithAreas[0]);
        }
    })
    .catch(error => {
        console.error('Error fetching flats:', error);
    });

function handlePropertyTypeChange() {
    const propertyType = document.getElementById('property_type').value;
    const apartmentSection = document.getElementById('apartment_section');
    const villaSection = document.getElementById('villa_section');
    
    console.log('Property type selected:', propertyType);
    
    // Reset all dropdowns
    resetAllDropdowns();
    
    if (propertyType === 'apartment') {
        apartmentSection.style.display = 'block';
        villaSection.style.display = 'none';
        loadTowers();
    } else if (propertyType === 'villa') {
        apartmentSection.style.display = 'none';
        villaSection.style.display = 'block';
        loadVillaAreas();
    } else {
        apartmentSection.style.display = 'none';
        villaSection.style.display = 'none';
    }
}

function resetAllDropdowns() {
    document.getElementById('tower_select').innerHTML = '<option value="">Select Tower</option>';
    document.getElementById('floor_select').innerHTML = '<option value="">Select Floor</option>';
    document.getElementById('flat_select').innerHTML = '<option value="">Select Flat</option>';
    document.getElementById('villa_area_select').innerHTML = '<option value="">Select Villa Area</option>';
    document.getElementById('villa_select').innerHTML = '<option value="">Select Villa</option>';
    document.getElementById('flat_id').value = '';
}

function loadTowers() {
    console.log('Loading towers...');
    console.log('All flats:', allFlats);
    
    const apartments = allFlats.filter(flat => {
        console.log('Checking flat:', flat.id, 'property_type:', flat.property_type, 'building:', flat.building);
        return flat.property_type === 'apartment' && flat.building;
    });
    console.log('Apartments found:', apartments.length, apartments);
    
    if (apartments.length === 0) {
        console.warn('No apartment flats with buildings found!');
        const towerSelect = document.getElementById('tower_select');
        towerSelect.innerHTML = '<option value="">No towers available</option>';
        return;
    }
    
    const towers = [...new Map(apartments.map(flat => [flat.building.id, flat.building])).values()];
    console.log('Towers map:', towers);
    
    const towerSelect = document.getElementById('tower_select');
    towerSelect.innerHTML = '<option value="">Select Tower</option>';
    
    towers.forEach(tower => {
        const option = document.createElement('option');
        option.value = tower.id;
        option.textContent = tower.name;
        towerSelect.appendChild(option);
        console.log('Added tower option:', tower.name);
    });
    
    console.log('Tower dropdown populated with', towers.length, 'options');
}

function updateFloorOptions() {
    const towerId = document.getElementById('tower_select').value;
    const floorSelect = document.getElementById('floor_select');
    const flatSelect = document.getElementById('flat_select');
    
    console.log('Updating floor options for tower ID:', towerId);
    
    floorSelect.innerHTML = '<option value="">Select Floor</option>';
    flatSelect.innerHTML = '<option value="">Select Flat</option>';
    document.getElementById('flat_id').value = '';
    
    if (!towerId) return;
    
    const apartments = allFlats.filter(flat => {
        const match = flat.property_type === 'apartment' && 
                     flat.building && 
                     flat.building.id == towerId;
        return match;
    });
    
    console.log('Apartments for tower', towerId, ':', apartments.length);
    
    if (apartments.length === 0) {
        floorSelect.innerHTML = '<option value="">No floors available</option>';
        return;
    }
    
    const floors = [...new Set(apartments.map(flat => flat.floor))].sort((a, b) => a - b);
    console.log('Floors found:', floors);
    
    floors.forEach(floor => {
        const option = document.createElement('option');
        option.value = floor;
        option.textContent = `Floor ${floor}`;
        floorSelect.appendChild(option);
        console.log('Added floor option:', floor);
    });
    
    console.log('Floor dropdown populated with', floors.length, 'options');
}

function updateFlatOptions() {
    const towerId = document.getElementById('tower_select').value;
    const floor = document.getElementById('floor_select').value;
    const flatSelect = document.getElementById('flat_select');
    
    console.log('Updating flat options for tower', towerId, 'floor', floor);
    
    flatSelect.innerHTML = '<option value="">Select Flat</option>';
    document.getElementById('flat_id').value = '';
    
    if (!towerId || !floor) return;
    
    const flats = allFlats.filter(flat => {
        const match = flat.property_type === 'apartment' && 
                     flat.building && 
                     flat.building.id == towerId && 
                     flat.floor == floor;
        if (match) {
            console.log('Found matching flat:', flat.id, flat.flat_number);
        }
        return match;
    });
    
    console.log('Flats for tower', towerId, 'floor', floor, ':', flats.length);
    
    if (flats.length === 0) {
        flatSelect.innerHTML = '<option value="">No flats available</option>';
        return;
    }
    
    flats.forEach(flat => {
        const option = document.createElement('option');
        option.value = flat.id;
        option.textContent = flat.flat_number;
        flatSelect.appendChild(option);
        console.log('Added flat option:', flat.flat_number);
    });
    
    console.log('Flat dropdown populated with', flats.length, 'options');
}

function loadVillaAreas() {
    console.log('Loading villa areas...');
    console.log('All flats:', allFlats);
    
    const villas = allFlats.filter(flat => {
        console.log('Checking flat:', flat.id, 'property_type:', flat.property_type, 'villa_area:', flat.villa_area);
        return flat.property_type === 'villa' && flat.villa_area;
    });
    console.log('Villas found:', villas.length, villas);
    
    if (villas.length === 0) {
        console.warn('No villa flats with villa areas found!');
        const villaAreaSelect = document.getElementById('villa_area_select');
        villaAreaSelect.innerHTML = '<option value="">No villa areas available</option>';
        return;
    }
    
    const villaAreas = [...new Map(villas.map(flat => [flat.villa_area.id, flat.villa_area])).values()];
    console.log('Villa areas map:', villaAreas);
    
    const villaAreaSelect = document.getElementById('villa_area_select');
    villaAreaSelect.innerHTML = '<option value="">Select Villa Area</option>';
    
    villaAreas.forEach(area => {
        const option = document.createElement('option');
        option.value = area.id;
        option.textContent = area.name;
        villaAreaSelect.appendChild(option);
        console.log('Added villa area option:', area.name);
    });
    
    console.log('Villa area dropdown populated with', villaAreas.length, 'options');
}

function updateVillaOptions() {
    const villaAreaId = document.getElementById('villa_area_select').value;
    const villaSelect = document.getElementById('villa_select');
    
    console.log('Updating villa options for area ID:', villaAreaId);
    
    villaSelect.innerHTML = '<option value="">Select Villa</option>';
    document.getElementById('flat_id').value = '';
    
    if (!villaAreaId) return;
    
    const villas = allFlats.filter(flat => {
        const match = flat.property_type === 'villa' && 
                     flat.villa_area && 
                     flat.villa_area.id == villaAreaId;
        if (match) {
            console.log('Found matching villa:', flat.id, flat.villa_name, flat.flat_number);
        }
        return match;
    });
    
    console.log('Villas for area', villaAreaId, ':', villas.length);
    
    if (villas.length === 0) {
        villaSelect.innerHTML = '<option value="">No villas available in this area</option>';
        return;
    }
    
    villas.forEach(villa => {
        const option = document.createElement('option');
        option.value = villa.id;
        option.textContent = villa.villa_name ? `${villa.villa_name} (${villa.flat_number})` : villa.flat_number;
        villaSelect.appendChild(option);
        console.log('Added villa option:', option.textContent);
    });
    
    console.log('Villa dropdown populated with', villas.length, 'options');
}

function updateFlatId() {
    const propertyType = document.getElementById('property_type').value;
    let flatId = '';
    
    if (propertyType === 'apartment') {
        flatId = document.getElementById('flat_select').value;
    } else if (propertyType === 'villa') {
        flatId = document.getElementById('villa_select').value;
    }
    
    document.getElementById('flat_id').value = flatId;
}

// Photo preview function
function previewPhoto(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('preview-image').src = e.target.result;
            document.getElementById('photo-preview').classList.remove('hidden');
        };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>
@endsection
