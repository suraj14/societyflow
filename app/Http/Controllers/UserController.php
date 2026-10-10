<?php

namespace App\Http\Controllers;

use App\Http\Controllers\BaseController;
use App\Models\User;
use App\Events\UserCreated;
use App\Traits\HandlesFormSubmissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class UserController extends BaseController
{
    use HandlesFormSubmissions;

    /**
     * Roles the acting admin is allowed to assign.
     *
     * CRITICAL: hiding "Super Admin" from the dropdown is not enough - the
     * form used to render every role via Role::all() regardless of who was
     * viewing it, and store()/update() validated the submitted role only
     * against `exists:roles,name`. An Admin (a society-level role) could
     * therefore POST role=Super Admin directly - by request-crafting, not
     * even needing the dropdown to show it - and grant a brand new account,
     * or an existing user in their own society, platform-wide authority.
     * Super Admin itself keeps unrestricted access; every other caller of
     * this controller is already limited to Admin by authorizeRole() above,
     * so excluding Super Admin here is the entire fix.
     *
     * @return \Illuminate\Support\Collection<int,\Spatie\Permission\Models\Role>
     */
    protected function assignableRoles()
    {
        // Villa Owner / Apartment Owner are legacy role names kept only for
        // users not yet migrated (php artisan users:migrate-to-owner-role) -
        // every new assignment should use the canonical 'Owner' role, so
        // they never appear as a choice here.
        $roles = Role::all()->reject(fn (Role $role) => in_array($role->name, ['Villa Owner', 'Apartment Owner'], true));

        if (auth()->user()->hasRole('Super Admin')) {
            return $roles->values();
        }

        return $roles->reject(fn (Role $role) => $role->name === 'Super Admin')->values();
    }

    public function index()
    {
        // Only admin and super admin can access this
        $this->authorizeRole('Super Admin', 'Admin');
        
        $user = auth()->user();
        $societyId = $this->getSocietyId();
        
        // Build query based on role
        if ($user->hasRole('Super Admin')) {
            // Super Admin sees all users including other admins
            $users = User::with('roles')->get();
        } else {
            // Admin sees their society's users including themselves and other admins from their society
            $users = User::bySociety($societyId)
                ->with('roles')
                ->get();
        }
        
        // Get available roles for the dropdown - the same modals on this
        // page post to store()/update(), which now enforce this same set
        // server-side, but the picker should not offer Super Admin at all
        // to a caller who could never actually grant it.
        $roles = $this->assignableRoles();

        return view('users.index', compact('users', 'roles'));
    }
    
    public function create()
    {
        // Only admin and super admin can access this
        $this->authorizeRole('Super Admin', 'Admin');

        // Get available roles (never Super Admin for a caller who is not one)
        $roles = $this->assignableRoles();

        // Super Admin has no society of their own, so the form must let them
        // pick one; store() rejected every Super Admin submission otherwise.
        $societies = auth()->user()->hasRole('Super Admin')
            ? \App\Models\Society::where('status', 'active')->orderBy('name')->get()
            : collect();

        return view('users.create', compact('roles', 'societies'));
    }
    
    public function store(Request $request)
    {
        // Only admin and super admin can access this
        $this->authorizeRole('Super Admin', 'Admin');
        
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'phone' => 'required|string|max:20',
            // In addition to existing as a role at all, it must be one this
            // caller is allowed to grant - never trust the dropdown alone.
            'role' => ['required', Rule::in($this->assignableRoles()->pluck('name'))],
            'avatar' => \App\Support\ProfileImage::rules(),
            // Shown on Society Info > Committee; empty = not a committee member.
            'committee_designation' => 'nullable|string|max:100',
        ]);

        $currentUser = auth()->user();
        $societyId = $this->getSocietyId();

        // Super Admin has no society_id; take the one chosen in the form,
        // validated against real societies so a forged id cannot attach a
        // user to an arbitrary tenant. Admins always get their own society
        // regardless of what the request carries.
        if ($currentUser->hasRole('Super Admin')) {
            $request->validate(['society_id' => 'required|exists:societies,id']);
            $societyId = (int) $request->society_id;
        }

        // A failed upload (storage full, a genuine filesystem permissions
        // problem) used to crash the whole request with a raw 500 before
        // the user row was ever created, and the admin had no idea why.
        // handleFormSubmission() turns any exception thrown in here -
        // including from handleFileUpload() - into an ordinary
        // redirect-back-with-error, same as a ValidationException would.
        //
        // The success message and redirect are passed to
        // handleFormSubmission() itself (the convention every other
        // controller using this trait follows) rather than built inside
        // the closure and returned - handleFormSubmission() only ever
        // uses the closure's return value as JSON `data`, never as the
        // HTML response; a closure that returned its own redirect() here
        // would have that response silently discarded in favour of a bare
        // back() with the generic default message, which is not what was
        // tested or intended.
        $successMessage = 'User created successfully! ' . \App\Helpers\TemporaryPassword::noticeFor($request->email);

        return $this->handleFormSubmission(function () use ($request, $societyId) {
            $avatarPath = $request->hasFile('avatar')
                ? \App\Support\ProfileImage::store($request->file('avatar'))
                : null;

            // Random, emailed to the user by the UserCreated listener below.
            // Never rendered back to the admin who created the account.
            $temporaryPassword = \App\Helpers\TemporaryPassword::generate();

            try {
                $user = User::create([
                    'name' => $request->name,
                    'email' => $request->email,
                    'phone' => $request->phone,
                    'password' => Hash::make($temporaryPassword),
                    'society_id' => $societyId,
                    'avatar' => $avatarPath,
                    'committee_designation' => $this->designation($request),
                    'status' => 'active',
                ]);

                $user->assignRole($request->role);
                UserCreated::dispatch($user, $temporaryPassword);
            } catch (\Throwable $e) {
                // The transaction rolls the row back; the file is outside it.
                \App\Support\ProfileImage::delete($avatarPath);
                throw $e;
            }

            return $user;
        }, $successMessage, 'users.index');
    }

    /**
     * "View" a user. The resource route (GET /users/{user}) has always
     * existed - Route::resource registers it regardless of whether the
     * controller implements it - but this method never did, so visiting
     * the URL directly (no link in the app points here) threw an uncaught
     * BadMethodCallException and 500'd. There is no dedicated read-only
     * profile view; the edit form already shows everything about a user,
     * so this redirects there under the same authorization edit() applies,
     * rather than duplicating it in a template that doesn't exist.
     */
    public function show(User $user)
    {
        $this->authorizeRole('Super Admin', 'Admin');

        if (!auth()->user()->hasRole('Super Admin') && $user->society_id !== $this->getSocietyId()) {
            abort(404, 'User not found.');
        }

        return redirect()->route('users.edit', $user);
    }

    public function edit(User $user)
    {
        // Only admin and super admin can access this
        $this->authorizeRole('Super Admin', 'Admin');
        
        $currentUser = auth()->user();
        $societyId = $this->getSocietyId();

        // Was `if ($currentUser->hasRole('Admin') && ...)`. Not independently
        // exploitable - authorizeRole('Super Admin', 'Admin') above already
        // limits callers to those two roles, and Admin is exactly the one
        // the && named. Rewritten anyway (CLAUDE.md §3: scope by default,
        // exempt Super Admin explicitly) so it stops depending on the
        // permission map never granting user management to a third role,
        // and so a user holding both Admin and Super Admin is still exempt.
        // Response shape (redirect-with-error, not abort) is unchanged.
        if (!$currentUser->hasRole('Super Admin') && $user->society_id !== $societyId) {
            return back()->with('error', 'User not found.');
        }

        // Get available roles (never Super Admin for a caller who is not one)
        $roles = $this->assignableRoles();

        return view('users.edit', compact('user', 'roles'));
    }
    
    /** The submitted committee designation, trimmed; null when blank. */
    private function designation(Request $request): ?string
    {
        $value = trim((string) $request->input('committee_designation'));

        return $value === '' ? null : $value;
    }

    public function update(Request $request, User $user)
    {
        // Only admin and super admin can access this
        $this->authorizeRole('Super Admin', 'Admin');
        
        $currentUser = auth()->user();
        $societyId = $this->getSocietyId();
        
        // Was `if ($currentUser->hasRole('Admin') && ...)` - see edit() above
        // for why this is defensive hardening rather than a live exploit fix.
        // These early returns used to be JSON-only, so the standalone edit
        // form (a normal browser POST) received raw JSON instead of a page.
        if (!$currentUser->hasRole('Super Admin') && $user->society_id !== $societyId) {
            abort(404, 'User not found.');
        }

        // Check if user is trying to change their own role
        if ($user->id === auth()->id() && $request->role !== $user->roles->first()?->name) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'You cannot change your own role.'], 422);
            }

            return back()->withInput()->withErrors(['role' => 'You cannot change your own role.']);
        }
        
        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            // Same rule as store(): never let a caller grant a role beyond
            // what they themselves are allowed to assign, regardless of
            // what the edit form's dropdown happened to render.
            'role' => ['required', Rule::in($this->assignableRoles()->pluck('name'))],
            'status' => 'required|in:active,inactive',
            'avatar' => \App\Support\ProfileImage::rules(),
            'remove_avatar' => 'nullable|boolean',
            'committee_designation' => 'nullable|string|max:100',
        ]);

        // See store() for why the success redirect is passed to
        // handleFormSubmission() rather than returned from the closure.
        return $this->handleFormSubmission(function () use ($request, $user) {
            $updateData = [
                'name' => $request->name,
                'phone' => $request->phone,
                'status' => $request->status,
                // Blank clears it: the person leaves the committee list.
                'committee_designation' => $this->designation($request),
            ];

            // The row update and the picture change succeed or fail
            // together. ProfileImage::change() stores the new file first and
            // only removes the old one after the row is saved (and only if
            // no other account/owner still points at it), so a failed upload
            // can never leave this user with neither picture - the
            // "leaked on disk / unchanged after edit" behaviour this
            // replaces. No file selected keeps the current picture.
            \Illuminate\Support\Facades\DB::transaction(function () use ($request, $user, $updateData) {
                $user->update($updateData);

                \App\Support\ProfileImage::change(
                    $user,
                    $request->file('avatar'),
                    $request->boolean('remove_avatar') && !$request->hasFile('avatar'),
                );
            });

            if ($user->id !== auth()->id()) {
                $user->syncRoles([$request->role]);
            }

            return $user;
        }, 'User updated successfully!', 'users.index');
    }

    /**
     * Admin/Super Admin sets a new password for any normal user's account -
     * Owner, Tenant, Staff, Guard, Accountant, or any other role. The one
     * shared AdminPasswordResetService is the same code OwnerController's
     * resetPassword() uses, so there is a single place that hashes and
     * saves a password and revokes tokens, not one per role. This only
     * ever changes the password - role, email, society and any
     * property/tenancy assignment are untouched.
     */
    public function resetPassword(Request $request, User $user, \App\Services\AdminPasswordResetService $passwordReset)
    {
        $this->authorizeRole('Super Admin', 'Admin');
        $this->authorizeSocietyAccess($user);

        // An ordinary Admin must never be able to reach a Super Admin
        // account through this generic endpoint - preserve the existing
        // privilege hierarchy. Super Admin itself is unrestricted.
        if ($user->hasRole('Super Admin') && !auth()->user()->hasRole('Super Admin')) {
            abort(403, 'You are not authorized to reset this account.');
        }

        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $passwordReset->reset($user, $validated['password']);

        return back()->with('success', 'Password updated. The user can log in with the new password immediately.');
    }

    public function destroy(User $user)
    {
        // Only admin and super admin can access this
        $this->authorizeRole('Super Admin', 'Admin');
        
        $currentUser = auth()->user();
        $societyId = $this->getSocietyId();

        // Was `if ($currentUser->hasRole('Admin') && ...)` - see edit() above.
        if (!$currentUser->hasRole('Super Admin') && $user->society_id !== $societyId) {
            abort(404, 'User not found.');
        }

        // Prevent deleting own account
        if ($user->id === auth()->id()) {
            if (request()->expectsJson()) {
                return response()->json(['error' => 'You cannot delete your own account.'], 422);
            }

            return back()->with('error', 'You cannot delete your own account.');
        }
        
        // Delete the row first, then the file - if the file delete were
        // first and the DB delete somehow failed, the user record would be
        // left pointing at a now-missing avatar.
        $avatar = $user->avatar;

        $user->delete();

        // Not deleteUploadedFile(): an owner's login shares its picture with
        // the owner record, which must keep showing it.
        \App\Support\ProfileImage::deleteIfUnreferenced($avatar);
        
        if (request()->expectsJson()) {
            return response()->json(['success' => 'User deleted successfully!'], 200);
        }
        
        return redirect()->route('users.index')
            ->with('success', 'User deleted successfully!');
    }
}