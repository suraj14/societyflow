<?php

namespace App\Support;

use App\Models\Flat;
use App\Models\Owner;
use App\Models\User;

/**
 * The `user` object the mobile app stores as its session (login, register,
 * OTP login, /auth/me, /profile). One builder, because three hand-copied
 * versions drifted: OTP login returned a thin payload with no villa, owner
 * or society details, so a villa owner who signed in by OTP saw no unit,
 * lost the owner-only screens and got the default society configuration.
 *
 * JSON keys are the app's API contract (societyflowmob UserModel.fromJson);
 * add keys freely, never rename or remove one without a coordinated release.
 */
final class MobileUserPayload
{
    public static function make(User $user): array
    {
        $resident = $user->resident()->with('flat.building')->first();
        $flat     = $resident?->flat;
        $building = $flat?->building;
        $society  = $user->society;
        $roles    = $user->getRoleNames();

        // Villa/apartment details from the Owner record (same logic as web).
        $villaNumber = null;
        $villaArea   = null;
        if ($user->owner_id) {
            $owner = Owner::with('villaArea')->find($user->owner_id);
            if ($owner && $owner->property_type === 'villa' && $owner->villa_no) {
                $villaNumber = $owner->villa_no;
                $villaArea   = $owner->villaArea?->name;
                if (!$flat && $owner->villa_area_id) {
                    $flat     = Flat::where('villa_area_id', $owner->villa_area_id)
                        ->where('flat_number', $owner->villa_no)
                        ->first();
                    $building = null;
                }
            } elseif ($owner && $owner->property_type === 'apartment' && $owner->flat_no) {
                if (!$flat && $owner->building_id) {
                    $flat     = Flat::where('building_id', $owner->building_id)
                        ->where('flat_number', $owner->flat_no)
                        ->first();
                    $building = $flat?->building ?? $building;
                }
            }
        }

        // A tenant created from Admin -> Tenants has a tenants row, not a
        // residents row and no owner record - the two paths above never find
        // the unit, so the app showed no villa/flat at all. Same resolver the
        // web uses (UserFlatResolver): the active tenancy decides.
        if (!$flat) {
            $tenantFlatId = UserFlatResolver::tenantFlatIds($user)[0] ?? null;
            if ($tenantFlatId) {
                $flat = Flat::with(['building', 'villaArea'])->find($tenantFlatId);
                if ($flat?->property_type === 'villa') {
                    $villaNumber = $flat->flat_number;
                    $villaArea   = $flat->villaArea?->name;
                    $building    = null;
                } else {
                    $building = $flat?->building;
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

            // The society's own capability, so the app branches on
            // configuration instead of guessing from whether villa_number
            // happens to be populated. A villa township never renders
            // apartment UI and vice versa.
            //
            // Kept as a nested object AND mirrored into the sibling
            // `property_type` key so older builds that do not know about
            // `society` keep working.
            // `public_id` is the identifier to quote in URLs and support
            // tickets. The numeric `id` stays because shipped builds parse
            // it; removing it would break them without a coordinated release.
            'society'       => $society ? array_merge(
                [
                    'id'        => $society->id,
                    'public_id' => $society->public_id,
                    'name'      => $society->name,
                ],
                $society->propertyConfig()
            ) : null,
            'property_type' => $society?->propertyType()->value,
        ];
    }
}
