<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What Settings -> WhatsApp needs to let a manager pick a template without
 * typing identifiers: the body text (to show and to map {{n}} variables),
 * how many variables it takes (a send with the wrong count is rejected by
 * Meta), Fast2SMS's own message_id, and which sender number owns it (a
 * template on another number cannot be sent from ours). All come from
 * GET /dev/dlt_manager/whatsapp?type=template (see Fast2SmsAccount).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_templates', function (Blueprint $table) {
            $table->string('message_id')->nullable()->after('external_id');
            $table->string('phone_number_id')->nullable()->after('message_id');
            $table->unsignedSmallInteger('var_count')->default(0)->after('category');
            $table->text('body_text')->nullable()->after('var_count');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_templates', function (Blueprint $table) {
            $table->dropColumn(['message_id', 'phone_number_id', 'var_count', 'body_text']);
        });
    }
};
