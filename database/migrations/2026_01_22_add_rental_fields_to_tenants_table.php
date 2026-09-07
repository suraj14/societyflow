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
        Schema::table('tenants', function (Blueprint $table) {
            // Add user_id relationship
            $table->unsignedBigInteger('user_id')->nullable()->after('id');
            
            // Add rental contract fields
            $table->date('contract_start_date')->nullable()->after('move_in_date');
            $table->date('contract_end_date')->nullable()->after('contract_start_date');
            $table->string('rent_billing_cycle')->nullable()->after('monthly_rent');
            
            // Add identification fields
            $table->string('id_type')->nullable()->after('status');
            $table->string('document_path')->nullable()->after('id_type');
            
            // Add foreign key for user_id
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn([
                'user_id',
                'contract_start_date',
                'contract_end_date',
                'rent_billing_cycle',
                'id_type',
                'document_path',
            ]);
        });
    }
};
