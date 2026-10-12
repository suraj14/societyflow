<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sms_message_templates')) {
            return;
        }

        Schema::create('sms_message_templates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('society_id')->nullable()->index();
            $table->string('name', 120);
            $table->string('purpose', 40)->default('other');
            $table->string('audience', 20)->default('resident');
            // The DLT-approved wording, with {{variable}} placeholders filled in order into variables_values.
            $table->text('body');
            // Fast2SMS's own numeric Message ID for this template (DLT Manager). Empty = sent as Quick SMS.
            $table->string('dlt_message_id', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['society_id', 'purpose']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_message_templates');
    }
};
