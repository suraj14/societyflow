<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Who hears about an event. The same rule SendEventCreatedEmail and
 * BusinessNotifications::eventCreated apply: owners of the event's society,
 * narrowed by the event's own visibility (roles or named users). Tenants,
 * guards, staff and accountants are not told, and nobody from another society.
 */
class EventAudience
{
    private const OWNER_ROLES = ['Owner', 'Villa Owner', 'Apartment Owner'];

    /** @return Collection<int,User> */
    public static function users(object $event): Collection
    {
        $societyId = $event->society_id;
        // only ACTIVE accounts of this society: a deactivated owner is not told about an event
        $base = fn () => User::where('society_id', $societyId)->where('status', 'active')
            ->whereHas('roles', fn ($q) => $q->whereIn('name', self::OWNER_ROLES));

        if (!empty($event->is_role_based) && !empty($event->visible_roles)) {
            $roles = array_values(array_intersect($event->visible_roles, self::OWNER_ROLES));

            return $roles === []
                ? collect()
                : User::where('society_id', $societyId)->where('status', 'active')->whereHas('roles', fn ($q) => $q->whereIn('name', $roles))->get();
        }

        if (!empty($event->visible_users)) {
            return $base()->whereIn('id', $event->visible_users)->get();
        }

        return $base()->get();
    }
}
