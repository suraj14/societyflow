<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_templates', function (Blueprint $table) {
            // Add trigger_event column if it doesn't exist
            if (!Schema::hasColumn('email_templates', 'trigger_event')) {
                $table->string('trigger_event')->nullable()->after('status');
                $table->index('trigger_event');
            }
        });
    }

    public function down(): void
    {
        Schema::table('email_templates', function (Blueprint $table) {
            if (Schema::hasColumn('email_templates', 'trigger_event')) {
                $table->dropIndex(['trigger_event']);
                $table->dropColumn('trigger_event');
            }
        });
    }
};
