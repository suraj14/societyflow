@extends('layouts.app')

@section('title', 'Edit Document Details')
@section('page-title', 'Edit Document Details')

@section('content')
<div class="container mx-auto px-4 py-6 sm:py-8 max-w-3xl">
    <a href="{{ route('admin.society-documents.show', $document) }}" class="text-sm text-purple-600 hover:text-purple-800">
        <i class="fas fa-arrow-left mr-1"></i> Back
    </a>
    <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 mt-3">Edit details</h1>
    <p class="text-gray-500 mt-1 text-sm">Changes the details of {{ $document->version }} only. To change the PDF itself, upload a new version.</p>

    <form method="POST" action="{{ route('admin.society-documents.update', $document) }}"
          class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 sm:p-6 mt-5 space-y-5">
        @csrf
        @method('PUT')

        @include('admin.society-documents.partials.fields', ['doc' => $document])

        <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-3 pt-2">
            <a href="{{ route('admin.society-documents.show', $document) }}" class="px-5 py-2.5 rounded-xl border border-gray-200 text-gray-700 text-sm font-medium text-center hover:bg-gray-50">Cancel</a>
            <button type="submit" class="px-6 py-2.5 rounded-xl bg-gradient-to-r from-purple-600 to-indigo-600 text-white text-sm font-semibold shadow hover:from-purple-700 hover:to-indigo-700">Save details</button>
        </div>
    </form>
</div>
@endsection
