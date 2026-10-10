@extends('layouts.super-admin')

@section('title', $package->name)
@section('page-title', 'Package Details')

@section('content')
<div class="max-w-4xl space-y-6">
    <div class="flex items-center justify-between">
        <a href="{{ route('super-admin.packages.index') }}" class="text-blue-600 hover:text-blue-800 text-sm">
            <i class="fas fa-arrow-left mr-1"></i> Back to Packages
        </a>
        <a href="{{ route('super-admin.packages.edit', $package) }}" class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50">
            <i class="fas fa-edit mr-2"></i>Edit
        </a>
    </div>

    <div class="bg-white rounded-lg shadow">
        <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
            <h2 class="text-lg font-semibold text-gray-800">{{ $package->name }}
                @if($package->is_popular)<span class="ml-2 px-2 text-xs leading-5 font-semibold rounded-full bg-orange-100 text-orange-800">Popular</span>@endif
            </h2>
            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $package->status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">{{ ucfirst($package->status) }}</span>
        </div>
        <dl class="grid grid-cols-1 sm:grid-cols-3 gap-x-6 gap-y-4 p-6 text-sm">
            <div><dt class="text-gray-500">Monthly price</dt><dd class="font-medium text-gray-900">&#8377;{{ number_format($package->monthly_price ?? 0, 2) }}</dd></div>
            <div><dt class="text-gray-500">Yearly price</dt><dd class="font-medium text-gray-900">&#8377;{{ number_format($package->yearly_price ?? 0, 2) }}</dd></div>
            <div><dt class="text-gray-500">Trial</dt><dd class="font-medium text-gray-900">{{ (int) $package->trial_days }} day(s)</dd></div>
            <div><dt class="text-gray-500">Max flats</dt><dd class="font-medium text-gray-900">{{ $package->max_flats ?: 'Unlimited' }}</dd></div>
            <div><dt class="text-gray-500">Max users</dt><dd class="font-medium text-gray-900">{{ $package->max_users ?: 'Unlimited' }}</dd></div>
            <div><dt class="text-gray-500">Max staff</dt><dd class="font-medium text-gray-900">{{ $package->max_staff ?: 'Unlimited' }}</dd></div>
            @if($package->description)
                <div class="sm:col-span-3"><dt class="text-gray-500">Description</dt><dd class="text-gray-900">{{ $package->description }}</dd></div>
            @endif
        </dl>
    </div>

    <div class="bg-white rounded-lg shadow">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-base font-semibold text-gray-800">Societies on this package ({{ $package->subscriptions->count() }})</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Society</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Billing cycle</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ends</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($package->subscriptions as $subscription)
                        <tr>
                            <td class="px-6 py-3 text-sm text-gray-900">{{ $subscription->society->name ?? 'N/A' }}</td>
                            <td class="px-6 py-3 text-sm text-gray-900">{{ ucfirst($subscription->billing_cycle) }}</td>
                            <td class="px-6 py-3 text-sm text-gray-900">{{ ucfirst($subscription->status) }}</td>
                            <td class="px-6 py-3 text-sm text-gray-900">{{ $subscription->end_date ? $subscription->end_date->format('d M Y') : '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-6 py-4 text-center text-gray-500">No society uses this package yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
