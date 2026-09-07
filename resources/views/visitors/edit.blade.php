@extends('layouts.app')

@section('title', 'Edit Visitor')
@section('page-title', 'Edit Visitor')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="flex items-center mb-6">
        <a href="{{ route('visitors.index') }}" class="text-gray-600 hover:text-gray-900 mr-4">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Edit Visitor</h1>
            <p class="text-gray-600 mt-1">Update visitor information</p>
        </div>
    </div>

    <div class="max-w-3xl bg-white rounded-lg shadow-md">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Visitor Information</h2>
        </div>
        
        <form action="{{ route('visitors.update', $visitor) }}" method="POST" enctype="multipart/form-data" class="px-6 py-6">
            @csrf
            @method('PUT')
            
            <div class="space-y-6">
                <!-- Visitor Details -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="visitor_name" class="block text-sm font-medium text-gray-700 mb-2">
                            Visitor Name <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="visitor_name" id="visitor_name" required 
                               value="{{ old('visitor_name', $visitor->visitor_name) }}"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>

                    <div>
                        <label for="visitor_phone" class="block text-sm font-medium text-gray-700 mb-2">
                            Mobile Number <span class="text-red-500">*</span>
                        </label>
                        <input type="tel" name="visitor_phone" id="visitor_phone" required 
                               value="{{ old('visitor_phone', $visitor->visitor_phone) }}"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>

                    <div>
                        <label for="visitor_type" class="block text-sm font-medium text-gray-700 mb-2">
                            Visitor Type <span class="text-red-500">*</span>
                        </label>
                        <select name="visitor_type" id="visitor_type" required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="guest" {{ old('visitor_type', $visitor->visitor_type) == 'guest' ? 'selected' : '' }}>Guest</option>
                            <option value="delivery" {{ old('visitor_type', $visitor->visitor_type) == 'delivery' ? 'selected' : '' }}>Delivery</option>
                            <option value="cab" {{ old('visitor_type', $visitor->visitor_type) == 'cab' ? 'selected' : '' }}>Cab</option>
                            <option value="service" {{ old('visitor_type', $visitor->visitor_type) == 'service' ? 'selected' : '' }}>Service Staff</option>
                            <option value="other" {{ old('visitor_type', $visitor->visitor_type) == 'other' ? 'selected' : '' }}>Other</option>
                        </select>
                    </div>

                    <div>
                        <label for="flat_id" class="block text-sm font-medium text-gray-700 mb-2">
                            Visiting Apartment <span class="text-red-500">*</span>
                        </label>
                        <select name="flat_id" id="flat_id" required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            @foreach($flats as $flat)
                                <option value="{{ $flat->id }}" {{ old('flat_id', $visitor->flat_id) == $flat->id ? 'selected' : '' }}>
                                    {{ $flat->building->name ?? 'N/A' }} - {{ $flat->flat_number }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <!-- Visit Times -->
                <div class="border-t border-gray-200 pt-6">
                    <h3 class="text-md font-semibold text-gray-900 mb-4">Visit Times</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="expected_entry_time" class="block text-sm font-medium text-gray-700 mb-2">
                                Date Of Visit <span class="text-red-500">*</span>
                            </label>
                            @php
                                $entryDate = old('expected_entry_time');
                                if (!$entryDate && $visitor->expected_entry_time) {
                                    $entryDate = is_string($visitor->expected_entry_time) 
                                        ? substr($visitor->expected_entry_time, 0, 10)
                                        : $visitor->expected_entry_time->format('Y-m-d');
                                }
                            @endphp
                            <input type="date" name="expected_entry_time" id="expected_entry_time" required
                                   value="{{ $entryDate }}"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>

                        <div>
                            <label for="in_time" class="block text-sm font-medium text-gray-700 mb-2">
                                In Time <span class="text-red-500">*</span>
                            </label>
                            @php
                                $inTime = old('in_time');
                                if (!$inTime && $visitor->expected_entry_time) {
                                    $inTime = is_string($visitor->expected_entry_time) 
                                        ? substr($visitor->expected_entry_time, 11, 5)
                                        : $visitor->expected_entry_time->format('H:i');
                                }
                            @endphp
                            <input type="time" name="in_time" id="in_time" required
                                   value="{{ $inTime }}"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>

                        <div>
                            <label for="expected_exit_time" class="block text-sm font-medium text-gray-700 mb-2">
                                Date Of Exit
                            </label>
                            @php
                                $exitDate = old('expected_exit_time');
                                if (!$exitDate && $visitor->expected_exit_time) {
                                    $exitDate = is_string($visitor->expected_exit_time) 
                                        ? substr($visitor->expected_exit_time, 0, 10)
                                        : $visitor->expected_exit_time->format('Y-m-d');
                                }
                            @endphp
                            <input type="date" name="expected_exit_time" id="expected_exit_time"
                                   value="{{ $exitDate }}"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>

                        <div>
                            <label for="out_time" class="block text-sm font-medium text-gray-700 mb-2">
                                Out Time
                            </label>
                            @php
                                $outTime = old('out_time');
                                if (!$outTime && $visitor->expected_exit_time) {
                                    $outTime = is_string($visitor->expected_exit_time) 
                                        ? substr($visitor->expected_exit_time, 11, 5)
                                        : $visitor->expected_exit_time->format('H:i');
                                }
                            @endphp
                            <input type="time" name="out_time" id="out_time"
                                   value="{{ $outTime }}"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                    </div>
                </div>
                <div class="border-t border-gray-200 pt-6">
                    <h3 class="text-md font-semibold text-gray-900 mb-4">Status Update</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="approval_status" class="block text-sm font-medium text-gray-700 mb-2">
                                Approval Status
                            </label>
                            <select name="approval_status" id="approval_status"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="pending" {{ old('approval_status', $visitor->approval_status) == 'pending' ? 'selected' : '' }}>Pending</option>
                                <option value="approved" {{ old('approval_status', $visitor->approval_status) == 'approved' ? 'selected' : '' }}>Approved</option>
                                <option value="rejected" {{ old('approval_status', $visitor->approval_status) == 'rejected' ? 'selected' : '' }}>Rejected</option>
                            </select>
                        </div>

                        <div>
                            <label for="entry_status" class="block text-sm font-medium text-gray-700 mb-2">
                                Entry Status
                            </label>
                            <select name="entry_status" id="entry_status"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="pending" {{ old('entry_status', $visitor->entry_status) == 'pending' ? 'selected' : '' }}>Pending</option>
                                <option value="entered" {{ old('entry_status', $visitor->entry_status) == 'entered' ? 'selected' : '' }}>Checked In</option>
                                <option value="exited" {{ old('entry_status', $visitor->entry_status) == 'exited' ? 'selected' : '' }}>Checked Out</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Additional Details -->
                <div class="border-t border-gray-200 pt-6">
                    <h3 class="text-md font-semibold text-gray-900 mb-4">Additional Details</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="expected_count" class="block text-sm font-medium text-gray-700 mb-2">
                                Number of Visitors
                            </label>
                            <input type="number" name="expected_count" id="expected_count" min="1" max="50"
                                   value="{{ old('expected_count', $visitor->expected_count) }}"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>

                        <div>
                            <label for="vehicle_number" class="block text-sm font-medium text-gray-700 mb-2">
                                Vehicle Number
                            </label>
                            <input type="text" name="vehicle_number" id="vehicle_number"
                                   value="{{ old('vehicle_number', $visitor->vehicle_number) }}"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                    </div>
                </div>

                <!-- Purpose and Photo -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="purpose" class="block text-sm font-medium text-gray-700 mb-2">
                            Purpose of Visit
                        </label>
                        <textarea name="purpose" id="purpose" rows="3"
                                  class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">{{ old('purpose', $visitor->purpose) }}</textarea>
                    </div>

                    <div>
                        <label for="photo" class="block text-sm font-medium text-gray-700 mb-2">
                            Upload Photo (Optional)
                        </label>
                        @if($visitor->photo)
                            <div class="mb-3">
                                <img src="{{ asset('storage/' . $visitor->photo) }}" alt="Current Photo" class="h-20 w-20 object-cover rounded-lg">
                                <p class="text-xs text-gray-500 mt-1">Current photo</p>
                            </div>
                        @endif
                        <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-lg hover:border-gray-400 transition-colors">
                            <div class="space-y-1 text-center">
                                <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                    <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                                <div class="flex text-sm text-gray-600">
                                    <label for="photo" class="relative cursor-pointer bg-white rounded-md font-medium text-blue-600 hover:text-blue-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-blue-500">
                                        <span>{{ $visitor->photo ? 'Change Photo' : 'Upload Photo' }}</span>
                                        <input id="photo" name="photo" type="file" accept="image/*" class="sr-only" onchange="previewPhoto(this)">
                                    </label>
                                    <p class="pl-1">or drag and drop</p>
                                </div>
                                <p class="text-xs text-gray-500">PNG, JPG, GIF up to 2MB</p>
                            </div>
                        </div>
                        <div id="photo-preview" class="mt-2 hidden">
                            <img id="preview-image" class="h-20 w-20 object-cover rounded-lg" src="" alt="Preview">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="mt-8 flex justify-end space-x-3 pt-6 border-t border-gray-200">
                <a href="{{ route('visitors.index') }}" 
                   class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 font-medium transition-colors">
                    Cancel
                </a>
                <button type="submit" 
                        class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition-colors">
                    Update Visitor
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Photo preview function
function previewPhoto(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('preview-image').src = e.target.result;
            document.getElementById('photo-preview').classList.remove('hidden');
        };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>
@endsection
