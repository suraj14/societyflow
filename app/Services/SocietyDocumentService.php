<?php

namespace App\Services;

use App\Events\SocietyDocumentPublished;
use App\Models\SocietyDocument;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Upload, publish and serve society documents. Shared by the web
 * controllers and the mobile API, so the PDF checks, the private-disk
 * storage and the "who may read this file" rule cannot drift apart.
 */
class SocietyDocumentService
{
    public const DISK = 'local';

    public const ANNOUNCE_CHANNELS = ['email', 'sms', 'whatsapp'];

    /** Laravel rule set for the uploaded file itself. */
    public static function fileRules(bool $required = true): array
    {
        return [
            $required ? 'required' : 'nullable',
            'file',
            'mimes:pdf',
            'mimetypes:application/pdf',
            'max:' . SocietyDocument::MAX_UPLOAD_KB,
        ];
    }

    /**
     * The extension/mimetype rules trust what the client and libmagic say.
     * This reads the bytes: a real PDF starts with `%PDF-` (allowing the
     * small leading junk some generators emit) and has an EOF marker near
     * the end. A renamed executable or a truncated download fails here.
     */
    public function assertLooksLikePdf(UploadedFile $file): void
    {
        $handle = @fopen($file->getRealPath(), 'rb');
        if (!$handle) {
            throw ValidationException::withMessages(['file' => 'The uploaded file could not be read.']);
        }

        $head = (string) fread($handle, 1024);
        $size = $file->getSize();
        fseek($handle, max(0, $size - 2048));
        $tail = (string) fread($handle, 2048);
        fclose($handle);

        if (!str_contains($head, '%PDF-') || !str_contains($tail, '%%EOF')) {
            throw ValidationException::withMessages(['file' => 'This file is not a valid PDF (it may be corrupt or renamed).']);
        }
    }

    /** Store the file privately and create the row. */
    public function create(array $attributes, UploadedFile $file, User $uploader, ?SocietyDocument $previous = null): SocietyDocument
    {
        $this->assertLooksLikePdf($file);

        $societyId = $previous?->society_id ?? $attributes['society_id'];
        $path = $file->storeAs(
            "society-documents/{$societyId}",
            Str::uuid()->toString() . '.pdf',
            self::DISK
        );

        return SocietyDocument::create([
            'society_id'               => $societyId,
            'series_key'               => $previous?->series_key ?? Str::uuid()->toString(),
            'version_number'           => $previous ? $previous->nextVersionNumber() : 1,
            'version'                  => $attributes['version'],
            'title'                    => $attributes['title'],
            'category'                 => $attributes['category'],
            'description'              => $attributes['description'] ?? null,
            'effective_from'           => $attributes['effective_from'] ?? null,
            'status'                   => 'draft',
            'visible_to_guards'        => (bool) ($attributes['visible_to_guards'] ?? false),
            'requires_acknowledgement' => (bool) ($attributes['requires_acknowledgement'] ?? false),
            'file_path'                => $path,
            'original_filename'        => $this->safeFilename($file->getClientOriginalName()),
            'file_size'                => (int) $file->getSize(),
            'uploaded_by'              => $uploader->id,
        ]);
    }

    /**
     * Publish, and announce only when the admin asked to and only once per
     * row - re-publishing after an unpublish never re-sends.
     *
     * @param string[] $channels subset of ANNOUNCE_CHANNELS
     */
    public function publish(SocietyDocument $document, array $channels = []): void
    {
        $document->publish();

        $channels = array_values(array_intersect($channels, self::ANNOUNCE_CHANNELS));
        if ($channels === [] || $document->announced_at !== null) {
            return;
        }

        $document->update(['announced_at' => now()]);
        SocietyDocumentPublished::dispatch($document->fresh(), $channels);
    }

    /** Stream the PDF inline (default) or as a download. 404 if the file is gone. */
    public function fileResponse(SocietyDocument $document, bool $download = false): Response
    {
        $disk = Storage::disk(self::DISK);

        if (!$document->file_path || !$disk->exists($document->file_path)) {
            abort(404, 'The file for this document is missing. Please ask the society office to re-upload it.');
        }

        $name = $this->downloadName($document);
        $headers = [
            'Content-Type'           => 'application/pdf',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control'          => 'private, no-store',
        ];

        return $download
            ? $disk->download($document->file_path, $name, $headers)
            : response()->file($disk->path($document->file_path), $headers + [
                'Content-Disposition' => 'inline; filename="' . $name . '"',
            ]);
    }

    public function downloadName(SocietyDocument $document): string
    {
        return Str::slug($document->title . ' ' . $document->version) . '.pdf';
    }

    private function safeFilename(string $name): string
    {
        $base = Str::slug(pathinfo($name, PATHINFO_FILENAME)) ?: 'document';

        return Str::limit($base, 120, '') . '.pdf';
    }
}
