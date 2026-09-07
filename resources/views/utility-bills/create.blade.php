@extends('layouts.app')

@section('title', 'Add Utility Bill')
@section('page-title', 'Add Utility Bill')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="flex items-center mb-6">
        <a href="{{ route('utility-bills.index') }}" 
           class="text-gray-600 hover:text-gray-900 mr-4 transition-colors">
            <i class="fas fa-arrow-left text-xl"></i>
        </a>
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Add Utility Bill</h1>
            <p class="text-gray-600 mt-1">Add a new utility bill to your society</p>
        </div>
    </div>

    <div class="max-w-2xl mx-auto">
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <form action="{{ route('utility-bills.store') }}" method="POST" enctype="multipart/form-data" class="p-6 space-y-6">
                @csrf

                <!-- Apartment Number -->
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
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('flat_id') border-red-500 @enderror"
                                required>
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
                @else
                    <div>
                        <label for="flat_id" class="block text-sm font-medium text-gray-700 mb-2">
                            Apartment Number <span class="text-red-500">*</span>
                        </label>
                        <select name="flat_id" id="flat_id" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('flat_id') border-red-500 @enderror"
                                required>
                            <option value="">Select Apartment Number</option>
                            @foreach($flats ?? [] as $flat)
                                <option value="{{ $flat->id }}" {{ old('flat_id') == $flat->id ? 'selected' : '' }}>
                                    {{ $flat->flat_number }}
                                    @if($flat->building)
                                        - {{ $flat->building->name }}
                                    @elseif($flat->villaArea)
                                        - {{ $flat->villaArea->name }}
                                    @endif
                                </option>
                            @endforeach
                        </select>
                        @error('flat_id')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                @endif

                <!-- Bill Type -->
                <div>
                    <label for="bill_type" class="block text-sm font-medium text-gray-700 mb-2">
                        Bill Type <span class="text-red-500">*</span>
                    </label>
                    <select name="bill_type" id="bill_type" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('bill_type') border-red-500 @enderror"
                            required>
                        <option value="">Select Bill Type</option>
                        @foreach($billTypes as $type)
                            <option value="{{ $type }}" {{ old('bill_type') == $type ? 'selected' : '' }}>
                                {{ $type }}
                            </option>
                        @endforeach
                    </select>
                    @error('bill_type')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Bill Amount -->
                <div>
                    <label for="bill_amount" class="block text-sm font-medium text-gray-700 mb-2">
                        Bill Amount <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <span class="text-gray-500 sm:text-sm">₹</span>
                        </div>
                        <input type="number" name="bill_amount" id="bill_amount" step="0.01" min="0"
                               value="{{ old('bill_amount') }}"
                               class="w-full pl-8 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('bill_amount') border-red-500 @enderror"
                               placeholder="0.00" required>
                    </div>
                    @error('bill_amount')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Bill Date -->
                <div>
                    <label for="bill_date" class="block text-sm font-medium text-gray-700 mb-2">
                        Bill Date <span class="text-red-500">*</span>
                    </label>
                    <input type="date" name="bill_date" id="bill_date"
                           value="{{ old('bill_date', date('Y-m-d')) }}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('bill_date') border-red-500 @enderror"
                           required>
                    @error('bill_date')
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

                <!-- Upload Bill (Optional) -->
                <div>
                    <label for="bill_file" class="block text-sm font-medium text-gray-700 mb-2">
                        Upload Bill (Optional)
                    </label>
                    <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center cursor-pointer hover:border-blue-500 transition-colors"
                         onclick="document.getElementById('bill_file').click()">
                        <i class="fas fa-cloud-upload-alt text-gray-400 text-3xl mb-2"></i>
                        <p class="text-gray-600">Click to upload or drag and drop</p>
                        <p class="text-sm text-gray-500">PDF, JPG, PNG up to 5MB</p>
                    </div>
                    <input type="file" name="bill_file" id="bill_file" accept=".pdf,.jpg,.jpeg,.png" class="hidden"
                           onchange="updateFilePreview(this)">
                    <div id="filePreview" class="mt-3"></div>
                    @error('bill_file')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Status -->
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-2">
                        Status <span class="text-red-500">*</span>
                    </label>
                    <select name="status" id="status" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('status') border-red-500 @enderror"
                            required>
                        <option value="unpaid" {{ old('status') == 'unpaid' ? 'selected' : '' }}>Unpaid</option>
                        <option value="partial" {{ old('status') == 'partial' ? 'selected' : '' }}>Partial</option>
                        <option value="paid" {{ old('status') == 'paid' ? 'selected' : '' }}>Paid</option>
                    </select>
                    @error('status')
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
                    <a href="{{ route('utility-bills.index') }}" 
                       class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">
                        Cancel
                    </a>
                    <button type="submit" 
                            class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition-colors">
                        Add Bill
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
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

function clearFile() {
    document.getElementById('bill_file').value = '';
    document.getElementById('filePreview').innerHTML = '';
}
</script>
@endsection
