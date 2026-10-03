@extends('layouts.app')

@section('title', 'Access Passes')
@section('page-title', 'Access Passes')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Access Passes</h1>
            <p class="text-gray-600 mt-1">Recurring staff and temporary service providers who can scan straight through the gate.</p>
        </div>
        <a href="{{ route('access-passes.create') }}" class="bg-purple-700 hover:bg-purple-800 text-white px-5 py-2.5 rounded-lg font-medium inline-flex items-center">
            <i class="fas fa-plus mr-2"></i> New Pass
        </a>
    </div>


    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        @if($passes->isEmpty())
            <div class="p-10 text-center text-gray-500">
                <i class="fas fa-id-card text-4xl mb-3 text-gray-300"></i>
                <p>No access passes yet.</p>
            </div>
        @else
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-gray-500 uppercase text-xs">
                    <tr>
                        <th class="px-6 py-3">Person</th>
                        <th class="px-6 py-3">Type</th>
                        <th class="px-6 py-3">Property</th>
                        <th class="px-6 py-3">Status</th>
                        <th class="px-6 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($passes as $pass)
                        <tr>
                            <td class="px-6 py-4">
                                <div class="font-medium text-gray-900">{{ $pass->person_name }}</div>
                                <div class="text-gray-500 text-xs">{{ $pass->category }}</div>
                            </td>
                            <td class="px-6 py-4 text-gray-700">
                                {{ $pass->pass_type === 'recurring_staff' ? 'Recurring Staff' : 'Temporary Service' }}
                            </td>
                            <td class="px-6 py-4 text-gray-700">
                                @if($pass->flat)
                                    {{ $pass->flat->property_type === 'villa' ? 'Villa ' . $pass->flat->flat_number : ($pass->flat->building->name ?? 'Building') . ' - ' . $pass->flat->flat_number }}
                                @else
                                    <span class="text-purple-700 font-medium">Entire Society</span>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-2 py-0.5 rounded-full text-xs font-semibold {{ $pass->status === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    {{ ucfirst($pass->status) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <a href="{{ route('access-passes.show', $pass) }}" class="text-blue-600 hover:underline">View QR</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="px-6 py-4">{{ $passes->links() }}</div>
        @endif
    </div>
</div>
@endsection
