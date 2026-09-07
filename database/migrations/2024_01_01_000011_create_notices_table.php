<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained()->onDelete('cascade');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->string('title');
            $table->text('content');
            $table->enum('type', ['general', 'urgent', 'maintenance', 'event', 'meeting'])->default('general');
            $table->enum('priority', ['low', 'medium', 'high'])->default('medium');
            $table->date('publish_date');
            $table->date('expiry_date')->nullable();
            $table->json('target_audience')->nullable(); // buildings, flats, roles
            $table->json('attachments')->nullable();
            $table->boolean('send_email')->default(false);
            $table->boolean('send_sms')->default(false);
            $table->enum('status', ['draft', 'published', 'expired'])->default('draft');
            $table->timestamps();
            
            $table->index(['society_id', 'status']);
            $table->index(['publish_date', 'status']);
            $table->index(['type', 'priority']);
        });

        Schema::create('notice_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notice_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->timestamp('read_at');
            
            $table->unique(['notice_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notice_reads');
        Schema::dropIfExists('notices');
    }
};