@extends('layouts.app')

@section('title', 'Add New Tenant')
@section('page-title', 'Add New Tenant')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="max-w-4xl mx-auto">
        <div class="mb-6">
            <a href="{{ route('admin.tenants.index') }}" class="text-blue-600 hover:text-blue-800 text-sm">
                <i class="fas fa-arrow-left mr-1"></i> Back to Tenants
            </a>
        </div>

        <div class="bg-white rounded-lg shadow-md">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-800">Create New Tenant</h2>
            </div>

            @include('components.form-errors')

            <form action="{{ route('admin.tenants.store') }}" method="POST" class="p-6" enctype="multipart/form-data" id="tenant-create-form">
                @csrf

                <div class="space-y-6">
                    <!-- Basic Information -->
                    <div class="border-b pb-6">
                        <h3 class="text-md font-semibold text-gray-800 mb-4">Basic Information</h3>
                        
                        <div class="grid md:grid-cols-2 gap-6">
                            <!-- Name -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Full Name *</label>
                                <input type="text" name="name" value="{{ old('name') }}" required
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                       placeholder="e.g., John Doe">
                                @error('name')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Email -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Email Address *</label>
                                <input type="email" name="email" value="{{ old('email') }}" required
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                       placeholder="e.g., tenant@example.com">
                                @error('email')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Phone -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Phone Number *</label>
                                <input type="text" name="phone" value="{{ old('phone') }}" required
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                       placeholder="e.g., +91 9876543210">
                                @error('phone')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Status -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Status *</label>
                                <select name="status" required
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <option value="active" {{ old('status', 'active') === 'active' ? 'selected' : '' }}>Active</option>
                                    <option value="inactive" {{ old('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                                </select>
                                @error('status')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <!-- Account Information -->
                    <div class="border-b pb-6">
                        <h3 class="text-md font-semibold text-gray-800 mb-4">Account Information</h3>
                        
                        <div class="grid md:grid-cols-2 gap-6">
                            <!-- Password -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Password *</label>
                                <input type="password" name="password" required
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                       placeholder="Enter password (min 8 characters)">
                                @error('password')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Confirm Password -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Confirm Password *</label>
                                <input type="password" name="password_confirmation" required
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                       placeholder="Confirm password">
                                @error('password_confirmation')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <!-- Profile Image Upload -->
                    <div class="border-b pb-6">
                        <h3 class="text-md font-semibold text-gray-800 mb-4">Profile Image</h3>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Upload Profile Picture (JPG, PNG)</label>
                            <div class="flex items-center justify-center w-full">
                                <label class="flex flex-col items-center justify-center w-full h-32 border-2 border-gray-300 border-dashed rounded-lg cursor-pointer bg-gray-50 hover:bg-gray-100 transition-colors">
                                    <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                        <i class="fas fa-image text-gray-400 text-3xl mb-2"></i>
                                        <p class="text-sm text-gray-500"><span class="font-semibold">Click to upload</span> or drag and drop</p>
                                        <p class="text-xs text-gray-500">JPG, PNG (Max 2MB)</p>
                                    </div>
                                    <input type="file" name="profile_image" class="hidden" accept=".jpg,.jpeg,.png" id="profile_image_input">
                                </label>
                            </div>
                            <div id="profile-image-name" class="mt-2 text-sm text-gray-600"></div>
                            @error('profile_image')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <!-- Family Members -->
                    <div class="border-b pb-6">
                        <h3 class="text-md font-semibold text-gray-800 mb-4">Family Members</h3>
                        
                        <div id="family-members-container" class="space-y-4">
                            <div class="family-member-row p-4 border border-gray-200 rounded-lg">
                                <div class="grid md:grid-cols-3 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                                        <input type="text" name="family_members[0][name]" 
                                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                               placeholder="e.g., John Doe">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Relationship</label>
                                        <select name="family_members[0][relationship]" 
                                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                            <option value="">Select Relationship</option>
                                            <option value="Spouse">Spouse</option>
                                            <option value="Child">Child</option>
                                            <option value="Parent">Parent</option>
                                            <option value="Sibling">Sibling</option>
                                            <option value="Other">Other</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                                        <input type="text" name="family_members[0][phone]" 
                                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                               placeholder="e.g., +91 9876543210">
                                    </div>
                                </div>
                                <button type="button" class="mt-3 text-red-600 hover:text-red-800 text-sm remove-family-member" style="display: none;">
                                    <i class="fas fa-trash mr-1"></i> Remove
                                </button>
                            </div>
                        </div>
                        
                        <button type="button" id="add-family-member" class="mt-4 px-4 py-2 bg-blue-100 text-blue-600 rounded-lg hover:bg-blue-200 transition-colors text-sm font-medium">
                            <i class="fas fa-plus mr-1"></i> Add Family Member
                        </button>
                    </div>

                    <!-- Rental Information -->
                    <div class="border-b pb-6">
                        <h3 class="text-md font-semibold text-gray-800 mb-4">Rental Information</h3>
                        
                        <div class="grid md:grid-cols-2 gap-6">
                            <!-- Contract Start Date -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Contract Start Date *</label>
                                <input type="date" name="contract_start_date" value="{{ old('contract_start_date') }}" required
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                @error('contract_start_date')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Contract End Date -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Contract End Date *</label>
                                <input type="date" name="contract_end_date" value="{{ old('contract_end_date') }}" required
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                @error('contract_end_date')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Rent Amount -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Rent Amount *</label>
                                <input type="number" name="rent_amount" value="{{ old('rent_amount') }}" required step="0.01"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                       placeholder="e.g., 50000">
                                @error('rent_amount')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Rent Billing Cycle -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Rent Billing Cycle *</label>
                                <select name="rent_billing_cycle" required
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <option value="">Select Billing Cycle</option>
                                    <option value="Monthly" {{ old('rent_billing_cycle') === 'Monthly' ? 'selected' : '' }}>Monthly</option>
                                    <option value="Quarterly" {{ old('rent_billing_cycle') === 'Quarterly' ? 'selected' : '' }}>Quarterly</option>
                                    <option value="Annually" {{ old('rent_billing_cycle') === 'Annually' ? 'selected' : '' }}>Annually</option>
                                </select>
                                @error('rent_billing_cycle')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <!-- Property Assignment -->
                    <div class="border-b pb-6">
                        <h3 class="text-md font-semibold text-gray-800 mb-4">Property Assignment</h3>
                        
                        <div class="space-y-4">
                            <!-- Property Type -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Property Type</label>
                                <select name="property_type" id="property_type"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <option value="">Select Property Type</option>
                                    <option value="apartment" {{ old('property_type') === 'apartment' ? 'selected' : '' }}>Apartment</option>
                                    <option value="villa" {{ old('property_type') === 'villa' ? 'selected' : '' }}>Villa</option>
                                </select>
                                @error('property_type')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Apartment Fields -->
                            <div id="apartment-fields" class="space-y-4" style="display: none;">
                                <div class="grid md:grid-cols-3 gap-4">
                                    <!-- Building -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Tower/Building</label>
                                        <select name="building_id" id="building_id"
                                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                            <option value="">Select Building</option>
                                        </select>
                                        @error('building_id')
                                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <!-- Floor -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Floor</label>
                                        <select name="floor" id="floor"
                                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                            <option value="">Select Floor</option>
                                        </select>
                                        @error('floor')
                                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <!-- Flat No -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Flat No.</label>
                                        <select name="flat_no" id="flat_no"
                                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                            <option value="">Select Flat No.</option>
                                        </select>
                                        @error('flat_no')
                                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            <!-- Villa Fields -->
                            <div id="villa-fields" class="space-y-4" style="display: none;">
                                <div class="grid md:grid-cols-2 gap-4">
                                    <!-- Villa Area -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Villa Area</label>
                                        <select name="villa_area_id" id="villa_area_id"
                                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                            <option value="">Select Villa Area</option>
                                        </select>
                                        @error('villa_area_id')
                                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <!-- Villa No -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Villa No.</label>
                                        <select name="villa_no" id="villa_no"
                                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                            <option value="">Select Villa No.</option>
                                        </select>
                                        @error('villa_no')
                                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            <!-- Hidden field for actual flat_id submission -->
                            <input type="hidden" name="flat_id" id="flat_id">
                            
                            <div>
                                <p class="text-sm text-gray-500">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    You can assign a unit now or leave it blank to assign later from the tenant management page.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Identification -->
                    <div class="border-b pb-6">
                        <h3 class="text-md font-semibold text-gray-800 mb-4">Identification</h3>
                        
                        <div class="grid md:grid-cols-2 gap-6">
                            <!-- ID Type -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">ID Type</label>
                                <select name="id_type"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <option value="">Select ID Type</option>
                                    <option value="Aadhar" {{ old('id_type') === 'Aadhar' ? 'selected' : '' }}>Aadhar</option>
                                    <option value="PAN" {{ old('id_type') === 'PAN' ? 'selected' : '' }}>PAN</option>
                                    <option value="Passport" {{ old('id_type') === 'Passport' ? 'selected' : '' }}>Passport</option>
                                    <option value="Driving License" {{ old('id_type') === 'Driving License' ? 'selected' : '' }}>Driving License</option>
                                </select>
                                @error('id_type')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <!-- Document Upload -->
                    <div class="border-b pb-6">
                        <h3 class="text-md font-semibold text-gray-800 mb-4">Document Upload</h3>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Upload Document (PDF, DOC, DOCX, JPG, PNG)</label>
                            <div class="flex items-center justify-center w-full">
                                <label class="flex flex-col items-center justify-center w-full h-32 border-2 border-gray-300 border-dashed rounded-lg cursor-pointer bg-gray-50 hover:bg-gray-100 transition-colors">
                                    <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                        <i class="fas fa-cloud-upload-alt text-gray-400 text-3xl mb-2"></i>
                                        <p class="text-sm text-gray-500"><span class="font-semibold">Click to upload</span> or drag and drop</p>
                                        <p class="text-xs text-gray-500">PDF, DOC, DOCX, JPG, PNG (Max 5MB)</p>
                                    </div>
                                    <input type="file" name="document_path" class="hidden" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" id="document_input">
                                </label>
                            </div>
                            <div id="file-name" class="mt-2 text-sm text-gray-600"></div>
                            @error('document_path')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <!-- Notes -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                        <textarea name="notes" rows="3"
                                  class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                  placeholder="Additional notes about the tenant">{{ old('notes') }}</textarea>
                        @error('notes')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="mt-6 flex items-center justify-end space-x-3 pt-6 border-t border-gray-200">
                    <a href="{{ route('admin.tenants.index') }}" class="px-4 py-2 text-gray-700 hover:text-gray-900">Cancel</a>
                    <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                        Create Tenant
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle profile image file input display
    const profileImageInput = document.getElementById('profile_image_input');
    if (profileImageInput) {
        profileImageInput.addEventListener('change', function(e) {
            const fileName = e.target.files[0]?.name || '';
            const fileNameDiv = document.getElementById('profile-image-name');
            if (fileNameDiv) {
                if (fileName) {
                    fileNameDiv.textContent = '✓ ' + fileName + ' (' + (e.target.files[0].size / 1024).toFixed(2) + ' KB)';
                    fileNameDiv.classList.add('text-green-600');
                } else {
                    fileNameDiv.textContent = '';
                    fileNameDiv.classList.remove('text-green-600');
                }
            }
        });
    }

    // Handle document file input display
    const documentInput = document.getElementById('document_input');
    if (documentInput) {
        documentInput.addEventListener('change', function(e) {
            const fileName = e.target.files[0]?.name || '';
            const fileNameDiv = document.getElementById('file-name');
            if (fileNameDiv) {
                if (fileName) {
                    fileNameDiv.textContent = '✓ ' + fileName + ' (' + (e.target.files[0].size / 1024).toFixed(2) + ' KB)';
                    fileNameDiv.classList.add('text-green-600');
                } else {
                    fileNameDiv.textContent = '';
                    fileNameDiv.classList.remove('text-green-600');
                }
            }
        });
    }

// Store all available units data from backend
const unitsData = {!! json_encode($availableUnits->map(function($unit) {
    return [
        'id' => $unit->id,
        'flat_number' => $unit->flat_number,
        'villa_name' => $unit->villa_name,
        'floor' => $unit->floor,
        'building_id' => $unit->building_id,
        'villa_area_id' => $unit->villa_area_id,
        'property_type' => $unit->property_type,
        'building_name' => $unit->building->name ?? null,
        'villa_area_name' => $unit->villaArea->name ?? null,
    ];
})->toArray()) !!};

function updatePropertyTypeSelection() {
    const propertyType = document.getElementById('property_type').value;
    const apartmentFlow = document.getElementById('apartment-fields');
    const villaFlow = document.getElementById('villa-fields');
    const towerSelect = document.getElementById('building_id');
    const villaAreaSelect = document.getElementById('villa_area_id');
    const flatIdInput = document.getElementById('flat_id');
    
    // Reset everything
    apartmentFlow.style.display = 'none';
    villaFlow.style.display = 'none';
    document.getElementById('floor').innerHTML = '<option value="">Select Floor</option>';
    document.getElementById('flat_no').innerHTML = '<option value="">Select Flat No.</option>';
    towerSelect.innerHTML = '<option value="">Select Building</option>';
    villaAreaSelect.innerHTML = '<option value="">Select Villa Area</option>';
    document.getElementById('villa_no').innerHTML = '<option value="">Select Villa No.</option>';
    flatIdInput.value = '';
    
    if (propertyType === 'apartment') {
        apartmentFlow.style.display = 'block';
        
        // Get all unique buildings with apartments
        const apartments = unitsData.filter(u => u.property_type !== 'villa' && u.building_id);
        const buildingsMap = {};
        
        apartments.forEach(unit => {
            if (unit.building_id && !buildingsMap[unit.building_id]) {
                buildingsMap[unit.building_id] = unit.building_name || 'Building ' + unit.building_id;
            }
        });
        
        Object.keys(buildingsMap).forEach(buildingId => {
            const option = document.createElement('option');
            option.value = buildingId;
            option.textContent = buildingsMap[buildingId];
            towerSelect.appendChild(option);
        });
        
    } else if (propertyType === 'villa') {
        villaFlow.style.display = 'block';
        
        // Get all unique villa areas
        const villas = unitsData.filter(u => u.property_type === 'villa' && u.villa_area_id);
        const villaAreasMap = {};
        
        villas.forEach(unit => {
            if (unit.villa_area_id && !villaAreasMap[unit.villa_area_id]) {
                villaAreasMap[unit.villa_area_id] = unit.villa_area_name || 'Villa Area ' + unit.villa_area_id;
            }
        });
        
        Object.keys(villaAreasMap).forEach(villaAreaId => {
            const option = document.createElement('option');
            option.value = villaAreaId;
            option.textContent = villaAreasMap[villaAreaId];
            villaAreaSelect.appendChild(option);
        });
    }
}

function updateFloorOptions() {
    const towerSelect = document.getElementById('building_id');
    const floorSelect = document.getElementById('floor');
    const flatIdInput = document.getElementById('flat_id');
    
    const selectedBuildingId = towerSelect.value;
    
    // Reset dependent fields
    floorSelect.innerHTML = '<option value="">Select Floor</option>';
    document.getElementById('flat_no').innerHTML = '<option value="">Select Flat No.</option>';
    flatIdInput.value = '';
    
    if (!selectedBuildingId) return;
    
    // Get all unique floors in this building
    const apartmentsInBuilding = unitsData.filter(u => u.building_id == selectedBuildingId);
    const floors = [...new Set(apartmentsInBuilding.map(u => u.floor))].sort((a, b) => a - b);
    
    if (floors.length > 0) {
        floors.forEach(floor => {
            const option = document.createElement('option');
            option.value = floor;
            option.textContent = 'Floor ' + floor;
            floorSelect.appendChild(option);
        });
    }
}

function updateApartmentOptions() {
    const towerSelect = document.getElementById('building_id');
    const floorSelect = document.getElementById('floor');
    const apartmentSelect = document.getElementById('flat_no');
    const flatIdInput = document.getElementById('flat_id');
    
    const selectedBuildingId = towerSelect.value;
    const selectedFloor = floorSelect.value;
    
    // Reset apartment selection
    apartmentSelect.innerHTML = '<option value="">Select Flat No.</option>';
    flatIdInput.value = '';
    
    if (!selectedBuildingId || !selectedFloor) return;
    
    // Get all apartments on this floor in this building
    const apartmentsOnFloor = unitsData.filter(u => 
        u.building_id == selectedBuildingId && u.floor == selectedFloor
    );
    
    if (apartmentsOnFloor.length > 0) {
        apartmentsOnFloor.forEach(unit => {
            const option = document.createElement('option');
            option.value = unit.id;
            option.textContent = unit.flat_number;
            apartmentSelect.appendChild(option);
        });
    }
}

function updateVillaOptions() {
    const villaAreaSelect = document.getElementById('villa_area_id');
    const villaSelect = document.getElementById('villa_no');
    const flatIdInput = document.getElementById('flat_id');
    
    const selectedVillaAreaId = villaAreaSelect.value;
    
    // Reset villa selection
    villaSelect.innerHTML = '<option value="">Select Villa No.</option>';
    flatIdInput.value = '';
    
    if (!selectedVillaAreaId) return;
    
    // Get all villas in this villa area
    const villasInArea = unitsData.filter(u => u.villa_area_id == selectedVillaAreaId);
    
    if (villasInArea.length > 0) {
        villasInArea.forEach(unit => {
            const option = document.createElement('option');
            option.value = unit.id;
            option.textContent = unit.villa_name || unit.flat_number;
            villaSelect.appendChild(option);
        });
    }
}

function updateFlatId() {
    const propertyType = document.getElementById('property_type').value;
    const flatIdInput = document.getElementById('flat_id');
    
    if (propertyType === 'apartment') {
        const apartmentSelect = document.getElementById('flat_no');
        flatIdInput.value = apartmentSelect.value;
    } else if (propertyType === 'villa') {
        const villaSelect = document.getElementById('villa_no');
        flatIdInput.value = villaSelect.value;
    }
}

// Event listeners
const propertyTypeSelect = document.getElementById('property_type');
const buildingSelect = document.getElementById('building_id');
const floorSelect = document.getElementById('floor');
const flatNoSelect = document.getElementById('flat_no');
const villaAreaSelect = document.getElementById('villa_area_id');
const villaNoSelect = document.getElementById('villa_no');

if (propertyTypeSelect) propertyTypeSelect.addEventListener('change', updatePropertyTypeSelection);
if (buildingSelect) buildingSelect.addEventListener('change', updateFloorOptions);
if (floorSelect) floorSelect.addEventListener('change', updateApartmentOptions);
if (flatNoSelect) flatNoSelect.addEventListener('change', updateFlatId);
if (villaAreaSelect) villaAreaSelect.addEventListener('change', updateVillaOptions);
if (villaNoSelect) villaNoSelect.addEventListener('change', updateFlatId);

// Family Members Management
let familyMemberCount = 1;

const addFamilyMemberBtn = document.getElementById('add-family-member');
if (addFamilyMemberBtn) {
    addFamilyMemberBtn.addEventListener('click', function(e) {
    e.preventDefault();
    
    const container = document.getElementById('family-members-container');
    const newRow = document.createElement('div');
    newRow.className = 'family-member-row p-4 border border-gray-200 rounded-lg';
    newRow.innerHTML = `
        <div class="grid md:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                <input type="text" name="family_members[${familyMemberCount}][name]" 
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                       placeholder="e.g., John Doe">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Relationship</label>
                <select name="family_members[${familyMemberCount}][relationship]" 
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Select Relationship</option>
                    <option value="Spouse">Spouse</option>
                    <option value="Child">Child</option>
                    <option value="Parent">Parent</option>
                    <option value="Sibling">Sibling</option>
                    <option value="Other">Other</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                <input type="text" name="family_members[${familyMemberCount}][phone]" 
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                       placeholder="e.g., +91 9876543210">
            </div>
        </div>
        <button type="button" class="mt-3 text-red-600 hover:text-red-800 text-sm remove-family-member">
            <i class="fas fa-trash mr-1"></i> Remove
        </button>
    `;
    
    container.appendChild(newRow);
    familyMemberCount++;
    
    // Update remove button visibility
    updateRemoveButtonVisibility();
    
    // Add event listener to new remove button
    newRow.querySelector('.remove-family-member').addEventListener('click', function(e) {
        e.preventDefault();
        newRow.remove();
        updateRemoveButtonVisibility();
    });
    });
}

function updateRemoveButtonVisibility() {
    const rows = document.querySelectorAll('.family-member-row');
    rows.forEach((row, index) => {
        const removeBtn = row.querySelector('.remove-family-member');
        if (rows.length > 1) {
            removeBtn.style.display = 'block';
        } else {
            removeBtn.style.display = 'none';
        }
    });
}

// Initialize remove button listeners
document.querySelectorAll('.remove-family-member').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        this.closest('.family-member-row').remove();
        updateRemoveButtonVisibility();
    });
});

// Initial visibility check
updateRemoveButtonVisibility();

// CRITICAL: Tenant form submission handler
const tenantForm = document.getElementById('tenant-create-form');
if (tenantForm) {
    tenantForm.addEventListener('submit', function(e) {
        console.log('🎯 Tenant form submission detected');
        
        const submitButton = tenantForm.querySelector('button[type="submit"]');
        if (submitButton) {
            // Disable button to prevent double submission
            submitButton.disabled = true;
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Creating...';
            
            // Re-enable after 30 seconds as failsafe
            setTimeout(() => {
                if (submitButton.disabled) {
                    submitButton.disabled = false;
                    submitButton.innerHTML = 'Create Tenant';
                    console.log('⚠️ Tenant form timeout - re-enabled submit button');
                }
            }, 30000);
        }
        
        // Let the form submit normally - don't prevent default
        console.log('✅ Tenant form submitting normally');
    });
}
});
</script>
@endpush
