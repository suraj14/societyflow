@extends('layouts.app')

@section('title', 'Add Expense')
@section('page-title', 'Add Expense')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="flex items-center mb-6">
        <a href="{{ route('expenses.index') }}" class="text-gray-600 hover:text-gray-900 mr-4">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Add Expense</h1>
            <p class="text-gray-600 mt-1">Record a society/common expense, such as security, common electricity, water or gardening.</p>
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
        <form action="{{ route('expenses.store') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
            @csrf
            @include('partials.idempotency')
            @include('expenses._form', ['expense' => null])

            <div class="flex justify-end gap-3 pt-4 border-t">
                <a href="{{ route('expenses.index') }}" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">Cancel</a>
                <button type="submit" class="px-6 py-2 bg-purple-700 hover:bg-purple-800 text-white rounded-lg font-medium">
                    <i class="fas fa-save mr-2"></i>Save Expense
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
