@extends('layouts.app')

@section('title', $previous ? 'Upload New Version' : 'Upload Document')
@section('page-title', $previous ? 'Upload New Version' : 'Upload Document')

@section('content')
<div class="container mx-auto px-4 py-6 sm:py-8 max-w-3xl">
    <a href="{{ $previous ? route('admin.society-documents.show', $previous) : route('admin.society-documents.index') }}" class="text-sm text-purple-600 hover:text-purple-800">
        <i class="fas fa-arrow-left mr-1"></i> Back
    </a>
    <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 mt-3">{{ $previous ? 'Upload new version' : 'Upload document' }}</h1>
    <p class="text-gray-500 mt-1 text-sm">
        @if ($previous)
            Replacing <strong>{{ $previous->title }}</strong> ({{ $previous->version }}). The current version stays live until you publish the new one; older versions are kept.
        @else
            PDF only, up to {{ (int) (\App\Models\SocietyDocument::MAX_UPLOAD_KB / 1024) }} MB. It is saved as a draft - nobody sees it until you publish.
        @endif
    </p>

    @if ($errors->any())
        <div class="mt-4 rounded-xl bg-red-50 border border-red-200 text-red-800 px-4 py-3 text-sm">Please fix the highlighted fields.</div>
    @endif

    <form method="POST" enctype="multipart/form-data"
          action="{{ $previous ? route('admin.society-documents.versions.store', $previous) : route('admin.society-documents.store') }}"
          class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 sm:p-6 mt-5 space-y-5">
        @csrf

        @if ($societies->isNotEmpty())
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Society <span class="text-red-500">*</span></label>
                <select name="society_id" required class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500">
                    <option value="">Select society</option>
                    @foreach ($societies as $id => $name)
                        <option value="{{ $id }}" @selected((string) old('society_id') === (string) $id)>{{ $name }}</option>
                    @endforeach
                </select>
                @error('society_id') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
        @endif

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">PDF file <span class="text-red-500">*</span></label>
            <label class="flex flex-col items-center justify-center gap-2 border-2 border-dashed border-purple-200 bg-purple-50/40 rounded-2xl px-4 py-8 cursor-pointer hover:bg-purple-50">
                <i class="fas fa-file-pdf text-3xl text-purple-500"></i>
                <span class="text-sm font-medium text-gray-700" id="sf-file-label">Choose a PDF</span>
                <input type="file" name="file" accept="application/pdf,.pdf" required class="sr-only"
                       onchange="document.getElementById('sf-file-label').textContent = this.files[0] ? this.files[0].name : 'Choose a PDF'">
            </label>
            @error('file') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        @include('admin.society-documents.partials.fields', [
            'doc' => $previous,
            'isUpload' => true,
            'versionDefault' => $previous ? 'v' . $previous->nextVersionNumber() : 'v1',
        ])

        <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-3 pt-2">
            <a href="{{ $previous ? route('admin.society-documents.show', $previous) : route('admin.society-documents.index') }}"
               class="px-5 py-2.5 rounded-xl border border-gray-200 text-gray-700 text-sm font-medium text-center hover:bg-gray-50">Cancel</a>
            <button type="submit" class="px-6 py-2.5 rounded-xl bg-gradient-to-r from-purple-600 to-indigo-600 text-white text-sm font-semibold shadow hover:from-purple-700 hover:to-indigo-700">
                <i class="fas fa-upload mr-1"></i> Upload as draft
            </button>
        </div>
    </form>
</div>
@endsection
