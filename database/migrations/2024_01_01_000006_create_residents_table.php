<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('residents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('flat_id')->constrained()->onDelete('cascade');
            $table->enum('type', ['owner', 'tenant'])->default('owner');
            $table->date('move_in_date');
            $table->date('move_out_date')->nullable();
            $table->decimal('security_deposit', 10, 2)->nullable();
            $table->decimal('monthly_rent', 10, 2)->nullable();
            $table->json('family_members')->nullable();
            $table->json('vehicles')->nullable();
            $table->text('emergency_contact')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
            
            $table->index(['society_id', 'type', 'status']);
            $table->index(['flat_id', 'status']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('residents');
    }
};