<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Setting extends Model
{
    use HasFactory;

    protected $fillable = [
        'society_id',
        'key',
        'value',
        'type',
        'description',
        'group',
    ];

    // Relationships
    public function society(): BelongsTo
    {
        return $this->belongsTo(Society::class);
    }

    // Scopes
    public function scopeGlobal($query)
    {
        return $query->whereNull('society_id');
    }

    public function scopeBySociety($query, $societyId)
    {
        return $query->where('society_id', $societyId);
    }

    public function scopeByGroup($query, $group)
    {
        return $query->where('group', $group);
    }

    // Helper methods
    public function getValue()
    {
        return match ($this->type) {
            'boolean' => (bool) $this->value,
            'integer' => (int) $this->value,
            'float' => (float) $this->value,
            'json', 'array' => json_decode($this->value, true),
            default => $this->value,
        };
    }

    public function setValue($value): void
    {
        $this->value = is_array($value) || is_object($value) 
            ? json_encode($value) 
            : $value;
    }

    public static function get(string $key, $default = null, int $societyId = null)
    {
        $query = static::where('key', $key);
        
        if ($societyId) {
            $query->where('society_id', $societyId);
        } else {
            $query->whereNull('society_id');
        }
        
        $setting = $query->first();
        
        return $setting ? $setting->getValue() : $default;
    }

    public static function set(string $key, $value, string $type = 'string', int $societyId = null): void
    {
        $setting = static::updateOrCreate(
            [
                'key' => $key,
                'society_id' => $societyId,
            ],
            [
                'value' => is_array($value) || is_object($value) ? json_encode($value) : $value,
                'type' => $type,
            ]
        );
    }
}