<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // For SQLite, we need to recreate the table to change column constraints
        // First, let's check if we can simply update
        
        // Create a temporary table with the new structure
        Schema::create('flats_new', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained()->onDelete('cascade');
            $table->foreignId('building_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('property_type')->default('apartment'); // apartment or villa
            $table->foreignId('villa_area_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('owner_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('flat_number');
            $table->string('villa_name')->nullable();
            $table->integer('floor')->nullable();
            $table->string('type')->nullable();
            $table->decimal('carpet_area', 10, 2)->nullable();
            $table->decimal('built_up_area', 10, 2)->nullable();
            $table->decimal('plot_area', 10, 2)->nullable();
            $table->integer('bedrooms')->nullable();
            $table->integer('bathrooms')->nullable();
            $table->boolean('has_garden')->default(false);
            $table->boolean('has_parking')->default(false);
            $table->integer('parking_slots')->nullable();
            $table->decimal('maintenance_amount', 10, 2)->nullable();
            $table->enum('status', ['vacant', 'occupied', 'under_maintenance'])->default('vacant');
            $table->json('amenities')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // Copy data from old table to new table
        DB::statement('INSERT INTO flats_new (id, society_id, building_id, property_type, villa_area_id, owner_id, flat_number, villa_name, floor, type, carpet_area, built_up_area, plot_area, bedrooms, bathrooms, has_garden, has_parking, parking_slots, maintenance_amount, status, amenities, description, created_at, updated_at) SELECT id, society_id, building_id, COALESCE(property_type, "apartment"), villa_area_id, owner_id, flat_number, villa_name, floor, type, carpet_area, built_up_area, plot_area, bedrooms, bathrooms, COALESCE(has_garden, 0), COALESCE(has_parking, 0), parking_slots, maintenance_amount, status, amenities, description, created_at, updated_at FROM flats');

        // Drop old table
        Schema::drop('flats');

        // Rename new table to original name
        Schema::rename('flats_new', 'flats');
    }

    public function down(): void
    {
        // Reverse is complex for SQLite, skip for now
    }
};
