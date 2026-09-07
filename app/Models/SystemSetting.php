<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

class SystemSetting extends Model
{
    protected $fillable = ['society_id', 'group', 'key', 'value', 'type'];

    // Relationships
    public function society(): BelongsTo
    {
        return $this->belongsTo(Society::class);
    }

    /**
     * Get a setting value by group and key with society fallback
     */
    public static function get(string $group, string $key, $default = null, ?int $societyId = null)
    {
        // If no society ID provided, try to get from current user
        if ($societyId === null) {
            $societyId = auth()->user()?->society_id;
        }

        $cacheKey = "setting.{$societyId}.{$group}.{$key}";
        
        return Cache::remember($cacheKey, 3600, function () use ($group, $key, $default, $societyId) {
            // First try to get society-specific setting
            if ($societyId) {
                $setting = self::where('society_id', $societyId)
                    ->where('group', $group)
                    ->where('key', $key)
                    ->first();
                
                if ($setting) {
                    return self::castValue($setting->value, $setting->type);
                }
            }
            
            // Fallback to global setting (society_id = null)
            $setting = self::whereNull('society_id')
                ->where('group', $group)
                ->where('key', $key)
                ->first();
            
            if (!$setting) {
                return $default;
            }
            
            return self::castValue($setting->value, $setting->type);
        });
    }

    /**
     * Set a setting value for a specific society or globally
     */
    public static function set(string $group, string $key, $value, string $type = 'string', ?int $societyId = null): void
    {
        // If no society ID provided, try to get from current user
        if ($societyId === null) {
            $societyId = auth()->user()?->society_id;
        }

        $storedValue = self::prepareValue($value, $type);
        
        self::updateOrCreate(
            ['society_id' => $societyId, 'group' => $group, 'key' => $key],
            ['value' => $storedValue, 'type' => $type]
        );
        
        // Clear cache for both society-specific and global
        Cache::forget("setting.{$societyId}.{$group}.{$key}");
        Cache::forget("settings.{$societyId}.{$group}");
        if ($societyId) {
            Cache::forget("setting.null.{$group}.{$key}");
            Cache::forget("settings.null.{$group}");
        }
    }

    /**
     * Get all settings for a group with society fallback
     */
    public static function getGroup(string $group, ?int $societyId = null): array
    {
        // If no society ID provided, try to get from current user
        if ($societyId === null) {
            $societyId = auth()->user()?->society_id;
        }

        $cacheKey = "settings.{$societyId}.{$group}";
        
        return Cache::remember($cacheKey, 3600, function () use ($group, $societyId) {
            $result = [];
            
            // For Admin users, only get society-specific settings (no global fallback)
            $user = auth()->user();
            if ($user && $user->hasRole('Admin') && $societyId) {
                // Admin only sees their society's settings
                $societySettings = self::where('society_id', $societyId)
                    ->where('group', $group)
                    ->get();
                
                foreach ($societySettings as $setting) {
                    $result[$setting->key] = self::castValue($setting->value, $setting->type);
                }
            } else {
                // Super Admin or global access: get global settings first
                $globalSettings = self::whereNull('society_id')
                    ->where('group', $group)
                    ->get();
                
                foreach ($globalSettings as $setting) {
                    $result[$setting->key] = self::castValue($setting->value, $setting->type);
                }
                
                // Override with society-specific settings if they exist
                if ($societyId) {
                    $societySettings = self::where('society_id', $societyId)
                        ->where('group', $group)
                        ->get();
                    
                    foreach ($societySettings as $setting) {
                        $result[$setting->key] = self::castValue($setting->value, $setting->type);
                    }
                }
            }
            
            return $result;
        });
    }

    /**
     * Set multiple settings for a group
     */
    public static function setGroup(string $group, array $settings, array $types = [], ?int $societyId = null): void
    {
        foreach ($settings as $key => $value) {
            $type = $types[$key] ?? 'string';
            self::set($group, $key, $value, $type, $societyId);
        }
    }

    /**
     * Safely decrypt a value — returns null if decryption fails
     */
    protected static function safeDecrypt(?string $value): ?string
    {
        if (empty($value)) return null;
        try {
            return Crypt::decryptString($value);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('SystemSetting decrypt failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Cast value based on type
     */
    protected static function castValue($value, string $type)
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $value,
            'json' => json_decode($value, true),
            'encrypted' => self::safeDecrypt($value),
            default => $value,
        };
    }

    /**
     * Prepare value for storage
     */
    protected static function prepareValue($value, string $type): ?string
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'boolean' => $value ? '1' : '0',
            'integer' => (string) $value,
            'json' => json_encode($value),
            'encrypted' => Crypt::encryptString($value),
            default => (string) $value,
        };
    }

    /**
     * Clear all settings cache
     */
    public static function clearCache(?int $societyId = null): void
    {
        $groups = ['app', 'language', 'storage', 'theme', 'currency', 'email', 'payment', 'push', 'sms', 'security', 'permissions'];
        
        foreach ($groups as $group) {
            // Clear society-specific cache
            Cache::forget("settings.{$societyId}.{$group}");
            
            // Clear global cache
            Cache::forget("settings.null.{$group}");
            
            // Clear individual setting caches
            $settings = self::where('group', $group)->pluck('key');
            foreach ($settings as $key) {
                Cache::forget("setting.{$societyId}.{$group}.{$key}");
                Cache::forget("setting.null.{$group}.{$key}");
            }
        }
    }
}
