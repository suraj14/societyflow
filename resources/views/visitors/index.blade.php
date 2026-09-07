@extends('layouts.app')

@section('title', 'Visitors Management')
@section('page-title', 'Visitors Management')

@section('content')
<div class="container mx-auto px-4 py-6">
    <!-- Header -->
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Visitors Management</h1>
            <p class="text-gray-600 mt-1">Track and manage all visitor entries</p>
        </div>
        <div class="flex space-x-3">
            @if(auth()->user()->hasRole(['Admin', 'Super Admin']))
                <a href="{{ route('visitors.export') }}" 
                   class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg font-medium transition-colors inline-flex items-center">
                    <i class="fas fa-download mr-2"></i>Export CSV
                </a>
            @endif
            @if(auth()->user()->hasRole(['Admin', 'Super Admin']))
                <a href="{{ route('visitors.create') }}" 
                   class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-medium transition-colors inline-flex items-center">
                    <i class="fas fa-plus mr-2"></i>
                    Add Visitor
                </a>
            @endif
        </div>
    </div>

    <!-- Success/Error Messages -->
    @if(session('success'))
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-lg">
            <div class="flex items-center">
                <i class="fas fa-check-circle mr-3"></i>
                <p class="font-medium">{{ session('success') }}</p>
            </div>
        </div>
    @endif

    @if(session('error'))
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-lg">
            <div class="flex items-center">
                <i class="fas fa-exclamation-circle mr-3"></i>
                <p class="font-medium">{{ session('error') }}</p>
            </div>
        </div>
    @endif

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-7 gap-6 mb-6">
        <div class="bg-white overflow-hidden shadow-md rounded-lg" data-stat="total">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-users text-blue-600"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Total</p>
                        <p class="text-xl font-bold text-gray-900">{{ $stats['total'] }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white overflow-hidden shadow-md rounded-lg" data-stat="today">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-calendar-day text-purple-600"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Today</p>
                        <p class="text-xl font-bold text-gray-900">{{ $stats['today'] }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white overflow-hidden shadow-md rounded-lg" data-stat="pending">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-yellow-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-clock text-yellow-600"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Pending</p>
                        <p class="text-xl font-bold text-gray-900">{{ $stats['pending'] }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white overflow-hidden shadow-md rounded-lg" data-stat="allowed">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-check text-green-600"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Allowed</p>
                        <p class="text-xl font-bold text-gray-900">{{ $stats['allowed'] }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white overflow-hidden shadow-md rounded-lg" data-stat="denied">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-red-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-times text-red-600"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Denied</p>
                        <p class="text-xl font-bold text-gray-900">{{ $stats['denied'] }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white overflow-hidden shadow-md rounded-lg">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-sign-in-alt text-blue-600"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Checked In</p>
                        <p class="text-xl font-bold text-gray-900">{{ $stats['checked_in'] }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white overflow-hidden shadow-md rounded-lg">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-sign-out-alt text-gray-600"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Checked Out</p>
                        <p class="text-xl font-bold text-gray-900">{{ $stats['checked_out'] }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-lg shadow-md p-4 mb-6">
        <form method="GET" action="{{ route('visitors.index') }}" class="flex flex-wrap gap-4">
            <div class="flex-1 min-w-64">
                <input type="text" name="search" placeholder="Search by name, phone, or apartment..." 
                       value="{{ request('search') }}"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            
            <div>
                <select name="visitor_type" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">All Types</option>
                    <option value="guest" {{ request('visitor_type') == 'guest' ? 'selected' : '' }}>Guest</option>
                    <option value="delivery" {{ request('visitor_type') == 'delivery' ? 'selected' : '' }}>Delivery</option>
                    <option value="cab" {{ request('visitor_type') == 'cab' ? 'selected' : '' }}>Cab</option>
                    <option value="service" {{ request('visitor_type') == 'service' ? 'selected' : '' }}>Service Staff</option>
                    <option value="other" {{ request('visitor_type') == 'other' ? 'selected' : '' }}>Other</option>
                </select>
            </div>

            <div>
                <select name="status" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">All Status</option>
                    <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                    <option value="allowed" {{ request('status') == 'allowed' ? 'selected' : '' }}>Allowed</option>
                    <option value="denied" {{ request('status') == 'denied' ? 'selected' : '' }}>Denied</option>
                    <option value="checked_in" {{ request('status') == 'checked_in' ? 'selected' : '' }}>Checked In</option>
                    <option value="checked_out" {{ request('status') == 'checked_out' ? 'selected' : '' }}>Checked Out</option>
                </select>
            </div>

            <div>
                <select name="date_filter" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">All Time</option>
                    <option value="today" {{ request('date_filter') == 'today' ? 'selected' : '' }}>Today</option>
                    <option value="yesterday" {{ request('date_filter') == 'yesterday' ? 'selected' : '' }}>Yesterday</option>
                    <option value="last_7_days" {{ request('date_filter') == 'last_7_days' ? 'selected' : '' }}>Last 7 Days</option>
                    <option value="last_30_days" {{ request('date_filter') == 'last_30_days' ? 'selected' : '' }}>Last 30 Days</option>
                </select>
            </div>

            <button type="submit" class="px-6 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors">
                <i class="fas fa-search mr-2"></i>Filter
            </button>
            
            @if(request()->hasAny(['search', 'visitor_type', 'status', 'date_filter']))
                <a href="{{ route('visitors.index') }}" class="px-6 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 transition-colors">
                    Reset
                </a>
            @endif
        </form>
    </div>

    <!-- Visitors Table -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        @if($visitors->count() > 0)
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Visitor Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Mobile Number</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Apartment</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date of Visit</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">In Time</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Out Time</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            @if(auth()->user()->hasRole(['Admin', 'Super Admin']))
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($visitors as $visitor)
                            <tr class="hover:bg-gray-50 transition-colors" id="row-{{ $visitor->id }}" data-id="{{ $visitor->id }}">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="w-10 h-10 bg-gray-100 rounded-full flex items-center justify-center">
                                            @php
                                                $typeIcons = [
                                                    'guest' => 'fa-user',
                                                    'delivery' => 'fa-truck',
                                                    'cab' => 'fa-car',
                                                    'service' => 'fa-wrench',
                                                    'other' => 'fa-users'
                                                ];
                                            @endphp
                                            <i class="fas {{ $typeIcons[$visitor->visitor_type] ?? 'fa-user' }} text-gray-600"></i>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900">{{ $visitor->visitor_name }}</div>
                                            @if($visitor->expected_count > 1)
                                                <div class="text-xs text-gray-500">+{{ $visitor->expected_count - 1 }} guests</div>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">{{ $visitor->visitor_phone }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">{{ $visitor->flat->building->name ?? '' }} - {{ $visitor->flat->flat_number ?? '' }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @php
                                        $typeColors = [
                                            'guest' => 'bg-blue-100 text-blue-800',
                                            'delivery' => 'bg-orange-100 text-orange-800',
                                            'cab' => 'bg-purple-100 text-purple-800',
                                            'service' => 'bg-green-100 text-green-800',
                                            'other' => 'bg-gray-100 text-gray-800'
                                        ];
                                    @endphp
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $typeColors[$visitor->visitor_type] ?? 'bg-gray-100 text-gray-800' }}">
                                        {{ ucfirst($visitor->visitor_type) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">{{ \Carbon\Carbon::parse($visitor->expected_entry_time)->format('M d, Y') }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        {{ $visitor->expected_entry_time ? \Carbon\Carbon::parse($visitor->expected_entry_time)->format('h:i A') : '-' }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        {{ $visitor->expected_exit_time ? \Carbon\Carbon::parse($visitor->expected_exit_time)->format('h:i A') : '-' }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @php
                                        if ($visitor->entry_status === 'entered') {
                                            $statusClass = 'bg-green-100 text-green-800';
                                            $statusText = 'Checked In';
                                        } elseif ($visitor->entry_status === 'exited') {
                                            $statusClass = 'bg-gray-100 text-gray-800';
                                            $statusText = 'Checked Out';
                                        } elseif ($visitor->approval_status === 'allowed') {
                                            $statusClass = 'bg-blue-100 text-blue-800';
                                            $statusText = 'Allowed';
                                        } elseif ($visitor->approval_status === 'denied') {
                                            $statusClass = 'bg-red-100 text-red-800';
                                            $statusText = 'Denied';
                                        } else {
                                            $statusClass = 'bg-yellow-100 text-yellow-800';
                                            $statusText = 'Pending';
                                        }
                                    @endphp
                                    <div class="flex items-center space-x-2">
                                        {{-- Status Dropdown for Admin/Staff only (when pending) --}}
                                        @if($visitor->approval_status === 'pending' && auth()->user()->hasRole(['Admin', 'Staff', 'Super Admin']))
                                            <div class="relative inline-block">
                                                <button onclick="return toggleVisitorDropdown({{ $visitor->id }}, event);" 
                                                        id="visitorDropdownButton{{ $visitor->id }}"
                                                        class="inline-flex items-center px-3 py-1 text-xs font-semibold text-gray-700 transition duration-150 ease-in-out bg-white border border-gray-300 rounded-lg shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2" 
                                                        type="button"
                                                        aria-expanded="false"
                                                        aria-haspopup="true">
                                                    <span class="capitalize">Pending</span>
                                                    <svg class="w-2.5 h-2.5 ms-2" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 10 6">
                                                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 4 4 4-4"></path>
                                                    </svg>
                                                </button>
                                                
                                                <!-- Dropdown menu -->
                                                <div id="visitorDropdown{{ $visitor->id }}" class="z-10 hidden absolute right-0 mt-2 bg-white divide-y divide-gray-100 rounded-lg shadow-lg w-44 border border-gray-200">
                                                    <ul class="py-2 text-sm text-gray-700">
                                                        <li>
                                                            <button onclick="return changeVisitorStatusAction({{ $visitor->id }}, 'allow', '{{ addslashes($visitor->visitor_name) }}', '{{ addslashes(($visitor->flat->building->name ?? '') . ' - ' . ($visitor->flat->flat_number ?? '')) }}', event);" 
                                                                    class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center"
                                                                    type="button">
                                                                <i class="fas fa-check text-green-600 mr-2"></i>
                                                                Allow
                                                            </button>
                                                        </li>
                                                        <li>
                                                            <button onclick="return changeVisitorStatusAction({{ $visitor->id }}, 'deny', '{{ addslashes($visitor->visitor_name) }}', '{{ addslashes(($visitor->flat->building->name ?? '') . ' - ' . ($visitor->flat->flat_number ?? '')) }}', event);" 
                                                                    class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center"
                                                                    type="button">
                                                                <i class="fas fa-times text-red-600 mr-2"></i>
                                                                Deny
                                                            </button>
                                                        </li>
                                                    </ul>
                                                </div>
                                            </div>
                                        @else
                                            {{-- Show status badge for non-pending visitors or non-admin users --}}
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $statusClass }}">
                                                {{ $statusText }}
                                            </span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex justify-end space-x-2">
                                        {{-- Check-in/Check-out for allowed visitors (all roles) --}}
                                        @if($visitor->approval_status === 'allowed' && $visitor->entry_status === 'pending')
                                            <form action="{{ route('visitors.check-in', $visitor) }}" method="POST" class="inline-block">
                                                @csrf
                                                <button type="submit" class="text-blue-600 hover:text-blue-900 transition-colors" title="Check In">
                                                    <i class="fas fa-sign-in-alt"></i>
                                                </button>
                                            </form>
                                        @endif
                                        @if($visitor->entry_status === 'entered')
                                            <form action="{{ route('visitors.check-out', $visitor) }}" method="POST" class="inline-block">
                                                @csrf
                                                <button type="submit" class="text-purple-600 hover:text-purple-900 transition-colors" title="Check Out">
                                                    <i class="fas fa-sign-out-alt"></i>
                                                </button>
                                            </form>
                                        @endif

                                        {{-- Admin-Only Actions (Edit/Delete) --}}
                                        @if(auth()->user()->hasRole(['Admin', 'Super Admin']))
                                            <a href="{{ route('visitors.edit', $visitor) }}" 
                                               class="text-indigo-600 hover:text-indigo-900 transition-colors" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            
                                            <form action="{{ route('visitors.destroy', $visitor) }}" method="POST" class="inline-block">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-red-600 hover:text-red-900 transition-colors" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="bg-white px-4 py-3 border-t border-gray-200">
                {{ $visitors->links() }}
            </div>
        @else
            <div class="text-center py-12">
                <div class="inline-flex items-center justify-center w-16 h-16 bg-gray-100 rounded-full mb-4">
                    <i class="fas fa-users text-gray-400 text-2xl"></i>
                </div>
                <h3 class="text-lg font-medium text-gray-900 mb-2">No visitors found</h3>
                @if(auth()->user()->hasRole(['Admin', 'Super Admin']))
                    <p class="text-gray-500 mb-6">Get started by adding a new visitor.</p>
                    <a href="{{ route('visitors.create') }}" 
                       class="inline-flex items-center bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-medium transition-colors">
                        <i class="fas fa-plus mr-2"></i>
                        Add Your First Visitor
                    </a>
                @else
                    <p class="text-gray-500">No visitors to manage at this time.</p>
                @endif
            </div>
        @endif
    </div>
</div>

<!-- Deny Visitor Modal -->
<div id="denyModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Deny Visitor</h3>
                <form id="denyForm" method="POST">
                    @csrf
                    
                    <div class="mb-4">
                        <p class="text-sm text-gray-600 mb-2">You are about to deny the following visitor:</p>
                        <div class="bg-gray-50 p-3 rounded-lg">
                            <p class="font-medium" id="deny_visitor_name"></p>
                            <p class="text-sm text-gray-600" id="deny_visitor_flat"></p>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label for="reason" class="block text-sm font-medium text-gray-700 mb-2">Reason for Denial *</label>
                        <textarea name="reason" id="reason" rows="3" 
                                  class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-red-500" 
                                  placeholder="Please provide a reason for denying this visitor..."
                                  required></textarea>
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeDenyModal()" 
                                class="px-4 py-2 text-gray-600 hover:text-gray-800 transition-colors">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg font-medium transition-colors">
                            <i class="fas fa-times mr-2"></i>Deny Visitor
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Global dropdown state management for visitor status
window.visitorStatusDropdowns = window.visitorStatusDropdowns || {};

function openDenyModal(visitorId, visitorName, flatInfo) {
    document.getElementById('deny_visitor_name').textContent = visitorName;
    document.getElementById('deny_visitor_flat').textContent = 'Visiting: ' + flatInfo;
    document.getElementById('denyForm').action = '/visitors/' + visitorId + '/deny';
    document.getElementById('denyModal').classList.remove('hidden');
}

function closeDenyModal() {
    document.getElementById('denyModal').classList.add('hidden');
    document.getElementById('reason').value = '';
}

// Improved dropdown toggle with unique naming to avoid conflicts
function toggleVisitorDropdown(visitorId, event) {
    if (event) {
        event.preventDefault();
        event.stopPropagation();
        event.stopImmediatePropagation();
    }
    
    const dropdown = document.getElementById('visitorDropdown' + visitorId);
    const button = document.getElementById('visitorDropdownButton' + visitorId);
    
    if (!dropdown || !button) {
        console.error('Visitor dropdown elements not found for ID:', visitorId);
        return false;
    }
    
    // Close all other visitor dropdowns first
    closeAllVisitorDropdowns(visitorId);
    
    // Toggle current dropdown
    const isCurrentlyHidden = dropdown.classList.contains('hidden');
    
    if (isCurrentlyHidden) {
        // Open dropdown
        dropdown.classList.remove('hidden');
        button.setAttribute('aria-expanded', 'true');
        window.visitorStatusDropdowns[visitorId] = true;
    } else {
        // Close dropdown
        dropdown.classList.add('hidden');
        button.setAttribute('aria-expanded', 'false');
        window.visitorStatusDropdowns[visitorId] = false;
    }
    
    return false;
}

// Close all visitor dropdowns except the specified one
function closeAllVisitorDropdowns(exceptVisitorId) {
    document.querySelectorAll('[id^="visitorDropdown"]:not([id*="Button"]):not([id*="Modal"])').forEach(function(dropdown) {
        const visitorId = dropdown.id.replace('visitorDropdown', '');
        
        if (visitorId !== exceptVisitorId.toString()) {
            dropdown.classList.add('hidden');
            
            const button = document.getElementById('visitorDropdownButton' + visitorId);
            if (button) {
                button.setAttribute('aria-expanded', 'false');
            }
            
            window.visitorStatusDropdowns[visitorId] = false;
        }
    });
}

// Handle status change with unique naming
function changeVisitorStatusAction(visitorId, action, visitorName, flatInfo, event) {
    if (event) {
        event.preventDefault();
        event.stopPropagation();
        event.stopImmediatePropagation();
    }
    
    // Close the dropdown immediately
    const dropdown = document.getElementById('visitorDropdown' + visitorId);
    const button = document.getElementById('visitorDropdownButton' + visitorId);
    
    if (dropdown) {
        dropdown.classList.add('hidden');
    }
    if (button) {
        button.setAttribute('aria-expanded', 'false');
    }
    window.visitorStatusDropdowns[visitorId] = false;
    
    if (action === 'allow') {
        if (confirm('Are you sure you want to allow this visitor?')) {
            submitVisitorStatusChange(visitorId, 'allow');
        }
    } else if (action === 'deny') {
        openDenyModal(visitorId, visitorName, flatInfo);
    }
    
    return false;
}

// Submit status change form with unique naming
function submitVisitorStatusChange(visitorId, action) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '/visitors/' + visitorId + '/' + action;
    form.style.display = 'none';
    
    const csrfToken = document.createElement('input');
    csrfToken.type = 'hidden';
    csrfToken.name = '_token';
    csrfToken.value = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    
    form.appendChild(csrfToken);
    document.body.appendChild(form);
    form.submit();
}

// Document ready and event listeners
document.addEventListener('DOMContentLoaded', function() {
    // Initialize visitor dropdown states
    document.querySelectorAll('[id^="visitorDropdownButton"]').forEach(function(button) {
        const visitorId = button.id.replace('visitorDropdownButton', '');
        window.visitorStatusDropdowns[visitorId] = false;
        button.setAttribute('aria-expanded', 'false');
    });
    
    // Global click handler with improved logic for visitor dropdowns
    document.addEventListener('click', function(event) {
        let clickedVisitorDropdownButton = false;
        let clickedVisitorDropdownMenu = false;
        
        // Check if clicked element or its parents are visitor dropdown related
        let element = event.target;
        while (element && element !== document) {
            if (element.id && element.id.startsWith('visitorDropdownButton')) {
                clickedVisitorDropdownButton = true;
                break;
            }
            if (element.id && element.id.startsWith('visitorDropdown') && !element.id.includes('Button') && !element.id.includes('Modal')) {
                clickedVisitorDropdownMenu = true;
                break;
            }
            element = element.parentElement;
        }
        
        // If clicked outside visitor dropdown area, close all visitor dropdowns
        if (!clickedVisitorDropdownButton && !clickedVisitorDropdownMenu) {
            closeAllVisitorDropdowns('none');
        }
    });
    
    // Modal click handler
    const denyModal = document.getElementById('denyModal');
    if (denyModal) {
        denyModal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeDenyModal();
            }
        });
    }
});

// Prevent form submission on visitor dropdown button clicks
document.addEventListener('click', function(event) {
    if (event.target.closest('[id^="visitorDropdownButton"]') || 
        event.target.closest('[onclick*="changeVisitorStatusAction"]')) {
        event.preventDefault();
        return false;
    }
});

// ── Auto-refresh: poll for real-time updates every 15 seconds ─────────────────
// Refreshes the page if visitor counts changed (new visitor added from mobile, etc.)
(function() {
    let lastStats = {
        total: {{ $stats['total'] }},
        pending: {{ $stats['pending'] }},
        checked_in: {{ $stats['checked_in'] }},
        checked_out: {{ $stats['checked_out'] }}
    };

    let refreshTimer = null;
    let isPageVisible = !document.hidden;

    function checkForUpdates() {
        if (!isPageVisible) return;

        fetch('/visitors/stats', {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        })
        .then(r => r.json())
        .then(data => {
            if (!data.success) return;
            const s = data.stats;
            // If anything changed, reload the page
            if (s.total !== lastStats.total ||
                s.pending !== lastStats.pending ||
                s.checked_in !== lastStats.checked_in ||
                s.checked_out !== lastStats.checked_out) {
                // Preserve current URL params (filters)
                window.location.reload();
            }
        })
        .catch(() => {}); // Silent fail
    }

    // Start polling
    function startPolling() {
        clearInterval(refreshTimer);
        refreshTimer = setInterval(checkForUpdates, 15000);
    }

    // Pause when tab is hidden, resume when visible
    document.addEventListener('visibilitychange', function() {
        isPageVisible = !document.hidden;
        if (isPageVisible) {
            checkForUpdates(); // Immediate check on tab focus
            startPolling();
        } else {
            clearInterval(refreshTimer);
        }
    });

    startPolling();
})();
</script>
@endsection
