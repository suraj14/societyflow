<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained()->onDelete('cascade');
            $table->foreignId('building_id')->constrained()->onDelete('cascade');
            $table->string('flat_number');
            $table->integer('floor');
            $table->enum('type', ['1BHK', '2BHK', '3BHK', '4BHK', '5BHK', 'Studio', 'Penthouse', 'Shop', 'Office'])->default('2BHK');
            $table->decimal('carpet_area', 8, 2)->nullable();
            $table->decimal('built_up_area', 8, 2)->nullable();
            $table->decimal('maintenance_amount', 10, 2)->default(0);
            $table->enum('status', ['occupied', 'vacant', 'maintenance'])->default('vacant');
            $table->json('amenities')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
            
            $table->unique(['building_id', 'flat_number']);
            $table->index(['society_id', 'status']);
            $table->index(['building_id', 'floor']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flats');
    }
};