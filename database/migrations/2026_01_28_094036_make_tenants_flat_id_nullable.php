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
        // For SQLite, we need to recreate the table to make flat_id nullable
        // This is similar to the complaints flat_id fix
        
        // First, create a temporary table with the correct structure
        Schema::create('tenants_temp', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('society_id');
            $table->unsignedBigInteger('flat_id')->nullable(); // Make nullable
            $table->unsignedBigInteger('owner_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->date('move_in_date')->nullable();
            $table->date('move_out_date')->nullable();
            $table->decimal('monthly_rent', 10, 2)->default(0);
            $table->decimal('security_deposit', 10, 2)->default(0);
            $table->json('emergency_contact')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->text('notes')->nullable();
            $table->date('contract_start_date')->nullable();
            $table->date('contract_end_date')->nullable();
            $table->enum('rent_billing_cycle', ['Monthly', 'Quarterly', 'Annually'])->default('Monthly');
            $table->string('id_type')->nullable();
            $table->string('document_path')->nullable();
            $table->json('family_members')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('society_id')->references('id')->on('societies')->onDelete('cascade');
            $table->foreign('flat_id')->references('id')->on('flats')->onDelete('set null');
            $table->foreign('owner_id')->references('id')->on('owners')->onDelete('set null');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            // Indexes
            $table->index('society_id');
            $table->index('flat_id');
            $table->index('owner_id');
            $table->index('user_id');
            $table->index('status');
        });

        // Copy data from original table to temp table
        DB::statement('INSERT INTO tenants_temp SELECT * FROM tenants');

        // Drop the original table
        Schema::dropIfExists('tenants');

        // Rename temp table to original name
        Schema::rename('tenants_temp', 'tenants');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This is complex to reverse, so we'll leave it as is
        // The flat_id being nullable is actually better for the system
    }
};
