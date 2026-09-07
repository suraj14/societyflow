<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visitors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained()->onDelete('cascade');
            $table->foreignId('flat_id')->constrained()->onDelete('cascade');
            $table->foreignId('host_user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->string('visitor_name');
            $table->string('visitor_phone');
            $table->string('visitor_id_proof')->nullable();
            $table->string('visitor_id_number')->nullable();
            $table->enum('visitor_type', ['guest', 'delivery', 'cab', 'service', 'other'])->default('guest');
            $table->text('purpose')->nullable();
            $table->integer('expected_count')->default(1);
            $table->string('vehicle_number')->nullable();
            $table->datetime('expected_entry_time');
            $table->datetime('expected_exit_time')->nullable();
            $table->datetime('actual_entry_time')->nullable();
            $table->datetime('actual_exit_time')->nullable();
            $table->enum('approval_status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->enum('entry_status', ['pending', 'entered', 'exited'])->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->text('security_notes')->nullable();
            $table->string('photo')->nullable();
            $table->timestamps();
            
            $table->index(['society_id', 'entry_status']);
            $table->index(['flat_id', 'expected_entry_time']);
            $table->index(['host_user_id', 'approval_status']);
            $table->index(['expected_entry_time', 'entry_status']);
            $table->index(['visitor_phone', 'expected_entry_time']);
        });

        Schema::create('visitor_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visitor_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('action', ['created', 'approved', 'rejected', 'entry', 'exit', 'updated'])->default('created');
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index(['visitor_id', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visitor_logs');
        Schema::dropIfExists('visitors');
    }
};