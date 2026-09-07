<?php

namespace App\Http\Controllers\Api;

/**
 * Helper to build storage URLs for the mobile API.
 * Returns relative paths (/storage/...) so Flutter can prepend
 * the correct server base URL (which changes with DHCP).
 */
class ApiImageHelper
{
    /**
     * Convert a storage path like "notices/abc.jpg" → relative URL "/storage/notices/abc.jpg"
     * Flutter will prepend the server base URL at runtime.
     * Returns null if path is empty.
     */
    public static function storageUrl(?string $path): ?string
    {
        if (empty($path)) return null;

        // Already a full external URL (e.g. ui-avatars.com, gravatar) — keep as-is
        if (str_starts_with($path, 'http')) return $path;

        // Strip leading /storage/ or storage/ if present
        $path = ltrim($path, '/');
        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, 8);
        }

        // Return relative path — Flutter will prepend server base URL
        return '/storage/' . $path;
    }
}
