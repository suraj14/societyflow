<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ProfileImageException;
use App\Http\Controllers\Controller;
use App\Support\MobileUserPayload;
use App\Support\ProfileImage;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ProfileApiController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

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

        // Same user object login / OTP login / me return (one builder, so a
        // session restored from /profile branches identically), plus the
        // owner's family members.
        return response()->json([
            'success' => true,
            'data'    => array_merge(MobileUserPayload::make($user), [
                'family_members' => $familyMembers,
            ]),
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

    /**
     * Update the signed-in user's own profile. Reached as PUT (JSON body,
     * unchanged) and as POST (needed for multipart - PHP only parses a
     * multipart body on POST, so a picture cannot arrive on PUT).
     *
     * `avatar` (file) replaces the picture, `remove_avatar` clears it, and
     * sending neither keeps the current one - so the existing name/phone-only
     * callers behave exactly as before. The account changed is always the
     * token's own user; no user id is accepted from the client.
     */
    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'name'  => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'avatar' => ProfileImage::rules(),
            'remove_avatar' => 'nullable|boolean',
        ]);

        $user = $request->user();

        try {
            DB::transaction(function () use ($request, $user) {
                $user->update([
                    'name'  => $request->name,
                    'phone' => $request->phone ?? $user->phone,
                ]);

                ProfileImage::change(
                    $user,
                    $request->file('avatar'),
                    $request->boolean('remove_avatar') && !$request->hasFile('avatar'),
                );
            });
        } catch (ProfileImageException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        // `data` is additive: the same user object /auth/me returns, so the
        // app can show the new picture without a second round trip.
        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'data'    => MobileUserPayload::make($user->fresh()),
        ]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => 'required|string',
            'password'         => \App\Support\PasswordPolicy::required(\App\Support\PasswordPolicy::forUser($request->user())),
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
