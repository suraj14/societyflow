<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('utility_bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained()->onDelete('cascade');
            $table->foreignId('flat_id')->constrained()->onDelete('cascade');
            $table->string('bill_type'); // Water Bill, Electricity Bill, Gas Bill, etc.
            $table->decimal('bill_amount', 10, 2);
            $table->date('bill_date');
            $table->date('due_date');
            $table->string('bill_file_path')->nullable(); // For uploaded bill documents
            $table->enum('status', ['unpaid', 'partial', 'paid'])->default('unpaid');
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index(['society_id', 'flat_id']);
            $table->index(['society_id', 'status']);
            $table->index(['flat_id', 'status']);
            $table->index(['due_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('utility_bills');
    }
};
