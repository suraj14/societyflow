@extends('layouts.app')

@section('title', 'Edit Tenant')
@section('page-title', 'Edit Tenant')

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
                <h2 class="text-lg font-semibold text-gray-800">Edit Tenant</h2>
            </div>

            @include('components.form-errors')

            <form action="{{ route('admin.tenants.update', $tenant) }}" method="POST" class="p-6" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                <div class="space-y-6">
                    <!-- Basic Information -->
                    <div class="border-b pb-6">
                        <h3 class="text-md font-semibold text-gray-800 mb-4">Basic Information</h3>
                        
                        <div class="grid md:grid-cols-2 gap-6">
                            <!-- Name -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Full Name *</label>
                                <input type="text" name="name" value="{{ old('name', $tenant->name) }}" required
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                       placeholder="e.g., John Doe">
                                @error('name')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Email -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Email Address *</label>
                                <input type="email" name="email" value="{{ old('email', $tenant->email) }}" required
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                       placeholder="e.g., tenant@example.com">
                                @error('email')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Phone -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Phone Number *</label>
                                <input type="text" name="phone" value="{{ old('phone', $tenant->phone) }}" required
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
                                    <option value="active" {{ old('status', $tenant->status) === 'active' ? 'selected' : '' }}>Active</option>
                                    <option value="inactive" {{ old('status', $tenant->status) === 'inactive' ? 'selected' : '' }}>Inactive</option>
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
                                <label class="block text-sm font-medium text-gray-700 mb-1">Password (Optional)</label>
                                <input type="password" name="password"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                       placeholder="Leave blank to keep current password">
                                <p class="text-sm text-gray-500 mt-1">Only enter a password if you want to change it.</p>
                                @error('password')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Confirm Password -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Confirm Password</label>
                                <input type="password" name="password_confirmation"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                       placeholder="Confirm new password">
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
                            @if($tenant->user && $tenant->user->avatar)
                                <div class="mb-4">
                                    <p class="text-sm text-gray-600 mb-2">Current Profile Image:</p>
                                    <img src="{{ asset('storage/' . $tenant->user->avatar) }}" alt="{{ $tenant->name }}" class="w-24 h-24 rounded-lg object-cover">
                                </div>
                            @endif
                            
                            <label class="block text-sm font-medium text-gray-700 mb-1">Upload New Profile Picture (JPG, PNG)</label>
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
                            @if($tenant->family_members && is_array($tenant->family_members) && count($tenant->family_members) > 0)
                                @foreach($tenant->family_members as $index => $member)
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

                    <!-- Rental Information -->
                    <div class="border-b pb-6">
                        <h3 class="text-md font-semibold text-gray-800 mb-4">Rental Information</h3>
                        
                        <div class="grid md:grid-cols-2 gap-6">
                            <!-- Contract Start Date -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Contract Start Date *</label>
                                <input type="date" name="contract_start_date" value="{{ old('contract_start_date', $tenant->contract_start_date?->format('Y-m-d')) }}" required
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                @error('contract_start_date')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Contract End Date -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Contract End Date *</label>
                                <input type="date" name="contract_end_date" value="{{ old('contract_end_date', $tenant->contract_end_date?->format('Y-m-d')) }}" required
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                @error('contract_end_date')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Rent Amount -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Rent Amount *</label>
                                <input type="number" name="rent_amount" value="{{ old('rent_amount', $tenant->monthly_rent) }}" required step="0.01"
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
                                    <option value="Monthly" {{ old('rent_billing_cycle', $tenant->rent_billing_cycle) === 'Monthly' ? 'selected' : '' }}>Monthly</option>
                                    <option value="Quarterly" {{ old('rent_billing_cycle', $tenant->rent_billing_cycle) === 'Quarterly' ? 'selected' : '' }}>Quarterly</option>
                                    <option value="Annually" {{ old('rent_billing_cycle', $tenant->rent_billing_cycle) === 'Annually' ? 'selected' : '' }}>Annually</option>
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
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Unit Assignment</label>
                            <select name="flat_id"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">No unit assigned</option>
                                @foreach($availableUnits as $unit)
                                    <option value="{{ $unit->id }}" 
                                            {{ old('flat_id', $tenant?->flat_id) == $unit->id ? 'selected' : '' }}>
                                        {{ $unit->flat_number ?? $unit->villa_name }} - 
                                        {{ $unit->building->name ?? $unit->villaArea->name ?? 'N/A' }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="text-sm text-gray-500 mt-1">Select a unit to assign or leave blank to remove assignment.</p>
                            @error('flat_id')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
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
                                    <option value="Aadhar" {{ old('id_type', $tenant->id_type) === 'Aadhar' ? 'selected' : '' }}>Aadhar</option>
                                    <option value="PAN" {{ old('id_type', $tenant->id_type) === 'PAN' ? 'selected' : '' }}>PAN</option>
                                    <option value="Passport" {{ old('id_type', $tenant->id_type) === 'Passport' ? 'selected' : '' }}>Passport</option>
                                    <option value="Driving License" {{ old('id_type', $tenant->id_type) === 'Driving License' ? 'selected' : '' }}>Driving License</option>
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
                        
                        @if($tenant->document_path)
                            <div class="mb-4">
                                <p class="text-sm text-gray-600 mb-2">Current Document:</p>
                                <a href="{{ asset('storage/' . $tenant->document_path) }}" target="_blank" class="text-blue-600 hover:text-blue-800 text-sm">
                                    <i class="fas fa-file mr-1"></i> View Document
                                </a>
                            </div>
                        @endif
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Upload New Document (PDF, DOC, DOCX, JPG, PNG)</label>
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
                                  placeholder="Additional notes about the tenant">{{ old('notes', $tenant->notes) }}</textarea>
                        @error('notes')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="mt-6 flex items-center justify-end space-x-3 pt-6 border-t border-gray-200">
                    <a href="{{ route('admin.tenants.index') }}" class="px-4 py-2 text-gray-700 hover:text-gray-900">Cancel</a>
                    <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                        Update Tenant
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

    // Family Members Management
    let familyMemberCount = {{ $tenant->family_members && is_array($tenant->family_members) ? count($tenant->family_members) : 1 }};

    const addFamilyMemberBtn = document.getElementById('add-family-member');
    if (addFamilyMemberBtn) {
        addFamilyMemberBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            const container = document.getElementById('family-members-container');
            if (!container) return;
            
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
            if (removeBtn) {
                if (rows.length > 1) {
                    removeBtn.style.display = 'block';
                } else {
                    removeBtn.style.display = 'none';
                }
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
});
</script>
@endpush
