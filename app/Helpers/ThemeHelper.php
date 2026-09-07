<?php

namespace App\Helpers;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class ThemeHelper
{
    /**
     * Get theme settings for the current user's society
     */
    public static function getThemeSettings()
    {
        $user = Auth::user();
        $societyId = $user ? $user->society_id : null;
        
        if (!$societyId) {
            return self::getDefaultSettings();
        }
        
        $cacheKey = "theme_settings_{$societyId}";
        
        return Cache::remember($cacheKey, 3600, function () use ($societyId) {
            $themeSettings = SystemSetting::getGroup('theme', $societyId);
            $appSettings = SystemSetting::getGroup('app', $societyId);
            
            $defaults = self::getDefaultSettings();
            
            return array_merge($defaults, $themeSettings, $appSettings);
        });
    }
    
    /**
     * Get default theme settings
     */
    public static function getDefaultSettings()
    {
        return [
            'primary_color' => '#6366f1',
            'secondary_color' => '#64748b',
            'accent_color' => '#f59e0b',
            'sidebar_theme' => 'light',
            'button_style' => 'rounded',
            'society_logo' => null,
            'app_name' => 'SocietyFlow',
            'society_tagline' => 'Modern Society Management',
        ];
    }
    
    /**
     * Get primary color for the current society
     */
    public static function getPrimaryColor()
    {
        $settings = self::getThemeSettings();
        return $settings['primary_color'] ?? '#6366f1';
    }
    
    /**
     * Get secondary color for the current society
     */
    public static function getSecondaryColor()
    {
        $settings = self::getThemeSettings();
        return $settings['secondary_color'] ?? '#64748b';
    }
    
    /**
     * Get app name for the current society
     */
    public static function getAppName()
    {
        $settings = self::getThemeSettings();
        return $settings['app_name'] ?? 'SocietyFlow';
    }
    
    /**
     * Get society tagline
     */
    public static function getSocietyTagline()
    {
        $settings = self::getThemeSettings();
        return $settings['society_tagline'] ?? 'Modern Society Management';
    }
    
    /**
     * Get society logo URL
     */
    public static function getSocietyLogo()
    {
        $settings = self::getThemeSettings();
        $logoPath = $settings['society_logo'] ?? null;
        
        if ($logoPath && file_exists(storage_path('app/public/' . $logoPath))) {
            return asset('storage/' . $logoPath);
        }
        
        return null;
    }
    
    /**
     * Generate CSS for theme colors
     */
    public static function getThemeCss()
    {
        $settings = self::getThemeSettings();
        $primaryColor = $settings['primary_color'] ?? '#6366f1';
        $secondaryColor = $settings['secondary_color'] ?? '#64748b';
        
        return "
        :root {
            --primary-color: {$primaryColor};
            --secondary-color: {$secondaryColor};
        }
        
        .bg-primary { 
            background-color: {$primaryColor} !important; 
        }
        
        .text-primary { 
            color: {$primaryColor} !important; 
        }
        
        .border-primary { 
            border-color: {$primaryColor} !important; 
        }
        
        .hover\\:bg-primary:hover { 
            background-color: {$primaryColor} !important; 
        }
        
        .focus\\:ring-primary:focus { 
            --tw-ring-color: {$primaryColor} !important; 
        }
        
        .bg-primary-50 {
            background-color: " . self::lightenColor($primaryColor, 0.9) . " !important;
        }
        
        .text-primary-600 {
            color: {$primaryColor} !important;
        }
        
        /* Button styles */
        .btn-primary {
            background-color: {$primaryColor} !important;
            color: white !important;
        }
        
        .btn-primary:hover {
            background-color: " . self::darkenColor($primaryColor, 0.1) . " !important;
        }
        
        /* Active navigation styles */
        .nav-active {
            background-color: " . self::lightenColor($primaryColor, 0.9) . " !important;
            color: {$primaryColor} !important;
        }
        ";
    }
    
    /**
     * Lighten a hex color
     */
    private static function lightenColor($hex, $percent)
    {
        $hex = str_replace('#', '', $hex);
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        
        $r = min(255, $r + ($percent * 255));
        $g = min(255, $g + ($percent * 255));
        $b = min(255, $b + ($percent * 255));
        
        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }
    
    /**
     * Darken a hex color
     */
    private static function darkenColor($hex, $percent)
    {
        $hex = str_replace('#', '', $hex);
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        
        $r = max(0, $r - ($percent * 255));
        $g = max(0, $g - ($percent * 255));
        $b = max(0, $b - ($percent * 255));
        
        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }
    
    /**
     * Clear theme cache for a society
     */
    public static function clearCache($societyId = null)
    {
        if ($societyId) {
            Cache::forget("theme_settings_{$societyId}");
        } else {
            $user = Auth::user();
            if ($user && $user->society_id) {
                Cache::forget("theme_settings_{$user->society_id}");
            }
        }
    }
}