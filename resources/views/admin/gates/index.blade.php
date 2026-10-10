@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Gates</h1>
            <p class="text-gray-600 mt-1">Entry points guards are assigned to.</p>
        </div>
        <a href="{{ route('admin.gates.create') }}" class="bg-blue-600 text-white px-5 py-2.5 rounded-lg font-medium hover:bg-blue-700">
            <i class="fas fa-plus mr-1"></i> Add Gate
        </a>
    </div>


    <div class="bg-white rounded-lg shadow overflow-hidden">
        @if ($gates->isEmpty())
            <div class="px-6 py-10 text-center text-gray-500">
                No gates yet. Add your Main Gate to get started.
            </div>
        @else
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-gray-500">
                    <tr>
                        <th class="px-6 py-3">Name</th>
                        <th class="px-6 py-3">Type</th>
                        <th class="px-6 py-3">Status</th>
                        <th class="px-6 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($gates as $gate)
                        <tr>
                            <td class="px-6 py-3 font-medium text-gray-900">{{ $gate->name }}</td>
                            <td class="px-6 py-3 text-gray-600">{{ ucfirst($gate->type) }}</td>
                            <td class="px-6 py-3">
                                <span class="px-2 py-0.5 rounded text-xs font-medium {{ $gate->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }}">
                                    {{ $gate->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-6 py-3 text-right">
                                <a href="{{ route('admin.gates.edit', $gate) }}" class="text-blue-600 hover:text-blue-800 text-xs font-medium">Edit</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
@endsection
