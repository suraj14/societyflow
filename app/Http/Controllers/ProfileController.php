<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

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
            'password' => 'nullable|string|min:8|confirmed',
        ]);

        // Only update password if provided
        if ($request->filled('password')) {
            $validated['password'] = bcrypt($validated['password']);
        } else {
            unset($validated['password']);
        }

        $user->update($validated);

        return redirect()->route('profile.show')
            ->with('success', 'Profile updated successfully.');
    }
}
