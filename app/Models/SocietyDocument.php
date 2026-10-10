<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * One version of one society document. See the creating migration
 * (2026_10_08_000002) for the versioning model: rows sharing a
 * `series_key` are versions of the same document, and the single
 * `published` row in a series is its current version.
 */
class SocietyDocument extends Model
{
    public const CATEGORIES = [
        'society_rules'  => 'Society Rules',
        'bye_laws'       => 'Bye-Laws',
        'parking'        => 'Parking',
        'visitor_gate'   => 'Visitor & Gate',
        'maintenance'    => 'Maintenance',
        'security'       => 'Security',
        'facilities'     => 'Facilities',
        'move_in_out'    => 'Move-In / Move-Out',
        'other'          => 'Other',
    ];

    /** Categories that default to guard-visible on the upload form. */
    public const GUARD_DEFAULT_CATEGORIES = ['visitor_gate', 'security'];

    public const STATUSES = ['draft', 'published', 'unpublished', 'archived'];

    /** Roles that read published documents as residents. */
    public const RESIDENT_ROLES = ['Owner', 'Villa Owner', 'Apartment Owner', 'Tenant'];

    public const MAX_UPLOAD_KB = 10240;

    protected $fillable = [
        'society_id',
        'series_key',
        'version_number',
        'version',
        'title',
        'category',
        'description',
        'effective_from',
        'status',
        'visible_to_guards',
        'requires_acknowledgement',
        'file_path',
        'original_filename',
        'file_size',
        'uploaded_by',
        'published_at',
        'announced_at',
    ];

    protected $casts = [
        'effective_from'           => 'date',
        'published_at'             => 'datetime',
        'announced_at'             => 'datetime',
        'visible_to_guards'        => 'boolean',
        'requires_acknowledgement' => 'boolean',
        'version_number'           => 'integer',
        'file_size'                => 'integer',
    ];

    public function society(): BelongsTo
    {
        return $this->belongsTo(Society::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function acknowledgements(): HasMany
    {
        return $this->hasMany(SocietyDocumentAcknowledgement::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(self::class, 'series_key', 'series_key')->orderByDesc('version_number');
    }

    public function getCategoryLabelAttribute(): string
    {
        return self::CATEGORIES[$this->category] ?? 'Other';
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public static function isManager(User $user): bool
    {
        return $user->hasAnyRole(['Super Admin', 'Admin']);
    }

    public static function isResident(User $user): bool
    {
        return $user->hasAnyRole(self::RESIDENT_ROLES);
    }

    /**
     * What a non-managing user may read: published rows of their own
     * society, and for a guard only the ones marked guard-visible. Anyone
     * who is neither resident nor guard (Staff, Accountant) gets nothing.
     * Managers do not go through this scope - they see every status.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        $query->where('society_id', $user->society_id)->where('status', 'published');

        if (self::isResident($user)) {
            return $query;
        }

        if ($user->hasRole('Guard')) {
            return $query->where('visible_to_guards', true);
        }

        return $query->whereRaw('1 = 0');
    }

    /** Residents, guards and managers read documents; Staff/Accountant do not. */
    public static function isReader(User $user): bool
    {
        return self::isManager($user) || self::isResident($user) || $user->hasRole('Guard');
    }

    /**
     * The reader-side query for web and API alike. A manager reading
     * through the reader screens sees what residents of their own society
     * see; management of every status happens on the admin screens.
     */
    public static function readableBy(User $user): Builder
    {
        if (self::isManager($user)) {
            return self::query()->where('society_id', $user->society_id)->where('status', 'published');
        }

        return self::query()->visibleTo($user);
    }

    /**
     * Make this version the current one. Any other published version in
     * the same series is archived in the same transaction, so a series
     * never has two current versions and never loses its history.
     */
    public function publish(): void
    {
        DB::transaction(function () {
            self::where('series_key', $this->series_key)
                ->where('id', '!=', $this->id)
                ->where('status', 'published')
                ->update(['status' => 'archived', 'updated_at' => now()]);

            $this->update(['status' => 'published', 'published_at' => now()]);
        });
    }

    public function acknowledgedBy(User $user): bool
    {
        return $this->acknowledgements()->where('user_id', $user->id)->exists();
    }

    public function nextVersionNumber(): int
    {
        return (int) self::where('series_key', $this->series_key)->max('version_number') + 1;
    }

    public function getFileSizeLabelAttribute(): string
    {
        $kb = $this->file_size / 1024;

        return $kb >= 1024 ? number_format($kb / 1024, 1) . ' MB' : max(1, (int) round($kb)) . ' KB';
    }
}
