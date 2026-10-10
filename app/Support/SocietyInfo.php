<?php

namespace App\Support;

use App\Http\Controllers\Api\ApiImageHelper;
use App\Models\Society;
use App\Models\User;

/**
 * "Society Info": the society's own details and its committee, built in one
 * place so the web page and the mobile API can never disagree.
 *
 * Informational only. Every value is read from existing records - the society
 * row and ordinary user profiles - and a value that is not recorded is
 * returned as null (screens say "Not available"). Nothing is defaulted,
 * guessed or invented, and placeholder text a registration form leaves behind
 * ("To be updated", pincode 000000) counts as not recorded.
 */
class SocietyInfo
{
    /** Display order for the well-known office bearers; anyone else follows alphabetically. */
    private const RANK = [
        'president' => 1, 'chairperson' => 1, 'chairman' => 1, 'chairwoman' => 1,
        'vice president' => 2, 'vice chairperson' => 2, 'vice chairman' => 2, 'vice-president' => 2,
        'secretary' => 3,
        'joint secretary' => 4,
        'treasurer' => 5,
        'joint treasurer' => 6,
    ];

    /** Suggestions offered by the admin form; any designation up to 100 characters is accepted. */
    public const SUGGESTED_DESIGNATIONS = [
        'President', 'Chairperson', 'Vice President', 'Secretary', 'Joint Secretary',
        'Treasurer', 'Joint Treasurer', 'Committee Member',
    ];

    /** A value the office actually entered, or null. */
    public static function real(?string $value): ?string
    {
        $v = trim((string) $value);

        if ($v === '' || strcasecmp($v, 'to be updated') === 0 || preg_match('/^0+$/', $v)) {
            return null;
        }

        return $v;
    }

    /**
     * @return array{name:string, registration_number:?string, address:?string, society_type:?string, registered_on:?string, registered_on_label:?string}
     */
    public static function details(Society $society): array
    {
        $address = implode(', ', array_filter(array_map(
            fn ($v) => self::real($v),
            [$society->address, $society->city, $society->state, $society->pincode]
        )));

        return [
            'name'                => $society->name,
            'registration_number' => self::real($society->registration_number),
            'address'             => $address !== '' ? $address : null,
            // Existing field (societies.property_type); rows without one read as "Both".
            'society_type'        => $society->propertyType()->label(),
            'registered_on'       => $society->registered_on?->toDateString(),
            'registered_on_label' => $society->registered_on?->format('d M Y'),
        ];
    }

    /**
     * Active members of this society who have a committee designation, in the
     * conventional order (President, Vice President, Secretary, ...).
     *
     * `photo` is the root-relative storage path the app prepends its base URL
     * to (same contract as every other image in the API); `photo_web` is the
     * browser URL. `phone` is only ever a number that can really be dialled.
     *
     * @return array<int, array{id:int, name:string, designation:string, phone:?string, can_call:bool, photo:?string, photo_web:?string}>
     */
    public static function committee(Society $society): array
    {
        return User::query()
            ->where('society_id', $society->id)
            ->where('status', 'active')
            ->whereNotNull('committee_designation')
            ->where('committee_designation', '!=', '')
            ->get(['id', 'society_id', 'name', 'phone', 'avatar', 'committee_designation'])
            // The SQL above drops NULL and ''; a whitespace-only value (from
            // anything that did not go through the trimming admin form) is
            // not a designation either.
            ->filter(fn (User $u) => trim((string) $u->committee_designation) !== '')
            ->map(function (User $u) {
                $phone = self::dialable($u->phone);

                return [
                    'id'          => $u->id,
                    'name'        => $u->name,
                    'designation' => trim($u->committee_designation),
                    'phone'       => $phone,
                    'can_call'    => $phone !== null,
                    'photo'       => ApiImageHelper::storageUrl($u->avatar),
                    'photo_web'   => ProfileImage::webUrl($u->avatar),
                ];
            })
            ->sortBy(fn (array $m) => sprintf('%02d|%s', self::rank($m['designation']), mb_strtolower($m['name'])))
            ->values()
            ->all();
    }

    public static function rank(string $designation): int
    {
        return self::RANK[mb_strtolower(trim($designation))] ?? 9;
    }

    /**
     * The number normalised for a tel: link (digits, optional leading +), or
     * null when it is not a plausible phone number (blank, placeholder, too
     * short/long) - in which case no Call button is shown.
     */
    public static function dialable(?string $phone): ?string
    {
        $raw = trim((string) $phone);
        $digits = preg_replace('/\D+/', '', $raw);

        if ($digits === '' || strlen($digits) < 7 || strlen($digits) > 15 || preg_match('/^0+$/', $digits)) {
            return null;
        }

        return (str_starts_with($raw, '+') ? '+' : '') . $digits;
    }
}
