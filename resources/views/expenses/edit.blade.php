@extends('layouts.app')

@section('title', 'Edit Expense')
@section('page-title', 'Edit Expense')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="flex items-center mb-6">
        <a href="{{ route('expenses.show', $expense) }}" class="text-gray-600 hover:text-gray-900 mr-4">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Edit Expense</h1>
            <p class="text-gray-600 mt-1">{{ $expense->title }} &middot; {{ $expense->expense_date?->format('d M Y') }}</p>
        </div>
    </div>

    @if($errors->any())
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-lg">
            <ul class="list-disc list-inside text-sm">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="max-w-3xl bg-white rounded-lg shadow-md p-6">
        <form action="{{ route('expenses.update', $expense) }}" method="POST" enctype="multipart/form-data" class="space-y-6">
            @csrf
            @method('PUT')
            @include('expenses._form', ['expense' => $expense])

            <div class="flex justify-end gap-3 pt-4 border-t">
                <a href="{{ route('expenses.show', $expense) }}" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">Cancel</a>
                <button type="submit" class="px-6 py-2 bg-purple-700 hover:bg-purple-800 text-white rounded-lg font-medium">
                    <i class="fas fa-save mr-2"></i>Save Changes
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
