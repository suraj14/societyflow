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
        // For SQLite, we need to recreate the table
        if (DB::connection()->getDriverName() === 'sqlite') {
            // Get the current table structure
            $residents = DB::table('residents')->get();
            
            // Drop the old table
            Schema::dropIfExists('residents');
            
            // Create new table with nullable user_id
            Schema::create('residents', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('society_id');
                $table->string('name')->nullable();
                $table->string('email')->nullable()->unique();
                $table->string('phone')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->unsignedBigInteger('flat_id');
                $table->enum('type', ['owner', 'tenant']);
                $table->date('move_in_date');
                $table->date('move_out_date')->nullable();
                $table->decimal('security_deposit', 10, 2)->default(0);
                $table->decimal('monthly_rent', 10, 2)->default(0);
                $table->json('family_members')->nullable();
                $table->json('vehicles')->nullable();
                $table->json('emergency_contact')->nullable();
                $table->enum('status', ['active', 'inactive'])->default('active');
                $table->timestamps();
                
                $table->foreign('society_id')->references('id')->on('societies')->onDelete('cascade');
                $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
                $table->foreign('flat_id')->references('id')->on('flats')->onDelete('cascade');
            });
            
            // Re-insert the data
            foreach ($residents as $resident) {
                DB::table('residents')->insert((array) $resident);
            }
        } else {
            // For other databases, use the change method
            Schema::table('residents', function (Blueprint $table) {
                $table->unsignedBigInteger('user_id')->nullable()->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // For SQLite, we would need to recreate again
        // For now, just leave it as is
    }
};
