@extends('layouts.super-admin')

@section('title', 'Societies')
@section('page-title', 'Societies')

@section('content')
<div class="space-y-6">
    <!-- Header with Search and Filters -->
    <div class="bg-white p-6 rounded-lg shadow">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between space-y-4 md:space-y-0">
            <div class="flex items-center space-x-4">
                <form method="GET" class="flex items-center space-x-4">
                    <div class="relative">
                        <input type="text" name="search" value="{{ request('search') }}" 
                               placeholder="Search by name or email"
                               class="pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                        <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                    </div>

                    <select name="status" class="border border-gray-300 rounded-lg px-3 py-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">All Status</option>
                        <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                        <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                        <option value="suspended" {{ request('status') === 'suspended' ? 'selected' : '' }}>Suspended</option>
                    </select>

                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                        <i class="fas fa-search mr-2"></i>Search
                    </button>
                </form>
            </div>

            <div class="flex items-center space-x-2">
                <a href="{{ route('super-admin.societies.create') }}" 
                   class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700">
                    Add Society
                </a>
            </div>
        </div>
    </div>

    <!-- Societies Table -->
    <div class="bg-white rounded-lg shadow">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Society</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Admin</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Package</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($societies as $society)
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 bg-red-600 rounded-lg flex items-center justify-center mr-3">
                                        <span class="text-white text-sm font-bold">{{ substr($society->name, 0, 1) }}</span>
                                    </div>
                                    <div>
                                        <div class="text-sm font-medium text-gray-900">{{ $society->name }}</div>
                                        <div class="text-sm text-gray-500">{{ $society->email }}</div>
                                        <div class="text-xs text-gray-400">ID: {{ $society->id }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($society->hasAdmin())
                                    <div class="flex items-center">
                                        <div class="w-8 h-8 bg-green-600 rounded-full flex items-center justify-center mr-3">
                                            <i class="fas fa-user-shield text-white text-xs"></i>
                                        </div>
                                        <div>
                                            <div class="text-sm font-medium text-gray-900">{{ $society->admin->name }}</div>
                                            <div class="text-sm text-gray-500">{{ $society->admin->email }}</div>
                                            <div class="text-xs text-gray-400">
                                                @php
                                                    $lastLogin = $society->admin->last_login_at;
                                                    if ($lastLogin) {
                                                        try {
                                                            $lastLoginDate = is_string($lastLogin) ? \Carbon\Carbon::parse($lastLogin) : $lastLogin;
                                                            $lastLoginText = $lastLoginDate->diffForHumans();
                                                        } catch (Exception $e) {
                                                            $lastLoginText = 'Unknown';
                                                        }
                                                    } else {
                                                        $lastLoginText = 'Never';
                                                    }
                                                @endphp
                                                Last login: {{ $lastLoginText }}
                                            </div>
                                        </div>
                                    </div>
                                @else
                                    <div class="flex items-center">
                                        <div class="w-8 h-8 bg-yellow-500 rounded-full flex items-center justify-center mr-3">
                                            <i class="fas fa-exclamation-triangle text-white text-xs"></i>
                                        </div>
                                        <div>
                                            <div class="text-sm font-medium text-yellow-800">No Admin</div>
                                            <div class="text-xs text-yellow-600">Admin not assigned</div>
                                        </div>
                                    </div>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($society->subscriptionPlan)
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-orange-100 text-orange-800">
                                        {{ $society->subscriptionPlan->name }}
                                    </span>
                                    <div class="text-xs text-gray-500 mt-1">
                                        ₹{{ number_format($society->subscriptionPlan->monthly_price) }}/month
                                    </div>
                                @else
                                    <span class="text-gray-400">No Package</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                    {{ $society->status === 'active' ? 'bg-green-100 text-green-800' : 
                                       ($society->status === 'inactive' ? 'bg-gray-100 text-gray-800' : 'bg-red-100 text-red-800') }}">
                                    {{ ucfirst($society->status) }}
                                </span>
                                
                                @php
                                    $trialEndsAt = $society->trial_ends_at;
                                    $isOnTrial = false;
                                    $trialText = '';
                                    
                                    if ($trialEndsAt) {
                                        try {
                                            // Handle both string and Carbon objects
                                            $trialDate = is_string($trialEndsAt) ? \Carbon\Carbon::parse($trialEndsAt) : $trialEndsAt;
                                            $isOnTrial = $trialDate->gt(now());
                                            $trialText = $trialDate->diffForHumans();
                                        } catch (Exception $e) {
                                            $isOnTrial = false;
                                        }
                                    }
                                @endphp
                                
                                @if($isOnTrial)
                                    <div class="mt-1">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                            Trial ({{ $trialText }})
                                        </span>
                                    </div>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <div class="relative" x-data="{ open: false }">
                                    <button @click="open = !open" class="text-gray-600 hover:text-gray-900">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <div x-show="open" @click.away="open = false" 
                                         class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg z-10">
                                        <div class="py-1">
                                            <a href="{{ route('super-admin.societies.show', $society) }}" 
                                               class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                <i class="fas fa-eye mr-2"></i>View
                                            </a>
                                            <a href="{{ route('super-admin.societies.edit', $society) }}" 
                                               class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                <i class="fas fa-edit mr-2"></i>Edit
                                            </a>
                                            <form action="{{ route('super-admin.societies.destroy', $society) }}" method="POST" class="inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" onclick="return confirm('Are you sure?')" 
                                                        class="block w-full text-left px-4 py-2 text-sm text-red-700 hover:bg-gray-100">
                                                    <i class="fas fa-trash mr-2"></i>Delete
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-4 text-center text-gray-500">No societies found</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        @if($societies->hasPages())
            <div class="px-6 py-4 border-t border-gray-200">
                {{ $societies->links() }}
            </div>
        @endif
    </div>
</div>
@endsection