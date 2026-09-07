<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthApiController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if ($user->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Your account is inactive. Please contact your society admin.',
            ], 403);
        }

        $user->update(['last_login_at' => now()]);

        $token = $user->createToken('mobile-app')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'token'   => $token,
            'user'    => $this->formatUser($user),
        ]);
    }

    /**
     * Step 1 of registration: verify the resident exists in the system.
     * Returns their flat/society info so they can confirm before setting password.
     */
    public function checkAccount(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'phone' => 'required|string|max:20',
        ]);

        $user = User::where('email', $request->email)
            ->where('phone', $request->phone)
            ->first();

        if (!$user) {
            // Try phone only (some admins may enter different email format)
            $user = User::where('phone', $request->phone)->first();
        }

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'No account found with this email and phone number. Please contact your society admin to get registered.',
            ], 404);
        }

        $resident = $user->resident()->with('flat.building')->first();
        $flat     = $resident?->flat;
        $building = $flat?->building;
        $society  = $user->society;

        return response()->json([
            'success' => true,
            'message' => 'Account found! Please set your password to continue.',
            'data'    => [
                'user_id'       => $user->id,
                'name'          => $user->name,
                'email'         => $user->email,
                'flat_number'   => $flat?->flat_number,
                'building_name' => $building?->name,
                'society_name'  => $society?->name,
                'role'          => $user->getRoleNames()->first() ?? 'Resident',
                'has_password'  => !empty($user->password),
            ],
        ]);
    }

    /**
     * Step 2 of registration: set password and activate account.
     */
    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => 'required|email',
            'phone'    => 'required|string|max:20',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $user = User::where('email', $request->email)
            ->where('phone', $request->phone)
            ->first();

        if (!$user) {
            $user = User::where('phone', $request->phone)->first();
        }

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'No account found. Please contact your society admin.',
            ], 404);
        }

        $user->update([
            'password' => Hash::make($request->password),
            'status'   => 'active',
        ]);

        $token = $user->createToken('mobile-app')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Account activated successfully! Welcome to SocietyFlow.',
            'token'   => $token,
            'user'    => $this->formatUser($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['success' => true, 'message' => 'Logged out successfully']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->formatUser($request->user()),
        ]);
    }

    private function formatUser(User $user): array
    {
        $resident  = $user->resident()->with('flat.building')->first();
        $flat      = $resident?->flat;
        $building  = $flat?->building;
        $society   = $user->society;
        $roles     = $user->getRoleNames();

        // Get villa/apartment details from Owner record (same logic as web)
        $villaNumber = null;
        $villaArea   = null;
        if ($user->owner_id) {
            $owner = \App\Models\Owner::with('villaArea')->find($user->owner_id);
            if ($owner && $owner->property_type === 'villa' && $owner->villa_no) {
                $villaNumber = $owner->villa_no;
                $villaArea   = $owner->villaArea?->name;
                if (!$flat && $owner->villa_area_id) {
                    $flat     = \App\Models\Flat::where('villa_area_id', $owner->villa_area_id)
                        ->where('flat_number', $owner->villa_no)
                        ->first();
                    $building = null;
                }
            } elseif ($owner && $owner->property_type === 'apartment' && $owner->flat_no) {
                if (!$flat && $owner->building_id) {
                    $flat     = \App\Models\Flat::where('building_id', $owner->building_id)
                        ->where('flat_number', $owner->flat_no)
                        ->first();
                    $building = $flat?->building ?? $building;
                }
            }
        }

        return [
            'id'            => $user->id,
            'name'          => $user->name,
            'email'         => $user->email,
            'phone'         => $user->phone,
            'avatar'        => $user->avatar_url,
            'role'          => $roles->first() ?? 'Resident',
            'status'        => $user->status,
            'flat_number'   => $flat?->flat_number ?? $villaNumber,
            'building_name' => $building?->name,
            'villa_number'  => $villaNumber,
            'villa_area'    => $villaArea,
            'society_name'  => $society?->name,
            'society_id'    => $user->society_id,
            'is_owner'      => $user->isOwner() || $user->isVillaOwner(),
            'is_tenant'     => $user->isTenant(),
        ];
    }
}
