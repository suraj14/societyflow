<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // For SQLite, we need to recreate the table with proper constraints
        if (DB::getDriverName() === 'sqlite') {
            // Create a temporary table with the correct structure
            Schema::create('visitors_temp', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('society_id');
                $table->unsignedBigInteger('flat_id');
                $table->unsignedBigInteger('host_user_id')->nullable();
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->string('visitor_name');
                $table->string('visitor_phone');
                $table->string('visitor_id_proof')->nullable();
                $table->string('visitor_id_number')->nullable();
                $table->enum('visitor_type', ['guest', 'delivery', 'cab', 'service', 'other'])->default('guest');
                $table->text('purpose')->nullable();
                $table->integer('expected_count')->default(1);
                $table->string('vehicle_number')->nullable();
                $table->timestamp('expected_entry_time');
                $table->timestamp('expected_exit_time')->nullable();
                $table->timestamp('actual_entry_time')->nullable();
                $table->timestamp('actual_exit_time')->nullable();
                $table->enum('approval_status', ['pending', 'allowed', 'denied'])->default('pending');
                $table->enum('entry_status', ['pending', 'entered', 'exited'])->default('pending');
                $table->text('rejection_reason')->nullable();
                $table->text('security_notes')->nullable();
                $table->string('photo')->nullable();
                $table->timestamps();

                $table->foreign('society_id')->references('id')->on('societies')->onDelete('cascade');
                $table->foreign('flat_id')->references('id')->on('flats')->onDelete('cascade');
                $table->foreign('host_user_id')->references('id')->on('users')->onDelete('set null');
                $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
            });

            // Copy data from original table to temp table
            DB::statement('INSERT INTO visitors_temp SELECT * FROM visitors');

            // Drop the original table
            Schema::dropIfExists('visitors');

            // Rename temp table to original name
            Schema::rename('visitors_temp', 'visitors');
        } else {
            // For other databases, just update the enum
            Schema::table('visitors', function (Blueprint $table) {
                $table->enum('approval_status', ['pending', 'allowed', 'denied'])->default('pending')->change();
            });
        }
    }

    public function down(): void
    {
        // For SQLite, recreate with old constraints
        if (DB::getDriverName() === 'sqlite') {
            Schema::create('visitors_temp', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('society_id');
                $table->unsignedBigInteger('flat_id');
                $table->unsignedBigInteger('host_user_id')->nullable();
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->string('visitor_name');
                $table->string('visitor_phone');
                $table->string('visitor_id_proof')->nullable();
                $table->string('visitor_id_number')->nullable();
                $table->enum('visitor_type', ['guest', 'delivery', 'cab', 'service', 'other'])->default('guest');
                $table->text('purpose')->nullable();
                $table->integer('expected_count')->default(1);
                $table->string('vehicle_number')->nullable();
                $table->timestamp('expected_entry_time');
                $table->timestamp('expected_exit_time')->nullable();
                $table->timestamp('actual_entry_time')->nullable();
                $table->timestamp('actual_exit_time')->nullable();
                $table->enum('approval_status', ['pending', 'approved', 'rejected'])->default('pending');
                $table->enum('entry_status', ['pending', 'entered', 'exited'])->default('pending');
                $table->text('rejection_reason')->nullable();
                $table->text('security_notes')->nullable();
                $table->string('photo')->nullable();
                $table->timestamps();

                $table->foreign('society_id')->references('id')->on('societies')->onDelete('cascade');
                $table->foreign('flat_id')->references('id')->on('flats')->onDelete('cascade');
                $table->foreign('host_user_id')->references('id')->on('users')->onDelete('set null');
                $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
            });

            // Update data back to old values
            DB::table('visitors')
                ->where('approval_status', 'allowed')
                ->update(['approval_status' => 'approved']);
                
            DB::table('visitors')
                ->where('approval_status', 'denied')
                ->update(['approval_status' => 'rejected']);

            // Copy data
            DB::statement('INSERT INTO visitors_temp SELECT * FROM visitors');

            // Drop and rename
            Schema::dropIfExists('visitors');
            Schema::rename('visitors_temp', 'visitors');
        } else {
            // Revert data changes first
            DB::table('visitors')
                ->where('approval_status', 'allowed')
                ->update(['approval_status' => 'approved']);
                
            DB::table('visitors')
                ->where('approval_status', 'denied')
                ->update(['approval_status' => 'rejected']);

            // Then revert enum
            Schema::table('visitors', function (Blueprint $table) {
                $table->enum('approval_status', ['pending', 'approved', 'rejected'])->default('pending')->change();
            });
        }
    }
};