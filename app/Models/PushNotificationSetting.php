<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class PushNotificationSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'society_id',
        'enabled',
        'fcm_server_key',
        'vapid_public_key',
        'vapid_private_key',
        'enabled_triggers',
        'role_permissions',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'enabled_triggers' => 'array',
        'role_permissions' => 'array',
    ];

    public function society(): BelongsTo
    {
        return $this->belongsTo(Society::class);
    }

    public function getFcmServerKeyAttribute($value)
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function setFcmServerKeyAttribute($value)
    {
        $this->attributes['fcm_server_key'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getVapidPrivateKeyAttribute($value)
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function setVapidPrivateKeyAttribute($value)
    {
        $this->attributes['vapid_private_key'] = $value ? Crypt::encryptString($value) : null;
    }

    public function isEnabled(): bool
    {
        return $this->enabled === true;
    }

    public function isTriggerEnabled(string $trigger): bool
    {
        return in_array($trigger, $this->enabled_triggers ?? []);
    }

    public function canRoleSend(string $role): bool
    {
        $permissions = $this->role_permissions ?? [];
        return $permissions[$role] ?? false;
    }

    public function enableTrigger(string $trigger): void
    {
        $triggers = $this->enabled_triggers ?? [];
        if (!in_array($trigger, $triggers)) {
            $triggers[] = $trigger;
            $this->update(['enabled_triggers' => $triggers]);
        }
    }

    public function disableTrigger(string $trigger): void
    {
        $triggers = $this->enabled_triggers ?? [];
        $this->update(['enabled_triggers' => array_diff($triggers, [$trigger])]);
    }
}
