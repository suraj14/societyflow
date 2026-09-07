<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained()->onDelete('cascade');
            $table->foreignId('maintenance_bill_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('payment_id')->unique();
            $table->string('transaction_id')->nullable();
            $table->decimal('amount', 10, 2);
            $table->enum('payment_method', ['cash', 'cheque', 'online', 'upi', 'card'])->default('online');
            $table->enum('payment_gateway', ['razorpay', 'stripe', 'payu', 'manual'])->nullable();
            $table->string('gateway_transaction_id')->nullable();
            $table->json('gateway_response')->nullable();
            $table->enum('status', ['pending', 'success', 'failed', 'cancelled'])->default('pending');
            $table->date('payment_date');
            $table->text('notes')->nullable();
            $table->string('receipt_number')->nullable();
            $table->timestamps();
            
            $table->index(['society_id', 'status']);
            $table->index(['maintenance_bill_id', 'status']);
            $table->index(['user_id', 'payment_date']);
            $table->index(['payment_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};