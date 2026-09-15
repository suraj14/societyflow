<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Society;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * "Login as Society Admin" - a Super Admin borrowing an admin's session to
 * see the society exactly as its own admin does, without ever knowing or
 * resetting the admin's password.
 *
 * Two session keys carry the whole feature:
 *
 *   impersonator_id  - the Super Admin's own user id, kept while borrowed
 *   impersonating    - true only while borrowed; checked instead of a role,
 *                      because auth()->user() IS the admin during this time
 *                      and so no longer carries the Super Admin role at all
 *
 * That second point is why stop() cannot be gated by role:Super Admin
 * middleware - the active session has the target's role, not the
 * impersonator's. It is gated by the session flag instead.
 */
class ImpersonationController extends Controller
{
    /** Borrow the society's admin account. */
    public function start(Request $request, Society $society): RedirectResponse
    {
        $superAdmin = auth()->user();

        abort_unless($superAdmin->hasRole('Super Admin'), 403);

        // Nested impersonation is refused outright, regardless of what role
        // the currently-borrowed account happens to hold.
        if (session()->has('impersonator_id')) {
            return redirect()->route('super-admin.societies.index')
                ->with('error', 'Already impersonating an admin. Return to Super Admin first.');
        }

        if (!$society->admin_id) {
            return redirect()->route('super-admin.societies.index')
                ->with('error', "{$society->name} has no admin assigned.");
        }

        $targetAdmin = User::where('id', $society->admin_id)
            ->where('society_id', $society->id)   // must belong to THIS society
            ->where('status', 'active')             // must be active
            ->first();

        if (!$targetAdmin) {
            return redirect()->route('super-admin.societies.index')
                ->with('error', 'The assigned admin is inactive or no longer belongs to this society.');
        }

        if (!$targetAdmin->hasAnyRole(['Admin', 'Society Admin'])) {
            return redirect()->route('super-admin.societies.index')
                ->with('error', 'The assigned user does not hold an admin role.');
        }

        ActivityLog::record('impersonation_started', [
            'super_admin_id'   => $superAdmin->id,
            'super_admin_name' => $superAdmin->name,
            'target_admin_id'  => $targetAdmin->id,
            'target_admin_name'=> $targetAdmin->name,
            'society_id'       => $society->id,
            'society_name'     => $society->name,
        ], $society->id, $superAdmin->id);

        session([
            'impersonator_id'   => $superAdmin->id,
            'impersonator_name' => $superAdmin->name,
            'impersonating'     => true,
        ]);

        Auth::login($targetAdmin);
        $request->session()->regenerate();

        return redirect()->route('dashboard')
            ->with('success', "You are now viewing {$society->name} as {$targetAdmin->name}.");
    }

    /** Give the session back to the original Super Admin. */
    public function stop(Request $request): RedirectResponse
    {
        $impersonatorId = session('impersonator_id');

        if (!$impersonatorId) {
            // Nothing to return to - send wherever the current account belongs.
            return redirect()->route('dashboard');
        }

        $original = User::find($impersonatorId);

        // If the Super Admin account vanished or lost the role mid-session,
        // fail safe: clear the impersonation state and do not resurrect
        // access that no longer exists.
        if (!$original || !$original->hasRole('Super Admin')) {
            session()->forget(['impersonator_id', 'impersonator_name', 'impersonating']);

            return redirect()->route('login')
                ->with('error', 'Could not restore the Super Admin session.');
        }

        $borrowedAdmin = auth()->user();

        ActivityLog::record('impersonation_ended', [
            'super_admin_id'    => $original->id,
            'super_admin_name'  => $original->name,
            'target_admin_id'   => $borrowedAdmin?->id,
            'target_admin_name' => $borrowedAdmin?->name,
            'society_id'        => $borrowedAdmin?->society_id,
        ], $borrowedAdmin?->society_id, $original->id);

        session()->forget(['impersonator_id', 'impersonator_name', 'impersonating']);

        Auth::login($original);
        $request->session()->regenerate();

        return redirect()->route('super-admin.societies.index')
            ->with('success', 'Returned to Super Admin.');
    }
}
