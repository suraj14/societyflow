<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\View\Composers\ThemeComposer;

class AppServiceProvider extends ServiceProvider
{
    /** Queries slower than this (milliseconds) are logged as warnings. */
    private const SLOW_QUERY_THRESHOLD_MS = 1000;

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
     * This used to call Cache::flush() on every request "to ensure fresh
     * data", which wiped the whole cache store - including Spatie's
     * permission cache, so every hasRole()/can() check re-queried the
     * database - on every single request. It also ran a cache write/read
     * probe and opened a PDO connection per request. All removed: nothing
     * can depend on a cache that is destroyed that often, so there is no
     * behaviour to preserve. If stale data reappears, fix the write path
     * that fails to invalidate rather than flushing globally.
     */
    public function boot(): void
    {
        // Kill switch for seeding / QA / demo runs against a database that holds
        // real provider credentials (see config/messaging.php).
        if (!\App\Support\ExternalMessaging::enabled()) {
            config(['mail.default' => 'log']);
        }

        // Defense in depth: whatever sets a mailer later (a society's SMTP
        // settings, a future code path), a message is cancelled at the last
        // moment while the switch is off unless the active transport is a
        // local one. Returning false from MessageSending stops the send.
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Mail\Events\MessageSending::class, function () {
            if (\App\Support\ExternalMessaging::enabled()) {
                return null;
            }

            $transport = config('mail.mailers.' . config('mail.default') . '.transport');
            if (in_array($transport, ['log', 'array'], true)) {
                return null;
            }

            \Illuminate\Support\Facades\Log::warning('Outgoing mail cancelled: external messaging is disabled', ['transport' => $transport]);

            return false;
        });

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
        
        // Storage directories the file cache and session drivers need.
        // is_dir-guarded, so this costs one stat() when they already exist.
        $this->ensureRuntimeDirectoriesExist();

        // Slow query logging. Useful in production and effectively free:
        // the listener only formats a message past the threshold.
        DB::listen(function ($query) {
            if ($query->time > self::SLOW_QUERY_THRESHOLD_MS) {
                Log::warning('Slow query detected', [
                    'query' => $query->sql,
                    'time'  => $query->time . 'ms',
                ]);
            }
        });
    }

    /**
     * Create the storage directories the file cache and session drivers
     * write into, if they are missing.
     */
    private function ensureRuntimeDirectoriesExist(): void
    {
        $paths = [];

        if (config('session.driver') === 'file') {
            $paths[] = storage_path('framework/sessions');
        }

        if (config('cache.default') === 'file') {
            $paths[] = storage_path('framework/cache/data');
        }

        foreach ($paths as $path) {
            if (!is_dir($path)) {
                @mkdir($path, 0775, true);
            }
        }
    }
}