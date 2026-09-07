<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Staff extends Model
{
    use HasFactory;

    protected $fillable = [
        'society_id',
        'user_id',
        'employee_id',
        'department',
        'designation',
        'joining_date',
        'leaving_date',
        'salary',
        'shift_timings',
        'documents',
        'emergency_contact',
        'address',
        'employment_type',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'joining_date' => 'date',
            'leaving_date' => 'date',
            'salary' => 'decimal:2',
            'shift_timings' => 'array',
            'documents' => 'array',
            'emergency_contact' => 'array',
        ];
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($staff) {
            if (empty($staff->employee_id)) {
                $staff->employee_id = static::generateEmployeeId($staff->society_id);
            }
        });
    }

    // Relationships
    public function society(): BelongsTo
    {
        return $this->belongsTo(Society::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attendance(): HasMany
    {
        return $this->hasMany(StaffAttendance::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(StaffTask::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeByDepartment($query, $department)
    {
        return $query->where('department', $department);
    }

    public function scopeBySociety($query, $societyId)
    {
        return $query->where('society_id', $societyId);
    }

    // Helper methods
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function getServiceDuration(): int
    {
        $endDate = $this->leaving_date ?? now();
        return $this->joining_date->diffInDays($endDate);
    }

    public function getTodayAttendance()
    {
        return $this->attendance()->where('attendance_date', now()->toDateString())->first();
    }

    public function markAttendance(string $status, string $checkInTime = null, string $notes = null): StaffAttendance
    {
        return $this->attendance()->updateOrCreate(
            ['attendance_date' => now()->toDateString()],
            [
                'status' => $status,
                'check_in_time' => $checkInTime ?? now()->format('H:i:s'),
                'notes' => $notes,
            ]
        );
    }

    public function checkOut(string $checkOutTime = null): void
    {
        $attendance = $this->getTodayAttendance();
        
        if ($attendance) {
            $checkOut = $checkOutTime ?? now()->format('H:i:s');
            $hoursWorked = $this->calculateHoursWorked($attendance->check_in_time, $checkOut);
            
            $attendance->update([
                'check_out_time' => $checkOut,
                'hours_worked' => $hoursWorked,
            ]);
        }
    }

    private function calculateHoursWorked(string $checkIn, string $checkOut): float
    {
        $start = \Carbon\Carbon::createFromFormat('H:i:s', $checkIn);
        $end = \Carbon\Carbon::createFromFormat('H:i:s', $checkOut);
        
        return $end->diffInHours($start, true);
    }

    public function getMonthlyAttendanceStats(int $month = null, int $year = null): array
    {
        $month = $month ?? now()->month;
        $year = $year ?? now()->year;

        $attendance = $this->attendance()
            ->whereMonth('attendance_date', $month)
            ->whereYear('attendance_date', $year)
            ->get();

        return [
            'total_days' => $attendance->count(),
            'present_days' => $attendance->where('status', 'present')->count(),
            'absent_days' => $attendance->where('status', 'absent')->count(),
            'half_days' => $attendance->where('status', 'half_day')->count(),
            'late_days' => $attendance->where('status', 'late')->count(),
            'total_hours' => $attendance->sum('hours_worked'),
        ];
    }

    public function getPendingTasks(): int
    {
        return $this->tasks()->whereIn('status', ['pending', 'in_progress'])->count();
    }

    public function getCompletedTasks(): int
    {
        return $this->tasks()->where('status', 'completed')->count();
    }

    public function getDepartmentColorAttribute(): string
    {
        return match ($this->department) {
            'security' => 'blue',
            'maintenance' => 'orange',
            'housekeeping' => 'green',
            'gardening' => 'emerald',
            'administration' => 'purple',
            default => 'gray',
        };
    }

    public static function generateEmployeeId(int $societyId): string
    {
        $prefix = 'EMP';
        $societyCode = str_pad($societyId, 3, '0', STR_PAD_LEFT);
        $sequence = static::where('society_id', $societyId)->count() + 1;
        $sequenceCode = str_pad($sequence, 4, '0', STR_PAD_LEFT);
        
        return $prefix . $societyCode . $sequenceCode;
    }
}