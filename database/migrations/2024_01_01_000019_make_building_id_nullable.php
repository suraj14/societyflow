<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL/Postgres: alter in place. The original drop-and-recreate
        // pattern (kept below for SQLite) fails on MySQL because other
        // tables already hold foreign keys to flats, so `flats` cannot be
        // dropped — which broke `php artisan migrate` on any fresh database.
        if (DB::getDriverName() !== 'sqlite') {
            // building_id: nullable (drop FK, modify, restore FK)
            Schema::table('flats', function (Blueprint $table) {
                $table->dropForeign(['building_id']);
            });
            // The composite unique/index on building_id go away in the
            // recreated table as well — drop them to keep schemas identical.
            foreach (['flats_building_id_flat_number_unique' => 'unique', 'flats_building_id_floor_index' => 'index', 'flats_society_id_status_index' => 'index'] as $name => $kind) {
                try {
                    Schema::table('flats', function (Blueprint $table) use ($name, $kind) {
                        $kind === 'unique' ? $table->dropUnique($name) : $table->dropIndex($name);
                    });
                } catch (\Throwable $e) {
                    // index absent — fine
                }
            }
            DB::statement('ALTER TABLE flats MODIFY building_id BIGINT UNSIGNED NULL');
            Schema::table('flats', function (Blueprint $table) {
                $table->foreign('building_id')->references('id')->on('buildings')->onDelete('cascade');
            });

            // Column relaxations the recreated table introduced
            DB::statement('ALTER TABLE flats MODIFY floor INT NULL');
            DB::statement('ALTER TABLE flats MODIFY type VARCHAR(255) NULL');
            DB::statement('ALTER TABLE flats MODIFY carpet_area DECIMAL(10,2) NULL');
            DB::statement('ALTER TABLE flats MODIFY built_up_area DECIMAL(10,2) NULL');
            DB::statement('ALTER TABLE flats MODIFY maintenance_amount DECIMAL(10,2) NULL');
            DB::statement("ALTER TABLE flats MODIFY status ENUM('vacant','occupied','under_maintenance') DEFAULT 'vacant'");

            // Columns the recreated table added beyond add_villa_support
            Schema::table('flats', function (Blueprint $table) {
                if (!Schema::hasColumn('flats', 'owner_id')) {
                    $table->foreignId('owner_id')->nullable()->constrained('users')->onDelete('set null');
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
                    $table->integer('parking_slots')->nullable();
                }
            });

            return;
        }

        // ── Original SQLite path (unchanged) ──────────────────────────────
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
