<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\EmailConfigurationService;

class LoadSocietyEmailSettings
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        // Only load email settings for authenticated users
        if (auth()->check()) {
            $societyId = EmailConfigurationService::getCurrentSocietyId();
            EmailConfigurationService::loadAndApplySettings($societyId);
        }

        return $next($request);
    }
}