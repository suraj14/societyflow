@extends('layouts.app')

@section('title', 'Edit User')
@section('page-title', 'Edit User')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="max-w-3xl mx-auto">
        <div class="mb-6">
            <a href="{{ route('users.index') }}" class="text-blue-600 hover:text-blue-800 text-sm">
                <i class="fas fa-arrow-left mr-1"></i> Back to Users
            </a>
        </div>

        <div class="bg-white rounded-lg shadow-md">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-800">Update User</h2>
                <p class="text-sm text-gray-500 mt-1">{{ $user->email }}</p>
            </div>

            <form action="{{ route('users.update', $user) }}" method="POST" class="p-6" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                @if($errors->any())
                    <div class="mb-6 bg-red-50 border-l-4 border-red-500 p-4 rounded-r-lg">
                        <div class="flex items-start">
                            <i class="fas fa-exclamation-circle text-red-500 mr-3 mt-0.5"></i>
                            <div>
                                <h4 class="text-sm font-medium text-red-800 mb-1">Please fix the following errors:</h4>
                                <ul class="text-sm text-red-700 list-disc list-inside">
                                    @foreach($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>
                @endif

                @php $isSelf = $user->id === auth()->id(); @endphp

                <div class="grid md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Full Name *</label>
                        <input type="text" name="name" value="{{ old('name', $user->name) }}" required
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        @error('name')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
                        <input type="email" value="{{ $user->email }}" readonly
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-50 text-gray-500">
                        <p class="text-xs text-gray-500 mt-1">Email cannot be changed after creation</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Phone Number *</label>
                        <div class="flex">
                            <span class="inline-flex items-center px-3 rounded-l-lg border border-r-0 border-gray-300 bg-gray-50 text-gray-500 text-sm">+91</span>
                            <input type="text" name="phone" value="{{ old('phone', $user->phone) }}" required
                                   class="flex-1 px-4 py-2 border border-gray-300 rounded-r-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        @error('phone')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Status *</label>
                        <select name="status" required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            @foreach(['active' => 'Active', 'inactive' => 'Inactive'] as $value => $label)
                                <option value="{{ $value }}" {{ old('status', $user->status) === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('status')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Role *</label>
                        @php $currentRole = old('role', $user->roles->first()?->name); @endphp
                        <select name="role" required {{ $isSelf ? 'disabled' : '' }}
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 {{ $isSelf ? 'bg-gray-50 text-gray-500' : '' }}">
                            @foreach($roles as $role)
                                <option value="{{ $role->name }}" {{ $currentRole === $role->name ? 'selected' : '' }}>{{ $role->name }}</option>
                            @endforeach
                        </select>
                        @if($isSelf)
                            {{-- Disabled selects are not submitted; the controller requires role. --}}
                            <input type="hidden" name="role" value="{{ $currentRole }}">
                            <p class="text-xs text-red-500 mt-1">You cannot change your own role</p>
                        @endif
                        @error('role')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="md:col-span-2">
                        <label for="committee_designation" class="block text-sm font-medium text-gray-700 mb-1">Committee Designation <span class="text-gray-400 font-normal">(optional)</span></label>
                        <input type="text" id="committee_designation" name="committee_designation" list="committee-designations" maxlength="100"
                               value="{{ old('committee_designation', $user->committee_designation) }}" placeholder="e.g. President, Secretary, Treasurer"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <datalist id="committee-designations">
                            @foreach(\App\Support\SocietyInfo::SUGGESTED_DESIGNATIONS as $designation)
                                <option value="{{ $designation }}">
                            @endforeach
                        </datalist>
                        <p class="text-xs text-gray-500 mt-1">Shown on Society Info &rarr; Committee with this person's photo and phone number. Leave empty if they are not on the committee.</p>
                        @error('committee_designation')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="md:col-span-2">
                        @include('partials.image-upload-field', [
                            'field' => 'avatar',
                            'currentUrl' => $user->avatar_web_url,
                            'personName' => $user->name,
                            'label' => 'Profile Image',
                        ])
                    </div>
                </div>

                <div class="flex justify-end space-x-3 border-t border-gray-200 mt-8 pt-6">
                    <a href="{{ route('users.index') }}"
                       class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                        Cancel
                    </a>
                    <button type="submit"
                            class="px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-lg hover:bg-blue-700">
                        <i class="fas fa-save mr-2"></i>Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
