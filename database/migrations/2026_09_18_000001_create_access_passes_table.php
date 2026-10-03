<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One table for both recurring-staff and temporary-service-provider gate
 * access, per CLAUDE.md-style minimalism ("do not create duplicate visitor
 * systems"): a pass is not a visit. Scanning a valid pass creates an ordinary
 * `visitors` row (see the next migration's `access_pass_id`/`qr_token`
 * columns) and reuses check-in/check-out/history/"Visitors Inside" exactly
 * as they already work - this table only decides whether that visit is
 * currently authorised.
 *
 * `token` is an opaque random string (Laravel's existing pattern for
 * Sanctum/password-reset tokens), not a signed/encoded payload - the QR
 * carries no personal data, and every property of the pass is resolved by a
 * server-side DB lookup on every scan, never trusted from the client.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('access_passes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained()->onDelete('cascade');
            $table->foreignId('flat_id')->constrained()->onDelete('cascade');
            // Who this pass lets in.
            $table->string('person_name');
            $table->string('person_phone')->nullable();
            $table->string('person_photo')->nullable();
            $table->string('category')->nullable(); // Cleaner, Electrician, ...
            $table->enum('pass_type', ['recurring_staff', 'temporary_service']);

            // The opaque QR payload. Indexed and unique - a scan is a
            // straight lookup, nothing to decode or verify beyond the row
            // it names existing and still being valid.
            $table->string('token', 64)->unique();

            // Recurring: which weekdays (0=Sunday..6=Saturday) and what daily
            // window. Temporary: a single valid_from/valid_until covering the
            // whole appointment, day/time columns unused.
            $table->json('allowed_days')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->date('valid_from');
            $table->date('valid_until')->nullable(); // null = indefinite (recurring only)

            // "Block Today" without touching the permanent authorisation -
            // a list of dates this pass is refused regardless of day/time.
            $table->json('blocked_dates')->nullable();

            $table->enum('status', ['active', 'revoked'])->default('active');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index(['society_id', 'status']);
            $table->index(['flat_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('access_passes');
    }
};
