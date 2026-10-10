<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Society Rules & Documents - official PDFs (rules, bye-laws, parking,
 * security SOPs) residents and guards can read without asking the office.
 *
 * Versioning: every upload is its own row, never an overwrite. Rows that
 * are versions of the same document share a `series_key`. The resident-
 * facing "current version" is simply the one row in a series whose status
 * is `published`; publishing a newer version moves the previously
 * published one to `archived` (see SocietyDocument::publish()). Archived
 * rows and their files are kept for admins - history is never deleted by
 * publishing.
 *
 * Files live on the private `local` disk (storage/app), never under
 * public/ - every read goes through an authorizing controller.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('society_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained()->cascadeOnDelete();
            $table->string('series_key', 36)->index();
            $table->unsignedInteger('version_number')->default(1);
            $table->string('version', 30);
            $table->string('title', 150);
            $table->string('category', 40);
            $table->text('description')->nullable();
            $table->date('effective_from')->nullable();
            $table->enum('status', ['draft', 'published', 'unpublished', 'archived'])->default('draft');
            $table->boolean('visible_to_guards')->default(false);
            $table->boolean('requires_acknowledgement')->default(false);
            $table->string('file_path');
            $table->string('original_filename');
            $table->unsignedInteger('file_size')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('announced_at')->nullable();
            $table->timestamps();

            $table->index(['society_id', 'status']);
            $table->unique(['series_key', 'version_number']);
        });

        Schema::create('society_document_acknowledgements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('version', 30);
            $table->timestamp('acknowledged_at');
            $table->timestamps();

            $table->unique(['society_document_id', 'user_id'], 'society_doc_ack_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('society_document_acknowledgements');
        Schema::dropIfExists('society_documents');
    }
};
