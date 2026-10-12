<?php

namespace App\Http\Controllers;

use App\Exceptions\ProfileImageException;
use App\Support\ProfileImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProfileController extends Controller
{
    public function show()
    {
        $user = auth()->user();

        // Use super-admin layout for Super Admin users
        if ($user->hasRole('Super Admin')) {
            return view('profile.show-super-admin', compact('user'));
        }

        return view('profile.show', compact('user'));
    }

    public function edit()
    {
        $user = auth()->user();

        // Use super-admin layout for Super Admin users
        if ($user->hasRole('Super Admin')) {
            return view('profile.edit-super-admin', compact('user'));
        }

        return view('profile.edit', compact('user'));
    }

    public function update(Request $request)
    {
        $user = auth()->user();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . $user->id,
            'phone' => 'nullable|string|max:20',
            // Changing the password needs the current one (a hijacked session must not be able to lock the owner out),
            // and the new one must meet the society's password policy - the same rules the form's meter shows.
            'current_password' => ['required_with:password', 'nullable', 'current_password'],
            'password' => array_merge(['nullable'], \App\Support\PasswordPolicy::rules(\App\Support\PasswordPolicy::forUser($user))),
            // Every role edits its own picture here - the account being
            // changed is always the signed-in user's, never an id the
            // client sends, so there is no one else's profile to reach.
            'avatar' => ProfileImage::rules(),
            'remove_avatar' => 'nullable|boolean',
        ]);

        // Only update password if provided
        $passwordChanged = $request->filled('password');
        if ($passwordChanged) {
            $validated['password'] = bcrypt($validated['password']);
        } else {
            unset($validated['password']);
        }
        unset($validated['current_password']);

        // The picture fields are not columns to mass-assign.
        unset($validated['avatar'], $validated['remove_avatar']);

        try {
            DB::transaction(function () use ($request, $user, $validated) {
                $user->update($validated);

                // Nothing selected keeps the current picture.
                ProfileImage::change(
                    $user,
                    $request->file('avatar'),
                    $request->boolean('remove_avatar') && !$request->hasFile('avatar'),
                );
            });
        } catch (ProfileImageException $e) {
            return back()->withInput()->withErrors(['avatar' => $e->getMessage()]);
        }

        // A new password ends every API session issued under the old one (the mobile app signs in again).
        if ($passwordChanged && method_exists($user, 'tokens')) {
            $user->tokens()->delete();
        }

        return redirect()->route('profile.show')
            ->with('success', $passwordChanged ? 'Profile updated and password changed.' : 'Profile updated successfully.');
    }
}
