<?php

namespace App\Http\Controllers;

use App\Http\Controllers\BaseController;
use App\Models\User;
use App\Events\UserCreated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

class UserController extends BaseController
{
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
        
        // Get available roles for the dropdown
        $roles = Role::all();
        
        return view('users.index', compact('users', 'roles'));
    }
    
    public function create()
    {
        // Only admin and super admin can access this
        $this->authorizeRole('Super Admin', 'Admin');
        
        // Get available roles
        $roles = Role::all();
        
        return view('users.create', compact('roles'));
    }
    
    public function store(Request $request)
    {
        // Only admin and super admin can access this
        $this->authorizeRole('Super Admin', 'Admin');
        
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'phone' => 'required|string|max:20',
            'role' => 'required|exists:roles,name',
            'avatar' => 'nullable|file|mimes:jpeg,png,jpg,gif|max:2048',
        ]);
        
        $currentUser = auth()->user();
        $societyId = $this->getSocietyId();
        
        if ($currentUser->hasRole('Super Admin') && !$societyId) {
            return response()->json(['error' => 'Super Admin must specify a society to create users.'], 422);
        }
        
        $avatarPath = null;
        if ($request->hasFile('avatar')) {
            $file = $request->file('avatar');
            $uploadPath = public_path('storage/avatars');
            @mkdir($uploadPath, 0777, true);
            $filename = time() . '_' . $file->getClientOriginalName();
            try {
                $file->move($uploadPath, $filename);
            } catch (\Exception $e) {
                @chmod($uploadPath, 0777);
                $file->move($uploadPath, $filename);
            }
            $avatarPath = 'avatars/' . $filename;
        }
        
        $temporaryPassword = 'password123';
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($temporaryPassword),
            'society_id' => $societyId,
            'avatar' => $avatarPath,
            'status' => 'active',
        ]);
        
        $user->assignRole($request->role);
        UserCreated::dispatch($user, $temporaryPassword);
        
        if ($request->expectsJson()) {
            return response()->json(['success' => 'User created successfully!', 'user' => $user], 201);
        }
        
        return redirect()->route('users.index')
            ->with('success', 'User created successfully! Default password is: password123');
    }
    
    public function edit(User $user)
    {
        // Only admin and super admin can access this
        $this->authorizeRole('Super Admin', 'Admin');
        
        $currentUser = auth()->user();
        $societyId = $this->getSocietyId();
        
        // Verify user belongs to the same society (for Admin only)
        if ($currentUser->hasRole('Admin') && $user->society_id !== $societyId) {
            return back()->with('error', 'User not found.');
        }
        
        // Get available roles
        $roles = Role::all();
        
        return view('users.edit', compact('user', 'roles'));
    }
    
    public function update(Request $request, User $user)
    {
        // Only admin and super admin can access this
        $this->authorizeRole('Super Admin', 'Admin');
        
        $currentUser = auth()->user();
        $societyId = $this->getSocietyId();
        
        // Verify user belongs to the same society (for Admin only)
        if ($currentUser->hasRole('Admin') && $user->society_id !== $societyId) {
            return response()->json(['error' => 'User not found.'], 404);
        }
        
        // Check if user is trying to change their own role
        if ($user->id === auth()->id() && $request->role !== $user->roles->first()->name) {
            return response()->json(['error' => 'You cannot change your own role.'], 422);
        }
        
        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'role' => 'required|exists:roles,name',
            'status' => 'required|in:active,inactive',
            'avatar' => 'nullable|file|mimes:jpeg,png,jpg,gif|max:2048',
        ]);
        
        $updateData = [
            'name' => $request->name,
            'phone' => $request->phone,
            'status' => $request->status,
        ];
        
        if ($request->hasFile('avatar')) {
            // Don't try to delete - just skip it to avoid fileinfo error
            $file = $request->file('avatar');
            $uploadPath = public_path('storage/avatars');
            @mkdir($uploadPath, 0777, true);
            $filename = time() . '_' . $file->getClientOriginalName();
            try {
                $file->move($uploadPath, $filename);
            } catch (\Exception $e) {
                @chmod($uploadPath, 0777);
                $file->move($uploadPath, $filename);
            }
            $updateData['avatar'] = 'avatars/' . $filename;
        }
        
        $user->update($updateData);
        
        if ($user->id !== auth()->id()) {
            $user->syncRoles([$request->role]);
        }
        
        if ($request->expectsJson()) {
            return response()->json(['success' => 'User updated successfully!', 'user' => $user], 200);
        }
        
        return redirect()->route('users.index')
            ->with('success', 'User updated successfully!');
    }
    
    public function destroy(User $user)
    {
        // Only admin and super admin can access this
        $this->authorizeRole('Super Admin', 'Admin');
        
        $currentUser = auth()->user();
        $societyId = $this->getSocietyId();
        
        // Verify user belongs to the same society (for Admin only)
        if ($currentUser->hasRole('Admin') && $user->society_id !== $societyId) {
            return response()->json(['error' => 'User not found.'], 404);
        }
        
        // Prevent deleting own account
        if ($user->id === auth()->id()) {
            return response()->json(['error' => 'You cannot delete your own account.'], 422);
        }
        
        // Delete avatar if exists - skip deletion to avoid fileinfo error
        if ($user->avatar) {
            // Don't try to delete
        }
        
        $user->delete();
        
        if (request()->expectsJson()) {
            return response()->json(['success' => 'User deleted successfully!'], 200);
        }
        
        return redirect()->route('users.index')
            ->with('success', 'User deleted successfully!');
    }
}