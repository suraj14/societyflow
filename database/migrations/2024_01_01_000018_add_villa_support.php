<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Create villa_areas table if not exists
        if (!Schema::hasTable('villa_areas')) {
            Schema::create('villa_areas', function (Blueprint $table) {
                $table->id();
                $table->foreignId('society_id')->constrained()->onDelete('cascade');
                $table->string('name');
                $table->string('code')->nullable();
                $table->text('description')->nullable();
                $table->integer('total_villas')->default(0);
                $table->enum('status', ['active', 'inactive'])->default('active');
                $table->timestamps();
                
                $table->unique(['society_id', 'name']);
                $table->index(['society_id', 'status']);
            });
        }

        // Add villa support columns to flats table (SQLite compatible)
        Schema::table('flats', function (Blueprint $table) {
            if (!Schema::hasColumn('flats', 'property_type')) {
                $table->string('property_type')->default('apartment');
            }
            if (!Schema::hasColumn('flats', 'villa_area_id')) {
                $table->unsignedBigInteger('villa_area_id')->nullable();
            }
            if (!Schema::hasColumn('flats', 'owner_id')) {
                $table->unsignedBigInteger('owner_id')->nullable();
            }
            if (!Schema::hasColumn('flats', 'villa_name')) {
                $table->string('villa_name')->nullable();
            }
            if (!Schema::hasColumn('flats', 'plot_area')) {
                $table->decimal('plot_area', 10, 2)->nullable();
            }
            if (!Schema::hasColumn('flats', 'bedrooms')) {
                $table->integer('bedrooms')->nullable();
            }
            if (!Schema::hasColumn('flats', 'bathrooms')) {
                $table->integer('bathrooms')->nullable();
            }
            if (!Schema::hasColumn('flats', 'has_garden')) {
                $table->boolean('has_garden')->default(false);
            }
            if (!Schema::hasColumn('flats', 'has_parking')) {
                $table->boolean('has_parking')->default(false);
            }
            if (!Schema::hasColumn('flats', 'parking_slots')) {
                $table->integer('parking_slots')->default(0);
            }
        });
    }

    public function down(): void
    {
        // SQLite doesn't support dropping columns easily
        // This is a no-op for SQLite
        Schema::dropIfExists('villa_areas');
    }
};
