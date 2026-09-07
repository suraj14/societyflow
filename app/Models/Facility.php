<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Facility extends Model
{
    use HasFactory;

    protected $fillable = [
        'society_id',
        'name',
        'description',
        'type',
        'booking_charge',
        'capacity',
        'amenities',
        'rules',
        'opening_time',
        'closing_time',
        'available_days',
        'advance_booking_days',
        'max_booking_hours',
        'requires_approval',
        'image',
        'status',
    ];

    protected $casts = [
        'booking_charge' => 'decimal:2',
        'amenities' => 'array',
        'rules' => 'array',
        'requires_approval' => 'boolean',
    ];

    /**
     * Get/Set available_days as array
     */
    protected function availableDays(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? json_decode($value, true) : [],
            set: fn ($value) => is_array($value) ? json_encode($value) : $value,
        );
    }

    // Relationships
    public function society(): BelongsTo
    {
        return $this->belongsTo(Society::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(FacilityBooking::class);
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

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    // Helper methods
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isAvailableOn(string $day): bool
    {
        return in_array(strtolower($day), $this->available_days ?? []);
    }

    public function isAvailableAt(\DateTime $dateTime): bool
    {
        $dayName = strtolower($dateTime->format('l'));
        
        if (!$this->isAvailableOn($dayName)) {
            return false;
        }

        $time = $dateTime->format('H:i');
        
        return $time >= $this->opening_time->format('H:i') && 
               $time <= $this->closing_time->format('H:i');
    }

    public function getBookingsForDate(\DateTime $date): \Illuminate\Database\Eloquent\Collection
    {
        return $this->bookings()
            ->where('booking_date', $date->format('Y-m-d'))
            ->whereIn('status', ['approved', 'pending'])
            ->orderBy('start_time')
            ->get();
    }

    public function isAvailableForTimeSlot(\DateTime $date, string $startTime, string $endTime): bool
    {
        $existingBookings = $this->getBookingsForDate($date);

        foreach ($existingBookings as $booking) {
            if ($this->timeSlotsOverlap($startTime, $endTime, $booking->start_time, $booking->end_time)) {
                return false;
            }
        }

        return true;
    }

    private function timeSlotsOverlap(string $start1, string $end1, string $start2, string $end2): bool
    {
        return ($start1 < $end2) && ($end1 > $start2);
    }

    public function getTotalBookings(): int
    {
        return $this->bookings()->count();
    }

    public function getApprovedBookings(): int
    {
        return $this->bookings()->where('status', 'approved')->count();
    }

    public function getPendingBookings(): int
    {
        return $this->bookings()->where('status', 'pending')->count();
    }

    public function getImageUrlAttribute(): string
    {
        if ($this->image) {
            return asset('storage/' . $this->image);
        }
        
        return asset('images/default-facility.png');
    }

    public function getTypeIconAttribute(): string
    {
        return match ($this->type) {
            'clubhouse' => 'building',
            'gym' => 'dumbbell',
            'swimming_pool' => 'swimmer',
            'hall' => 'users',
            'playground' => 'football',
            'parking' => 'car',
            default => 'home',
        };
    }
}