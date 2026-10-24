<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * A deactivated (`inactive`) or `suspended` account may not keep using the app: its open web session is ended here on the next request, and
 * its mobile token is refused (tokens are also deleted the moment the status changes - see User::booted). Active accounts, guests, and the
 * Super Admin's own active account pass untouched. Sign-in itself is refused separately (web, API and OTP login).
 */
class EnsureAccountActive
{
    public const MESSAGE = 'Your account is inactive. Please contact your society admin.';

    public function handle(Request $request, Closure $next): Response
    {
        $isApi = $request->is('api/*');
        $user = $isApi ? auth('sanctum')->user() : $request->user();

        if (!$user || !$user->isBlocked()) {
            return $next($request);
        }

        if ($isApi || $request->expectsJson()) {
            return response()->json(['success' => false, 'message' => self::MESSAGE, 'account_inactive' => true], 403);
        }

        Auth::guard('web')->logout();
        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return redirect()->route('login')->withErrors(['email' => self::MESSAGE]);
    }
}
