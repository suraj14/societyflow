<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MobileOtp extends Model
{
    protected $fillable = ['phone', 'otp', 'attempts', 'is_used', 'expires_at'];

    protected $casts = [
        'expires_at' => 'datetime',
        'is_used'    => 'boolean',
    ];

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isValid(string $otp): bool
    {
        return !$this->is_used
            && !$this->isExpired()
            && $this->attempts < 5
            && $this->otp === $otp;
    }

    public function incrementAttempts(): void
    {
        $this->increment('attempts');
    }

    public function markUsed(): void
    {
        $this->update(['is_used' => true]);
    }
}
