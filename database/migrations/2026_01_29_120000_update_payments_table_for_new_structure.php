<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Add new fields
            $table->foreignId('flat_id')->nullable()->constrained()->onDelete('cascade')->after('society_id');
            $table->string('bill_type')->after('user_id');
            $table->date('due_date')->after('payment_date');
            $table->string('receipt_file_path')->nullable()->after('receipt_number');
            
            // Make maintenance_bill_id and user_id nullable since they're no longer required
            $table->foreignId('maintenance_bill_id')->nullable()->change();
            $table->foreignId('user_id')->nullable()->change();
            
            // Add indexes for new fields
            $table->index(['flat_id', 'bill_type']);
            $table->index(['due_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Remove new fields
            $table->dropForeign(['flat_id']);
            $table->dropColumn(['flat_id', 'bill_type', 'due_date', 'receipt_file_path']);
            
            // Make maintenance_bill_id and user_id required again
            $table->foreignId('maintenance_bill_id')->nullable(false)->change();
            $table->foreignId('user_id')->nullable(false)->change();
            
            // Remove indexes
            $table->dropIndex(['flat_id', 'bill_type']);
            $table->dropIndex(['due_date', 'status']);
        });
    }
};