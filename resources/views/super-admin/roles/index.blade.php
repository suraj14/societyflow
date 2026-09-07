@extends('layouts.super-admin')

@section('title', 'Role Management')

@section('content')
<div class="space-y-6">
    <!-- Page Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Role Management</h1>
            <p class="text-gray-600 mt-1">Manage user roles and their permissions</p>
        </div>
    </div>

    <!-- Roles Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @foreach($roles as $role)
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center">
                            <div class="w-12 h-12 rounded-lg flex items-center justify-center
                                @if($role->name === 'Super Admin') bg-red-100
                                @elseif($role->name === 'Admin') bg-purple-100
                                @elseif($role->name === 'Villa Owner') bg-green-100
                                @elseif($role->name === 'Apartment Owner') bg-blue-100
                                @elseif($role->name === 'Tenant') bg-yellow-100
                                @else bg-gray-100
                                @endif">
                                <i class="fas 
                                    @if($role->name === 'Super Admin') fa-crown text-red-600
                                    @elseif($role->name === 'Admin') fa-user-shield text-purple-600
                                    @elseif($role->name === 'Villa Owner') fa-house-user text-green-600
                                    @elseif($role->name === 'Apartment Owner') fa-door-open text-blue-600
                                    @elseif($role->name === 'Tenant') fa-user text-yellow-600
                                    @else fa-user-tie text-gray-600
                                    @endif text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <h3 class="font-semibold text-gray-900">{{ $role->name }}</h3>
                                <p class="text-sm text-gray-500">{{ $role->permissions->count() }} permissions</p>
                            </div>
                        </div>
                    </div>

                    <!-- Permission Preview -->
                    <div class="mb-4">
                        <p class="text-xs text-gray-500 mb-2">Key Permissions:</p>
                        <div class="flex flex-wrap gap-1">
                            @foreach($role->permissions->take(5) as $permission)
                                <span class="px-2 py-0.5 text-xs bg-gray-100 text-gray-600 rounded">
                                    {{ str_replace('_', ' ', $permission->name) }}
                                </span>
                            @endforeach
                            @if($role->permissions->count() > 5)
                                <span class="px-2 py-0.5 text-xs bg-purple-100 text-purple-600 rounded">
                                    +{{ $role->permissions->count() - 5 }} more
                                </span>
                            @endif
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center justify-between pt-4 border-t border-gray-200">
                        <span class="text-xs text-gray-500">
                            {{ \App\Models\User::role($role->name)->count() }} users
                        </span>
                        @if($role->name !== 'Super Admin')
                            <a href="{{ route('super-admin.roles.show', $role) }}" 
                               class="text-sm text-purple-600 hover:text-purple-700 font-medium">
                                Edit Permissions
                            </a>
                        @else
                            <span class="text-xs text-gray-400">Full Access</span>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <!-- Permission Legend -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h3 class="font-semibold text-gray-900 mb-4">Permission Groups</h3>
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
            @foreach($permissions as $group => $perms)
                <div class="p-3 bg-gray-50 rounded-lg">
                    <p class="text-sm font-medium text-gray-700 capitalize">{{ $group }}</p>
                    <p class="text-xs text-gray-500">{{ $perms->count() }} permissions</p>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endsection
