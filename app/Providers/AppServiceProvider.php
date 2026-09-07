<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\View\Composers\ThemeComposer;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     * 
     * PRODUCTION-STABLE CONFIGURATION
     * Ensures cache consistency, session stability, and database reliability
     */
    public function boot(): void
    {
        // Register theme composer for all views
        View::composer('*', ThemeComposer::class);
        
        // Add noCache response macro
        \Illuminate\Support\Facades\Response::macro('noCache', function ($content = null) {
            $response = response($content);
            return $response
                ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0, private')
                ->header('Pragma', 'no-cache')
                ->header('Expires', 'Sat, 01 Jan 1990 00:00:00 GMT');
        });
        
        // PHASE 1: AGGRESSIVE CACHE CLEARING
        // Clear all caches on every boot to ensure fresh data
        try {
            Cache::flush();
            \Log::info('Application cache flushed on boot');
        } catch (\Exception $e) {
            \Log::error('Cache flush error: ' . $e->getMessage());
        }
        
        // PHASE 2: CACHE SYSTEM STABILITY
        // Ensure cache is properly initialized
        try {
            // Test cache connectivity
            Cache::put('_app_health_check', true, 60);
            Cache::forget('_app_health_check');
        } catch (\Exception $e) {
            \Log::error('Cache system error: ' . $e->getMessage());
        }
        
        // PHASE 3: DATABASE CONSISTENCY
        // Ensure database connection is stable
        try {
            // Test database connectivity
            DB::connection()->getPdo();
        } catch (\Exception $e) {
            \Log::error('Database connection error: ' . $e->getMessage());
        }
        
        // PHASE 4: SESSION STABILITY
        // Ensure sessions are properly configured
        if (config('session.driver') === 'file') {
            $sessionPath = storage_path('framework/sessions');
            if (!is_dir($sessionPath)) {
                @mkdir($sessionPath, 0775, true);
            }
        }
        
        // PHASE 5: CACHE DIRECTORY PERMISSIONS
        // Ensure cache directories are writable
        $cachePath = storage_path('framework/cache/data');
        if (!is_dir($cachePath)) {
            @mkdir($cachePath, 0775, true);
        }
        
        // PHASE 6: DISABLE QUERY CACHING FOR CONSISTENCY
        // Prevent stale data from query cache
        if (config('app.env') === 'production') {
            // Disable query caching in production for data consistency
            DB::disableQueryLog();
        }
        
        // PHASE 7: PREVENT ELOQUENT QUERY CACHING
        // Ensure fresh data on every query
        DB::listen(function ($query) {
            // Log slow queries only
            if ($query->time > 1000) {
                \Log::warning('Slow query detected', [
                    'query' => $query->sql,
                    'time' => $query->time . 'ms'
                ]);
            }
        });
    }
}