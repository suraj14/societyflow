<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Email template for `society_document.published` (NotificationEvents).
 * Global (society_id = null) and idempotent, like every other
 * template-seeding migration here - a society's own copy is never touched.
 */
return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('email_templates')
            ->where('trigger_event', 'society_document_published')
            ->whereNull('society_id')
            ->exists();

        if ($exists) {
            return;
        }

        $now = now();

        DB::table('email_templates')->insert([
            'society_id'    => null,
            'name'          => 'Society Document Published',
            'slug'          => 'society-document-published',
            'subject'       => '{{document_title}} ({{document_version}}) - {{society_name}}',
            'body'          => '<p>Dear {{user_name}},</p><p>The society has published <strong>{{document_title}}</strong> ({{document_version}}) under {{category}}, effective {{effective_from}}.</p><p><a href="{{view_url}}">Read the document</a></p><p>Best regards,<br>{{society_name}} Team</p>',
            'variables'     => json_encode(['user_name', 'society_name', 'document_title', 'document_version', 'category', 'effective_from', 'view_url']),
            'type'          => 'system',
            'status'        => 'active',
            'trigger_event' => 'society_document_published',
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);
    }

    public function down(): void
    {
        DB::table('email_templates')
            ->where('trigger_event', 'society_document_published')
            ->whereNull('society_id')
            ->delete();
    }
};
