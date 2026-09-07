@extends('layouts.app')

@section('title', 'My Rent')
@section('page-title', 'My Rent')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">My Rent</h1>
            <p class="text-gray-600 mt-1">View and manage your rent payments</p>
        </div>
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

    <!-- Property Info -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">
            @if($resident->flat->property_type === 'villa')
                Villa Information
            @else
                Apartment Information
            @endif
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <p class="text-sm text-gray-500">
                    @if($resident->flat->property_type === 'villa')
                        Villa No.
                    @else
                        Apartment Number
                    @endif
                </p>
                <p class="font-medium text-gray-900">
                    @if($resident->flat->property_type === 'villa')
                        @if($resident->flat->villa_name && $resident->flat->flat_number)
                            {{ $resident->flat->villa_name }} ({{ $resident->flat->flat_number }})
                        @else
                            {{ $resident->flat->villa_name ?: $resident->flat->flat_number ?: 'N/A' }}
                        @endif
                    @else
                        {{ $resident->flat->flat_number ?? 'N/A' }}
                    @endif
                </p>
            </div>
            <div>
                <p class="text-sm text-gray-500">
                    @if($resident->flat->property_type === 'villa')
                        Villa Area
                    @else
                        Building
                    @endif
                </p>
                <p class="font-medium text-gray-900">
                    @if($resident->flat->property_type === 'villa')
                        {{ $resident->flat->villaArea->name ?? 'N/A' }}
                    @else
                        {{ $resident->flat->building->name ?? 'N/A' }}
                    @endif
                </p>
            </div>
            <div>
                <p class="text-sm text-gray-500">Tenant Name</p>
                <p class="font-medium text-gray-900">{{ auth()->user()->name }}</p>
            </div>
        </div>
    </div>

    <!-- Rent Statistics -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
        <div class="bg-white overflow-hidden shadow-md rounded-lg hover:shadow-lg transition-shadow">
            <div class="p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-rupee-sign text-blue-600 text-xl"></i>
                        </div>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Total Rent</dt>
                            <dd class="text-2xl font-bold text-gray-900">₹{{ number_format($totalRent, 2) }}</dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white overflow-hidden shadow-md rounded-lg hover:shadow-lg transition-shadow">
            <div class="p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-check-circle text-green-600 text-xl"></i>
                        </div>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Total Paid</dt>
                            <dd class="text-2xl font-bold text-gray-900">₹{{ number_format($totalPaid, 2) }}</dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white overflow-hidden shadow-md rounded-lg hover:shadow-lg transition-shadow">
            <div class="p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                        </div>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Pending Amount</dt>
                            <dd class="text-2xl font-bold text-gray-900">₹{{ number_format($pendingAmount, 2) }}</dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white overflow-hidden shadow-md rounded-lg hover:shadow-lg transition-shadow">
            <div class="p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-clock text-orange-600 text-xl"></i>
                        </div>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Unpaid Bills</dt>
                            <dd class="text-2xl font-bold text-gray-900">{{ $unpaidBills }}</dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Rent Records Table -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-800">Rent History</h2>
        </div>
        
        @if($rentRecords->count() > 0)
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rent Period</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                @if($resident->flat->property_type === 'villa')
                                    Villa
                                @else
                                    Apartment
                                @endif
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rent Amount</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment Date</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($rentRecords as $bill)
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">
                                        {{ \Carbon\Carbon::parse($bill->bill_date)->format('M Y') }}
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        Due: {{ \Carbon\Carbon::parse($bill->due_date)->format('M d, Y') }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">
                                        @if($bill->flat->property_type === 'villa')
                                            @if($bill->flat->villa_name && $bill->flat->flat_number)
                                                {{ $bill->flat->villa_name }} ({{ $bill->flat->flat_number }})
                                            @else
                                                {{ $bill->flat->villa_name ?: $bill->flat->flat_number ?: 'N/A' }}
                                            @endif
                                        @else
                                            {{ $bill->flat->flat_number ?? 'N/A' }}
                                        @endif
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        @if($bill->flat->property_type === 'villa')
                                            {{ $bill->flat->villaArea->name ?? 'N/A' }}
                                        @else
                                            {{ $bill->flat->building->name ?? 'N/A' }}
                                        @endif
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-semibold text-gray-900">₹{{ number_format($bill->amount, 2) }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($bill->status == 'paid')
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                            Paid
                                        </span>
                                    @elseif($bill->status == 'partial')
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                            Partial
                                        </span>
                                    @else
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                            Unpaid
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        @if($bill->status == 'paid' && $bill->paid_date)
                                            {{ \Carbon\Carbon::parse($bill->paid_date)->format('M d, Y') }}
                                        @else
                                            -
                                        @endif
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex justify-end space-x-2">
                                        @if($bill->status != 'paid')
                                            <button onclick="openPaymentModal({{ $bill->id }}, '{{ $bill->month }} {{ $bill->year }}', {{ $bill->balance_amount }})" 
                                                    class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded text-xs transition-colors">
                                                Pay Now
                                            </button>
                                        @endif
                                        @if($bill->status == 'paid' || $bill->payments->count() > 0)
                                            <a href="{{ route('tenant.rent.receipt', $bill->id) }}" 
                                               class="text-green-600 hover:text-green-900 transition-colors"
                                               title="View Rent Receipt"
                                               target="_blank">
                                                <i class="fas fa-download"></i>
                                            </a>
                                        @else
                                            <button onclick="alert('Receipt available only for paid bills')" 
                                                    class="text-gray-400 cursor-not-allowed"
                                                    title="View Rent Receipt"
                                                    disabled>
                                                <i class="fas fa-download"></i>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <!-- Empty State -->
            <div class="text-center py-12">
                <div class="inline-flex items-center justify-center w-16 h-16 bg-gray-100 rounded-full mb-4">
                    <i class="fas fa-money-bill-wave text-gray-400 text-2xl"></i>
                </div>
                <h3 class="text-lg font-medium text-gray-900 mb-2">No rent records found</h3>
                <p class="text-gray-500 mb-6">Your rent records will appear here once they are generated by the administrator.</p>
            </div>
        @endif
    </div>
</div>

<!-- Payment Modal -->
<div id="paymentModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium text-gray-900" id="modalTitle">Add Payment Detail</h3>
                <button onclick="closePaymentModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form id="paymentForm" enctype="multipart/form-data">
                @csrf
                <input type="hidden" id="rentId" name="rent_id">
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Rent Period</label>
                    <p id="rentPeriod" class="text-sm text-gray-600 bg-gray-50 p-2 rounded"></p>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Outstanding Amount</label>
                    <p id="outstandingAmount" class="text-sm text-gray-600 bg-gray-50 p-2 rounded font-semibold"></p>
                </div>
                
                <div class="mb-4">
                    <label for="payment_date" class="block text-sm font-medium text-gray-700 mb-2">
                        Payment Date <span class="text-red-500">*</span>
                    </label>
                    <input type="date" 
                           id="payment_date" 
                           name="payment_date" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                           required>
                </div>
                
                <div class="mb-4">
                    <label for="payment_amount" class="block text-sm font-medium text-gray-700 mb-2">
                        Payment Amount <span class="text-red-500">*</span>
                    </label>
                    <input type="number" 
                           id="payment_amount" 
                           name="payment_amount" 
                           step="0.01"
                           min="0.01"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                           required>
                </div>
                
                <div class="mb-4">
                    <label for="payment_method" class="block text-sm font-medium text-gray-700 mb-2">
                        Payment Method <span class="text-red-500">*</span>
                    </label>
                    <select id="payment_method" 
                            name="payment_method" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            required>
                        <option value="">Select Payment Method</option>
                        <option value="cash">Cash</option>
                        <option value="cheque">Cheque</option>
                        <option value="online">Online Transfer</option>
                        <option value="upi">UPI</option>
                        <option value="card">Card</option>
                    </select>
                </div>
                
                <div class="mb-4">
                    <label for="payment_proof" class="block text-sm font-medium text-gray-700 mb-2">
                        Upload Payment Proof (Optional)
                    </label>
                    <input type="file" 
                           id="payment_proof" 
                           name="payment_proof" 
                           accept=".jpg,.jpeg,.png,.pdf"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <p class="text-xs text-gray-500 mt-1">Accepted formats: JPG, PNG, PDF (Max: 2MB)</p>
                </div>
                
                <div class="mb-6">
                    <label for="notes" class="block text-sm font-medium text-gray-700 mb-2">
                        Notes (Optional)
                    </label>
                    <textarea id="notes" 
                              name="notes" 
                              rows="3"
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                              placeholder="Additional payment details..."></textarea>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" 
                            onclick="closePaymentModal()" 
                            class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 transition-colors">
                        Cancel
                    </button>
                    <button type="submit" 
                            id="savePaymentBtn"
                            class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">
                        <i class="fas fa-save mr-2"></i>Save Payment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openPaymentModal(rentId, rentPeriod, outstandingAmount) {
    document.getElementById('rentId').value = rentId;
    document.getElementById('rentPeriod').textContent = rentPeriod;
    document.getElementById('outstandingAmount').textContent = '₹' + parseFloat(outstandingAmount).toLocaleString('en-IN', {minimumFractionDigits: 2});
    document.getElementById('payment_amount').value = outstandingAmount;
    document.getElementById('payment_date').value = new Date().toISOString().split('T')[0];
    document.getElementById('paymentModal').classList.remove('hidden');
}

function closePaymentModal() {
    document.getElementById('paymentModal').classList.add('hidden');
    document.getElementById('paymentForm').reset();
}

// Handle form submission
document.getElementById('paymentForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const saveBtn = document.getElementById('savePaymentBtn');
    const originalText = saveBtn.innerHTML;
    
    // Show loading state
    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processing...';
    saveBtn.disabled = true;
    
    const formData = new FormData(this);
    
    fetch('{{ route("tenant.rent.payment") }}', {
        method: 'POST',
        body: formData,
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Show success message
            showNotification('Payment submitted successfully!', 'success');
            closePaymentModal();
            // Reload page to show updated data
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            showNotification(data.message || 'Failed to submit payment. Please try again.', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('An error occurred. Please try again.', 'error');
    })
    .finally(() => {
        // Reset button state
        saveBtn.innerHTML = originalText;
        saveBtn.disabled = false;
    });
});

function showNotification(message, type) {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `fixed top-4 right-4 p-4 rounded-md shadow-lg z-50 ${
        type === 'success' ? 'bg-green-500 text-white' : 'bg-red-500 text-white'
    }`;
    notification.innerHTML = `
        <div class="flex items-center">
            <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'} mr-2"></i>
            <span>${message}</span>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    // Remove notification after 3 seconds
    setTimeout(() => {
        notification.remove();
    }, 3000);
}

// Close modal when clicking outside
document.getElementById('paymentModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closePaymentModal();
    }
});
</script>
@endsection