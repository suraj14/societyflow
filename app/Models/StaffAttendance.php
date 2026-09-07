<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffAttendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'staff_id',
        'attendance_date',
        'check_in_time',
        'check_out_time',
        'hours_worked',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
            'check_in_time' => 'datetime:H:i:s',
            'check_out_time' => 'datetime:H:i:s',
            'hours_worked' => 'decimal:2',
        ];
    }

    // Relationships
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    // Scopes
    public function scopePresent($query)
    {
        return $query->where('status', 'present');
    }

    public function scopeAbsent($query)
    {
        return $query->where('status', 'absent');
    }

    public function scopeHalfDay($query)
    {
        return $query->where('status', 'half_day');
    }

    public function scopeLate($query)
    {
        return $query->where('status', 'late');
    }

    public function scopeForDate($query, $date)
    {
        return $query->where('attendance_date', $date);
    }

    public function scopeForMonth($query, $month, $year)
    {
        return $query->whereMonth('attendance_date', $month)
                    ->whereYear('attendance_date', $year);
    }

    // Helper methods
    public function isPresent(): bool
    {
        return $this->status === 'present';
    }

    public function isAbsent(): bool
    {
        return $this->status === 'absent';
    }

    public function isHalfDay(): bool
    {
        return $this->status === 'half_day';
    }

    public function isLate(): bool
    {
        return $this->status === 'late';
    }

    public function isHoliday(): bool
    {
        return $this->status === 'holiday';
    }

    public function hasCheckedIn(): bool
    {
        return !is_null($this->check_in_time);
    }

    public function hasCheckedOut(): bool
    {
        return !is_null($this->check_out_time);
    }

    public function calculateHoursWorked(): float
    {
        if (!$this->check_in_time || !$this->check_out_time) {
            return 0;
        }

        $checkIn = \Carbon\Carbon::createFromFormat('H:i:s', $this->check_in_time);
        $checkOut = \Carbon\Carbon::createFromFormat('H:i:s', $this->check_out_time);

        return $checkOut->diffInHours($checkIn, true);
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'present' => 'green',
            'absent' => 'red',
            'half_day' => 'yellow',
            'late' => 'orange',
            'holiday' => 'blue',
            default => 'gray',
        };
    }

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($attendance) {
            if ($attendance->check_in_time && $attendance->check_out_time) {
                $attendance->hours_worked = $attendance->calculateHoursWorked();
            }
        });
    }
}