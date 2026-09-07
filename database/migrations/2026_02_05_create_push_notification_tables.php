<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Push subscription tokens per user
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('society_id')->constrained('societies')->onDelete('cascade');
            $table->text('endpoint');
            $table->text('auth_key');
            $table->text('p256dh_key');
            $table->string('device_type')->default('web'); // web, mobile, etc
            $table->string('browser')->nullable(); // Chrome, Firefox, Safari, etc
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
            
            $table->unique(['user_id', 'endpoint']);
            $table->index(['society_id', 'is_active']);
        });

        // Push notification logs
        Schema::create('push_notification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('society_id')->constrained('societies')->onDelete('cascade');
            $table->string('title');
            $table->text('body');
            $table->string('trigger_action'); // bill_generated, payment_success, etc
            $table->json('data')->nullable();
            $table->enum('status', ['pending', 'sent', 'failed', 'bounced'])->default('pending');
            $table->text('error_message')->nullable();
            $table->integer('retry_count')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            
            $table->index(['society_id', 'status']);
            $table->index(['trigger_action', 'created_at']);
        });

        // Push notification settings per society
        Schema::create('push_notification_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained('societies')->onDelete('cascade');
            $table->boolean('enabled')->default(true);
            $table->text('fcm_server_key')->nullable(); // Encrypted
            $table->text('vapid_public_key')->nullable();
            $table->text('vapid_private_key')->nullable(); // Encrypted
            $table->json('enabled_triggers')->default('[]'); // Array of enabled notification types
            $table->json('role_permissions')->default('[]'); // Role-based delivery settings
            $table->timestamps();
            
            $table->unique('society_id');
        });

        // Push notification templates
        Schema::create('push_notification_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->nullable()->constrained('societies')->onDelete('cascade');
            $table->string('trigger_action')->unique();
            $table->string('title');
            $table->text('body');
            $table->string('icon')->nullable();
            $table->string('badge')->nullable();
            $table->string('click_action')->nullable();
            $table->json('data')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['trigger_action', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_notification_templates');
        Schema::dropIfExists('push_notification_settings');
        Schema::dropIfExists('push_notification_logs');
        Schema::dropIfExists('push_subscriptions');
    }
};
