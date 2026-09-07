<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_bills', function (Blueprint $table) {
            $table->string('bill_type')->default('maintenance')->after('notes');
            $table->date('paid_date')->nullable()->after('paid_amount');
            
            // Add index for bill_type for better query performance
            $table->index(['society_id', 'bill_type']);
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_bills', function (Blueprint $table) {
            $table->dropIndex(['society_id', 'bill_type']);
            $table->dropColumn(['bill_type', 'paid_date']);
        });
    }
};