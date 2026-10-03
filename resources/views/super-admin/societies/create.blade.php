@extends('layouts.super-admin')

@section('title', 'Add Society')
@section('page-title', 'Add New Society')

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('super-admin.societies.index') }}" class="text-blue-600 hover:text-blue-800">
            <i class="fas fa-arrow-left mr-2"></i>Back to Societies
        </a>
    </div>

    <div class="bg-white rounded-lg shadow-md">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Society Information</h2>
        </div>

        <form action="{{ route('super-admin.societies.store') }}" method="POST" class="p-6">
            @csrf
            @include('partials.idempotency')

            <!-- Society Details -->
            <div class="mb-8">
                <h3 class="text-md font-medium text-gray-900 mb-4">Society Details</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Society Name *</label>
                        <input type="text" name="name" value="{{ old('name') }}" required
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 @error('name') border-red-500 @enderror">
                        @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Email *</label>
                        <input type="email" name="email" value="{{ old('email') }}" required
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 @error('email') border-red-500 @enderror">
                        @error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Phone *</label>
                        <input type="text" name="phone" value="{{ old('phone') }}" required
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 @error('phone') border-red-500 @enderror">
                        @error('phone')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Subscription Plan *</label>
                        <select name="subscription_plan_id" required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 @error('subscription_plan_id') border-red-500 @enderror">
                            <option value="">Select Plan</option>
                            @foreach($subscriptionPlans as $plan)
                                <option value="{{ $plan->id }}" {{ old('subscription_plan_id') == $plan->id ? 'selected' : '' }}>
                                    {{ $plan->name }} - ₹{{ number_format($plan->monthly_price) }}/month
                                </option>
                            @endforeach
                        </select>
                        @error('subscription_plan_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    {{--
                        Property Type — the source of truth for this society.
                        It decides which property types can be created, which
                        navigation appears, and which dashboard figures mean
                        anything. Set once here; never asked again elsewhere.
                    --}}
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Property Type <span class="text-red-500">*</span>
                        </label>
                        <div class="grid sm:grid-cols-3 gap-3" id="property-type-choices">
                            @foreach(\App\Enums\SocietyPropertyType::options() as $value => $label)
                                <label class="relative flex items-start p-4 border rounded-lg cursor-pointer hover:bg-gray-50
                                              {{ old('property_type', 'both') === $value ? 'border-blue-500 ring-2 ring-blue-200' : 'border-gray-300' }}">
                                    <input type="radio" name="property_type" value="{{ $value }}" required
                                           class="mt-1 text-blue-600 focus:ring-blue-500"
                                           {{ old('property_type', 'both') === $value ? 'checked' : '' }}>
                                    <span class="ml-3">
                                        <span class="block text-sm font-medium text-gray-900">{{ $label }}</span>
                                        <span class="block text-xs text-gray-500 mt-0.5">
                                            @if($value === 'villa') Villas and villa areas only
                                            @elseif($value === 'apartment') Buildings and apartments only
                                            @else Both apartments and villas
                                            @endif
                                        </span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                        @error('property_type')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror

                        <div id="property-type-preview" class="mt-3 rounded-lg bg-blue-50 border border-blue-200 p-4 text-sm text-blue-900"></div>
                    </div>
                </div>
            </div>

            <!-- Address -->
            <div class="mb-8">
                <h3 class="text-md font-medium text-gray-900 mb-4">Address</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Address *</label>
                        <textarea name="address" rows="2" required
                                  class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 @error('address') border-red-500 @enderror">{{ old('address') }}</textarea>
                        @error('address')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">City *</label>
                        <input type="text" name="city" value="{{ old('city') }}" required
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">State *</label>
                        <input type="text" name="state" value="{{ old('state') }}" required
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Country *</label>
                        <input type="text" name="country" value="{{ old('country', 'India') }}" required
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Pincode *</label>
                        <input type="text" name="pincode" value="{{ old('pincode') }}" required
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>
            </div>

            <!-- Admin Details -->
            <div class="mb-8">
                <h3 class="text-md font-medium text-gray-900 mb-4">Society Admin Account</h3>
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-info-circle text-blue-400"></i>
                        </div>
                        <div class="ml-3">
                            <h4 class="text-sm font-medium text-blue-800">One Society = One Admin</h4>
                            <p class="text-sm text-blue-700">Each society can have exactly one admin. The admin will have full access to manage their society.</p>
                        </div>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Admin Name *</label>
                        <input type="text" name="admin_name" value="{{ old('admin_name') }}" required
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 @error('admin_name') border-red-500 @enderror">
                        @error('admin_name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Admin Email *</label>
                        <input type="email" name="admin_email" value="{{ old('admin_email') }}" required
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 @error('admin_email') border-red-500 @enderror">
                        @error('admin_email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Admin Password *</label>
                        <input type="password" name="admin_password" required
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 @error('admin_password') border-red-500 @enderror">
                        @error('admin_password')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        <p class="mt-1 text-xs text-gray-500">Minimum 8 characters required</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Confirm Password *</label>
                        <input type="password" name="admin_password_confirmation" required
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                        <p class="mt-1 text-xs text-gray-500">Re-enter the password to confirm</p>
                    </div>
                </div>
            </div>

            <div class="flex justify-end space-x-3 pt-6 border-t">
                <a href="{{ route('super-admin.societies.index') }}" 
                   class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">Cancel</a>
                <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                    Create Society
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
// Live preview of what the chosen property type switches on, so the admin
// can see the consequence before saving. Purely informational - the server
// enforces the value.
document.addEventListener('DOMContentLoaded', function () {
    const preview = document.getElementById('property-type-preview');
    const choices = document.querySelectorAll('input[name="property_type"]');
    if (!preview || !choices.length) return;

    const FEATURES = {
        villa:     ['Villa areas', 'Villas', 'Villa owners', 'Villa residents', 'Villa visitors', 'Villa billing'],
        apartment: ['Buildings / towers', 'Apartments', 'Apartment owners', 'Apartment residents', 'Apartment visitors', 'Apartment billing'],
        both:      ['Buildings / towers', 'Apartments', 'Villa areas', 'Villas', 'Owners of both kinds', 'Residents of both kinds', 'Visitors for both', 'Billing for both'],
    };

    function render(value) {
        const items = FEATURES[value] || [];
        preview.innerHTML =
            '<p class="font-semibold mb-2">This society will have:</p><ul class="space-y-1">' +
            items.map(i => '<li>&#10003; ' + i + '</li>').join('') +
            '</ul>';

        document.querySelectorAll('#property-type-choices label').forEach(function (label) {
            const input = label.querySelector('input');
            label.classList.toggle('border-blue-500', input.checked);
            label.classList.toggle('ring-2', input.checked);
            label.classList.toggle('ring-blue-200', input.checked);
            label.classList.toggle('border-gray-300', !input.checked);
        });
    }

    choices.forEach(c => c.addEventListener('change', () => render(c.value)));
    render(document.querySelector('input[name="property_type"]:checked')?.value || 'both');
});
</script>
@endpush
