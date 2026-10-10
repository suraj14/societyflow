@extends('layouts.app')

@section('title', 'Society Info')
@section('page-title', 'Society Info')

@section('content')
<div class="container mx-auto px-4 py-6 sm:py-8 max-w-6xl">
    <div class="bg-gradient-to-r from-blue-600 via-purple-600 to-indigo-700 rounded-2xl shadow-lg p-6 sm:p-8 mb-6 text-white">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <p class="text-purple-200 text-xs font-semibold uppercase tracking-wider">Society Info &middot; Documents</p>
                <h1 class="text-2xl sm:text-3xl font-bold mt-1">Manage Documents</h1>
                <p class="text-purple-100 mt-1 text-sm">Official society PDFs residents and guards can read any time.</p>
            </div>
            <a href="{{ route('admin.society-documents.create') }}"
               class="inline-flex items-center justify-center gap-2 bg-white text-purple-700 px-5 py-2.5 rounded-xl font-semibold shadow hover:bg-purple-50 transition">
                <i class="fas fa-upload"></i> Upload PDF
            </a>
        </div>
    </div>

    {{-- Same three sections as the Society Info page this screen belongs to. --}}
    <nav aria-label="Society Info sections" class="grid grid-cols-3 gap-1 p-1 mb-5 bg-gray-100 rounded-2xl max-w-xl">
        <a href="{{ route('society-rules.index', ['tab' => 'details']) }}" class="flex items-center justify-center gap-2 px-2 py-2.5 rounded-xl text-xs sm:text-sm font-semibold text-gray-600 hover:text-gray-900">
            <i class="fas fa-building" aria-hidden="true"></i> Society Details
        </a>
        <a href="{{ route('society-rules.index', ['tab' => 'committee']) }}" class="flex items-center justify-center gap-2 px-2 py-2.5 rounded-xl text-xs sm:text-sm font-semibold text-gray-600 hover:text-gray-900">
            <i class="fas fa-users" aria-hidden="true"></i> Committee
        </a>
        <a href="{{ route('admin.society-documents.index') }}" aria-current="page" class="flex items-center justify-center gap-2 px-2 py-2.5 rounded-xl text-xs sm:text-sm font-semibold bg-white text-purple-700 shadow-sm">
            <i class="fas fa-book-open" aria-hidden="true"></i> Documents
        </a>
    </nav>


    <form method="GET" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 mb-4 grid grid-cols-1 sm:grid-cols-4 gap-3">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Search title"
               class="px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
        <select name="category" class="px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500">
            <option value="">All categories</option>
            @foreach ($categories as $key => $label)
                <option value="{{ $key }}" @selected(request('category') === $key)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="status" class="px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500">
            <option value="">Any status (latest version)</option>
            @foreach (['draft', 'published', 'unpublished', 'archived'] as $s)
                <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s) }}</option>
            @endforeach
        </select>
        <div class="flex gap-2">
            <button class="flex-1 bg-purple-600 text-white rounded-xl text-sm font-semibold px-4 py-2.5 hover:bg-purple-700">Filter</button>
            @if (request()->hasAny(['q', 'category', 'status']))
                <a href="{{ route('admin.society-documents.index') }}" class="px-4 py-2.5 rounded-xl text-sm border border-gray-200 text-gray-600 hover:bg-gray-50">Clear</a>
            @endif
        </div>
    </form>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        @if ($documents->isEmpty())
            <div class="px-6 py-14 text-center">
                <div class="w-14 h-14 mx-auto rounded-2xl bg-purple-50 text-purple-600 flex items-center justify-center mb-4">
                    <i class="fas fa-file-pdf text-2xl"></i>
                </div>
                <p class="font-semibold text-gray-900">No documents yet</p>
                <p class="text-sm text-gray-500 mt-1">Upload your society rules, bye-laws or parking rules as a PDF.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-5 py-3">Document</th>
                            <th class="px-5 py-3">Category</th>
                            <th class="px-5 py-3">Latest version</th>
                            <th class="px-5 py-3">Live version</th>
                            <th class="px-5 py-3">Audience</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($documents as $doc)
                            <tr class="hover:bg-gray-50/60">
                                <td class="px-5 py-3">
                                    <a href="{{ route('admin.society-documents.show', $doc) }}" class="font-semibold text-gray-900 hover:text-purple-700">{{ $doc->title }}</a>
                                    @if (auth()->user()->hasRole('Super Admin'))
                                        <p class="text-xs text-gray-500">{{ $doc->society?->name }}</p>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-gray-600">{{ $doc->category_label }}</td>
                                <td class="px-5 py-3">
                                    <span class="text-gray-900 font-medium">{{ $doc->version }}</span>
                                    @include('admin.society-documents.partials.status-badge', ['status' => $doc->status])
                                </td>
                                <td class="px-5 py-3">
                                    @if (isset($publishedSeries[$doc->series_key]))
                                        <span class="inline-flex items-center gap-1 text-green-700 font-medium"><i class="fas fa-circle text-[6px]"></i> {{ $publishedSeries[$doc->series_key] }}</span>
                                    @else
                                        <span class="text-gray-400">Not live</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-gray-600">
                                    Residents{{ $doc->visible_to_guards ? ' + Guards' : '' }}
                                    @if ($doc->requires_acknowledgement)
                                        <span class="block text-xs text-purple-600">{{ $doc->acknowledgements_count }} acknowledged</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-right whitespace-nowrap">
                                    <a href="{{ route('admin.society-documents.show', $doc) }}" class="text-purple-600 hover:text-purple-800 text-xs font-semibold">Manage</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-5 py-3 border-t border-gray-100">{{ $documents->links() }}</div>
        @endif
    </div>
</div>
@endsection
