<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Replay protection for ordinary HTML form posts.
 *
 * A form that includes partials/idempotency (a hidden `_idempotency` token
 * minted when the page rendered) can be submitted once per token. A double
 * click, a browser "resend" after a timeout, or a retried request carries
 * the same token and is answered with a redirect back and a notice instead
 * of creating a second complaint / visitor / expense / owner.
 *
 * The token is bound to the session so one user's token can never be used
 * to suppress another's submission. Forms without the field are untouched,
 * and a validation failure releases the token so the corrected resubmission
 * goes through (the token is only consumed once the response is a success
 * redirect, i.e. the write happened).
 */
class PreventDuplicateFormSubmission
{
    public const FIELD = '_idempotency';

    /** How long a consumed token stays consumed. */
    private const TTL_SECONDS = 600;

    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->isMethod('POST') && !$request->isMethod('PUT') && !$request->isMethod('PATCH')) {
            return $next($request);
        }

        $token = (string) $request->input(self::FIELD, '');
        if ($token === '' || strlen($token) > 80 || !preg_match('/^[A-Za-z0-9\-_]+$/', $token)) {
            return $next($request);
        }

        // Bound to the signed-in user (or, for a guest, the session) so one
        // person's token can never suppress another person's submission.
        $owner = $request->user()?->getAuthIdentifier();
        $scope = $owner !== null ? 'u' . $owner : 's' . ($request->session()->getId() ?: 'nosession');
        $key = 'idem:' . sha1($scope . '|' . $token);

        if (Cache::get($key) === 'done') {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'This form was already submitted.'], 409);
            }

            return back()->with('info', 'This form was already submitted - the first submission was kept.');
        }

        // Claim the token for the duration of this request so two truly
        // simultaneous requests cannot both pass the check above.
        if (!Cache::add($key, 'pending', self::TTL_SECONDS)) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'This form is already being submitted.'], 409);
            }

            return back()->with('info', 'This form was already submitted - the first submission was kept.');
        }

        try {
            $response = $next($request);
        } catch (\Throwable $e) {
            Cache::forget($key);
            throw $e;
        }

        // A write that did not happen (validation error, 4xx/5xx) must not
        // burn the token - the user corrects the form and submits again.
        $succeeded = $response->isRedirection() && !$request->session()->has('errors')
            || ($response->isSuccessful() && !$response->isRedirection() && $request->expectsJson());

        if ($succeeded) {
            Cache::put($key, 'done', self::TTL_SECONDS);
        } else {
            Cache::forget($key);
        }

        return $response;
    }
}
