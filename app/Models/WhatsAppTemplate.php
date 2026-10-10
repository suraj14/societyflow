<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A society's view of its own approved WhatsApp Business templates, synced
 * from Fast2SMS/Meta by `WhatsAppTemplateSyncService`. See that class and
 * the creating migration for where this data actually comes from.
 */
class WhatsAppTemplate extends Model
{
    // Eloquent's snake_case inference splits "WhatsApp" into
    // "whats_app_templates" - the migration uses "whatsapp_templates"
    // (matching every other WhatsApp-prefixed identifier in this project,
    // e.g. the `whatsapp_*` SystemSetting keys), so the table name is
    // pinned explicitly rather than renaming the migration to match a
    // naming convention this project doesn't otherwise follow.
    protected $table = 'whatsapp_templates';

    protected $fillable = [
        'society_id',
        'external_id',
        'message_id',
        'phone_number_id',
        'name',
        'friendly_name',
        'status',
        'language',
        'category',
        'var_count',
        'body_text',
        'synced_at',
    ];

    protected $casts = [
        'synced_at' => 'datetime',
        'var_count' => 'integer',
    ];

    public function society(): BelongsTo
    {
        return $this->belongsTo(Society::class);
    }

    public function scopeBySociety($query, ?int $societyId)
    {
        return $query->where('society_id', $societyId);
    }

    /** Meta says APPROVED, Fast2SMS's lookup says Approved - accept both. */
    public function scopeApproved($query)
    {
        return $query->whereIn('status', ['APPROVED', 'Approved', 'approved']);
    }

    public function isApproved(): bool
    {
        return strtoupper((string) $this->status) === 'APPROVED';
    }

    /** What the Communication Center shows - never the raw `name` or `external_id`. */
    public function displayName(): string
    {
        return $this->friendly_name ?: self::humanize($this->name);
    }

    public static function humanize(string $technicalName): string
    {
        return ucwords(str_replace(['_', '-'], ' ', $technicalName));
    }
}
