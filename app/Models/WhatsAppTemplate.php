<?php

namespace App\Models;

use App\Support\WhatsAppPurposes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A WhatsApp message template the society uses for one notification. The admin copies an approved template from the
 * provider portal and pastes it in (Message Templates -> WhatsApp -> New Template): its provider name / Fast2SMS
 * Message ID, language, body text and which value fills each {{n}}. Nothing is fetched from the provider.
 *
 * `source` says where the row came from: 'manual' (typed in here) or 'synced' (rows imported by the earlier
 * sync feature - kept, editable, never overwritten). A manual row is NOT provider-approved just because it was saved:
 * `isVerified()` is true only for a row whose approval was read from the provider.
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

    public const SOURCE_MANUAL = 'manual';
    public const SOURCE_SYNCED = 'synced';

    protected $fillable = [
        'society_id',
        'purpose',
        'external_id',
        'message_id',
        'phone_number_id',
        'name',
        'friendly_name',
        'status',
        'is_active',
        'source',
        'language',
        'category',
        'var_count',
        'variable_map',
        'body_text',
        'synced_at',
    ];

    protected $casts = [
        'synced_at'    => 'datetime',
        'var_count'    => 'integer',
        'is_active'    => 'boolean',
        'variable_map' => 'array',
    ];

    public function society(): BelongsTo
    {
        return $this->belongsTo(Society::class);
    }

    public function scopeBySociety($query, ?int $societyId)
    {
        return $query->where('society_id', $societyId);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
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

    public function isManual(): bool
    {
        return $this->source === self::SOURCE_MANUAL;
    }

    /** Provider approval actually read from the provider - never true for something an admin only typed in. */
    public function isVerified(): bool
    {
        return !$this->isManual() && $this->isApproved();
    }

    /**
     * What the provider says about the template, in words. A hand-typed row has never been checked ("LOCAL"); a synced row
     * carries Meta's own status.
     */
    public function providerStatusLabel(): string
    {
        return match (strtoupper((string) $this->status)) {
            'APPROVED'                => 'Approved',
            'PENDING', 'IN_REVIEW'    => 'Pending review',
            'REJECTED'                => 'Rejected',
            'PAUSED'                  => 'Paused',
            'DISABLED'                => 'Disabled',
            'FLAGGED'                 => 'Flagged',
            'IN_APPEAL'               => 'In appeal',
            'LOCAL', ''               => 'Not checked',
            default                   => ucfirst(strtolower((string) $this->status)),
        };
    }

    /** True when the provider has not confirmed the template: it is known to be pending, rejected, paused ... */
    public function isKnownUnapproved(): bool
    {
        return !$this->isManual() && !$this->isApproved();
    }

    /**
     * May a message go out with this template? Never one the provider reported as pending / rejected / paused, and never
     * another product's template. A hand-typed template has no provider status to check; the provider itself rejects it if
     * it is not approved, and that answer is shown.
     */
    public function sendBlockReason(): ?string
    {
        if ($this->isKnownUnapproved()) {
            return "\"{$this->displayName()}\" is {$this->providerStatusLabel()} at the provider, not approved, so it is not sent.";
        }
        if (\App\Support\WhatsAppTemplateScope::isBookMizo($this)) {
            return "\"{$this->displayName()}\" is a BookMizo template, not a SocietyFlow one, so it is not sent to residents.";
        }

        return null;
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

    public function purposeLabel(): string
    {
        return $this->purpose ? (WhatsAppPurposes::all()[$this->purpose]['label'] ?? ucfirst(str_replace('_', ' ', $this->purpose))) : 'Not assigned';
    }

    /** The template a notification uses in a society: its newest active one. */
    public static function forPurpose(?int $societyId, string $purpose): ?self
    {
        return static::where('society_id', $societyId)->where('purpose', $purpose)->where('is_active', true)->orderByDesc('id')->first();
    }

    /** Make this the only active template of its purpose in its society. */
    public function makeTheActiveOne(): void
    {
        static::where('society_id', $this->society_id)->where('purpose', $this->purpose)->where('id', '!=', $this->id)->where('is_active', true)->update(['is_active' => false]);
        $this->forceFill(['is_active' => true])->save();
    }

    /** Which value fills {{1}}, {{2}} ...: the saved order, else the notification's default order. */
    public function variableFields(): array
    {
        $map = is_array($this->variable_map) ? array_values($this->variable_map) : [];
        if (count($map) === (int) $this->var_count && $map !== []) {
            return $map;
        }

        // not chosen by hand: read from the template's own wording (see WhatsAppTemplateMapping)
        return $this->purpose ? \App\Support\WhatsAppTemplateMapping::suggest($this->purpose, (string) $this->body_text, (int) $this->var_count) : [];
    }

    /** "{{1}} Resident name · {{2}} Society name ..." - what fills each variable, for the card. */
    public function mappingSummary(): string
    {
        return $this->purpose && $this->var_count > 0 ? \App\Support\WhatsAppTemplateMapping::describe($this->purpose, $this->variableFields()) : '';
    }

    /** The body with each {{n}} replaced by an example value of the field chosen for it (the saved text is untouched). */
    public function preview(): string
    {
        $fields = $this->variableFields();

        return (string) preg_replace_callback('/\{\{\s*(\d+)\s*\}\}/', function ($m) use ($fields) {
            $field = $fields[((int) $m[1]) - 1] ?? null;

            return $field ? WhatsAppPurposes::sample($field) : $m[0];
        }, (string) $this->body_text);
    }

    /** The numbers used as {{n}} in a body, in order of appearance, without repeats. */
    public static function placeholderNumbers(string $body): array
    {
        preg_match_all('/\{\{\s*(\d+)\s*\}\}/', $body, $m);

        return array_values(array_unique(array_map('intval', $m[1])));
    }
}
