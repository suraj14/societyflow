<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class FileHelper
{
    /**
     * Safely delete a file from storage without throwing errors
     * Handles missing fileinfo extension gracefully
     */
    public static function safeDelete($path, $disk = 'public')
    {
        if (!$path) {
            return true;
        }

        try {
            if (Storage::disk($disk)->exists($path)) {
                Storage::disk($disk)->delete($path);
            }
            return true;
        } catch (\Exception $e) {
            // Log the error but don't throw - allows operations to continue
            Log::warning("Failed to delete file '{$path}' from disk '{$disk}': " . $e->getMessage());
            return false;
        }
    }

    /**
     * Safely store a file without throwing errors
     */
    public static function safeStore($file, $path, $disk = 'public')
    {
        try {
            return $file->store($path, $disk);
        } catch (\Exception $e) {
            Log::error("Failed to store file in '{$path}' on disk '{$disk}': " . $e->getMessage());
            return null;
        }
    }
}
