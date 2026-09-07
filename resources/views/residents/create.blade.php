@extends('layouts.app')

@section('title', 'Add New Resident')
@section('page-title', 'Add New Resident')

@section('content')
<div class="container mx-auto px-4 py-6">
    <!-- Header -->
    <div class="flex items-center mb-6">
        <a href="{{ route('residents.index') }}" 
           class="text-gray-600 hover:text-gray-900 mr-4">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Add New Resident</h1>
            <p class="text-gray-600 mt-1">Register a new resident in the system</p>
        </div>
    </div>

    <!-- Create Form -->
    <div class="max-w-4xl bg-white rounded-lg shadow-md">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Resident Information</h2>
        </div>
        
        <form action="{{ route('residents.store') }}" method="POST" class="px-6 py-6">
            @csrf
            
            <div class="space-y-6">
                <!-- Personal Information -->
                <div class="border-b border-gray-200 pb-6">
                    <h3 class="text-md font-semibold text-gray-900 mb-4">Personal Information</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
                                Full Name <span class="text-red-500">*</span>
                            </label>
                            <input type="text" 
                                   name="name" 
                                   id="name" 
                                   required
                                   value="{{ old('name') }}"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('name') border-red-500 @enderror"
                                   placeholder="Enter full name">
                            @error('name')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                                Email <span class="text-red-500">*</span>
                            </label>
                            <input type="email" 
                                   name="email" 
                                   id="email" 
                                   required
                                   value="{{ old('email') }}"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('email') border-red-500 @enderror"
                                   placeholder="Enter email address">
                            @error('email')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="phone" class="block text-sm font-medium text-gray-700 mb-2">
                                Phone <span class="text-red-500">*</span>
                            </label>
                            <input type="tel" 
                                   name="phone" 
                                   id="phone" 
                                   required
                                   value="{{ old('phone') }}"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('phone') border-red-500 @enderror"
                                   placeholder="Enter phone number">
                            @error('phone')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <!-- Residence Information -->
                <div class="border-b border-gray-200 pb-6">
                    <h3 class="text-md font-semibold text-gray-900 mb-4">Residence Information</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        @if(auth()->user()->hasRole('Super Admin'))
                        <div class="md:col-span-2">
                            <label for="society_id" class="block text-sm font-medium text-gray-700 mb-2">
                                Society <span class="text-red-500">*</span>
                            </label>
                            <select name="society_id" 
                                    id="society_id" 
                                    required
                                    onchange="reloadFlats()"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('society_id') border-red-500 @enderror">
                                <option value="">Select Society</option>
                                @foreach($societies as $society)
                                    <option value="{{ $society->id }}" {{ (old('society_id', $societyId) == $society->id) ? 'selected' : '' }}>
                                        {{ $society->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('society_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        @else
                        <input type="hidden" name="society_id" value="{{ $societyId }}">
                        @endif
                        
                        <!-- Select Apartment/Villa Type -->
                        <div class="md:col-span-2">
                            <label for="property_type_select" class="block text-sm font-medium text-gray-700 mb-2">
                                Select Apartment/Villa <span class="text-red-500">*</span>
                            </label>
                            <select id="property_type_select" 
                                    onchange="updatePropertyTypeSelection()"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Select Property Type</option>
                                <option value="apartment">Apartment</option>
                                <option value="villa">Villa</option>
                            </select>
                        </div>

                        <!-- Apartment Selection Flow -->
                        <div id="apartment_flow" style="display: none;" class="md:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Select Tower (Building) -->
                            <div>
                                <label for="tower_select" class="block text-sm font-medium text-gray-700 mb-2">
                                    Select Tower <span class="text-red-500">*</span>
                                </label>
                                <select id="tower_select" 
                                        onchange="updateFloorOptions()"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <option value="">Select Tower</option>
                                </select>
                            </div>

                            <!-- Select Floor -->
                            <div id="floor_container" style="display: none;">
                                <label for="floor_select" class="block text-sm font-medium text-gray-700 mb-2">
                                    Select Floor <span class="text-red-500">*</span>
                                </label>
                                <select id="floor_select" 
                                        onchange="updateApartmentOptions()"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <option value="">Select Floor</option>
                                </select>
                            </div>

                            <!-- Select Apartment -->
                            <div id="apartment_container" style="display: none;">
                                <label for="apartment_select" class="block text-sm font-medium text-gray-700 mb-2">
                                    Flat No. <span class="text-red-500">*</span>
                                </label>
                                <select id="apartment_select" 
                                        onchange="updateFlatId()"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <option value="">Select Apartment</option>
                                </select>
                            </div>
                        </div>

                        <!-- Villa Selection Flow -->
                        <div id="villa_flow" style="display: none;" class="md:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Select Villa Area -->
                            <div>
                                <label for="villa_area_select" class="block text-sm font-medium text-gray-700 mb-2">
                                    Villa Areas <span class="text-red-500">*</span>
                                </label>
                                <select id="villa_area_select" 
                                        onchange="updateVillaOptions()"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <option value="">Select Villa Area</option>
                                </select>
                            </div>

                            <!-- Select Villa -->
                            <div id="villa_container" style="display: none;">
                                <label for="villa_select" class="block text-sm font-medium text-gray-700 mb-2">
                                    Villa No. <span class="text-red-500">*</span>
                                </label>
                                <select id="villa_select" 
                                        onchange="updateFlatId()"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <option value="">Select Villa</option>
                                </select>
                            </div>
                        </div>

                        <!-- Hidden field for actual flat_id submission -->
                        <input type="hidden" name="flat_id" id="flat_id" required>
                        @error('flat_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror

                        <div>
                            <label for="move_in_date" class="block text-sm font-medium text-gray-700 mb-2">
                                Move In Date <span class="text-red-500">*</span>
                            </label>
                            <input type="date" 
                                   name="move_in_date" 
                                   id="move_in_date" 
                                   required
                                   value="{{ old('move_in_date', date('Y-m-d')) }}"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('move_in_date') border-red-500 @enderror">
                            @error('move_in_date')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <!-- Financial Information -->
                <div>
                    <h3 class="text-md font-semibold text-gray-900 mb-4">Financial Information (Optional)</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="monthly_rent" class="block text-sm font-medium text-gray-700 mb-2">
                                Monthly Rent (₹)
                            </label>
                            <input type="number" 
                                   name="monthly_rent" 
                                   id="monthly_rent" 
                                   step="0.01"
                                   min="0"
                                   value="{{ old('monthly_rent', 0) }}"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('monthly_rent') border-red-500 @enderror"
                                   placeholder="0.00">
                            @error('monthly_rent')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="security_deposit" class="block text-sm font-medium text-gray-700 mb-2">
                                Security Deposit (₹)
                            </label>
                            <input type="number" 
                                   name="security_deposit" 
                                   id="security_deposit" 
                                   step="0.01"
                                   min="0"
                                   value="{{ old('security_deposit', 0) }}"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('security_deposit') border-red-500 @enderror"
                                   placeholder="0.00">
                            @error('security_deposit')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <!-- Info Box -->
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <div class="flex items-start">
                        <i class="fas fa-info-circle text-blue-600 mt-1 mr-3"></i>
                        <div>
                            <h4 class="text-sm font-medium text-blue-900 mb-1">Default Login Credentials</h4>
                            <p class="text-sm text-blue-700">
                                A user account will be created automatically with the default password: <strong>password</strong>
                                <br>The resident can change this password after first login.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="mt-8 flex justify-end space-x-3 pt-6 border-t border-gray-200">
                <a href="{{ route('residents.index') }}" 
                   class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 font-medium transition-colors">
                    Cancel
                </a>
                <button type="submit" 
                        class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition-colors">
                    Create Resident
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
// Store all flats data from backend
const flatsData = {!! json_encode($flats->map(function($flat) {
    return [
        'id' => $flat->id,
        'flat_number' => $flat->flat_number,
        'villa_name' => $flat->villa_name,
        'floor' => $flat->floor,
        'building_id' => $flat->building_id,
        'villa_area_id' => $flat->villa_area_id,
        'property_type' => $flat->property_type,
        'building_name' => $flat->building->name ?? null,
        'villa_area_name' => $flat->villaArea->name ?? null,
    ];
})->toArray()) !!};

console.log('Total flats loaded:', flatsData.length);
console.log('Flats data:', flatsData);

function reloadFlats() {
    const societyId = document.getElementById('society_id').value;
    if (societyId) {
        // Reload the page with the selected society
        window.location.href = '{{ route("residents.create") }}?society_id=' + societyId;
    }
}

function updatePropertyTypeSelection() {
    const propertyType = document.getElementById('property_type_select').value;
    const apartmentFlow = document.getElementById('apartment_flow');
    const villaFlow = document.getElementById('villa_flow');
    const towerSelect = document.getElementById('tower_select');
    const villaAreaSelect = document.getElementById('villa_area_select');
    const flatIdInput = document.getElementById('flat_id');
    
    console.log('Property type selected:', propertyType);
    
    // Reset everything
    apartmentFlow.style.display = 'none';
    villaFlow.style.display = 'none';
    document.getElementById('floor_container').style.display = 'none';
    document.getElementById('apartment_container').style.display = 'none';
    document.getElementById('villa_container').style.display = 'none';
    towerSelect.innerHTML = '<option value="">Select Tower</option>';
    villaAreaSelect.innerHTML = '<option value="">Select Villa Area</option>';
    flatIdInput.value = '';
    
    if (propertyType === 'apartment') {
        apartmentFlow.style.display = 'block';
        
        // Get all unique buildings with apartments
        const apartments = flatsData.filter(u => u.property_type !== 'villa' && u.building_id);
        console.log('Apartments found:', apartments.length);
        const buildingsMap = {};
        
        apartments.forEach(unit => {
            if (unit.building_id && !buildingsMap[unit.building_id]) {
                buildingsMap[unit.building_id] = unit.building_name || 'Building ' + unit.building_id;
            }
        });
        
        console.log('Buildings map:', buildingsMap);
        
        Object.keys(buildingsMap).forEach(buildingId => {
            const option = document.createElement('option');
            option.value = buildingId;
            option.textContent = buildingsMap[buildingId];
            towerSelect.appendChild(option);
        });
        
    } else if (propertyType === 'villa') {
        villaFlow.style.display = 'block';
        
        // Get all unique villa areas
        const villas = flatsData.filter(u => u.property_type === 'villa' && u.villa_area_id);
        console.log('Villas found:', villas.length, villas);
        const villaAreasMap = {};
        
        villas.forEach(unit => {
            if (unit.villa_area_id && !villaAreasMap[unit.villa_area_id]) {
                villaAreasMap[unit.villa_area_id] = unit.villa_area_name || 'Villa Area ' + unit.villa_area_id;
            }
        });
        
        console.log('Villa areas map:', villaAreasMap);
        
        Object.keys(villaAreasMap).forEach(villaAreaId => {
            const option = document.createElement('option');
            option.value = villaAreaId;
            option.textContent = villaAreasMap[villaAreaId];
            villaAreaSelect.appendChild(option);
        });
    }
}

function updateFloorOptions() {
    const towerSelect = document.getElementById('tower_select');
    const floorContainer = document.getElementById('floor_container');
    const floorSelect = document.getElementById('floor_select');
    const apartmentContainer = document.getElementById('apartment_container');
    const flatIdInput = document.getElementById('flat_id');
    
    const selectedBuildingId = towerSelect.value;
    
    // Reset dependent fields
    floorSelect.innerHTML = '<option value="">Select Floor</option>';
    document.getElementById('apartment_select').innerHTML = '<option value="">Select Apartment</option>';
    flatIdInput.value = '';
    floorContainer.style.display = 'none';
    apartmentContainer.style.display = 'none';
    
    if (!selectedBuildingId) return;
    
    // Get all unique floors in this building
    const apartmentsInBuilding = flatsData.filter(u => u.building_id == selectedBuildingId);
    const floors = [...new Set(apartmentsInBuilding.map(u => u.floor))].sort((a, b) => a - b);
    
    if (floors.length > 0) {
        floorContainer.style.display = 'block';
        
        floors.forEach(floor => {
            const option = document.createElement('option');
            option.value = floor;
            option.textContent = 'Floor ' + floor;
            floorSelect.appendChild(option);
        });
    }
}

function updateApartmentOptions() {
    const towerSelect = document.getElementById('tower_select');
    const floorSelect = document.getElementById('floor_select');
    const apartmentContainer = document.getElementById('apartment_container');
    const apartmentSelect = document.getElementById('apartment_select');
    const flatIdInput = document.getElementById('flat_id');
    
    const selectedBuildingId = towerSelect.value;
    const selectedFloor = floorSelect.value;
    
    // Reset apartment selection
    apartmentSelect.innerHTML = '<option value="">Select Apartment</option>';
    flatIdInput.value = '';
    apartmentContainer.style.display = 'none';
    
    if (!selectedBuildingId || !selectedFloor) return;
    
    // Get all apartments on this floor in this building
    const apartmentsOnFloor = flatsData.filter(u => 
        u.building_id == selectedBuildingId && u.floor == selectedFloor
    );
    
    if (apartmentsOnFloor.length > 0) {
        apartmentContainer.style.display = 'block';
        
        apartmentsOnFloor.forEach(unit => {
            const option = document.createElement('option');
            option.value = unit.id;
            option.textContent = unit.flat_number;
            apartmentSelect.appendChild(option);
        });
    }
}

function updateVillaOptions() {
    const villaAreaSelect = document.getElementById('villa_area_select');
    const villaContainer = document.getElementById('villa_container');
    const villaSelect = document.getElementById('villa_select');
    const flatIdInput = document.getElementById('flat_id');
    
    const selectedVillaAreaId = villaAreaSelect.value;
    
    // Reset villa selection
    villaSelect.innerHTML = '<option value="">Select Villa</option>';
    flatIdInput.value = '';
    villaContainer.style.display = 'none';
    
    if (!selectedVillaAreaId) return;
    
    // Get all villas in this villa area
    const villasInArea = flatsData.filter(u => u.villa_area_id == selectedVillaAreaId);
    
    if (villasInArea.length > 0) {
        villaContainer.style.display = 'block';
        
        villasInArea.forEach(unit => {
            const option = document.createElement('option');
            option.value = unit.id;
            option.textContent = unit.villa_name || unit.flat_number;
            villaSelect.appendChild(option);
        });
    }
}

function updateFlatId() {
    const propertyType = document.getElementById('property_type_select').value;
    const flatIdInput = document.getElementById('flat_id');
    
    if (propertyType === 'apartment') {
        const apartmentSelect = document.getElementById('apartment_select');
        flatIdInput.value = apartmentSelect.value;
    } else if (propertyType === 'villa') {
        const villaSelect = document.getElementById('villa_select');
        flatIdInput.value = villaSelect.value;
    }
}
</script>
@endpush
