@extends('layouts.app')

@section('title', 'Expense Categories')
@section('page-title', 'Expense Categories')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="flex items-center mb-6">
        <a href="{{ route('expenses.index') }}" class="text-gray-600 hover:text-gray-900 mr-4">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Expense Categories</h1>
            <p class="text-gray-600 mt-1">The financial report groups society spend by these. Hide a category you don't use; add one only if nothing here fits.</p>
        </div>
    </div>

    @if(session('success'))
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-lg">{{ session('success') }}</div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-1">
            <form action="{{ route('expenses.categories.store') }}" method="POST" class="bg-white rounded-lg shadow-md p-5">
                @csrf
                <label for="name" class="block text-sm font-medium text-gray-700 mb-1">New category</label>
                <input type="text" name="name" id="name" required maxlength="100" value="{{ old('name') }}"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500" placeholder="e.g., Water Tanker">
                @error('name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                <button type="submit" class="mt-3 w-full px-4 py-2 bg-purple-700 hover:bg-purple-800 text-white rounded-lg font-medium"><i class="fas fa-plus mr-1"></i> Add</button>
            </form>
        </div>

        <div class="lg:col-span-2 bg-white rounded-lg shadow-md overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-gray-500 uppercase text-xs">
                    <tr>
                        <th class="px-6 py-3">Category</th>
                        <th class="px-6 py-3 text-right">Expenses</th>
                        <th class="px-6 py-3">Status</th>
                        <th class="px-6 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($categories as $category)
                        <tr>
                            <td class="px-6 py-3 font-medium text-gray-900">{{ $category->name }}</td>
                            <td class="px-6 py-3 text-right text-gray-700">{{ $category->expenses_count }}</td>
                            <td class="px-6 py-3">
                                <span class="px-2 py-0.5 rounded-full text-xs font-semibold {{ $category->status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">
                                    {{ $category->status === 'active' ? 'Active' : 'Hidden' }}
                                </span>
                            </td>
                            <td class="px-6 py-3 text-right">
                                <form action="{{ route('expenses.categories.toggle', $category) }}" method="POST" class="inline">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="text-purple-600 hover:underline text-sm">{{ $category->status === 'active' ? 'Hide' : 'Enable' }}</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
