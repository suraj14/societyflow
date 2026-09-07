<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // This migration will properly fix the SQLite constraint issue
        // by recreating the flats table with the correct status enum values
        
        if (DB::getDriverName() === 'sqlite') {
            // Step 1: Create temporary table with correct schema
            Schema::create('flats_temp', function (Blueprint $table) {
                $table->id();
                $table->foreignId('society_id')->constrained()->onDelete('cascade');
                $table->foreignId('building_id')->nullable()->constrained()->onDelete('cascade');
                $table->string('property_type')->default('apartment');
                $table->foreignId('villa_area_id')->nullable()->constrained('villa_areas')->onDelete('cascade');
                $table->foreignId('owner_id')->nullable()->constrained('owners')->onDelete('set null');
                $table->string('flat_number');
                $table->string('villa_name')->nullable();
                $table->integer('floor')->nullable(); // Make nullable to handle existing data
                $table->enum('type', ['1BHK', '2BHK', '3BHK', '4BHK', '5BHK', 'Studio', 'Penthouse', 'Shop', 'Office'])->default('2BHK');
                $table->decimal('carpet_area', 8, 2)->nullable();
                $table->decimal('built_up_area', 8, 2)->nullable();
                $table->decimal('plot_area', 8, 2)->nullable();
                $table->integer('bedrooms')->nullable();
                $table->integer('bathrooms')->nullable();
                $table->boolean('has_garden')->default(false);
                $table->boolean('has_parking')->default(false);
                $table->integer('parking_slots')->default(0);
                $table->decimal('maintenance_amount', 10, 2)->default(0);
                // FIXED: Updated status enum with all valid values including on_rent
                $table->enum('status', ['occupied', 'vacant', 'maintenance', 'on_rent', 'under_maintenance'])->default('vacant');
                $table->json('amenities')->nullable();
                $table->text('description')->nullable();
                $table->timestamps();
                
                $table->unique(['building_id', 'flat_number']);
                $table->index(['society_id', 'status']);
                $table->index(['building_id', 'floor']);
            });
            
            // Step 2: Copy all data from original table to temp table
            // Use explicit column mapping to ensure data integrity
            $columns = [
                'id', 'society_id', 'building_id', 'property_type', 'villa_area_id', 
                'owner_id', 'flat_number', 'villa_name', 'floor', 'type', 
                'carpet_area', 'built_up_area', 'plot_area', 'bedrooms', 'bathrooms', 
                'has_garden', 'has_parking', 'parking_slots', 'maintenance_amount', 
                'status', 'amenities', 'description', 'created_at', 'updated_at'
            ];
            
            $columnList = implode(', ', $columns);
            DB::statement("INSERT INTO flats_temp ({$columnList}) SELECT {$columnList} FROM flats");
            
            // Step 3: Drop original table
            Schema::drop('flats');
            
            // Step 4: Rename temp table to original name
            Schema::rename('flats_temp', 'flats');
            
        } else {
            // For MySQL/PostgreSQL
            DB::statement("ALTER TABLE flats MODIFY COLUMN status ENUM('occupied', 'vacant', 'maintenance', 'on_rent', 'under_maintenance') DEFAULT 'vacant'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Update any records using new status values to valid old values
        DB::statement("UPDATE flats SET status = 'vacant' WHERE status IN ('on_rent', 'under_maintenance')");
        
        if (DB::getDriverName() === 'sqlite') {
            // Recreate with original constraint
            Schema::create('flats_temp', function (Blueprint $table) {
                $table->id();
                $table->foreignId('society_id')->constrained()->onDelete('cascade');
                $table->foreignId('building_id')->nullable()->constrained()->onDelete('cascade');
                $table->string('property_type')->default('apartment');
                $table->foreignId('villa_area_id')->nullable()->constrained('villa_areas')->onDelete('cascade');
                $table->foreignId('owner_id')->nullable()->constrained('owners')->onDelete('set null');
                $table->string('flat_number');
                $table->string('villa_name')->nullable();
                $table->integer('floor');
                $table->enum('type', ['1BHK', '2BHK', '3BHK', '4BHK', '5BHK', 'Studio', 'Penthouse', 'Shop', 'Office'])->default('2BHK');
                $table->decimal('carpet_area', 8, 2)->nullable();
                $table->decimal('built_up_area', 8, 2)->nullable();
                $table->decimal('plot_area', 8, 2)->nullable();
                $table->integer('bedrooms')->nullable();
                $table->integer('bathrooms')->nullable();
                $table->boolean('has_garden')->default(false);
                $table->boolean('has_parking')->default(false);
                $table->integer('parking_slots')->default(0);
                $table->decimal('maintenance_amount', 10, 2)->default(0);
                // Original status enum
                $table->enum('status', ['occupied', 'vacant', 'maintenance'])->default('vacant');
                $table->json('amenities')->nullable();
                $table->text('description')->nullable();
                $table->timestamps();
                
                $table->unique(['building_id', 'flat_number']);
                $table->index(['society_id', 'status']);
                $table->index(['building_id', 'floor']);
            });
            
            $columns = [
                'id', 'society_id', 'building_id', 'property_type', 'villa_area_id', 
                'owner_id', 'flat_number', 'villa_name', 'floor', 'type', 
                'carpet_area', 'built_up_area', 'plot_area', 'bedrooms', 'bathrooms', 
                'has_garden', 'has_parking', 'parking_slots', 'maintenance_amount', 
                'status', 'amenities', 'description', 'created_at', 'updated_at'
            ];
            
            $columnList = implode(', ', $columns);
            DB::statement("INSERT INTO flats_temp ({$columnList}) SELECT {$columnList} FROM flats");
            
            Schema::drop('flats');
            Schema::rename('flats_temp', 'flats');
            
        } else {
            // For MySQL/PostgreSQL
            DB::statement("ALTER TABLE flats MODIFY COLUMN status ENUM('occupied', 'vacant', 'maintenance') DEFAULT 'vacant'");
        }
    }
};
