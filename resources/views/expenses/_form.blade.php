{{--
    Shared by create and edit. $expense is null on create.

    Society/common expenses only. The page already operates inside the
    current society, so no society, building, flat, villa, owner or tenant
    is asked for - and the server sets scope itself.
--}}
<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
        <label for="expense_category_id" class="block text-sm font-medium text-gray-700 mb-1">Category <span class="text-red-500">*</span></label>
        <select name="expense_category_id" id="expense_category_id" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
            <option value="">Select category</option>
            @foreach($categories as $category)
                <option value="{{ $category->id }}" {{ (string) old('expense_category_id', $expense->expense_category_id ?? '') === (string) $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
            @endforeach
        </select>
        @error('expense_category_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        <p class="text-xs text-gray-500 mt-1"><a href="{{ route('expenses.categories') }}" class="text-purple-600 hover:underline">Manage categories</a></p>
    </div>
    <div>
        <label for="amount" class="block text-sm font-medium text-gray-700 mb-1">Amount (₹) <span class="text-red-500">*</span></label>
        <input type="number" step="0.01" min="0.01" name="amount" id="amount" required value="{{ old('amount', $expense->amount ?? '') }}"
               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500" placeholder="0.00">
        @error('amount')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
    </div>
</div>

<div>
    <label for="title" class="block text-sm font-medium text-gray-700 mb-1">What was paid for <span class="text-red-500">*</span></label>
    <input type="text" name="title" id="title" required value="{{ old('title', $expense->title ?? '') }}" maxlength="255"
           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500" placeholder="e.g., Security agency - September invoice">
    @error('title')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <div>
        <label for="expense_date" class="block text-sm font-medium text-gray-700 mb-1">Expense date <span class="text-red-500">*</span></label>
        <input type="date" name="expense_date" id="expense_date" required
               value="{{ old('expense_date', isset($expense) && $expense ? $expense->expense_date?->toDateString() : now()->toDateString()) }}"
               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
        @error('expense_date')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="payment_method" class="block text-sm font-medium text-gray-700 mb-1">Payment method <span class="text-red-500">*</span></label>
        @php $method = old('payment_method', $expense->payment_method ?? 'online'); @endphp
        <select name="payment_method" id="payment_method" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
            @foreach(['online' => 'Online / Bank transfer', 'upi' => 'UPI', 'cash' => 'Cash', 'cheque' => 'Cheque', 'card' => 'Card'] as $value => $label)
                <option value="{{ $value }}" {{ $method === $value ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="bill_number" class="block text-sm font-medium text-gray-700 mb-1">Invoice / reference no.</label>
        <input type="text" name="bill_number" id="bill_number" value="{{ old('bill_number', $expense->bill_number ?? '') }}" maxlength="100"
               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500" placeholder="Optional">
    </div>
</div>

<div>
    <label for="vendor_name" class="block text-sm font-medium text-gray-700 mb-1">Paid to / vendor</label>
    <input type="text" name="vendor_name" id="vendor_name" value="{{ old('vendor_name', $expense->vendor_name ?? '') }}" maxlength="255"
           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500" placeholder="e.g., ABC Security Agency, Electricity Provider, Garden Contractor">
</div>

<div>
    <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
    <textarea name="description" id="description" rows="2" maxlength="2000"
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500" placeholder="Optional notes">{{ old('description', $expense->description ?? '') }}</textarea>
</div>

<div>
    <label for="receipt" class="block text-sm font-medium text-gray-700 mb-1">Receipt / attachment</label>
    <input type="file" name="receipt" id="receipt" accept=".pdf,.jpg,.jpeg,.png"
           class="w-full text-sm text-gray-700 file:mr-3 file:px-3 file:py-1.5 file:border-0 file:rounded file:bg-purple-50 file:text-purple-700">
    <p class="text-xs text-gray-500 mt-1">PDF, JPG or PNG, up to 5 MB.
        @if(!empty($expense?->receipt))
            Current: <a href="{{ $expense->receipt_url }}" target="_blank" class="text-purple-600 hover:underline">view receipt</a> (uploading a new file replaces it).
        @endif
    </p>
    @error('receipt')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
</div>
