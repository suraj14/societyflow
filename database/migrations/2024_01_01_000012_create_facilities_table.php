<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('type', ['clubhouse', 'gym', 'swimming_pool', 'hall', 'playground', 'parking', 'other'])->default('other');
            $table->decimal('booking_charge', 8, 2)->default(0);
            $table->integer('capacity')->nullable();
            $table->json('amenities')->nullable();
            $table->json('rules')->nullable();
            $table->time('opening_time')->nullable();
            $table->time('closing_time')->nullable();
            $table->json('available_days')->nullable(); // ['monday', 'tuesday', ...]
            $table->integer('advance_booking_days')->default(30);
            $table->integer('max_booking_hours')->default(4);
            $table->boolean('requires_approval')->default(true);
            $table->string('image')->nullable();
            $table->enum('status', ['active', 'inactive', 'maintenance'])->default('active');
            $table->timestamps();
            
            $table->index(['society_id', 'status']);
            $table->index(['type', 'status']);
        });

        Schema::create('facility_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained()->onDelete('cascade');
            $table->foreignId('facility_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('flat_id')->constrained()->onDelete('cascade');
            $table->string('booking_number')->unique();
            $table->date('booking_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->text('purpose')->nullable();
            $table->integer('expected_guests')->default(0);
            $table->decimal('booking_amount', 8, 2)->default(0);
            $table->decimal('security_deposit', 8, 2)->default(0);
            $table->enum('payment_status', ['pending', 'paid', 'refunded'])->default('pending');
            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled', 'completed'])->default('pending');
            $table->text('admin_notes')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            
            $table->index(['society_id', 'status']);
            $table->index(['facility_id', 'booking_date']);
            $table->index(['user_id', 'status']);
            $table->index(['booking_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facility_bookings');
        Schema::dropIfExists('facilities');
    }
};