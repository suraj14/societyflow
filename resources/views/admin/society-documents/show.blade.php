@extends('layouts.app')

@section('title', $document->title)
@section('page-title', 'Rules & Documents')

@section('content')
<div class="container mx-auto px-4 py-6 sm:py-8 max-w-6xl">
    <a href="{{ route('admin.society-documents.index') }}" class="text-sm text-purple-600 hover:text-purple-800">
        <i class="fas fa-arrow-left mr-1"></i> Rules &amp; Documents
    </a>

    <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4 mt-3 mb-6">
        <div>
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">{{ $document->title }}</h1>
                <span class="px-2.5 py-0.5 rounded-full bg-purple-50 text-purple-700 text-xs font-semibold">{{ $document->version }}</span>
                @include('admin.society-documents.partials.status-badge', ['status' => $document->status])
            </div>
            <p class="text-gray-500 text-sm mt-1">
                {{ $document->category_label }}
                @if ($document->effective_from) &middot; Effective {{ $document->effective_from->format('d M Y') }} @endif
                &middot; {{ $document->file_size_label }}
                @if (auth()->user()->hasRole('Super Admin')) &middot; {{ $document->society?->name }} @endif
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.society-documents.file', $document) }}" target="_blank" rel="noopener"
               class="px-4 py-2 rounded-xl border border-gray-200 text-sm font-medium text-gray-700 hover:bg-gray-50"><i class="fas fa-eye mr-1"></i> View</a>
            <a href="{{ route('admin.society-documents.file', [$document, 'download' => 1]) }}"
               class="px-4 py-2 rounded-xl border border-gray-200 text-sm font-medium text-gray-700 hover:bg-gray-50"><i class="fas fa-download mr-1"></i> Download</a>
            <a href="{{ route('admin.society-documents.edit', $document) }}"
               class="px-4 py-2 rounded-xl border border-gray-200 text-sm font-medium text-gray-700 hover:bg-gray-50"><i class="fas fa-pen mr-1"></i> Edit details</a>
            <a href="{{ route('admin.society-documents.new-version', $document) }}"
               class="px-4 py-2 rounded-xl bg-purple-600 text-white text-sm font-semibold hover:bg-purple-700"><i class="fas fa-code-branch mr-1"></i> Upload new version</a>
        </div>
    </div>


    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            {{-- Publishing --}}
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                <h2 class="font-semibold text-gray-900 mb-3">Visibility</h2>
                @if ($document->status === 'published')
                    <p class="text-sm text-gray-600 mb-4">
                        <i class="fas fa-circle-check text-green-600 mr-1"></i>
                        Live for residents{{ $document->visible_to_guards ? ' and guards' : '' }} since {{ $document->published_at?->format('d M Y, h:i A') }}.
                        @if ($document->announced_at) Announced {{ $document->announced_at->format('d M Y') }}. @endif
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <form method="POST" action="{{ route('admin.society-documents.unpublish', $document) }}">@csrf
                            <button class="px-4 py-2 rounded-xl border border-gray-200 text-sm font-medium text-gray-700 hover:bg-gray-50">Unpublish</button>
                        </form>
                        <form method="POST" action="{{ route('admin.society-documents.archive', $document) }}" onsubmit="return confirm('Archive this version? Residents will no longer see it.')">@csrf
                            <button class="px-4 py-2 rounded-xl border border-gray-200 text-sm font-medium text-gray-700 hover:bg-gray-50">Archive</button>
                        </form>
                    </div>
                @else
                    <form method="POST" action="{{ route('admin.society-documents.publish', $document) }}" x-data="{ announce: false }" class="space-y-3">
                        @csrf
                        <p class="text-sm text-gray-600">
                            @if ($document->status === 'archived')
                                This is an older version kept for the record. Publishing it makes it the current version again.
                            @else
                                Not visible to residents yet.
                            @endif
                            Publishing replaces any other live version of this document (that one is archived, not deleted).
                        </p>
                        @if (!$document->announced_at)
                            <label class="flex items-center gap-2 text-sm text-gray-800">
                                <input type="checkbox" name="announce" value="1" x-model="announce" class="rounded text-purple-600 focus:ring-purple-500">
                                Announce to {{ $document->visible_to_guards ? 'residents and guards' : 'residents' }}
                            </label>
                            <div x-show="announce" x-cloak class="flex flex-wrap gap-3 pl-6">
                                @foreach ($channels as $ch)
                                    <label class="flex items-center gap-1.5 text-sm text-gray-700">
                                        <input type="checkbox" name="channels[]" value="{{ $ch }}" @checked($ch === 'email') class="rounded text-purple-600 focus:ring-purple-500">
                                        {{ $ch === 'sms' ? 'SMS' : ($ch === 'whatsapp' ? 'WhatsApp' : 'Email') }}
                                    </label>
                                @endforeach
                            </div>
                        @else
                            <p class="text-xs text-gray-500">Already announced on {{ $document->announced_at->format('d M Y') }} - it will not be sent again.</p>
                        @endif
                        <div class="flex flex-wrap gap-2">
                            <button class="px-5 py-2 rounded-xl bg-green-600 text-white text-sm font-semibold hover:bg-green-700"><i class="fas fa-globe mr-1"></i> Publish</button>
                        </div>
                    </form>
                    @if ($document->status === 'draft' && !$document->published_at)
                        <form method="POST" action="{{ route('admin.society-documents.destroy', $document) }}" class="mt-3" onsubmit="return confirm('Delete this draft permanently?')">
                            @csrf @method('DELETE')
                            <button class="text-xs text-red-600 hover:text-red-800 font-medium"><i class="fas fa-trash mr-1"></i> Delete draft</button>
                        </form>
                    @elseif ($document->status !== 'archived')
                        <form method="POST" action="{{ route('admin.society-documents.archive', $document) }}" class="mt-3">@csrf
                            <button class="text-xs text-gray-600 hover:text-gray-800 font-medium"><i class="fas fa-box-archive mr-1"></i> Archive</button>
                        </form>
                    @endif
                @endif
            </div>

            {{-- Inline preview --}}
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <object data="{{ route('admin.society-documents.file', $document) }}" type="application/pdf" class="w-full h-[70vh]">
                    <div class="p-6 text-sm text-gray-600">
                        Your browser cannot show the PDF here.
                        <a href="{{ route('admin.society-documents.file', $document) }}" target="_blank" class="text-purple-600 font-medium">Open it in a new tab</a>.
                    </div>
                </object>
            </div>
        </div>

        <div class="space-y-6">
            @if ($document->description)
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                    <h2 class="font-semibold text-gray-900 mb-2">About</h2>
                    <p class="text-sm text-gray-600 whitespace-pre-line">{{ $document->description }}</p>
                </div>
            @endif

            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                <h2 class="font-semibold text-gray-900 mb-3">Version history</h2>
                <ul class="space-y-2">
                    @foreach ($versions as $v)
                        <li class="flex items-center justify-between gap-2 p-2.5 rounded-xl {{ $v->id === $document->id ? 'bg-purple-50' : 'hover:bg-gray-50' }}">
                            <a href="{{ route('admin.society-documents.show', $v) }}" class="min-w-0">
                                <span class="text-sm font-semibold text-gray-900">{{ $v->version }}</span>
                                @include('admin.society-documents.partials.status-badge', ['status' => $v->status])
                                <span class="block text-xs text-gray-500 truncate">{{ $v->created_at->format('d M Y') }} &middot; {{ $v->uploader?->name ?? 'Unknown' }}</span>
                            </a>
                            <a href="{{ route('admin.society-documents.file', [$v, 'download' => 1]) }}" class="text-gray-400 hover:text-purple-600" title="Download {{ $v->version }}"><i class="fas fa-download"></i></a>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                <h2 class="font-semibold text-gray-900 mb-1">Acknowledgements</h2>
                @if ($document->requires_acknowledgement)
                    <p class="text-sm text-gray-500 mb-3">{{ $acknowledgements->count() }} of {{ $audienceCount }} residents confirmed reading {{ $document->version }}.</p>
                    @forelse ($acknowledgements as $ack)
                        <div class="flex items-center justify-between py-1.5 text-sm">
                            <span class="text-gray-800 truncate">{{ $ack->user?->name ?? 'Former user' }}</span>
                            <span class="text-xs text-gray-500 whitespace-nowrap">{{ $ack->acknowledged_at->format('d M, h:i A') }}</span>
                        </div>
                    @empty
                        <p class="text-sm text-gray-400">Nobody yet.</p>
                    @endforelse
                @else
                    <p class="text-sm text-gray-500">Not requested for this document. Turn it on in <a class="text-purple-600" href="{{ route('admin.society-documents.edit', $document) }}">Edit details</a> for important rules.</p>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
