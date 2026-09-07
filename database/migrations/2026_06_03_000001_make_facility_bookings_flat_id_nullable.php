<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite requires table recreation to make a column nullable
        // Create temp table with flat_id nullable
        Schema::create('facility_bookings_temp', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained()->onDelete('cascade');
            $table->foreignId('facility_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->unsignedBigInteger('flat_id')->nullable(); // nullable
            $table->string('booking_number')->unique();
            $table->date('booking_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->text('purpose')->nullable();
            $table->integer('expected_guests')->default(0);
            $table->decimal('booking_amount', 8, 2)->default(0);
            $table->decimal('security_deposit', 8, 2)->default(0);
            $table->enum('payment_status', ['pending', 'paid', 'refunded'])->default('pending');
            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled', 'completed'])->default('pending');
            $table->text('admin_notes')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });

        // Copy all existing data
        DB::statement('INSERT INTO facility_bookings_temp SELECT * FROM facility_bookings');

        // Drop old table and rename temp
        Schema::drop('facility_bookings');
        Schema::rename('facility_bookings_temp', 'facility_bookings');
    }

    public function down(): void
    {
        // Not reversible safely
    }
};
