<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained()->onDelete('cascade');
            $table->foreignId('flat_id')->constrained()->onDelete('cascade');
            $table->string('bill_number')->unique();
            $table->string('month');
            $table->integer('year');
            $table->date('bill_date');
            $table->date('due_date');
            $table->decimal('maintenance_amount', 10, 2)->default(0);
            $table->decimal('water_charges', 10, 2)->default(0);
            $table->decimal('electricity_charges', 10, 2)->default(0);
            $table->decimal('parking_charges', 10, 2)->default(0);
            $table->decimal('penalty_amount', 10, 2)->default(0);
            $table->decimal('other_charges', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->decimal('paid_amount', 10, 2)->default(0);
            $table->decimal('balance_amount', 10, 2)->default(0);
            $table->enum('status', ['pending', 'partial', 'paid', 'overdue'])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->unique(['society_id', 'flat_id', 'month', 'year']);
            $table->index(['society_id', 'status']);
            $table->index(['flat_id', 'status']);
            $table->index(['due_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_bills');
    }
};