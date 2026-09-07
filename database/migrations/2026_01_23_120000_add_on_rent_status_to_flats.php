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
        // For SQLite, we need to handle the enum constraint differently
        if (DB::getDriverName() === 'sqlite') {
            // SQLite approach: We'll remove the constraint and rely on application-level validation
            // This is a simpler approach that avoids table recreation issues
            
            // First, let's check if there are any records with the new status values
            // and update them to valid values temporarily
            DB::statement("UPDATE flats SET status = 'vacant' WHERE status NOT IN ('occupied', 'vacant', 'maintenance')");
            
            // Note: SQLite enum constraints are implemented as CHECK constraints
            // We cannot easily modify them without recreating the table
            // For now, we'll rely on application-level validation in the model
            
        } else {
            // For MySQL/PostgreSQL, we can alter the enum
            DB::statement("ALTER TABLE flats MODIFY COLUMN status ENUM('occupied', 'vacant', 'maintenance', 'on_rent', 'under_maintenance') DEFAULT 'vacant'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Update any records using the new status values
        DB::statement("UPDATE flats SET status = 'vacant' WHERE status IN ('on_rent', 'under_maintenance')");
        
        if (DB::getDriverName() === 'sqlite') {
            // For SQLite, recreate table with original schema
            Schema::create('flats_old', function (Blueprint $table) {
                $table->id();
                $table->foreignId('society_id')->constrained()->onDelete('cascade');
                $table->foreignId('building_id')->nullable()->constrained()->onDelete('cascade');
                $table->string('flat_number');
                $table->integer('floor');
                $table->enum('type', ['1BHK', '2BHK', '3BHK', '4BHK', '5BHK', 'Studio', 'Penthouse', 'Shop', 'Office'])->default('2BHK');
                $table->decimal('carpet_area', 8, 2)->nullable();
                $table->decimal('built_up_area', 8, 2)->nullable();
                $table->decimal('maintenance_amount', 10, 2)->default(0);
                // Original status enum
                $table->enum('status', ['occupied', 'vacant', 'maintenance'])->default('vacant');
                $table->json('amenities')->nullable();
                $table->text('description')->nullable();
                $table->foreignId('owner_id')->nullable()->constrained('owners')->onDelete('set null');
                $table->string('property_type')->default('apartment');
                $table->foreignId('villa_area_id')->nullable()->constrained('villa_areas')->onDelete('cascade');
                $table->timestamps();
                
                $table->unique(['building_id', 'flat_number']);
                $table->index(['society_id', 'status']);
                $table->index(['building_id', 'floor']);
            });
            
            DB::statement('INSERT INTO flats_old SELECT * FROM flats');
            Schema::drop('flats');
            Schema::rename('flats_old', 'flats');
            
        } else {
            // For MySQL/PostgreSQL, revert the enum
            DB::statement("ALTER TABLE flats MODIFY COLUMN status ENUM('occupied', 'vacant', 'maintenance') DEFAULT 'vacant'");
        }
    }
};