@extends('layouts.app')

@section('title', 'Utility Bills')
@section('page-title', 'Utility Bills')

@section('content')
<div class="min-h-screen bg-gray-50">
    <!-- Header -->
    <div class="bg-white border-b border-gray-200 px-6 py-4">
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">Utility Bills</h1>
                <p class="text-sm text-gray-600 mt-1">Manage utility bills for your society</p>
            </div>
            <div class="flex gap-3">
                <button onclick="exportBills()" class="bg-white border border-gray-300 text-gray-700 px-4 py-2 rounded-md font-medium hover:bg-gray-50 transition-colors inline-flex items-center">
                    <i class="fas fa-download mr-2"></i>
                    Export
                </button>
                <button onclick="openAddBillDrawer()" 
                        class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md font-medium transition-colors inline-flex items-center">
                    <i class="fas fa-plus mr-2"></i>
                    Add Bill
                </button>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="px-6 py-6">
        @if(session('success'))
            <div class="bg-green-50 border border-green-200 text-green-700 p-4 mb-6 rounded-md" role="alert">
                <div class="flex items-center">
                    <i class="fas fa-check-circle mr-3"></i>
                    <p class="font-medium">{{ session('success') }}</p>
                </div>
            </div>
        @endif

        @if(session('error'))
            <div class="bg-red-50 border border-red-200 text-red-700 p-4 mb-6 rounded-md" role="alert">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle mr-3"></i>
                    <p class="font-medium">{{ session('error') }}</p>
                </div>
            </div>
        @endif

        <!-- Search and Filters -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 mb-6">
            <form method="GET" action="{{ route('utility-bills.index') }}" class="space-y-4">
                <div class="flex flex-col lg:flex-row gap-4">
                    <!-- Search -->
                    <div class="flex-1">
                        <div class="relative">
                            <input type="text" 
                                   name="search" 
                                   value="{{ request('search') }}"
                                   placeholder="Search by Apartment Number, Bill Type..." 
                                   class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                        </div>
                    </div>
                    
                    <!-- Filters Button -->
                    <button type="button" 
                            onclick="toggleFilters()" 
                            class="px-4 py-2 border border-gray-300 rounded-md hover:bg-gray-50 transition-colors inline-flex items-center">
                        <i class="fas fa-filter mr-2"></i>
                        Filters
                    </button>
                </div>

                <!-- Advanced Filters (Hidden by default) -->
                <div id="advanced-filters" class="hidden border-t pt-4">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <!-- Apartment Filter -->
                        <div>
                            <label for="flat_id" class="block text-sm font-medium text-gray-700 mb-1">
                                Apartment Number
                            </label>
                            <select name="flat_id" id="flat_id" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">All Apartments</option>
                                @foreach($flats as $flat)
                                    <option value="{{ $flat->id }}" {{ request('flat_id') == $flat->id ? 'selected' : '' }}>
                                        {{ $flat->flat_number }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Bill Type Filter -->
                        <div>
                            <label for="bill_type" class="block text-sm font-medium text-gray-700 mb-1">
                                Bill Type
                            </label>
                            <select name="bill_type" id="bill_type" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">All Types</option>
                                @foreach(\App\Models\UtilityBill::getBillTypes() as $key => $type)
                                    <option value="{{ $key }}" {{ request('bill_type') == $key ? 'selected' : '' }}>
                                        {{ $type }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Status Filter -->
                        <div>
                            <label for="status" class="block text-sm font-medium text-gray-700 mb-1">
                                Status
                            </label>
                            <select name="status" id="status" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">All Status</option>
                                <option value="unpaid" {{ request('status') == 'unpaid' ? 'selected' : '' }}>Unpaid</option>
                                <option value="partial" {{ request('status') == 'partial' ? 'selected' : '' }}>Partial</option>
                                <option value="paid" {{ request('status') == 'paid' ? 'selected' : '' }}>Paid</option>
                            </select>
                        </div>

                        <!-- Apply Filters Button -->
                        <div class="flex items-end">
                            <button type="submit" class="w-full px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md font-medium transition-colors">
                                Apply Filters
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- Bills Table -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
            @if($bills->count() > 0)
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Apartment Number
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Bill Type
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Bill Date
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Bill Amount
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Status
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Bill Payment Date
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($bills as $bill)
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">{{ $bill->flat->flat_number ?? 'N/A' }}</div>
                                        @if($bill->flat && $bill->flat->building)
                                            <div class="text-xs text-gray-500">{{ $bill->flat->building->name }}</div>
                                        @elseif($bill->flat && $bill->flat->villaArea)
                                            <div class="text-xs text-gray-500">{{ $bill->flat->villaArea->name }}</div>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">{{ $bill->bill_type }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">{{ $bill->bill_date->format('d M Y') }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-semibold text-gray-900">₹{{ number_format($bill->bill_amount, 2) }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if($bill->status == 'paid')
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                <i class="fas fa-check-circle mr-1"></i>
                                                Paid
                                            </span>
                                        @elseif($bill->status == 'partial')
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                <i class="fas fa-clock mr-1"></i>
                                                Partial
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                <i class="fas fa-exclamation-circle mr-1"></i>
                                                Unpaid
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">
                                            {{ $bill->payment_date ? $bill->payment_date->format('d M Y') : '--' }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right">
                                        <div class="relative inline-block text-left">
                                            <button type="button" 
                                                    onclick="toggleActionMenu({{ $bill->id }})"
                                                    class="inline-flex items-center px-3 py-1 border border-gray-300 rounded-md bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                                Actions
                                                <i class="fas fa-chevron-down ml-1 text-xs"></i>
                                            </button>
                                            
                                            <div id="action-menu-{{ $bill->id }}" class="hidden absolute right-0 z-10 mt-2 w-48 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5">
                                                <div class="py-1">
                                                    <button onclick="viewBill({{ $bill->id }})" 
                                                            class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                        <i class="fas fa-eye mr-2"></i>View
                                                    </button>
                                                    <button onclick="editBill({{ $bill->id }})" 
                                                            class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                        <i class="fas fa-edit mr-2"></i>Update
                                                    </button>
                                                    <button onclick="deleteBill({{ $bill->id }})" 
                                                            class="block w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-gray-100">
                                                        <i class="fas fa-trash mr-2"></i>Delete
                                                    </button>
                                                    @if($bill->status != 'paid')
                                                        <button onclick="openPaymentModal({{ $bill->id }}, '{{ $bill->flat->flat_number }}', '{{ $bill->bill_type }}', {{ $bill->bill_amount }})"
                                                                class="block w-full text-left px-4 py-2 text-sm text-green-600 hover:bg-gray-100">
                                                            <i class="fas fa-credit-card mr-2"></i>Pay
                                                        </button>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
                    <div class="flex-1 flex justify-between sm:hidden">
                        {{ $bills->simplePaginate() }}
                    </div>
                    <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                        <div>
                            <p class="text-sm text-gray-700">
                                Showing {{ $bills->firstItem() ?? 0 }} to {{ $bills->lastItem() ?? 0 }} of {{ $bills->total() }} results
                            </p>
                        </div>
                        <div>
                            {{ $bills->links() }}
                        </div>
                    </div>
                </div>
            @else
                <!-- Empty State -->
                <div class="text-center py-12">
                    <div class="inline-flex items-center justify-center w-16 h-16 bg-gray-100 rounded-full mb-4">
                        <i class="fas fa-file-invoice-dollar text-gray-400 text-2xl"></i>
                    </div>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">No utility bills found</h3>
                    <p class="text-gray-500 mb-6">Get started by adding your first utility bill.</p>
                    <button onclick="openAddBillDrawer()" 
                            class="inline-flex items-center bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-md font-medium transition-colors">
                        <i class="fas fa-plus mr-2"></i>
                        Add Utility Bill
                    </button>
                </div>
            @endif
        </div>
    </div>
</div>

<!-- Right Side Drawer for Add/Edit Bill -->
<div id="bill-drawer" class="fixed inset-0 overflow-hidden z-50 hidden">
    <div class="absolute inset-0 overflow-hidden">
        <!-- Backdrop -->
        <div class="absolute inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="closeBillDrawer()"></div>
        
        <!-- Drawer -->
        <section class="absolute inset-y-0 right-0 pl-10 max-w-full flex">
            <div class="w-screen max-w-md">
                <div class="h-full flex flex-col bg-white shadow-xl">
                    <!-- Header -->
                    <div class="px-4 py-6 bg-gray-50 sm:px-6">
                        <div class="flex items-center justify-between">
                            <h2 id="drawer-title" class="text-lg font-medium text-gray-900">Add Utility Bill</h2>
                            <button type="button" onclick="closeBillDrawer()" class="text-gray-400 hover:text-gray-500">
                                <i class="fas fa-times text-xl"></i>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Form -->
                    <div class="flex-1 overflow-y-auto">
                        <form id="bill-form" method="POST" enctype="multipart/form-data" class="px-4 py-6 sm:px-6 space-y-6">
                            @csrf
                            <div id="method-field"></div>

                            <!-- Property Type Selection -->
                            <div>
                                <label for="drawer_property_type" class="block text-sm font-medium text-gray-700 mb-2">
                                    Property Type <span class="text-red-500">*</span>
                                </label>
                                <select name="property_type" id="drawer_property_type" 
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                        required onchange="handlePropertyTypeChange()">
                                    <option value="">Select Property Type</option>
                                    <option value="apartment">Apartment</option>
                                    <option value="villa">Villa</option>
                                </select>
                                <div class="text-red-500 text-sm mt-1 hidden" id="drawer_property_type_error"></div>
                            </div>

                            <!-- Apartment Selection (Hidden by default) -->
                            <div id="apartment-selection" class="hidden space-y-4">
                                <!-- Building/Tower -->
                                <div>
                                    <label for="drawer_building_id" class="block text-sm font-medium text-gray-700 mb-2">
                                        Select Tower <span class="text-red-500">*</span>
                                    </label>
                                    <select name="building_id" id="drawer_building_id" 
                                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                            onchange="loadFloors()">
                                        <option value="">Select Tower</option>
                                    </select>
                                    <div class="text-red-500 text-sm mt-1 hidden" id="drawer_building_id_error"></div>
                                </div>

                                <!-- Floor -->
                                <div>
                                    <label for="drawer_floor" class="block text-sm font-medium text-gray-700 mb-2">
                                        Select Floor <span class="text-red-500">*</span>
                                    </label>
                                    <select name="floor" id="drawer_floor" 
                                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                            onchange="loadFlats()">
                                        <option value="">Select Floor</option>
                                    </select>
                                    <div class="text-red-500 text-sm mt-1 hidden" id="drawer_floor_error"></div>
                                </div>

                                <!-- Flat Number -->
                                <div>
                                    <label for="drawer_flat_id" class="block text-sm font-medium text-gray-700 mb-2">
                                        Flat Number <span class="text-red-500">*</span>
                                    </label>
                                    <select name="flat_id" id="drawer_flat_id" 
                                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                        <option value="">Select Flat Number</option>
                                    </select>
                                    <div class="text-red-500 text-sm mt-1 hidden" id="drawer_flat_id_error"></div>
                                </div>
                            </div>

                            <!-- Villa Selection (Hidden by default) -->
                            <div id="villa-selection" class="hidden space-y-4">
                                <!-- Villa Area -->
                                <div>
                                    <label for="drawer_villa_area_id" class="block text-sm font-medium text-gray-700 mb-2">
                                        Villa Area <span class="text-red-500">*</span>
                                    </label>
                                    <select name="villa_area_id" id="drawer_villa_area_id" 
                                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                            onchange="loadVillas()">
                                        <option value="">Select Villa Area</option>
                                    </select>
                                    <div class="text-red-500 text-sm mt-1 hidden" id="drawer_villa_area_id_error"></div>
                                </div>

                                <!-- Villa Number -->
                                <div>
                                    <label for="drawer_villa_id" class="block text-sm font-medium text-gray-700 mb-2">
                                        Villa Number <span class="text-red-500">*</span>
                                    </label>
                                    <select name="villa_id" id="drawer_villa_id" 
                                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                        <option value="">Select Villa Number</option>
                                    </select>
                                    <div class="text-red-500 text-sm mt-1 hidden" id="drawer_villa_id_error"></div>
                                </div>
                            </div>

                            <!-- Bill Type -->
                            <div>
                                <label for="drawer_bill_type" class="block text-sm font-medium text-gray-700 mb-2">
                                    Bill Type <span class="text-red-500">*</span>
                                </label>
                                <select name="bill_type" id="drawer_bill_type" 
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                        required>
                                    <option value="">Select Bill Type</option>
                                    <option value="Water">Water</option>
                                    <option value="Electricity">Electricity</option>
                                    <option value="Gas">Gas</option>
                                    <option value="Internet">Internet</option>
                                    <option value="Cable TV">Cable TV</option>
                                    <option value="Sewage">Sewage</option>
                                    <option value="Other">Other</option>
                                </select>
                                <div class="text-red-500 text-sm mt-1 hidden" id="drawer_bill_type_error"></div>
                            </div>

                            <!-- Bill Amount -->
                            <div>
                                <label for="drawer_bill_amount" class="block text-sm font-medium text-gray-700 mb-2">
                                    Bill Amount <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <span class="text-gray-500 sm:text-sm">₹</span>
                                    </div>
                                    <input type="number" name="bill_amount" id="drawer_bill_amount" step="0.01" min="0"
                                           class="w-full pl-8 pr-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                           placeholder="0.00" required>
                                </div>
                                <div class="text-red-500 text-sm mt-1 hidden" id="drawer_bill_amount_error"></div>
                            </div>

                            <!-- Bill Date -->
                            <div>
                                <label for="drawer_bill_date" class="block text-sm font-medium text-gray-700 mb-2">
                                    Bill Date <span class="text-red-500">*</span>
                                </label>
                                <input type="date" name="bill_date" id="drawer_bill_date"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                       required>
                                <div class="text-red-500 text-sm mt-1 hidden" id="drawer_bill_date_error"></div>
                            </div>

                            <!-- Bill Due Date -->
                            <div>
                                <label for="drawer_due_date" class="block text-sm font-medium text-gray-700 mb-2">
                                    Bill Due Date <span class="text-red-500">*</span>
                                </label>
                                <input type="date" name="due_date" id="drawer_due_date"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                       required>
                                <div class="text-red-500 text-sm mt-1 hidden" id="drawer_due_date_error"></div>
                            </div>

                            <!-- Upload Bill (Optional) -->
                            <div>
                                <label for="drawer_bill_file" class="block text-sm font-medium text-gray-700 mb-2">
                                    Upload Bill (Optional)
                                </label>
                                <div class="border-2 border-dashed border-gray-300 rounded-md p-4 text-center cursor-pointer hover:border-blue-500 transition-colors"
                                     onclick="document.getElementById('drawer_bill_file').click()">
                                    <i class="fas fa-cloud-upload-alt text-gray-400 text-2xl mb-2"></i>
                                    <p class="text-gray-600 text-sm">Click to upload</p>
                                    <p class="text-xs text-gray-500">PDF, JPG, PNG up to 5MB</p>
                                </div>
                                <input type="file" name="bill_file" id="drawer_bill_file" accept=".pdf,.jpg,.jpeg,.png" class="hidden"
                                       onchange="updateDrawerFilePreview(this)">
                                <div id="drawer-file-preview" class="mt-3"></div>
                                <div class="text-red-500 text-sm mt-1 hidden" id="drawer_bill_file_error"></div>
                            </div>

                            <!-- Status -->
                            <div>
                                <label for="drawer_status" class="block text-sm font-medium text-gray-700 mb-2">
                                    Status <span class="text-red-500">*</span>
                                </label>
                                <select name="status" id="drawer_status" 
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                        required>
                                    <option value="unpaid">Unpaid</option>
                                    <option value="partial">Partial</option>
                                    <option value="paid">Paid</option>
                                </select>
                                <div class="text-red-500 text-sm mt-1 hidden" id="drawer_status_error"></div>
                            </div>

                            <!-- Notes -->
                            <div>
                                <label for="drawer_notes" class="block text-sm font-medium text-gray-700 mb-2">
                                    Notes
                                </label>
                                <textarea name="notes" id="drawer_notes" rows="3"
                                          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                          placeholder="Add any additional notes (optional)"></textarea>
                                <div class="text-red-500 text-sm mt-1 hidden" id="drawer_notes_error"></div>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Footer -->
                    <div class="px-4 py-4 bg-gray-50 sm:px-6">
                        <div class="flex justify-end space-x-3">
                            <button type="button" onclick="closeBillDrawer()"
                                    class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 transition-colors">
                                Cancel
                            </button>
                            <button type="button" onclick="submitBillForm()" id="submit-btn"
                                    class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md font-medium transition-colors">
                                <span id="submit-text">Save Bill</span>
                                <i class="fas fa-spinner fa-spin ml-2 hidden" id="submit-spinner"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>

@push('scripts')
<script>
// Handle property type change
function handlePropertyTypeChange() {
    const propertyType = document.getElementById('drawer_property_type').value;
    const apartmentSelection = document.getElementById('apartment-selection');
    const villaSelection = document.getElementById('villa-selection');
    
    // Hide both sections first
    apartmentSelection.classList.add('hidden');
    villaSelection.classList.add('hidden');
    
    // Reset all dropdowns
    resetDropdowns();
    
    if (propertyType === 'apartment') {
        apartmentSelection.classList.remove('hidden');
        loadBuildings();
    } else if (propertyType === 'villa') {
        villaSelection.classList.remove('hidden');
        loadVillaAreas();
    }
}

// Reset all cascading dropdowns
function resetDropdowns() {
    // Reset apartment dropdowns
    document.getElementById('drawer_building_id').innerHTML = '<option value="">Select Tower</option>';
    document.getElementById('drawer_floor').innerHTML = '<option value="">Select Floor</option>';
    document.getElementById('drawer_flat_id').innerHTML = '<option value="">Select Flat Number</option>';
    
    // Reset villa dropdowns
    document.getElementById('drawer_villa_area_id').innerHTML = '<option value="">Select Villa Area</option>';
    document.getElementById('drawer_villa_id').innerHTML = '<option value="">Select Villa Number</option>';
}

// Load buildings/towers
function loadBuildings() {
    fetch('/api/buildings')
        .then(response => response.json())
        .then(data => {
            const buildingSelect = document.getElementById('drawer_building_id');
            buildingSelect.innerHTML = '<option value="">Select Tower</option>';
            
            data.forEach(building => {
                const option = document.createElement('option');
                option.value = building.id;
                option.textContent = building.name;
                buildingSelect.appendChild(option);
            });
        })
        .catch(error => {
            console.error('Error loading buildings:', error);
            showToast('error', 'Failed to load buildings');
        });
}

// Load floors based on selected building
function loadFloors() {
    const buildingId = document.getElementById('drawer_building_id').value;
    const floorSelect = document.getElementById('drawer_floor');
    const flatSelect = document.getElementById('drawer_flat_id');
    
    // Reset dependent dropdowns
    floorSelect.innerHTML = '<option value="">Select Floor</option>';
    flatSelect.innerHTML = '<option value="">Select Flat Number</option>';
    
    if (!buildingId) return;
    
    fetch(`/api/floors?building_id=${buildingId}`)
        .then(response => response.json())
        .then(data => {
            data.forEach(floor => {
                const option = document.createElement('option');
                option.value = floor;
                option.textContent = `Floor ${floor}`;
                floorSelect.appendChild(option);
            });
        })
        .catch(error => {
            console.error('Error loading floors:', error);
            showToast('error', 'Failed to load floors');
        });
}

// Load flats based on selected building and floor
function loadFlats() {
    const buildingId = document.getElementById('drawer_building_id').value;
    const floor = document.getElementById('drawer_floor').value;
    const flatSelect = document.getElementById('drawer_flat_id');
    
    // Reset flat dropdown
    flatSelect.innerHTML = '<option value="">Select Flat Number</option>';
    
    if (!buildingId || !floor) return;
    
    fetch(`/api/flats?building_id=${buildingId}&floor=${floor}`)
        .then(response => response.json())
        .then(data => {
            data.forEach(flat => {
                const option = document.createElement('option');
                option.value = flat.id;
                option.textContent = flat.flat_number;
                flatSelect.appendChild(option);
            });
        })
        .catch(error => {
            console.error('Error loading flats:', error);
            showToast('error', 'Failed to load flats');
        });
}

// Load villa areas
function loadVillaAreas() {
    fetch('/api/villa-areas')
        .then(response => response.json())
        .then(data => {
            const villaAreaSelect = document.getElementById('drawer_villa_area_id');
            villaAreaSelect.innerHTML = '<option value="">Select Villa Area</option>';
            
            data.forEach(area => {
                const option = document.createElement('option');
                option.value = area.id;
                option.textContent = area.name;
                villaAreaSelect.appendChild(option);
            });
        })
        .catch(error => {
            console.error('Error loading villa areas:', error);
            showToast('error', 'Failed to load villa areas');
        });
}

// Load villas based on selected villa area
function loadVillas() {
    const villaAreaId = document.getElementById('drawer_villa_area_id').value;
    const villaSelect = document.getElementById('drawer_villa_id');
    
    // Reset villa dropdown
    villaSelect.innerHTML = '<option value="">Select Villa Number</option>';
    
    if (!villaAreaId) return;
    
    fetch(`/api/villas?villa_area_id=${villaAreaId}`)
        .then(response => response.json())
        .then(data => {
            data.forEach(villa => {
                const option = document.createElement('option');
                option.value = villa.id;
                option.textContent = villa.villa_number;
                villaSelect.appendChild(option);
            });
        })
        .catch(error => {
            console.error('Error loading villas:', error);
            showToast('error', 'Failed to load villas');
        });
}

// Toggle filters
function toggleFilters() {
    const filters = document.getElementById('advanced-filters');
    filters.classList.toggle('hidden');
}

// Toggle action menu
function toggleActionMenu(billId) {
    // Close all other menus
    document.querySelectorAll('[id^="action-menu-"]').forEach(menu => {
        if (menu.id !== `action-menu-${billId}`) {
            menu.classList.add('hidden');
        }
    });
    
    // Toggle current menu
    const menu = document.getElementById(`action-menu-${billId}`);
    menu.classList.toggle('hidden');
}

// Open Add Bill Drawer
function openAddBillDrawer() {
    isEditMode = false;
    currentBillId = null;
    
    // Reset form
    document.getElementById('bill-form').reset();
    document.getElementById('method-field').innerHTML = '';
    document.getElementById('drawer-title').textContent = 'Add Utility Bill';
    document.getElementById('submit-text').textContent = 'Save Bill';
    
    // Set default date
    document.getElementById('drawer_bill_date').value = new Date().toISOString().split('T')[0];
    
    // Clear file preview
    document.getElementById('drawer-file-preview').innerHTML = '';
    
    // Clear errors
    clearDrawerErrors();
    
    // Set form action
    document.getElementById('bill-form').action = '{{ route("utility-bills.store") }}';
    
    // Show drawer
    document.getElementById('bill-drawer').classList.remove('hidden');
}

// Edit Bill
function editBill(billId) {
    isEditMode = true;
    currentBillId = billId;
    
    // Set form for editing
    document.getElementById('method-field').innerHTML = '@method("PUT")';
    document.getElementById('drawer-title').textContent = 'Edit Utility Bill';
    document.getElementById('submit-text').textContent = 'Update Bill';
    
    // Set form action
    document.getElementById('bill-form').action = `/utility-bills/${billId}`;
    
    // Load bill data (you would fetch this via AJAX in a real app)
    // For now, we'll show the drawer
    document.getElementById('bill-drawer').classList.remove('hidden');
    
    // Close action menu
    document.getElementById(`action-menu-${billId}`).classList.add('hidden');
}

// Close Bill Drawer
function closeBillDrawer() {
    document.getElementById('bill-drawer').classList.add('hidden');
    clearDrawerErrors();
}

// Submit Bill Form
function submitBillForm() {
    const form = document.getElementById('bill-form');
    const submitBtn = document.getElementById('submit-btn');
    const submitText = document.getElementById('submit-text');
    const submitSpinner = document.getElementById('submit-spinner');
    
    // Show loading state
    submitBtn.disabled = true;
    submitText.classList.add('hidden');
    submitSpinner.classList.remove('hidden');
    
    // Clear previous errors
    clearDrawerErrors();
    
    // Determine the flat_id based on property type
    const propertyType = document.getElementById('drawer_property_type').value;
    let flatId = null;
    
    if (propertyType === 'apartment') {
        flatId = document.getElementById('drawer_flat_id').value;
    } else if (propertyType === 'villa') {
        flatId = document.getElementById('drawer_villa_id').value;
    }
    
    // Validate that a flat is selected
    if (!flatId) {
        showToast('error', 'Please select a property');
        submitBtn.disabled = false;
        submitText.classList.remove('hidden');
        submitSpinner.classList.add('hidden');
        return;
    }
    
    // Create FormData
    const formData = new FormData(form);
    formData.set('flat_id', flatId); // Override with the correct flat_id
    
    // Submit form
    fetch(form.action, {
        method: 'POST',
        body: formData,
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Show success message and reload page
            showToast('success', data.message || 'Bill saved successfully!');
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            // Show validation errors
            if (data.errors) {
                showDrawerErrors(data.errors);
            } else {
                showToast('error', data.message || 'An error occurred');
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('error', 'An error occurred while saving the bill');
    })
    .finally(() => {
        // Reset loading state
        submitBtn.disabled = false;
        submitText.classList.remove('hidden');
        submitSpinner.classList.add('hidden');
    });
}

// View Bill
function viewBill(billId) {
    // Redirect to the show page instead of trying to show a modal
    window.location.href = `/utility-bills/${billId}`;
}

// Open Payment Modal
function openPaymentModal(billId, flatNumber, billType, amount) {
    const billInfo = `
        <div class="text-sm">
            <p><strong>Apartment:</strong> ${flatNumber}</p>
            <p><strong>Bill Type:</strong> ${billType}</p>
            <p><strong>Amount:</strong> ₹${parseFloat(amount).toLocaleString('en-IN', {minimumFractionDigits: 2})}</p>
        </div>
    `;
    
    document.getElementById('payment-bill-info').innerHTML = billInfo;
    document.getElementById('payment-form').action = `/utility-bills/${billId}/payment`;
    document.getElementById('payment-modal').classList.remove('hidden');
    
    // Close action menu
    document.getElementById(`action-menu-${billId}`).classList.add('hidden');
}

// Close Payment Modal
function closePaymentModal() {
    document.getElementById('payment-modal').classList.add('hidden');
}

// Print Bill
function printBill(billId) {
    // Implement print functionality
    window.print();
    
    // Close action menu
    document.getElementById(`action-menu-${billId}`).classList.add('hidden');
}

// Delete Bill
function deleteBill(billId) {
    if (confirm('Are you sure you want to delete this bill? This action cannot be undone.')) {
        // Create form and submit
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = `/utility-bills/${billId}`;
        
        const csrfToken = document.createElement('input');
        csrfToken.type = 'hidden';
        csrfToken.name = '_token';
        csrfToken.value = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        
        const methodField = document.createElement('input');
        methodField.type = 'hidden';
        methodField.name = '_method';
        methodField.value = 'DELETE';
        
        form.appendChild(csrfToken);
        form.appendChild(methodField);
        document.body.appendChild(form);
        form.submit();
    }
    
    // Close action menu
    document.getElementById(`action-menu-${billId}`).classList.add('hidden');
}

// Export Bills
function exportBills() {
    // Implement export functionality
    showToast('info', 'Export functionality will be implemented');
}

// File preview for drawer
function updateDrawerFilePreview(input) {
    const preview = document.getElementById('drawer-file-preview');
    preview.innerHTML = '';
    
    if (input.files && input.files[0]) {
        const file = input.files[0];
        const container = document.createElement('div');
        container.className = 'flex items-center space-x-3 mt-3 p-3 bg-blue-50 rounded-md border border-blue-200';
        
        let icon = '<i class="fas fa-file-pdf text-red-600 text-xl"></i>';
        if (file.type.includes('image')) {
            icon = '<i class="fas fa-image text-blue-600 text-xl"></i>';
        }
        
        container.innerHTML = `
            <div>${icon}</div>
            <div class="flex-1">
                <p class="font-medium text-gray-900 text-sm">${file.name}</p>
                <p class="text-xs text-gray-500">${(file.size / 1024).toFixed(2)} KB</p>
            </div>
            <button type="button" onclick="clearDrawerFile()" class="text-red-600 hover:text-red-800">
                <i class="fas fa-trash"></i>
            </button>
        `;
        preview.appendChild(container);
    }
}

// Clear drawer file
function clearDrawerFile() {
    document.getElementById('drawer_bill_file').value = '';
    document.getElementById('drawer-file-preview').innerHTML = '';
}

// Show drawer errors
function showDrawerErrors(errors) {
    Object.keys(errors).forEach(field => {
        const errorElement = document.getElementById(`drawer_${field}_error`);
        if (errorElement) {
            errorElement.textContent = errors[field][0];
            errorElement.classList.remove('hidden');
        }
    });
}

// Clear drawer errors
function clearDrawerErrors() {
    document.querySelectorAll('[id$="_error"]').forEach(element => {
        element.classList.add('hidden');
        element.textContent = '';
    });
}

// Show toast notification
function showToast(type, message) {
    // Create toast element
    const toast = document.createElement('div');
    toast.className = `fixed top-4 right-4 z-50 p-4 rounded-md shadow-lg max-w-sm ${
        type === 'success' ? 'bg-green-50 border border-green-200 text-green-700' :
        type === 'error' ? 'bg-red-50 border border-red-200 text-red-700' :
        'bg-blue-50 border border-blue-200 text-blue-700'
    }`;
    
    toast.innerHTML = `
        <div class="flex items-center">
            <i class="fas ${
                type === 'success' ? 'fa-check-circle' :
                type === 'error' ? 'fa-exclamation-circle' :
                'fa-info-circle'
            } mr-3"></i>
            <p class="font-medium">${message}</p>
        </div>
    `;
    
    document.body.appendChild(toast);
    
    // Remove toast after 5 seconds
    setTimeout(() => {
        toast.remove();
    }, 5000);
}

// Close action menus when clicking outside
document.addEventListener('click', function(event) {
    if (!event.target.closest('[onclick^="toggleActionMenu"]') && !event.target.closest('[id^="action-menu-"]')) {
        document.querySelectorAll('[id^="action-menu-"]').forEach(menu => {
            menu.classList.add('hidden');
        });
    }
});

// Close modals when clicking outside
document.getElementById('payment-modal').addEventListener('click', function(event) {
    if (event.target === this) {
        closePaymentModal();
    }
});

// Handle payment form submission
document.getElementById('payment-form').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    fetch(this.action, {
        method: 'POST',
        body: formData,
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('success', data.message || 'Payment recorded successfully!');
            closePaymentModal();
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            showToast('error', data.message || 'An error occurred');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('error', 'An error occurred while recording payment');
    });
});
</script>
@endpush
@endsection
<!-- Payment Modal -->
<div id="payment-modal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium text-gray-900">Add Payment Detail</h3>
                <button onclick="closePaymentModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div id="payment-bill-info" class="mb-4 p-3 bg-gray-50 rounded-md">
                <!-- Bill info will be populated here -->
            </div>
            
            <form id="payment-form" method="POST">
                @csrf
                @method('PATCH')
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Bill Payment Date <span class="text-red-500">*</span>
                    </label>
                    <input type="date" 
                           name="payment_date" 
                           required
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Upload Payment Proof (Optional)
                    </label>
                    <input type="file" 
                           name="payment_proof" 
                           accept=".pdf,.jpg,.jpeg,.png"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <p class="text-xs text-gray-500 mt-1">Supported formats: PDF, JPG, PNG (Max 5MB)</p>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" 
                            onclick="closePaymentModal()"
                            class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 transition-colors">
                        Close
                    </button>
                    <button type="submit" 
                            class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-md transition-colors">
                        Save
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>