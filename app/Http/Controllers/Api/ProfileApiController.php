<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class ProfileApiController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user     = $request->user();
        $resident = $user->resident()->with('flat.building')->first();
        $flat     = $resident?->flat;
        $building = $flat?->building;
        $society  = $user->society;

        // Villa owner details — use Owner's own villa_no + villa_area_id (same as web)
        $villaNumber = null;
        $villaArea   = null;
        if ($user->owner_id) {
            $owner = \App\Models\Owner::with('villaArea')->find($user->owner_id);
            if ($owner && $owner->property_type === 'villa' && $owner->villa_no) {
                $villaNumber = $owner->villa_no;
                $villaArea   = $owner->villaArea?->name;
                // Also get the Flat record for additional details
                if (!$flat && $owner->villa_area_id) {
                    $flat = \App\Models\Flat::where('villa_area_id', $owner->villa_area_id)
                        ->where('flat_number', $owner->villa_no)
                        ->first();
                }
            } elseif ($owner && $owner->property_type === 'apartment' && $owner->flat_no) {
                // Apartment owner — use owner's flat_no + building_id
                if (!$flat && $owner->building_id) {
                    $flat = \App\Models\Flat::where('building_id', $owner->building_id)
                        ->where('flat_number', $owner->flat_no)
                        ->first();
                    $building = $flat?->building ?? $building;
                }
            }
        }

        // Tenant created from Admin -> Tenants: tenants row only (see
        // AuthApiController::formatUser for the same fallback).
        if (!$flat) {
            $tenantFlatId = \App\Support\UserFlatResolver::tenantFlatIds($user)[0] ?? null;
            if ($tenantFlatId) {
                $flat = \App\Models\Flat::with(['building', 'villaArea'])->find($tenantFlatId);
                if ($flat?->property_type === 'villa') {
                    $villaNumber = $flat->flat_number;
                    $villaArea   = $flat->villaArea?->name;
                    $building    = null;
                } else {
                    $building = $flat?->building;
                }
            }
        }

        // Get family members from Owner record
        $familyMembers = [];
        if ($user->owner_id) {
            $owner = \App\Models\Owner::find($user->owner_id);
            if ($owner && !empty($owner->family_members)) {
                $familyMembers = collect($owner->family_members)
                    ->map(fn($m) => [
                        'name'         => $m['name'] ?? '',
                        'relationship' => $m['relationship'] ?? '',
                        'phone'        => $m['phone'] ?? null,
                    ])
                    ->filter(fn($m) => !empty($m['name']))
                    ->values()
                    ->toArray();
            }
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'id'             => $user->id,
                'name'           => $user->name,
                'email'          => $user->email,
                'phone'          => $user->phone,
                'avatar'         => $user->avatar_url,
                'role'           => $user->getRoleNames()->first() ?? 'Resident',
                'status'         => $user->status,
                'flat_number'    => $flat?->flat_number ?? $villaNumber,
                'building_name'  => $building?->name,
                'villa_number'   => $villaNumber,
                'villa_area'     => $villaArea,
                'society_name'   => $society?->name,
                'society_id'     => $user->society_id,
                'is_owner'       => $user->isOwner() || $user->isVillaOwner(),
                'is_tenant'      => $user->isTenant(),
                'family_members' => $familyMembers,

                // Same society capability object the login response carries,
                // so a session restored from /profile branches identically.
                'society'        => $society ? array_merge(
                    [
                        'id'        => $society->id,
                        'public_id' => $society->public_id,
                        'name'      => $society->name,
                    ],
                    $society->propertyConfig()
                ) : null,
                'property_type'  => $society?->propertyType()->value,
            ],
        ]);
    }

    public function familyMembers(Request $request): JsonResponse
    {
        $user = $request->user();
        $members = [];

        if ($user->owner_id) {
            $owner = \App\Models\Owner::find($user->owner_id);
            if ($owner && !empty($owner->family_members)) {
                $members = collect($owner->family_members)
                    ->map(fn($m) => [
                        'name'         => $m['name'] ?? '',
                        'relationship' => $m['relationship'] ?? '',
                        'phone'        => $m['phone'] ?? null,
                    ])
                    ->filter(fn($m) => !empty($m['name']))
                    ->values()
                    ->toArray();
            }
        }

        return response()->json(['success' => true, 'data' => $members]);
    }

    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'name'  => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
        ]);

        $user = $request->user();
        $user->update([
            'name'  => $request->name,
            'phone' => $request->phone ?? $user->phone,
        ]);

        return response()->json(['success' => true, 'message' => 'Profile updated successfully']);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => 'required|string',
            'password'         => ['required', 'confirmed', Password::min(6)],
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Current password is incorrect.',
            ], 422);
        }

        $user->update(['password' => Hash::make($request->password)]);

        // Changing a password must end every other session. Without this a
        // stolen token stayed valid indefinitely after the victim changed
        // their password - the one action a user takes to recover from a
        // compromise did nothing to the attacker's access. The OTP reset
        // flow already did this; the in-app change did not.
        //
        // The current token is preserved so the user is not logged out of
        // the device they just changed it on.
        $currentTokenId = $user->currentAccessToken()?->id;

        $user->tokens()
            ->when($currentTokenId, fn ($q) => $q->where('id', '!=', $currentTokenId))
            ->delete();

        return response()->json(['success' => true, 'message' => 'Password changed successfully']);
    }
}
