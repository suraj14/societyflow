@extends('layouts.app')

@section('title', 'Edit Owner')
@section('page-title', 'Edit Owner - ' . $owner->name)

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="max-w-3xl mx-auto">
        <div class="mb-6">
            <a href="{{ route('owners.index') }}" class="text-blue-600 hover:text-blue-800 text-sm">
                <i class="fas fa-arrow-left mr-1"></i> Back to Owners
            </a>
        </div>

        <div class="bg-white rounded-lg shadow-md">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-800">Edit Owner</h2>
            </div>

            <form action="{{ route('owners.update', $owner) }}" method="POST" class="p-6" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                <div class="space-y-6">
                    <!-- Basic Information -->
                    <div class="border-b pb-6">
                        <h3 class="text-md font-semibold text-gray-800 mb-4">Basic Information</h3>
                        
                        <div class="grid md:grid-cols-2 gap-6">
                            <!-- Society -->
                            @if(auth()->user()->hasRole('Super Admin'))
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Society *</label>
                                    <select name="society_id" required
                                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                        <option value="">Select Society</option>
                                        @foreach($societies as $society)
                                            <option value="{{ $society->id }}" {{ old('society_id', $owner->society_id) == $society->id ? 'selected' : '' }}>
                                                {{ $society->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('society_id')
                                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                    @enderror
                                </div>
                            @else
                                <input type="hidden" name="society_id" value="{{ auth()->user()->society_id }}">
                            @endif

                            <!-- Name -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Full Name *</label>
                                <input type="text" name="name" value="{{ old('name', $owner->name) }}" required
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                @error('name')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Email -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Email Address *</label>
                                <input type="email" name="email" value="{{ old('email', $owner->email) }}" required
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                @error('email')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Phone -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
                                <input type="text" name="phone" value="{{ old('phone', $owner->phone) }}"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                @error('phone')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Status -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Status *</label>
                                <select name="status" required
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <option value="active" {{ old('status', $owner->status) === 'active' ? 'selected' : '' }}>Active</option>
                                    <option value="inactive" {{ old('status', $owner->status) === 'inactive' ? 'selected' : '' }}>Inactive</option>
                                </select>
                                @error('status')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <!-- Profile Image Upload -->
                    <div class="border-b pb-6">
                        <h3 class="text-md font-semibold text-gray-800 mb-4">Profile Image</h3>
                        
                        @if($owner->profile_image)
                            <div class="mb-4 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                                <p class="text-sm text-blue-700">
                                    <i class="fas fa-check-circle mr-2"></i>
                                    Current image: <img src="{{ asset('storage/' . $owner->profile_image) }}" alt="Profile" class="inline-block w-12 h-12 rounded-full">
                                </p>
                            </div>
                        @endif
                        
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
                            @if($owner->family_members && count($owner->family_members) > 0)
                                @foreach($owner->family_members as $index => $member)
                                    <div class="family-member-row p-4 border border-gray-200 rounded-lg">
                                        <div class="grid md:grid-cols-3 gap-4">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                                                <input type="text" name="family_members[{{ $index }}][name]" 
                                                       value="{{ $member['name'] ?? '' }}"
                                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                                       placeholder="e.g., John Doe">
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Relationship</label>
                                                <select name="family_members[{{ $index }}][relationship]" 
                                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                                    <option value="">Select Relationship</option>
                                                    <option value="Spouse" {{ ($member['relationship'] ?? '') === 'Spouse' ? 'selected' : '' }}>Spouse</option>
                                                    <option value="Child" {{ ($member['relationship'] ?? '') === 'Child' ? 'selected' : '' }}>Child</option>
                                                    <option value="Parent" {{ ($member['relationship'] ?? '') === 'Parent' ? 'selected' : '' }}>Parent</option>
                                                    <option value="Sibling" {{ ($member['relationship'] ?? '') === 'Sibling' ? 'selected' : '' }}>Sibling</option>
                                                    <option value="Other" {{ ($member['relationship'] ?? '') === 'Other' ? 'selected' : '' }}>Other</option>
                                                </select>
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                                                <input type="text" name="family_members[{{ $index }}][phone]" 
                                                       value="{{ $member['phone'] ?? '' }}"
                                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                                       placeholder="e.g., +91 9876543210">
                                            </div>
                                        </div>
                                        <button type="button" class="mt-3 text-red-600 hover:text-red-800 text-sm remove-family-member">
                                            <i class="fas fa-trash mr-1"></i> Remove
                                        </button>
                                    </div>
                                @endforeach
                            @else
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
                            @endif
                        </div>
                        
                        <button type="button" id="add-family-member" class="mt-4 px-4 py-2 bg-blue-100 text-blue-600 rounded-lg hover:bg-blue-200 transition-colors text-sm font-medium">
                            <i class="fas fa-plus mr-1"></i> Add Family Member
                        </button>
                    </div>

                    <!-- Property Selection -->

                    <div class="border-b pb-6">
                        <h3 class="text-md font-semibold text-gray-800 mb-4">Property Information</h3>
                        
                        <div class="space-y-4">
                            <!-- Property Type -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Property Type</label>
                                <select name="property_type" id="property_type"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <option value="">Select Property Type</option>
                                    <option value="apartment" {{ old('property_type', $owner->property_type) === 'apartment' ? 'selected' : '' }}>Apartment</option>
                                    <option value="villa" {{ old('property_type', $owner->property_type) === 'villa' ? 'selected' : '' }}>Villa</option>
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
                                    <option value="Aadhar" {{ old('id_type', $owner->id_type) === 'Aadhar' ? 'selected' : '' }}>Aadhar</option>
                                    <option value="PAN" {{ old('id_type', $owner->id_type) === 'PAN' ? 'selected' : '' }}>PAN</option>
                                    <option value="Passport" {{ old('id_type', $owner->id_type) === 'Passport' ? 'selected' : '' }}>Passport</option>
                                    <option value="Driving License" {{ old('id_type', $owner->id_type) === 'Driving License' ? 'selected' : '' }}>Driving License</option>
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
                            @if($owner->document_path)
                                <div class="mb-3 p-3 bg-green-50 border border-green-200 rounded-lg">
                                    <p class="text-sm text-green-700">
                                        <i class="fas fa-check-circle mr-2"></i>
                                        Current document: <a href="{{ asset('storage/' . $owner->document_path) }}" target="_blank" class="font-semibold hover:underline">View</a>
                                    </p>
                                </div>
                            @endif
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

                    <!-- Bank Information -->
                    <div class="pb-6">
                        <h3 class="text-md font-semibold text-gray-800 mb-4">Bank Information</h3>
                        
                        <div class="grid md:grid-cols-3 gap-6">
                            <!-- Bank Name -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Bank Name</label>
                                <input type="text" name="bank_name" value="{{ old('bank_name', $owner->bank_name) }}"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                @error('bank_name')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Account Number -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Account Number</label>
                                <input type="text" name="account_number" value="{{ old('account_number', $owner->account_number) }}"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                @error('account_number')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- IFSC Code -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">IFSC Code</label>
                                <input type="text" name="ifsc_code" value="{{ old('ifsc_code', $owner->ifsc_code) }}"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                @error('ifsc_code')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                        <textarea name="notes" rows="3"
                                  class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">{{ old('notes', $owner->notes) }}</textarea>
                        @error('notes')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="mt-6 flex items-center justify-end space-x-3 pt-6 border-t border-gray-200">
                    <a href="{{ route('owners.index') }}" class="px-4 py-2 text-gray-700 hover:text-gray-900">Cancel</a>
                    <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                        Update Owner
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
    const propertyTypeSelect = document.getElementById('property_type');
    const apartmentFields = document.getElementById('apartment-fields');
    const villaFields = document.getElementById('villa-fields');
    const buildingSelect = document.getElementById('building_id');
    const floorSelect = document.getElementById('floor');
    const flatNoSelect = document.getElementById('flat_no');
    const villaAreaSelect = document.getElementById('villa_area_id');
    const villaNoSelect = document.getElementById('villa_no');
    const documentInput = document.getElementById('document_input');
    const fileNameDiv = document.getElementById('file-name');
    const societySelect = document.querySelector('select[name="society_id"]');

    // Store original values for edit mode
    const originalBuildingId = '{{ old("building_id", $owner->building_id) }}';
    const originalFloor = '{{ old("floor", $owner->floor) }}';
    const originalFlatNo = '{{ old("flat_no", $owner->flat_no) }}';
    const originalVillaAreaId = '{{ old("villa_area_id", $owner->villa_area_id) }}';
    const originalVillaNo = '{{ old("villa_no", $owner->villa_no) }}';

    console.log('Original values:', {
        buildingId: originalBuildingId,
        floor: originalFloor,
        flatNo: originalFlatNo,
        villaAreaId: originalVillaAreaId,
        villaNo: originalVillaNo,
        propertyType: '{{ old("property_type", $owner->property_type) }}'
    });

    // Get society ID
    function getSocietyId() {
        if (societySelect) {
            return societySelect.value;
        }
        return document.querySelector('input[name="society_id"]')?.value || '';
    }

    // Fetch buildings
    function fetchBuildings() {
        const societyId = getSocietyId();
        console.log('Fetching buildings for society:', societyId);
        
        if (!societyId) {
            console.warn('No society ID available');
            buildingSelect.innerHTML = '<option value="">Select Building</option>';
            floorSelect.innerHTML = '<option value="">Select Floor</option>';
            flatNoSelect.innerHTML = '<option value="">Select Flat No.</option>';
            return;
        }

        fetch(`/api/owners/buildings?society_id=${societyId}`)
            .then(response => {
                console.log('Buildings response status:', response.status);
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('Buildings data:', data);
                buildingSelect.innerHTML = '<option value="">Select Building</option>';
                if (data && data.length > 0) {
                    data.forEach(building => {
                        const option = document.createElement('option');
                        option.value = building.id;
                        option.textContent = building.name;
                        if (building.id == originalBuildingId) {
                            option.selected = true;
                        }
                        buildingSelect.appendChild(option);
                    });
                    // Trigger floor fetch if building was selected
                    if (originalBuildingId) {
                        fetchFloors();
                    }
                } else {
                    console.warn('No buildings found');
                }
            })
            .catch(error => {
                console.error('Error fetching buildings:', error);
                buildingSelect.innerHTML = '<option value="">Error loading buildings</option>';
            });
    }

    // Fetch floors for selected building
    function fetchFloors() {
        const buildingId = buildingSelect.value;
        console.log('Fetching floors for building:', buildingId);
        
        if (!buildingId) {
            floorSelect.innerHTML = '<option value="">Select Floor</option>';
            flatNoSelect.innerHTML = '<option value="">Select Flat No.</option>';
            return;
        }

        fetch(`/api/owners/floors?building_id=${buildingId}`)
            .then(response => {
                console.log('Floors response status:', response.status);
                return response.json();
            })
            .then(data => {
                console.log('Floors data:', data);
                floorSelect.innerHTML = '<option value="">Select Floor</option>';
                if (data && data.length > 0) {
                    data.forEach(floor => {
                        const option = document.createElement('option');
                        option.value = floor;
                        option.textContent = 'Floor ' + floor;
                        if (floor == originalFloor) {
                            option.selected = true;
                        }
                        floorSelect.appendChild(option);
                    });
                    // Trigger flat fetch if floor was selected
                    if (originalFloor) {
                        fetchFlats();
                    }
                } else {
                    console.warn('No floors found for building:', buildingId);
                }
            })
            .catch(error => console.error('Error fetching floors:', error));
    }

    // Fetch flats for selected building and floor
    function fetchFlats() {
        const buildingId = buildingSelect.value;
        const floor = floorSelect.value;
        console.log('Fetching flats for building:', buildingId, 'floor:', floor);
        
        if (!buildingId || floor === '') {
            flatNoSelect.innerHTML = '<option value="">Select Flat No.</option>';
            return;
        }

        fetch(`/api/owners/flats?building_id=${buildingId}&floor=${floor}`)
            .then(response => {
                console.log('Flats response status:', response.status);
                return response.json();
            })
            .then(data => {
                console.log('Flats data:', data);
                flatNoSelect.innerHTML = '<option value="">Select Flat No.</option>';
                if (data && data.length > 0) {
                    data.forEach(flatNo => {
                        const option = document.createElement('option');
                        option.value = flatNo;
                        option.textContent = flatNo;
                        if (flatNo == originalFlatNo) {
                            option.selected = true;
                        }
                        flatNoSelect.appendChild(option);
                    });
                } else {
                    console.warn('No flats found for building:', buildingId, 'floor:', floor);
                }
            })
            .catch(error => console.error('Error fetching flats:', error));
    }

    // Fetch villa areas
    function fetchVillaAreas() {
        const societyId = getSocietyId();
        console.log('Fetching villa areas for society:', societyId);
        
        if (!societyId) {
            console.warn('No society ID available');
            villaAreaSelect.innerHTML = '<option value="">Select Villa Area</option>';
            villaNoSelect.innerHTML = '<option value="">Select Villa No.</option>';
            return;
        }

        fetch(`/api/owners/villa-areas?society_id=${societyId}`)
            .then(response => {
                console.log('Villa areas response status:', response.status);
                return response.json();
            })
            .then(data => {
                console.log('Villa areas data:', data);
                villaAreaSelect.innerHTML = '<option value="">Select Villa Area</option>';
                if (data && data.length > 0) {
                    data.forEach(area => {
                        const option = document.createElement('option');
                        option.value = area.id;
                        option.textContent = area.name;
                        if (area.id == originalVillaAreaId) {
                            option.selected = true;
                        }
                        villaAreaSelect.appendChild(option);
                    });
                    // Trigger villa number fetch if villa area was selected
                    if (originalVillaAreaId) {
                        fetchVillaNumbers();
                    }
                } else {
                    console.warn('No villa areas found');
                }
            })
            .catch(error => console.error('Error fetching villa areas:', error));
    }

    // Fetch villa numbers for selected villa area
    function fetchVillaNumbers() {
        const villaAreaId = villaAreaSelect.value;
        console.log('Fetching villa numbers for villa area:', villaAreaId);
        
        if (!villaAreaId) {
            villaNoSelect.innerHTML = '<option value="">Select Villa No.</option>';
            return;
        }

        fetch(`/api/owners/villa-numbers?villa_area_id=${villaAreaId}`)
            .then(response => {
                console.log('Villa numbers response status:', response.status);
                return response.json();
            })
            .then(data => {
                console.log('Villa numbers data:', data);
                villaNoSelect.innerHTML = '<option value="">Select Villa No.</option>';
                if (data && data.length > 0) {
                    data.forEach(villaNo => {
                        const option = document.createElement('option');
                        option.value = villaNo;
                        option.textContent = villaNo;
                        if (villaNo == originalVillaNo) {
                            option.selected = true;
                        }
                        villaNoSelect.appendChild(option);
                    });
                } else {
                    console.warn('No villa numbers found for villa area:', villaAreaId);
                }
            })
            .catch(error => console.error('Error fetching villa numbers:', error));
    }

    // Toggle property type fields and fetch data
    propertyTypeSelect.addEventListener('change', function() {
        if (this.value === 'apartment') {
            apartmentFields.style.display = 'block';
            villaFields.style.display = 'none';
            // Clear villa fields
            villaAreaSelect.value = '';
            villaNoSelect.innerHTML = '<option value="">Select Villa No.</option>';
            fetchBuildings();
        } else if (this.value === 'villa') {
            apartmentFields.style.display = 'none';
            villaFields.style.display = 'block';
            // Clear apartment fields
            buildingSelect.value = '';
            floorSelect.innerHTML = '<option value="">Select Floor</option>';
            flatNoSelect.innerHTML = '<option value="">Select Flat No.</option>';
            fetchVillaAreas();
        } else {
            // No property type selected - clear all fields
            apartmentFields.style.display = 'none';
            villaFields.style.display = 'none';
            // Clear all property fields
            buildingSelect.value = '';
            floorSelect.innerHTML = '<option value="">Select Floor</option>';
            flatNoSelect.innerHTML = '<option value="">Select Flat No.</option>';
            villaAreaSelect.value = '';
            villaNoSelect.innerHTML = '<option value="">Select Villa No.</option>';
        }
    });

    // Building change - fetch floors
    buildingSelect.addEventListener('change', fetchFloors);

    // Floor change - fetch flats
    floorSelect.addEventListener('change', fetchFlats);

    // Villa area change - fetch villa numbers
    villaAreaSelect.addEventListener('change', fetchVillaNumbers);

    // Fetch data when society changes
    if (societySelect) {
        societySelect.addEventListener('change', function() {
            if (propertyTypeSelect.value === 'apartment') {
                fetchBuildings();
            } else if (propertyTypeSelect.value === 'villa') {
                fetchVillaAreas();
            }
        });
    }

    // File upload display
    documentInput.addEventListener('change', function() {
        if (this.files && this.files[0]) {
            fileNameDiv.textContent = '✓ ' + this.files[0].name + ' (' + (this.files[0].size / 1024).toFixed(2) + ' KB)';
            fileNameDiv.classList.add('text-green-600');
        }
    });

    // Drag and drop
    const dropZone = document.querySelector('label[for="document_input"]');
    if (dropZone) {
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        ['dragenter', 'dragover'].forEach(eventName => {
            dropZone.addEventListener(eventName, () => {
                dropZone.classList.add('bg-blue-100', 'border-blue-500');
            });
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, () => {
                dropZone.classList.remove('bg-blue-100', 'border-blue-500');
            });
        });

        dropZone.addEventListener('drop', (e) => {
            const dt = e.dataTransfer;
            const files = dt.files;
            documentInput.files = files;
            const event = new Event('change', { bubbles: true });
            documentInput.dispatchEvent(event);
        });
    }

    // Initial load if property type is already selected
    if (propertyTypeSelect.value === 'apartment') {
        apartmentFields.style.display = 'block';
        villaFields.style.display = 'none';
        fetchBuildings();
    } else if (propertyTypeSelect.value === 'villa') {
        apartmentFields.style.display = 'none';
        villaFields.style.display = 'block';
        fetchVillaAreas();
    }

    // Family Members Management
    let familyMemberCount = {{ $owner->family_members && count($owner->family_members) > 0 ? count($owner->family_members) : 1 }};
    const addFamilyMemberBtn = document.getElementById('add-family-member');
    const familyMembersContainer = document.getElementById('family-members-container');
    const profileImageInput = document.getElementById('profile_image_input');
    const profileImageNameDiv = document.getElementById('profile-image-name');

    // Add family member
    addFamilyMemberBtn.addEventListener('click', function() {
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
        familyMembersContainer.appendChild(newRow);
        familyMemberCount++;
        updateRemoveButtons();
    });

    // Remove family member
    function updateRemoveButtons() {
        const removeButtons = document.querySelectorAll('.remove-family-member');
        removeButtons.forEach(btn => {
            if (removeButtons.length > 1) {
                btn.style.display = 'block';
                btn.addEventListener('click', function() {
                    this.closest('.family-member-row').remove();
                    updateRemoveButtons();
                });
            } else {
                btn.style.display = 'none';
            }
        });
    }

    // Profile image upload
    profileImageInput.addEventListener('change', function() {
        if (this.files && this.files[0]) {
            profileImageNameDiv.textContent = '✓ ' + this.files[0].name + ' (' + (this.files[0].size / 1024).toFixed(2) + ' KB)';
            profileImageNameDiv.classList.add('text-green-600');
        }
    });

    // Drag and drop for profile image
    const profileImageDropZone = document.querySelector('label[for="profile_image_input"]');
    if (profileImageDropZone) {
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            profileImageDropZone.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        ['dragenter', 'dragover'].forEach(eventName => {
            profileImageDropZone.addEventListener(eventName, () => {
                profileImageDropZone.classList.add('bg-blue-100', 'border-blue-500');
            });
        });

        ['dragleave', 'drop'].forEach(eventName => {
            profileImageDropZone.addEventListener(eventName, () => {
                profileImageDropZone.classList.remove('bg-blue-100', 'border-blue-500');
            });
        });

        profileImageDropZone.addEventListener('drop', (e) => {
            const dt = e.dataTransfer;
            const files = dt.files;
            profileImageInput.files = files;
            const event = new Event('change', { bubbles: true });
            profileImageInput.dispatchEvent(event);
        });
    }

    // Initialize remove buttons
    updateRemoveButtons();
});
</script>
@endpush
