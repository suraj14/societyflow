<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Parking Areas (Basement, Ground Floor, etc.)
        Schema::create('parking_areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained()->onDelete('cascade');
            $table->string('name'); // Basement Level 1, Ground Floor, etc.
            $table->string('code')->nullable(); // B1, GF, etc.
            $table->text('description')->nullable();
            $table->integer('total_slots')->default(0);
            $table->enum('status', ['active', 'inactive', 'maintenance'])->default('active');
            $table->timestamps();
            
            $table->unique(['society_id', 'name']);
            $table->unique(['society_id', 'code']);
        });

        // Individual Parking Slots
        Schema::create('parking_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained()->onDelete('cascade');
            $table->foreignId('parking_area_id')->constrained()->onDelete('cascade');
            $table->string('slot_number'); // A1, B2, etc.
            $table->enum('slot_type', ['car', 'bike', 'both'])->default('car');
            $table->enum('status', ['available', 'occupied', 'reserved', 'maintenance'])->default('available');
            $table->decimal('monthly_rent', 8, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->unique(['parking_area_id', 'slot_number']);
        });

        // Parking Assignments to Residents
        Schema::create('parking_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained()->onDelete('cascade');
            $table->foreignId('parking_slot_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('flat_id')->nullable()->constrained()->onDelete('set null');
            $table->string('vehicle_number');
            $table->string('vehicle_type'); // Car, Bike, SUV, etc.
            $table->string('vehicle_model')->nullable();
            $table->date('assigned_date');
            $table->date('expiry_date')->nullable();
            $table->enum('status', ['active', 'expired', 'cancelled'])->default('active');
            $table->decimal('monthly_rent', 8, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Visitor Parking
        Schema::create('visitor_parking', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained()->onDelete('cascade');
            $table->foreignId('parking_slot_id')->constrained()->onDelete('cascade');
            $table->foreignId('requested_by')->constrained('users')->onDelete('cascade');
            $table->string('visitor_name');
            $table->string('visitor_phone');
            $table->string('vehicle_number');
            $table->string('vehicle_type');
            $table->datetime('start_time');
            $table->datetime('end_time');
            $table->enum('status', ['active', 'completed', 'cancelled', 'overstayed'])->default('active');
            $table->text('purpose')->nullable();
            $table->timestamps();
        });

        // Parking Violations
        Schema::create('parking_violations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained()->onDelete('cascade');
            $table->foreignId('parking_slot_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('reported_by')->constrained('users')->onDelete('cascade');
            $table->string('vehicle_number');
            $table->enum('violation_type', ['unauthorized_parking', 'overstay', 'wrong_slot', 'blocking', 'other']);
            $table->text('description');
            $table->json('images')->nullable(); // Store image paths
            $table->datetime('violation_time');
            $table->decimal('fine_amount', 8, 2)->nullable();
            $table->enum('status', ['reported', 'acknowledged', 'resolved', 'dismissed'])->default('reported');
            $table->foreignId('resolved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->datetime('resolved_at')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parking_violations');
        Schema::dropIfExists('visitor_parking');
        Schema::dropIfExists('parking_assignments');
        Schema::dropIfExists('parking_slots');
        Schema::dropIfExists('parking_areas');
    }
};