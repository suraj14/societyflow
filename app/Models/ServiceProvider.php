<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceProvider extends Model
{
    use HasFactory;

    protected $fillable = [
        'society_id',
        'service_id',
        'name',
        'contact_number',
        'website',
        'photo',
        'availability',
        'is_daily_help',
        'price',
        'price_type',
        'status',
        'notes',
    ];

    protected $casts = [
        'is_daily_help' => 'boolean',
        'price' => 'decimal:2',
    ];

    // Relationships
    public function society(): BelongsTo
    {
        return $this->belongsTo(Society::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function attendance(): HasMany
    {
        return $this->hasMany(ServiceAttendance::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeAvailable($query)
    {
        return $query->where('availability', 'available');
    }

    public function scopeBySociety($query, $societyId)
    {
        return $query->where('society_id', $societyId);
    }

    public function scopeByService($query, $serviceId)
    {
        return $query->where('service_id', $serviceId);
    }

    // Helper methods
    public function getAvailabilityBadgeAttribute(): string
    {
        return match($this->availability) {
            'available' => '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Available</span>',
            'not_available' => '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">Not Available</span>',
            default => '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">Unknown</span>',
        };
    }

    public function getStatusBadgeAttribute(): string
    {
        return match($this->status) {
            'active' => '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">Active</span>',
            'inactive' => '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">Inactive</span>',
            default => '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">Unknown</span>',
        };
    }

    public function getFormattedPriceAttribute(): string
    {
        if (!$this->price) {
            return 'Price not set';
        }

        $priceText = '₹' . number_format($this->price, 2);
        
        return match($this->price_type) {
            'per_day' => $priceText . ' per day',
            'per_month' => $priceText . ' per month',
            'per_visit' => $priceText . ' per visit',
            default => $priceText,
        };
    }

    public function getTodayAttendanceAttribute()
    {
        return $this->attendance()
            ->where('attendance_date', today())
            ->first();
    }

    public function hasAttendanceToday(): bool
    {
        return $this->attendance()
            ->where('attendance_date', today())
            ->exists();
    }

    public function isClockedInToday(): bool
    {
        $attendance = $this->getTodayAttendanceAttribute();
        return $attendance && $attendance->clock_in_time && !$attendance->clock_out_time;
    }
}