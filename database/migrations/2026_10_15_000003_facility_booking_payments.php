<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Facility-booking money was impossible to record: `payment_status` could say pending / paid / refunded but nothing in the
 * application ever set it past `pending`, and nothing recorded when, how much, by what method or against which reference.
 * A report cannot honestly count "facility income" from a flag with no date and no evidence.
 *
 * These columns are what recording a payment (and a refund) now writes. All are nullable: existing bookings are untouched
 * (every one of them is `pending` today), and nothing is back-filled or guessed.
 *
 *   paid_amount / paid_at / payment_method / payment_reference   the money received, when, how, and the evidence
 *   payment_recorded_by                                          who recorded it
 *   refunded_amount / refunded_at / refund_reason                a refund paid back, when and why
 *
 * (society_id, payment_reference) is unique, so the same transaction reference cannot be recorded against two bookings -
 * which is also what makes a repeated gateway callback or a double submit harmless.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facility_bookings', function (Blueprint $table) {
            $table->decimal('paid_amount', 10, 2)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_method', 20)->nullable();
            $table->string('payment_reference', 80)->nullable();
            $table->unsignedBigInteger('payment_recorded_by')->nullable();
            $table->decimal('refunded_amount', 10, 2)->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->string('refund_reason', 255)->nullable();

            $table->unique(['society_id', 'payment_reference'], 'facility_bookings_payment_reference_unique');
            $table->index(['society_id', 'payment_status'], 'facility_bookings_society_payment_idx');
        });
    }

    public function down(): void
    {
        // Forward-only: these columns hold money records that a rollback must not silently drop.
    }
};
