<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('owners', function (Blueprint $table) {
            if (!Schema::hasColumn('owners', 'profile_image')) {
                $table->string('profile_image')->nullable()->after('document_path');
            }
            if (!Schema::hasColumn('owners', 'family_members')) {
                $table->json('family_members')->nullable()->after('profile_image');
            }
        });
    }

    public function down(): void
    {
        Schema::table('owners', function (Blueprint $table) {
            if (Schema::hasColumn('owners', 'profile_image')) {
                $table->dropColumn('profile_image');
            }
            if (Schema::hasColumn('owners', 'family_members')) {
                $table->dropColumn('family_members');
            }
        });
    }
};
