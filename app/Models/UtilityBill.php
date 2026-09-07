<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UtilityBill extends Model
{
    use HasFactory;

    protected $fillable = [
        'society_id',
        'flat_id',
        'bill_type',
        'bill_amount',
        'bill_date',
        'due_date',
        'bill_file_path',
        'payment_date',
        'payment_proof_path',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'bill_amount' => 'decimal:2',
            'bill_date' => 'date',
            'due_date' => 'date',
            'payment_date' => 'date',
        ];
    }

    // Relationships
    public function society(): BelongsTo
    {
        return $this->belongsTo(Society::class);
    }

    public function flat(): BelongsTo
    {
        return $this->belongsTo(Flat::class);
    }

    // Scopes
    public function scopeBySociety($query, $societyId)
    {
        return $query->where('society_id', $societyId);
    }

    public function scopeByFlat($query, $flatId)
    {
        return $query->where('flat_id', $flatId);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeUnpaid($query)
    {
        return $query->where('status', 'unpaid');
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    public function scopeOverdue($query)
    {
        return $query->where('due_date', '<', now()->toDateString())
                     ->where('status', '!=', 'paid');
    }

    // Helper methods
    public function isOverdue(): bool
    {
        return $this->due_date < now()->toDateString() && $this->status !== 'paid';
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function isUnpaid(): bool
    {
        return $this->status === 'unpaid';
    }

    public function getStatusBadgeAttribute(): string
    {
        return match($this->status) {
            'paid' => '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Paid</span>',
            'partial' => '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">Partial</span>',
            'unpaid' => '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">Unpaid</span>',
            default => '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">Unknown</span>',
        };
    }

    public static function getBillTypes(): array
    {
        return [
            'Water Bill' => 'Water Bill',
            'Electricity Bill' => 'Electricity Bill',
            'Gas Bill' => 'Gas Bill',
            'Internet Bill' => 'Internet Bill',
            'Maintenance Bill' => 'Maintenance Bill',
            'Parking Bill' => 'Parking Bill',
            'Waste Management' => 'Waste Management',
            'Security Bill' => 'Security Bill',
            'Other' => 'Other',
        ];
    }
}
