<?php

namespace App\Support;

use App\Models\User;
use App\Services\SmsService;
use Illuminate\Support\Collection;

/**
 * Who an SMS audience really is, decided on the server from the database - never from user ids or role labels sent by a browser.
 *
 * Eligible = an active account of THIS society (never another society's, never a Super Admin, never a deleted one) with a valid
 * 10-digit mobile number, counted once however many roles they hold and however many accounts share a number. There is no
 * per-person SMS opt-out in the product today, so eligibility is exactly that; the society's SMS settings are the switch.
 *
 *   resident       Owners and Tenants                         (the pool an event-driven message then narrows to the right unit)
 *   guard          Guards
 *   admin          the society's Admins
 *   committee      active members that carry a committee designation (Society Info -> Committee); nothing is inferred
 *   staff          active Staff
 *   all_residents  every eligible Owner and Tenant
 *   all_team       Admins + Guards + Staff + committee members
 *   everyone       all of the above (Accountants have no SMS audience: they are not one of the supported roles)
 */
class SmsAudience
{
    public const RESIDENT_ROLES = ['Owner', 'Villa Owner', 'Apartment Owner', 'Tenant'];

    /** key => [label, explanation] */
    public const OPTIONS = [
        'resident'      => ['Resident', 'Owners and Tenants - for a message about one resident\'s own unit.'],
        'guard'         => ['Guard', 'Security guards.'],
        'admin'         => ['Admin', 'The society\'s administrators.'],
        'committee'     => ['Committee', 'Members with a committee designation in Society Info.'],
        'staff'         => ['Staff', 'Active society staff.'],
        'all_residents' => ['All Residents', 'Every eligible Owner and Tenant in this society.'],
        'all_team'      => ['All Team Members', 'Admins, Guards, Staff and Committee members.'],
        'everyone'      => ['Everyone in Society', 'All residents and all team members above - the widest audience.'],
    ];

    /** Audiences that reach many people at once: they need a count and an explicit confirmation before any send. */
    public const BULK = ['all_residents', 'all_team', 'everyone'];

    public static function labels(): array
    {
        return array_map(fn ($o) => $o[0], self::OPTIONS);
    }

    public static function isBulk(string $audience): bool
    {
        return in_array($audience, self::BULK, true);
    }

    /**
     * The eligible people, one entry per person (and per phone number).
     *
     * @return Collection<int, User>
     */
    public static function resolve(string $audience, int $societyId): Collection
    {
        if (!isset(self::OPTIONS[$audience])) {
            return collect();
        }

        $groups = match ($audience) {
            'resident', 'all_residents' => ['residents'],
            'guard'                     => ['guards'],
            'admin'                     => ['admins'],
            'committee'                 => ['committee'],
            'staff'                     => ['staff'],
            'all_team'                  => ['admins', 'guards', 'staff', 'committee'],
            'everyone'                  => ['residents', 'admins', 'guards', 'staff', 'committee'],
        };

        $people = collect();
        foreach ($groups as $group) {
            $people = $people->merge(self::group($group, $societyId));
        }

        return $people->unique('id')
            ->filter(fn (User $u) => self::phoneOf($u) !== null)
            ->unique(fn (User $u) => self::phoneOf($u))     // two accounts, one phone: one SMS
            ->values();
    }

    /**
     * What the manager is shown before a bulk send: how many will receive it, and why others were left out.
     *
     * @return array{audience:string,label:string,count:int,excluded_no_phone:int,excluded_duplicates:int,can_send:bool,reason:?string,sample:list<string>}
     */
    public static function preview(string $audience, int $societyId): array
    {
        $label = self::OPTIONS[$audience][0] ?? $audience;
        if (!isset(self::OPTIONS[$audience])) {
            return ['audience' => $audience, 'label' => $label, 'count' => 0, 'excluded_no_phone' => 0, 'excluded_duplicates' => 0, 'can_send' => false, 'reason' => 'Unknown audience.', 'sample' => []];
        }

        $groups = match ($audience) {
            'resident', 'all_residents' => ['residents'], 'guard' => ['guards'], 'admin' => ['admins'], 'committee' => ['committee'], 'staff' => ['staff'],
            'all_team' => ['admins', 'guards', 'staff', 'committee'], 'everyone' => ['residents', 'admins', 'guards', 'staff', 'committee'],
        };
        $pool = collect();
        foreach ($groups as $g) {
            $pool = $pool->merge(self::group($g, $societyId));
        }
        $pool = $pool->unique('id');
        $withPhone = $pool->filter(fn (User $u) => self::phoneOf($u) !== null);
        $resolved = self::resolve($audience, $societyId);
        $count = $resolved->count();

        return [
            'audience' => $audience, 'label' => $label, 'count' => $count,
            'excluded_no_phone' => $pool->count() - $withPhone->count(),
            'excluded_duplicates' => $withPhone->count() - $count,
            'can_send' => $count > 0,
            'reason' => $count > 0 ? null : 'Nobody in this society is eligible for "' . $label . '": no active account has a valid mobile number.',
            // masked: enough to recognise a person, never a full number
            'sample' => $resolved->take(3)->map(fn (User $u) => $u->name . ' (' . SmsService::maskPhone((string) self::phoneOf($u)) . ')')->all(),
        ];
    }

    /** @return Collection<int, User> */
    private static function group(string $group, int $societyId): Collection
    {
        $q = User::query()->where('society_id', $societyId)->where('status', 'active');

        return match ($group) {
            'residents' => $q->whereHas('roles', fn ($r) => $r->whereIn('name', self::RESIDENT_ROLES))->get(),
            'admins'    => $q->whereHas('roles', fn ($r) => $r->where('name', 'Admin'))->get(),
            'guards'    => $q->whereHas('roles', fn ($r) => $r->where('name', 'Guard'))->get(),
            'staff'     => $q->whereHas('roles', fn ($r) => $r->where('name', 'Staff'))->get(),
            'committee' => $q->whereNotNull('committee_designation')->get()->filter(fn (User $u) => trim((string) $u->committee_designation) !== ''),
        };
    }

    /** A Super Admin is never a society recipient, whatever else the account holds. */
    private static function phoneOf(User $u): ?string
    {
        if ($u->hasRole('Super Admin')) {
            return null;
        }
        $digits = SmsService::normalizePhone((string) $u->phone);

        return preg_match('/^[0-9]{10}$/', $digits) ? $digits : null;
    }
}
