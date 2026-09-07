<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceAttendance extends Model
{
    use HasFactory;

    protected $table = 'service_attendances';

    protected $fillable = [
        'society_id',
        'service_provider_id',
        'created_by',
        'attendance_date',
        'clock_in_time',
        'clock_out_time',
        'notes',
    ];

    protected $casts = [
        'attendance_date' => 'date',
        'clock_in_time' => 'datetime:H:i',
        'clock_out_time' => 'datetime:H:i',
    ];

    // Relationships
    public function society(): BelongsTo
    {
        return $this->belongsTo(Society::class);
    }

    public function serviceProvider(): BelongsTo
    {
        return $this->belongsTo(ServiceProvider::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Scopes
    public function scopeBySociety($query, $societyId)
    {
        return $query->where('society_id', $societyId);
    }

    public function scopeToday($query)
    {
        return $query->where('attendance_date', today());
    }

    public function scopeByDate($query, $date)
    {
        return $query->where('attendance_date', $date);
    }

    // Helper methods
    public function getStatusAttribute(): string
    {
        if (!$this->clock_in_time) {
            return 'Not Started';
        }
        
        if ($this->clock_in_time && !$this->clock_out_time) {
            return 'Working';
        }
        
        if ($this->clock_in_time && $this->clock_out_time) {
            return 'Completed';
        }
        
        return 'Unknown';
    }

    public function getStatusBadgeAttribute(): string
    {
        return match($this->getStatusAttribute()) {
            'Not Started' => '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">Not Started</span>',
            'Working' => '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">Working</span>',
            'Completed' => '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Completed</span>',
            default => '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">Unknown</span>',
        };
    }

    public function getDurationAttribute(): ?string
    {
        if (!$this->clock_in_time || !$this->clock_out_time) {
            return null;
        }

        $start = \Carbon\Carbon::parse($this->clock_in_time);
        $end = \Carbon\Carbon::parse($this->clock_out_time);
        
        return $start->diff($end)->format('%H:%I');
    }
}