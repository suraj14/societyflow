<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ServiceApiController::requestService() has been inserting into a
 * `service_requests` table that was never migrated, so every mobile
 * service request failed with a 500. This creates the table with the
 * exact columns that insert already uses. Purely additive — no
 * existing data is touched.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('service_requests')) {
            return;
        }

        Schema::create('service_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('service_id')->constrained()->onDelete('cascade');
            $table->text('description');
            $table->date('preferred_date')->nullable();
            $table->string('status')->default('pending'); // pending, assigned, in_progress, completed, cancelled
            $table->timestamps();

            $table->index(['society_id', 'status']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_requests');
    }
};
