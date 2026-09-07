@extends('layouts.app')

@section('title', 'Create Rent Record')
@section('page-title', 'Create Rent Record')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Create Rent Record</h1>
            <p class="text-gray-600 mt-1">Generate a new rent record for a tenant</p>
        </div>
        <a href="{{ route('admin.rents.index') }}" 
           class="bg-gray-600 hover:bg-gray-700 text-white px-6 py-3 rounded-lg font-medium transition-colors inline-flex items-center">
            <i class="fas fa-arrow-left mr-2"></i>
            Back to Rent Records
        </a>
    </div>

    @if($errors->any())
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-lg" role="alert">
            <div class="flex items-center mb-2">
                <i class="fas fa-exclamation-circle mr-3"></i>
                <p class="font-medium">Please fix the following errors:</p>
            </div>
            <ul class="list-disc list-inside ml-6">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <form id="rent-create-form" action="{{ route('admin.rents.store') }}" method="POST" class="p-6" enctype="multipart/form-data">
            @csrf
            
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Left Column: Unit & Tenant Selection -->
                <div class="space-y-6">
                    <h3 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-2">Unit & Tenant Details</h3>
                    
                    <!-- Property Type -->
                    <div>
                        <label for="property_type" class="block text-sm font-medium text-gray-700 mb-2">
                            Property Type <span class="text-red-500">*</span>
                        </label>
                        <select id="property_type" 
                                name="property_type" 
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                                required>
                            <option value="">Select Property Type</option>
                            <option value="apartment" {{ old('property_type') == 'apartment' ? 'selected' : '' }}>Apartment</option>
                            <option value="villa" {{ old('property_type') == 'villa' ? 'selected' : '' }}>Villa</option>
                        </select>
                    </div>

                    <!-- Select Tenant Unit -->
                    <div>
                        <label for="unit_id" class="block text-sm font-medium text-gray-700 mb-2">
                            Select Tenant Unit <span class="text-red-500">*</span>
                        </label>
                        <select id="unit_id" 
                                name="unit_id" 
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                                required
                                disabled>
                            <option value="">First select property type</option>
                        </select>
                        <p class="text-sm text-gray-500 mt-1">Only units with active tenants are shown</p>
                    </div>

                    <!-- Tenant Name (Auto-populated) -->
                    <div>
                        <label for="tenant_name" class="block text-sm font-medium text-gray-700 mb-2">
                            Tenant Name <span class="text-red-500">*</span>
                        </label>
                        <input type="text" 
                               id="tenant_name" 
                               name="tenant_name" 
                               value="{{ old('tenant_name') }}"
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg bg-gray-50 text-gray-600"
                               placeholder="Will be auto-populated"
                               readonly>
                        <input type="hidden" id="tenant_id" name="tenant_id" value="{{ old('tenant_id') }}">
                    </div>

                    <!-- Status -->
                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700 mb-2">
                            Status <span class="text-red-500">*</span>
                        </label>
                        <select id="status" 
                                name="status" 
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                                required>
                            <option value="pending" {{ old('status', 'pending') == 'pending' ? 'selected' : '' }}>Unpaid</option>
                            <option value="paid" {{ old('status') == 'paid' ? 'selected' : '' }}>Paid</option>
                        </select>
                    </div>
                </div>

                <!-- Right Column: Rent Details -->
                <div class="space-y-6">
                    <h3 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-2">Rent Details</h3>
                    
                    <!-- Rent Amount -->
                    <div>
                        <label for="rent_amount" class="block text-sm font-medium text-gray-700 mb-2">
                            Rent Amount <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500">₹</span>
                            <input type="number" 
                                   id="rent_amount" 
                                   name="rent_amount" 
                                   value="{{ old('rent_amount') }}"
                                   step="0.01"
                                   min="1"
                                   class="w-full pl-8 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                                   placeholder="0.00"
                                   required>
                        </div>
                        <p class="text-sm text-gray-500 mt-1">Will be auto-filled from unit settings</p>
                    </div>

                    <!-- Bill Date -->
                    <div>
                        <label for="bill_date" class="block text-sm font-medium text-gray-700 mb-2">
                            Bill Date <span class="text-red-500">*</span>
                        </label>
                        <input type="date" 
                               id="bill_date" 
                               name="bill_date" 
                               value="{{ old('bill_date', date('Y-m-d')) }}"
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                               required>
                    </div>

                    <!-- Due Date -->
                    <div>
                        <label for="due_date" class="block text-sm font-medium text-gray-700 mb-2">
                            Due Date <span class="text-red-500">*</span>
                        </label>
                        <input type="date" 
                               id="due_date" 
                               name="due_date" 
                               value="{{ old('due_date') }}"
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                               required>
                        <p class="text-sm text-gray-500 mt-1">Auto-calculated based on bill date + {{ $rentDueDays }} days</p>
                    </div>

                    <!-- Quick Date Setup -->
                    <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                        <h4 class="text-sm font-medium text-gray-900 mb-3">Quick Date Setup</h4>
                        <div class="grid grid-cols-2 gap-3">
                            <button type="button" 
                                    onclick="setCurrentMonth()"
                                    class="px-4 py-2 text-sm bg-blue-100 text-blue-700 rounded-lg hover:bg-blue-200 transition-colors">
                                Current Month
                            </button>
                            <button type="button" 
                                    onclick="setNextMonth()"
                                    class="px-4 py-2 text-sm bg-green-100 text-green-700 rounded-lg hover:bg-green-200 transition-colors">
                                Next Month
                            </button>
                        </div>
                    </div>

                    <!-- Description -->
                    <div>
                        <label for="description" class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                        <textarea id="description" 
                                  name="description" 
                                  rows="3"
                                  maxlength="500"
                                  class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                                  placeholder="Optional description (max 500 characters)">{{ old('description', 'Monthly Rent') }}</textarea>
                    </div>

                    <!-- Payment Details Section -->
                    <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                        <h4 class="text-sm font-medium text-gray-900 mb-4 flex items-center">
                            <i class="fas fa-credit-card mr-2 text-blue-600"></i>
                            Payment Details (Optional)
                        </h4>
                        
                        <!-- Payment Date -->
                        <div class="mb-4">
                            <label for="payment_date" class="block text-sm font-medium text-gray-700 mb-2">
                                Payment Date
                            </label>
                            <input type="date" 
                                   id="payment_date" 
                                   name="payment_date" 
                                   value="{{ old('payment_date') }}"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                            <p class="text-xs text-gray-500 mt-1">Leave empty if payment not received yet</p>
                        </div>
                        
                        <!-- Payment Method -->
                        <div class="mb-4">
                            <label for="payment_method" class="block text-sm font-medium text-gray-700 mb-2">
                                Payment Method
                            </label>
                            <select id="payment_method" 
                                    name="payment_method" 
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                                <option value="">Select Payment Method</option>
                                <option value="cash" {{ old('payment_method') == 'cash' ? 'selected' : '' }}>Cash</option>
                                <option value="bank_transfer" {{ old('payment_method') == 'bank_transfer' ? 'selected' : '' }}>Bank Transfer</option>
                                <option value="cheque" {{ old('payment_method') == 'cheque' ? 'selected' : '' }}>Cheque</option>
                                <option value="online" {{ old('payment_method') == 'online' ? 'selected' : '' }}>Online Payment</option>
                                <option value="upi" {{ old('payment_method') == 'upi' ? 'selected' : '' }}>UPI</option>
                            </select>
                        </div>
                        
                        <!-- Upload Payment Proof -->
                        <div class="mb-4">
                            <label for="payment_proof" class="block text-sm font-medium text-gray-700 mb-2">
                                Upload Payment Proof (Optional)
                            </label>
                            <input type="file" 
                                   id="payment_proof" 
                                   name="payment_proof" 
                                   accept="image/*,.pdf"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                            <p class="text-xs text-gray-500 mt-1">Accepted formats: JPG, PNG, PDF (Max: 2MB)</p>
                        </div>
                        
                        <!-- Payment Notes -->
                        <div>
                            <label for="payment_notes" class="block text-sm font-medium text-gray-700 mb-2">
                                Payment Notes
                            </label>
                            <textarea id="payment_notes" 
                                      name="payment_notes" 
                                      rows="2"
                                      maxlength="255"
                                      class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                                      placeholder="Additional payment details...">{{ old('payment_notes') }}</textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Validation Messages -->
            <div id="validation-messages" class="mt-6 hidden">
                <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 rounded-lg">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-triangle mr-3"></i>
                        <span id="validation-text"></span>
                    </div>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="flex justify-end space-x-4 mt-8 pt-6 border-t border-gray-200">
                <a href="{{ route('admin.rents.index') }}" 
                   class="px-6 py-3 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 font-medium transition-colors">
                    Cancel
                </a>
                <button type="submit" 
                        id="submitBtn"
                        class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition-colors inline-flex items-center"
                        disabled>
                    <i class="fas fa-save mr-2"></i>
                    <span id="submitText">Create Rent Record</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Configuration
const RENT_DUE_DAYS = {{ $rentDueDays }};
let currentUnits = [];

// Initialize form
document.addEventListener('DOMContentLoaded', function() {
    initializeForm();
});

function initializeForm() {
    const propertyTypeSelect = document.getElementById('property_type');
    const unitSelect = document.getElementById('unit_id');
    const billDateInput = document.getElementById('bill_date');
    const dueDateInput = document.getElementById('due_date');
    
    // Property type change handler
    propertyTypeSelect.addEventListener('change', handlePropertyTypeChange);
    
    // Unit selection change handler
    unitSelect.addEventListener('change', handleUnitChange);
    
    // Bill date change handler
    billDateInput.addEventListener('change', function() {
        updateDueDate();
        validateRentRecord();
    });
    
    // Status change handler for payment fields
    const statusSelect = document.getElementById('status');
    const paymentDateInput = document.getElementById('payment_date');
    
    statusSelect.addEventListener('change', function() {
        if (this.value === 'paid' && !paymentDateInput.value) {
            // Auto-set payment date to today when status is changed to paid
            paymentDateInput.value = new Date().toISOString().split('T')[0];
        }
    });
    
    // Form submission handler
    document.getElementById('rent-create-form').addEventListener('submit', handleFormSubmit);
    
    // Initialize due date
    updateDueDate();
}

async function handlePropertyTypeChange() {
    const propertyType = document.getElementById('property_type').value;
    const unitSelect = document.getElementById('unit_id');
    
    console.log('Property type changed to:', propertyType);
    
    // Reset dependent fields
    resetForm();
    
    if (!propertyType) {
        unitSelect.innerHTML = '<option value="">First select property type</option>';
        unitSelect.disabled = true;
        return;
    }
    
    // Show loading state
    unitSelect.innerHTML = '<option value="">Loading units...</option>';
    unitSelect.disabled = true;
    
    try {
        console.log('Making API call to:', `/admin/api/rent/units?property_type=${propertyType}`);
        
        const response = await fetch(`/admin/api/rent/units?property_type=${propertyType}`, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            }
        });
        
        console.log('API response status:', response.status);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const units = await response.json();
        console.log('API returned units:', units);
        currentUnits = units;
        
        // Populate dropdown
        unitSelect.innerHTML = '<option value="">Select Tenant Unit</option>';
        
        if (units.length === 0) {
            unitSelect.innerHTML += '<option value="" disabled>No units available on rent</option>';
            console.warn('No units found for property type:', propertyType);
        } else {
            units.forEach(unit => {
                unitSelect.innerHTML += `<option value="${unit.id}" data-tenant-id="${unit.tenant_id}" data-tenant-name="${unit.tenant_name}" data-rent="${unit.monthly_rent}">${unit.label}</option>`;
            });
            unitSelect.disabled = false;
            console.log(`Added ${units.length} units to dropdown`);
        }
        
    } catch (error) {
        console.error('Error loading units:', error);
        unitSelect.innerHTML = '<option value="" disabled>Error loading units</option>';
        showValidationMessage('Failed to load units. Please try again.', 'error');
    }
}

function handleUnitChange() {
    const unitSelect = document.getElementById('unit_id');
    const selectedOption = unitSelect.options[unitSelect.selectedIndex];
    
    if (!selectedOption.value) {
        resetTenantFields();
        return;
    }
    
    // Auto-populate tenant information
    const tenantId = selectedOption.getAttribute('data-tenant-id');
    const tenantName = selectedOption.getAttribute('data-tenant-name');
    const monthlyRent = selectedOption.getAttribute('data-rent');
    
    document.getElementById('tenant_id').value = tenantId || '';
    document.getElementById('tenant_name').value = tenantName || 'Unknown Tenant';
    document.getElementById('rent_amount').value = monthlyRent || '';
    
    // Validate rent record
    validateRentRecord();
    
    // Enable submit button if all required fields are filled
    updateSubmitButton();
}

function resetForm() {
    resetTenantFields();
    hideValidationMessage();
    updateSubmitButton();
}

function resetTenantFields() {
    document.getElementById('tenant_id').value = '';
    document.getElementById('tenant_name').value = '';
    document.getElementById('rent_amount').value = '';
}

function updateDueDate() {
    const billDate = document.getElementById('bill_date').value;
    const dueDateInput = document.getElementById('due_date');
    
    if (billDate) {
        const bill = new Date(billDate);
        const due = new Date(bill);
        due.setDate(due.getDate() + RENT_DUE_DAYS);
        
        dueDateInput.value = due.toISOString().split('T')[0];
    }
}

async function validateRentRecord() {
    const unitId = document.getElementById('unit_id').value;
    const billDate = document.getElementById('bill_date').value;
    
    if (!unitId || !billDate) {
        hideValidationMessage();
        return;
    }
    
    try {
        const response = await fetch('/admin/api/rent/validate', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            },
            body: JSON.stringify({
                unit_id: unitId,
                bill_date: billDate
            })
        });
        
        const result = await response.json();
        
        if (!result.valid) {
            showValidationMessage(result.message, 'warning');
            document.getElementById('submitBtn').disabled = true;
        } else {
            hideValidationMessage();
            updateSubmitButton();
        }
        
    } catch (error) {
        console.error('Error validating rent record:', error);
    }
}

function updateSubmitButton() {
    const submitBtn = document.getElementById('submitBtn');
    const requiredFields = ['property_type', 'unit_id', 'tenant_id', 'rent_amount', 'bill_date', 'due_date'];
    
    const allFilled = requiredFields.every(fieldId => {
        const field = document.getElementById(fieldId);
        return field && field.value.trim() !== '';
    });
    
    submitBtn.disabled = !allFilled;
}

function showValidationMessage(message, type = 'warning') {
    const container = document.getElementById('validation-messages');
    const text = document.getElementById('validation-text');
    
    text.textContent = message;
    container.classList.remove('hidden');
    
    // Scroll to message
    container.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

function hideValidationMessage() {
    document.getElementById('validation-messages').classList.add('hidden');
}

function handleFormSubmit(e) {
    const submitBtn = document.getElementById('submitBtn');
    const submitText = document.getElementById('submitText');
    
    // Show loading state
    submitBtn.disabled = true;
    submitText.textContent = 'Creating...';
    
    // Form will submit normally
}

// Quick date setup functions
function setCurrentMonth() {
    const now = new Date();
    const firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
    
    document.getElementById('bill_date').value = firstDay.toISOString().split('T')[0];
    updateDueDate();
    validateRentRecord();
}

function setNextMonth() {
    const now = new Date();
    const firstDay = new Date(now.getFullYear(), now.getMonth() + 1, 1);
    
    document.getElementById('bill_date').value = firstDay.toISOString().split('T')[0];
    updateDueDate();
    validateRentRecord();
}

// Monitor all required fields for submit button state
document.addEventListener('input', function(e) {
    if (e.target.matches('#property_type, #unit_id, #rent_amount, #bill_date, #due_date')) {
        updateSubmitButton();
    }
});
</script>
@endsection