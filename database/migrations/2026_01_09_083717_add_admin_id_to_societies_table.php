<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('societies', function (Blueprint $table) {
            $table->foreignId('admin_id')->nullable()->after('id')->constrained('users')->onDelete('set null');
            $table->foreignId('subscription_plan_id')->nullable()->after('admin_id')->constrained()->onDelete('set null');
            
            // Add indexes for better performance
            $table->index(['admin_id', 'status']);
            $table->index(['subscription_plan_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('societies', function (Blueprint $table) {
            $table->dropForeign(['admin_id']);
            $table->dropForeign(['subscription_plan_id']);
            $table->dropColumn(['admin_id', 'subscription_plan_id']);
        });
    }
};
