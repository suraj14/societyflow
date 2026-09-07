<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // For SQLite, we need to recreate the table with the new enum values
        if (DB::getDriverName() === 'sqlite') {
            // Create a temporary table with the correct enum values
            Schema::create('notices_temp', function (Blueprint $table) {
                $table->id();
                $table->foreignId('society_id')->constrained()->onDelete('cascade');
                $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
                $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
                $table->string('title');
                $table->text('content');
                $table->enum('type', ['general', 'urgent', 'maintenance', 'event', 'meeting'])->default('general');
                $table->enum('priority', ['low', 'medium', 'high'])->default('medium');
                $table->date('publish_date')->nullable();
                $table->date('expiry_date')->nullable();
                $table->json('target_audience')->nullable();
                $table->json('attachments')->nullable();
                $table->string('image')->nullable();
                $table->boolean('send_email')->default(false);
                $table->boolean('send_sms')->default(false);
                $table->enum('status', ['draft', 'pending', 'published', 'rejected', 'expired'])->default('draft');
                $table->timestamp('approved_at')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->timestamps();
                
                $table->index(['society_id', 'status']);
                $table->index(['publish_date', 'status']);
                $table->index(['type', 'priority']);
            });

            // Copy data from old table to new table, handling NULL publish_date
            DB::statement('INSERT INTO notices_temp (id, society_id, created_by, approved_by, title, content, type, priority, publish_date, expiry_date, target_audience, attachments, image, send_email, send_sms, status, approved_at, rejection_reason, created_at, updated_at) SELECT id, society_id, created_by, approved_by, title, content, type, priority, publish_date, expiry_date, target_audience, attachments, image, send_email, send_sms, status, approved_at, rejection_reason, created_at, updated_at FROM notices');

            // Drop the old table
            Schema::dropIfExists('notices');

            // Rename the temporary table
            Schema::rename('notices_temp', 'notices');
        } else {
            // For other databases, use ALTER TABLE
            DB::statement("ALTER TABLE notices MODIFY COLUMN status ENUM('draft', 'pending', 'published', 'rejected', 'expired') DEFAULT 'draft'");
        }
    }

    public function down(): void
    {
        // For SQLite, recreate with original enum values
        if (DB::getDriverName() === 'sqlite') {
            Schema::create('notices_temp', function (Blueprint $table) {
                $table->id();
                $table->foreignId('society_id')->constrained()->onDelete('cascade');
                $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
                $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
                $table->string('title');
                $table->text('content');
                $table->enum('type', ['general', 'urgent', 'maintenance', 'event', 'meeting'])->default('general');
                $table->enum('priority', ['low', 'medium', 'high'])->default('medium');
                $table->date('publish_date')->nullable();
                $table->date('expiry_date')->nullable();
                $table->json('target_audience')->nullable();
                $table->json('attachments')->nullable();
                $table->string('image')->nullable();
                $table->boolean('send_email')->default(false);
                $table->boolean('send_sms')->default(false);
                $table->enum('status', ['draft', 'published', 'expired'])->default('draft');
                $table->timestamp('approved_at')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->timestamps();
                
                $table->index(['society_id', 'status']);
                $table->index(['publish_date', 'status']);
                $table->index(['type', 'priority']);
            });

            // Copy data, converting unsupported statuses
            DB::statement("INSERT INTO notices_temp (id, society_id, created_by, approved_by, title, content, type, priority, publish_date, expiry_date, target_audience, attachments, image, send_email, send_sms, status, approved_at, rejection_reason, created_at, updated_at) SELECT id, society_id, created_by, approved_by, title, content, type, priority, publish_date, expiry_date, target_audience, attachments, image, send_email, send_sms, CASE WHEN status IN ('pending', 'rejected') THEN 'draft' ELSE status END, approved_at, rejection_reason, created_at, updated_at FROM notices");

            Schema::dropIfExists('notices');
            Schema::rename('notices_temp', 'notices');
        } else {
            DB::statement("ALTER TABLE notices MODIFY COLUMN status ENUM('draft', 'published', 'expired') DEFAULT 'draft'");
        }
    }
};