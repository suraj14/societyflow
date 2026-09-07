<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HandleInertiaRequests
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // This is a placeholder for Inertia.js middleware
        // Since we're using Blade + Livewire, we don't need Inertia
        return $next($request);
    }
}