<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Services table - defines service types
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category'); // daily, maintenance, society, optional
            $table->string('icon')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->boolean('requires_attendance')->default(false); // for clock-in/out
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            
            $table->index(['category', 'is_enabled']);
        });

        // Service providers table
        Schema::create('service_providers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained()->onDelete('cascade');
            $table->foreignId('service_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('contact_number');
            $table->enum('availability', ['available', 'not_available'])->default('available');
            $table->boolean('is_daily_help')->default(false);
            $table->decimal('price', 10, 2)->nullable();
            $table->enum('price_type', ['per_day', 'per_month', 'per_visit'])->default('per_visit');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index(['society_id', 'service_id', 'status']);
        });

        // Service attendance table - for clock-in/out
        Schema::create('service_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained()->onDelete('cascade');
            $table->foreignId('service_provider_id')->constrained()->onDelete('cascade');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->date('attendance_date');
            $table->time('clock_in_time')->nullable();
            $table->time('clock_out_time')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->unique(['service_provider_id', 'attendance_date']);
            $table->index(['society_id', 'attendance_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_attendances');
        Schema::dropIfExists('service_providers');
        Schema::dropIfExists('services');
    }
};