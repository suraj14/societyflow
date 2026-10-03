@extends('layouts.app')

@section('title', 'Record New Payment')
@section('page-title', 'Record New Payment')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="flex items-center mb-6">
        <a href="{{ route('payments.index') }}" 
           class="text-gray-600 hover:text-gray-900 mr-4 transition-colors">
            <i class="fas fa-arrow-left text-xl"></i>
        </a>
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Record New Payment</h1>
            <p class="text-gray-600 mt-1">Record a new payment transaction</p>
        </div>
    </div>

    <div class="max-w-2xl mx-auto">
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <form action="{{ route('payments.store') }}" method="POST" enctype="multipart/form-data" class="p-6 space-y-6" id="payment-form">
                @csrf
                @include('partials.idempotency')

                <!-- Property Type -->
                @if(!$propertyType)
                    {{-- Admin/unknown case: the reusable component itself hides the
                         select and shows a read-only line when the society only
                         supports one type - the same "ask only what the system
                         doesn't know" rule the resident branch below already follows. --}}
                    @include('partials.property-type-field', [
                        'sfOnChange' => 'handlePropertyTypeChange()',
                    ])
                @else
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Property Type</label>
                        <div class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-50 text-gray-700">
                            {{ $propertyType === 'mixed' ? 'Apartment & Villa' : ucfirst($propertyType) }}
                        </div>
                        @if($propertyType !== 'mixed')
                            {{-- 'mixed' is a display label, not a PropertyType. Submitting it
                                 fails the in:apartment,villa rule and the owner cannot correct a
                                 hidden field. The chosen unit below determines the real type. --}}
                            <input type="hidden" name="property_type" value="{{ $propertyType }}">
                        @endif
                    </div>
                @endif

                <!-- Pre-populated Properties for Owners -->
                @if($propertyType && $properties->count() > 0)
                    <div>
                        <label for="flat_id" class="block text-sm font-medium text-gray-700 mb-2">
                            @if($propertyType === 'villa')
                                Villa <span class="text-red-500">*</span>
                            @elseif($propertyType === 'apartment')
                                Apartment <span class="text-red-500">*</span>
                            @else
                                Property <span class="text-red-500">*</span>
                            @endif
                        </label>
                        <select name="flat_id" id="flat_id"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('flat_id') border-red-500 @enderror"
                                required onchange="loadOutstandingBills(this.value)">
                            @if($propertyType === 'villa')
                                <option value="">Select Villa</option>
                            @elseif($propertyType === 'apartment')
                                <option value="">Select Apartment</option>
                            @else
                                <option value="">Select Property</option>
                            @endif
                            @foreach($properties as $property)
                                <option value="{{ $property['id'] }}" {{ old('flat_id') == $property['id'] ? 'selected' : '' }}>
                                    {{ $property['display_name'] }}
                                </option>
                            @endforeach
                        </select>
                        @error('flat_id')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                @endif

                <!-- Apartment Selection (Hidden by default or when pre-populated) -->
                <div id="apartment-selection" style="display: none;" class="space-y-6">
                    <!-- Building/Tower -->
                    <div>
                        <label for="building_id" class="block text-sm font-medium text-gray-700 mb-2">
                            Select Tower <span class="text-red-500">*</span>
                        </label>
                        <select name="building_id" id="building_id" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                onchange="loadFloors()">
                            <option value="">Select Tower</option>
                        </select>
                        @error('building_id')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Floor -->
                    <div>
                        <label for="floor" class="block text-sm font-medium text-gray-700 mb-2">
                            Select Floor <span class="text-red-500">*</span>
                        </label>
                        <select name="floor" id="floor" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                onchange="loadFlats()">
                            <option value="">Select Floor</option>
                        </select>
                        @error('floor')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Flat Number -->
                    <div>
                        <label for="apartment_flat_id" class="block text-sm font-medium text-gray-700 mb-2">
                            Flat Number <span class="text-red-500">*</span>
                        </label>
                        <select name="flat_id" id="apartment_flat_id"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                onchange="loadOutstandingBills(this.value)">
                            <option value="">Select Flat Number</option>
                        </select>
                        @error('flat_id')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <!-- Villa Selection (Hidden by default or when pre-populated) -->
                <div id="villa-selection" style="display: none;" class="space-y-6">
                    <!-- Villa Area -->
                    <div>
                        <label for="villa_area_id" class="block text-sm font-medium text-gray-700 mb-2">
                            Villa Area <span class="text-red-500">*</span>
                        </label>
                        <select name="villa_area_id" id="villa_area_id" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                onchange="loadVillas()">
                            <option value="">Select Villa Area</option>
                        </select>
                        @error('villa_area_id')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Villa Number -->
                    <div>
                        <label for="villa_flat_id" class="block text-sm font-medium text-gray-700 mb-2">
                            Villa Number <span class="text-red-500">*</span>
                        </label>
                        <select name="flat_id" id="villa_flat_id"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                onchange="loadOutstandingBills(this.value)">
                            <option value="">Select Villa Number</option>
                        </select>
                        @error('flat_id')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <!-- Select Bill: once a property is known, its own unpaid/partial
                     bills are the source of truth for bill type, amount and due
                     date - the admin picks the bill instead of retyping its
                     details. Optional: an ad-hoc payment not tied to a
                     generated bill still falls back to manual entry below. -->
                <div id="bill-select-wrapper" style="display: none;">
                    <label for="maintenance_bill_id" class="block text-sm font-medium text-gray-700 mb-2">
                        Select Bill
                    </label>
                    <select id="maintenance_bill_id" name="maintenance_bill_id"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            onchange="applySelectedBill()">
                        <option value="">Enter details manually</option>
                    </select>
                    <p class="text-sm text-gray-500 mt-1">Selecting a bill fills in its type, amount and due date below.</p>
                </div>

                <!-- Bill Type -->
                <div>
                    <label for="bill_type" class="block text-sm font-medium text-gray-700 mb-2">
                        Bill Type <span class="text-red-500">*</span>
                    </label>
                    <select name="bill_type" id="bill_type" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('bill_type') border-red-500 @enderror"
                            required>
                        <option value="">Select Bill Type</option>
                        <option value="Maintenance" {{ old('bill_type') == 'Maintenance' ? 'selected' : '' }}>Maintenance</option>
                        <option value="Water" {{ old('bill_type') == 'Water' ? 'selected' : '' }}>Water</option>
                        <option value="Electricity" {{ old('bill_type') == 'Electricity' ? 'selected' : '' }}>Electricity</option>
                        <option value="Gas" {{ old('bill_type') == 'Gas' ? 'selected' : '' }}>Gas</option>
                        <option value="Internet" {{ old('bill_type') == 'Internet' ? 'selected' : '' }}>Internet</option>
                        <option value="Cable TV" {{ old('bill_type') == 'Cable TV' ? 'selected' : '' }}>Cable TV</option>
                        <option value="Sewage" {{ old('bill_type') == 'Sewage' ? 'selected' : '' }}>Sewage</option>
                        <option value="Parking" {{ old('bill_type') == 'Parking' ? 'selected' : '' }}>Parking</option>
                        <option value="Other" {{ old('bill_type') == 'Other' ? 'selected' : '' }}>Other</option>
                    </select>
                    @error('bill_type')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Amount -->
                <div>
                    <label for="amount" class="block text-sm font-medium text-gray-700 mb-2">
                        Amount <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <span class="text-gray-500 sm:text-sm">₹</span>
                        </div>
                        <input type="number" name="amount" id="amount" step="0.01" min="0"
                               value="{{ old('amount') }}"
                               class="w-full pl-8 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('amount') border-red-500 @enderror"
                               placeholder="0.00" required>
                    </div>
                    @error('amount')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Payment Method -->
                <div>
                    <label for="payment_method" class="block text-sm font-medium text-gray-700 mb-2">
                        Payment Method <span class="text-red-500">*</span>
                    </label>
                    <select name="payment_method" id="payment_method" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('payment_method') border-red-500 @enderror"
                            required>
                        <option value="">Select payment method</option>
                        <option value="cash" {{ old('payment_method') == 'cash' ? 'selected' : '' }}>Cash</option>
                        <option value="online" {{ old('payment_method') == 'online' ? 'selected' : '' }}>Online Transfer</option>
                        <option value="upi" {{ old('payment_method') == 'upi' ? 'selected' : '' }}>UPI</option>
                        <option value="cheque" {{ old('payment_method') == 'cheque' ? 'selected' : '' }}>Cheque</option>
                    </select>
                    @error('payment_method')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Payment Date -->
                <div>
                    <label for="payment_date" class="block text-sm font-medium text-gray-700 mb-2">
                        Payment Date <span class="text-red-500">*</span>
                    </label>
                    <input type="date" name="payment_date" id="payment_date"
                           value="{{ old('payment_date', date('Y-m-d')) }}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('payment_date') border-red-500 @enderror"
                           required>
                    @error('payment_date')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Bill Due Date -->
                <div>
                    <label for="due_date" class="block text-sm font-medium text-gray-700 mb-2">
                        Bill Due Date <span class="text-red-500">*</span>
                    </label>
                    <input type="date" name="due_date" id="due_date"
                           value="{{ old('due_date') }}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('due_date') border-red-500 @enderror"
                           required>
                    @error('due_date')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Upload Receipt (Optional) -->
                <div>
                    <label for="receipt_file" class="block text-sm font-medium text-gray-700 mb-2">
                        Upload Receipt (Optional)
                    </label>
                    <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center cursor-pointer hover:border-blue-500 transition-colors"
                         onclick="document.getElementById('receipt_file').click()">
                        <i class="fas fa-cloud-upload-alt text-gray-400 text-3xl mb-2"></i>
                        <p class="text-gray-600">Click to upload or drag and drop</p>
                        <p class="text-sm text-gray-500">PDF, JPG, PNG up to 5MB</p>
                    </div>
                    <input type="file" name="receipt_file" id="receipt_file" accept=".pdf,.jpg,.jpeg,.png" class="hidden"
                           onchange="updateFilePreview(this)">
                    <div id="filePreview" class="mt-3"></div>
                    @error('receipt_file')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Notes -->
                <div>
                    <label for="notes" class="block text-sm font-medium text-gray-700 mb-2">
                        Notes
                    </label>
                    <textarea name="notes" id="notes" rows="3"
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('notes') border-red-500 @enderror"
                              placeholder="Add any additional notes (optional)">{{ old('notes') }}</textarea>
                    @error('notes')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Form Actions -->
                <div class="flex justify-end space-x-4 pt-6 border-t border-gray-200">
                    <a href="{{ route('payments.index') }}" 
                       class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">
                        Cancel
                    </a>
                    <button type="submit" id="submit-btn"
                            class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition-colors">
                        Record Payment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
// PAYMENT FORM - Direct submission handler (bypasses global handlers)
(function() {
    'use strict';
    
    // Wait for DOM to be ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPaymentForm);
    } else {
        initPaymentForm();
    }
    
    function initPaymentForm() {
        console.log('🔧 Payment Form: Initializing direct handler...');
        
        const form = document.getElementById('payment-form');
        const submitBtn = document.getElementById('submit-btn');
        
        if (!form || !submitBtn) {
            console.error('❌ Payment form elements not found');
            return;
        }
        
        // Remove any existing event listeners
        const newForm = form.cloneNode(true);
        form.parentNode.replaceChild(newForm, form);
        
        // Get fresh references
        const freshForm = document.getElementById('payment-form');
        const freshSubmitBtn = document.getElementById('submit-btn');
        
        console.log('✅ Payment form elements found and refreshed');
        
        // Add our direct event listener
        freshForm.addEventListener('submit', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            console.log('📝 Payment form submission started');
            
            // Disable submit button immediately
            freshSubmitBtn.disabled = true;
            freshSubmitBtn.innerHTML = '<span>Processing...</span><i class="fas fa-spinner fa-spin ml-2"></i>';
            
            // Get property type - check for hidden input first (for owners), then dropdown (for admin)
            let propertyType = null;
            const propertyTypeInput = freshForm.querySelector('input[name="property_type"]');
            const propertyTypeSelect = document.getElementById('property_type');
            
            if (propertyTypeInput) {
                propertyType = propertyTypeInput.value;
            } else if (propertyTypeSelect) {
                propertyType = propertyTypeSelect.value;
            }
            
            console.log('Property type:', propertyType);
            
            // Get selected property ID based on property type
            let selectedPropertyId = null;
            
            // Check if this is a pre-populated property (for owners/tenants with hidden property_type)
            if (propertyTypeInput) {
                // Pre-populated case: get from the main flat_id select
                const flatIdSelect = freshForm.querySelector('select[name="flat_id"]');
                selectedPropertyId = flatIdSelect ? flatIdSelect.value : null;
                console.log('Pre-populated property ID:', selectedPropertyId);
            } else if (propertyType === 'apartment') {
                // For apartment: get flat_id from apartment cascade
                const apartmentFlatSelect = document.getElementById('apartment_flat_id');
                selectedPropertyId = apartmentFlatSelect ? apartmentFlatSelect.value : null;
                console.log('Apartment property ID:', selectedPropertyId);
            } else if (propertyType === 'villa') {
                // For villa: get flat_id from villa cascade
                const villaFlatSelect = document.getElementById('villa_flat_id');
                selectedPropertyId = villaFlatSelect ? villaFlatSelect.value : null;
                console.log('Villa property ID:', selectedPropertyId);
            }
            
            console.log('Selected property ID:', selectedPropertyId);
            
            // Validate
            if (!selectedPropertyId) {
                alert('Please select a property');
                freshSubmitBtn.disabled = false;
                freshSubmitBtn.innerHTML = 'Record Payment';
                return;
            }
            
            // Set flat_id in form
            let flatIdInput = freshForm.querySelector('input[name="flat_id"]');
            if (!flatIdInput) {
                flatIdInput = document.createElement('input');
                flatIdInput.type = 'hidden';
                flatIdInput.name = 'flat_id';
                freshForm.appendChild(flatIdInput);
            }
            flatIdInput.value = selectedPropertyId;
            console.log('✅ Flat ID set:', selectedPropertyId);
            
            // Submit directly
            console.log('🚀 Submitting form to:', freshForm.action);
            freshForm.submit();
        });
        
        console.log('✅ Payment form handler attached');
    }
})();

// Handle property type change
function handlePropertyTypeChange() {
    const propertyTypeSelect = document.getElementById('property_type');
    if (!propertyTypeSelect) return;
    
    const propertyType = propertyTypeSelect.value;
    const apartmentSelection = document.getElementById('apartment-selection');
    const villaSelection = document.getElementById('villa-selection');
    
    // Hide both sections first
    apartmentSelection.style.display = 'none';
    villaSelection.style.display = 'none';
    
    // Reset all dropdowns
    resetDropdowns();
    
    if (propertyType === 'apartment') {
        apartmentSelection.style.display = 'block';
        loadBuildings();
    } else if (propertyType === 'villa') {
        villaSelection.style.display = 'block';
        loadVillaAreas();
    }
}

// Firing on page load for a pre-selected single-type society is handled by
// partials/property-type-field.blade.php itself (its own sfOnChange trigger),
// so this file doesn't need a second DOMContentLoaded listener for it.

// Bill-first: once a property is known, fetch its unpaid/partial bills and
// let picking one auto-fill type/amount/due-date instead of retyping them.
let outstandingBills = [];

async function loadOutstandingBills(flatId) {
    const wrapper = document.getElementById('bill-select-wrapper');
    const billSelect = document.getElementById('maintenance_bill_id');
    outstandingBills = [];
    billSelect.innerHTML = '<option value="">Enter details manually</option>';

    if (!flatId) {
        wrapper.style.display = 'none';
        return;
    }

    try {
        const response = await fetch(`{{ route('api.payments.outstanding-bills') }}?flat_id=${flatId}`, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            }
        });
        if (!response.ok) return;

        outstandingBills = await response.json();
        if (!Array.isArray(outstandingBills) || outstandingBills.length === 0) {
            wrapper.style.display = 'none';
            return;
        }

        outstandingBills.forEach(bill => {
            const option = document.createElement('option');
            option.value = bill.id;
            option.textContent = `${bill.label} - ₹${bill.outstanding_amount}`;
            billSelect.appendChild(option);
        });
        wrapper.style.display = 'block';

        // Only one outstanding bill: there is no real choice, so pick it and
        // fill the form the same as if the admin had picked it themselves.
        if (outstandingBills.length === 1) {
            billSelect.value = outstandingBills[0].id;
            applySelectedBill();
        }
    } catch (error) {
        wrapper.style.display = 'none';
    }
}

function applySelectedBill() {
    const billSelect = document.getElementById('maintenance_bill_id');
    const bill = outstandingBills.find(b => String(b.id) === billSelect.value);

    if (!bill) return;

    const billTypeSelect = document.getElementById('bill_type');
    // The bill's own type may not be one of the dropdown's preset options
    // (a custom bill type entered elsewhere) - add it rather than silently
    // leaving the field unselected.
    if (![...billTypeSelect.options].some(o => o.value === bill.bill_type)) {
        const option = document.createElement('option');
        option.value = bill.bill_type;
        option.textContent = bill.bill_type;
        billTypeSelect.appendChild(option);
    }
    billTypeSelect.value = bill.bill_type;

    document.getElementById('amount').value = bill.outstanding_amount;
    if (bill.due_date) {
        document.getElementById('due_date').value = bill.due_date;
    }
}

// A resident/owner with exactly one property never needs to pick it - the
// server already listed only their own unit(s) as options above.
document.addEventListener('DOMContentLoaded', function () {
    const flatSelect = document.getElementById('flat_id');
    if (!flatSelect) return;

    if (!flatSelect.value) {
        const options = Array.from(flatSelect.options).filter(o => o.value !== '');
        if (options.length === 1) {
            flatSelect.value = options[0].value;
        }
    }
    if (flatSelect.value) {
        loadOutstandingBills(flatSelect.value);
    }
});

// Reset all cascading dropdowns
function resetDropdowns() {
    // Reset apartment dropdowns
    document.getElementById('building_id').innerHTML = '<option value="">Select Tower</option>';
    document.getElementById('floor').innerHTML = '<option value="">Select Floor</option>';
    document.getElementById('apartment_flat_id').innerHTML = '<option value="">Select Flat Number</option>';
    
    // Reset villa dropdowns
    document.getElementById('villa_area_id').innerHTML = '<option value="">Select Villa Area</option>';
    document.getElementById('villa_flat_id').innerHTML = '<option value="">Select Villa Number</option>';
}

// Load buildings/towers
function loadBuildings() {
    fetch('/api/payments/buildings')
        .then(response => {
            if (!response.ok) {
                throw new Error('Failed to load buildings');
            }
            return response.json();
        })
        .then(data => {
            const buildingSelect = document.getElementById('building_id');
            buildingSelect.innerHTML = '<option value="">Select Tower</option>';
            
            if (Array.isArray(data)) {
                data.forEach(building => {
                    const option = document.createElement('option');
                    option.value = building.id;
                    option.textContent = building.name;
                    buildingSelect.appendChild(option);
                });
            }
        })
        .catch(error => {
            console.error('Error loading buildings:', error);
            alert('Failed to load buildings. Please refresh the page.');
        });
}

// Load floors based on selected building
function loadFloors() {
    const buildingId = document.getElementById('building_id').value;
    const floorSelect = document.getElementById('floor');
    const flatSelect = document.getElementById('apartment_flat_id');
    
    // Reset dependent dropdowns
    floorSelect.innerHTML = '<option value="">Select Floor</option>';
    flatSelect.innerHTML = '<option value="">Select Flat Number</option>';
    
    if (!buildingId) return;
    
    fetch(`/api/payments/floors?building_id=${buildingId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Failed to load floors');
            }
            return response.json();
        })
        .then(data => {
            if (Array.isArray(data)) {
                data.forEach(floor => {
                    const option = document.createElement('option');
                    option.value = floor;
                    option.textContent = `Floor ${floor}`;
                    floorSelect.appendChild(option);
                });
            }
        })
        .catch(error => {
            console.error('Error loading floors:', error);
            alert('Failed to load floors. Please try again.');
        });
}

// Load flats based on selected building and floor
function loadFlats() {
    const buildingId = document.getElementById('building_id').value;
    const floor = document.getElementById('floor').value;
    const flatSelect = document.getElementById('apartment_flat_id');
    
    // Reset flat dropdown
    flatSelect.innerHTML = '<option value="">Select Flat Number</option>';
    
    if (!buildingId || !floor) return;
    
    fetch(`/api/payments/flats?building_id=${buildingId}&floor=${floor}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Failed to load flats');
            }
            return response.json();
        })
        .then(data => {
            if (Array.isArray(data)) {
                data.forEach(flat => {
                    const option = document.createElement('option');
                    option.value = flat.id;
                    option.textContent = flat.flat_number;
                    flatSelect.appendChild(option);
                });
            }
        })
        .catch(error => {
            console.error('Error loading flats:', error);
            alert('Failed to load flats. Please try again.');
        });
}

// Load villa areas
function loadVillaAreas() {
    fetch('/api/payments/villa-areas')
        .then(response => {
            if (!response.ok) {
                throw new Error('Failed to load villa areas');
            }
            return response.json();
        })
        .then(data => {
            const villaAreaSelect = document.getElementById('villa_area_id');
            villaAreaSelect.innerHTML = '<option value="">Select Villa Area</option>';
            
            if (Array.isArray(data)) {
                data.forEach(area => {
                    const option = document.createElement('option');
                    option.value = area.id;
                    option.textContent = area.name;
                    villaAreaSelect.appendChild(option);
                });
            }
        })
        .catch(error => {
            console.error('Error loading villa areas:', error);
            alert('Failed to load villa areas. Please refresh the page.');
        });
}

// Load villas based on selected villa area
function loadVillas() {
    const villaAreaId = document.getElementById('villa_area_id').value;
    const villaSelect = document.getElementById('villa_flat_id');
    
    // Reset villa dropdown
    villaSelect.innerHTML = '<option value="">Select Villa Number</option>';
    
    if (!villaAreaId) return;
    
    fetch(`/api/payments/villas?villa_area_id=${villaAreaId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Failed to load villas');
            }
            return response.json();
        })
        .then(data => {
            console.log('Villas loaded:', data);
            if (Array.isArray(data)) {
                data.forEach(villa => {
                    const option = document.createElement('option');
                    option.value = villa.id;
                    option.textContent = villa.villa_number;
                    villaSelect.appendChild(option);
                });
            }
        })
        .catch(error => {
            console.error('Error loading villas:', error);
            alert('Failed to load villas. Please try again.');
        });
}

// File preview function
function updateFilePreview(input) {
    const preview = document.getElementById('filePreview');
    preview.innerHTML = '';
    
    if (input.files && input.files[0]) {
        const file = input.files[0];
        const container = document.createElement('div');
        container.className = 'flex items-center space-x-3 mt-3 p-3 bg-blue-50 rounded-lg border border-blue-200';
        
        let icon = '<i class="fas fa-file-pdf text-red-600 text-2xl"></i>';
        if (file.type.includes('image')) {
            icon = '<i class="fas fa-image text-blue-600 text-2xl"></i>';
        }
        
        container.innerHTML = `
            <div>${icon}</div>
            <div class="flex-1">
                <p class="font-medium text-gray-900">${file.name}</p>
                <p class="text-sm text-gray-500">${(file.size / 1024).toFixed(2)} KB</p>
            </div>
            <button type="button" onclick="clearFile()" class="text-red-600 hover:text-red-800">
                <i class="fas fa-trash"></i>
            </button>
        `;
        preview.appendChild(container);
    }
}

// Clear file function
function clearFile() {
    document.getElementById('receipt_file').value = '';
    document.getElementById('filePreview').innerHTML = '';
}
</script>
@endpush
@endsection