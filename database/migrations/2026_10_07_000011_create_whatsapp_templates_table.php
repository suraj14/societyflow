<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Local cache of a society's approved WhatsApp templates, pulled from
 * Fast2SMS's real `GET /dev/whatsapp/{version}/{waba_id}/message_templates`
 * endpoint (confirmed against docs.fast2sms.com/reference/get-all-templates,
 * not guessed) by `WhatsAppTemplateSyncService`. Meta's template object has
 * no separate "friendly name" field - `name` is the only identifier, often
 * a slug like `order_update_v2` - so `friendly_name` is a purely local,
 * admin-editable label the Communication Center shows instead of that raw
 * name or the technical `external_id`, defaulting to a humanised version of
 * `name` on first sync.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_templates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('society_id')->nullable();
            $table->string('external_id'); // Meta/Fast2SMS template id - never shown to a normal admin
            $table->string('name'); // the technical template name used in the send API
            $table->string('friendly_name')->nullable();
            $table->string('status')->default('UNKNOWN'); // APPROVED, PENDING, REJECTED, ...
            $table->string('language')->nullable();
            $table->string('category')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['society_id', 'external_id']);
            $table->index(['society_id', 'status']);

            $table->foreign('society_id')->references('id')->on('societies')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_templates');
    }
};
