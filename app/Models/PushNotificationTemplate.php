<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PushNotificationTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'society_id',
        'trigger_action',
        'title',
        'body',
        'icon',
        'badge',
        'click_action',
        'data',
        'is_active',
    ];

    protected $casts = [
        'data' => 'array',
        'is_active' => 'boolean',
    ];

    public function society(): BelongsTo
    {
        return $this->belongsTo(Society::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForTrigger($query, string $trigger)
    {
        return $query->where('trigger_action', $trigger);
    }

    public function replaceVariables(array $variables): array
    {
        $title = $this->title;
        $body = $this->body;

        foreach ($variables as $key => $value) {
            $placeholder = '{{' . $key . '}}';
            $title = str_replace($placeholder, (string)$value, $title);
            $body = str_replace($placeholder, (string)$value, $body);
        }

        return [
            'title' => $title,
            'body' => $body,
        ];
    }
}
