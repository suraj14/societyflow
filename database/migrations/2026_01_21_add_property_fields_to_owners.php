<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('owners', function (Blueprint $table) {
            // Property type selection
            if (!Schema::hasColumn('owners', 'property_type')) {
                $table->enum('property_type', ['apartment', 'villa'])->nullable()->after('society_id');
            }
            
            // Apartment fields
            if (!Schema::hasColumn('owners', 'building_id')) {
                $table->unsignedBigInteger('building_id')->nullable()->after('property_type');
            }
            if (!Schema::hasColumn('owners', 'floor')) {
                $table->integer('floor')->nullable()->after('building_id');
            }
            if (!Schema::hasColumn('owners', 'flat_no')) {
                $table->string('flat_no')->nullable()->after('floor');
            }
            
            // Villa fields
            if (!Schema::hasColumn('owners', 'villa_area_id')) {
                $table->unsignedBigInteger('villa_area_id')->nullable()->after('flat_no');
            }
            if (!Schema::hasColumn('owners', 'villa_no')) {
                $table->string('villa_no')->nullable()->after('villa_area_id');
            }
            
            // Document upload
            if (!Schema::hasColumn('owners', 'document_path')) {
                $table->string('document_path')->nullable()->after('villa_no');
            }
        });
    }

    public function down(): void
    {
        Schema::table('owners', function (Blueprint $table) {
            if (Schema::hasColumn('owners', 'property_type')) {
                $table->dropColumn('property_type');
            }
            if (Schema::hasColumn('owners', 'building_id')) {
                $table->dropColumn('building_id');
            }
            if (Schema::hasColumn('owners', 'floor')) {
                $table->dropColumn('floor');
            }
            if (Schema::hasColumn('owners', 'flat_no')) {
                $table->dropColumn('flat_no');
            }
            if (Schema::hasColumn('owners', 'villa_area_id')) {
                $table->dropColumn('villa_area_id');
            }
            if (Schema::hasColumn('owners', 'villa_no')) {
                $table->dropColumn('villa_no');
            }
            if (Schema::hasColumn('owners', 'document_path')) {
                $table->dropColumn('document_path');
            }
        });
    }
};
