<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ComplaintCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'society_id',
        'name',
        'description',
        'status',
    ];

    /** What a society starts with: kept short and generic; the admin edits the list in Settings. */
    public const DEFAULTS = [
        'Maintenance' => 'General maintenance issues', 'Plumbing' => 'Water and plumbing related issues', 'Electrical' => 'Electrical problems and repairs',
        'Security' => 'Security and safety concerns', 'Noise' => 'Noise complaints', 'Parking' => 'Parking related issues and violations',
        'Cleanliness' => 'Cleaning and hygiene issues', 'Lift/Elevator' => 'Lift and elevator problems', 'Common Area' => 'Common area related issues',
        'Water Supply' => 'Water supply and quality issues', 'Garbage/Waste' => 'Garbage collection and waste management', 'Billing/Finance' => 'Billing, payments, and financial issues',
        'Facilities' => 'Amenities and facility related issues', 'Visitor Management' => 'Issues related to visitor entry and management',
        'Staff/Service' => 'Issues with society staff or service providers', 'Suggestion' => 'Suggestions for society improvements', 'Other' => 'Other miscellaneous complaints',
    ];

    /**
     * A society registered through the app has no complaint categories, and a complaint needs one, so the first resident could not file anything.
     * Gives a society with NO categories the defaults; one that already has any (or has deleted them on purpose) is left exactly as it is.
     */
    public static function ensureDefaults(int $societyId): void
    {
        if ($societyId <= 0 || static::where('society_id', $societyId)->exists()) {
            return;
        }

        // two first requests at once must not both insert the list (there is no unique index on society + name): one holds the lock, the other
        // waits a moment and then finds the categories there - or gives up quietly, since the next request will see them
        $lock = \Illuminate\Support\Facades\Cache::lock('complaint-categories-defaults:' . $societyId, 10);
        try {
            $lock->block(1);
        } catch (\Illuminate\Contracts\Cache\LockTimeoutException) {
            return;
        }

        try {
            if (!static::where('society_id', $societyId)->exists()) {
                foreach (self::DEFAULTS as $name => $description) {
                    static::firstOrCreate(['society_id' => $societyId, 'name' => $name], ['description' => $description, 'status' => 'active']);
                }
            }
        } finally {
            $lock->release();
        }
    }

    // Relationships
    public function society(): BelongsTo
    {
        return $this->belongsTo(Society::class);
    }

    public function complaints(): HasMany
    {
        return $this->hasMany(Complaint::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeBySociety($query, $societyId)
    {
        return $query->where('society_id', $societyId);
    }

    // Helper methods
    public function getComplaintsCount(): int
    {
        return $this->complaints()->count();
    }

    public function getOpenComplaintsCount(): int
    {
        return $this->complaints()->whereIn('status', ['open', 'in_progress'])->count();
    }

    public function getResolvedComplaintsCount(): int
    {
        return $this->complaints()->where('status', 'resolved')->count();
    }
}