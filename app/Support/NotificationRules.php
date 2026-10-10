<?php

namespace App\Support;

use App\Models\SystemSetting;

/**
 * Whether a given registered event (NotificationEvents::all()) sends on a
 * given channel, per society.
 *
 * The per-event "Rules & Notifications" screen (Communication Center) was
 * removed on 2026-10-08: managers no longer configure individual events.
 * Every event/channel is on by default; rows saved earlier are still
 * honoured (one exists in the dev data - society 8 turned off SMS for
 * access_pass_revoked) and can be cleared from the database if needed.
 *
 * Deliberately **opt-out, not opt-in** - every registered event/channel pair
 * is enabled unless a row explicitly disables it. CLAUDE.md §10/this slice's
 * own instruction #7 ("preserve existing working Email/SMS behaviour") rules
 * out an opt-in design: the moment this table existed, an opt-in default
 * would have gone right back to the "missing config = email silently never
 * sent" bug NotificationConfigIntegrityTest exists to catch, just moved from
 * a missing EmailTemplate row to a missing rule row.
 *
 * Stored in the existing `system_settings` table (group `notification_rules`,
 * key `"{$eventKey}:{$channel}"`) rather than a new table - a boolean toggle
 * is exactly what that table already models, and it is already
 * society-scoped and already has an admin-facing settings idiom. No secret
 * ever lives here, so none of SystemSetting's encryption path is needed.
 */
class NotificationRules
{
    private const GROUP = 'notification_rules';

    private static function key(string $eventKey, string $channel): string
    {
        return "{$eventKey}:{$channel}";
    }

    /**
     * The identifier a rule toggle is actually stored/read under for one
     * registry entry. For a trigger_event event this is the trigger_event
     * value itself (`$event['templates'][0]`) - exactly what
     * `DispatchEventNotification::handle()` checks - not the registry's
     * dot-notation array key, so a toggle saved here and the job's own
     * check are reading the same row. Slug-based events return null: they
     * go through `EmailNotificationService::shouldReceiveEmail()`'s
     * role-permission map instead, a different, pre-existing mechanism
     * this rule store does not cover, so they are not toggleable here.
     * The single source of this mapping - `CommunicationCenterController`
     * used to duplicate it, which is exactly how `allFor()` ended up
     * keying its map differently from what `isEnabled()`/`setEnabled()`
     * actually read and write (found and fixed together).
     */
    public static function ruleKeyFor(array $event): ?string
    {
        return $event['lookup'] === NotificationEvents::LOOKUP_TRIGGER_EVENT
            ? $event['templates'][0]
            : null;
    }

    /**
     * True unless an admin has explicitly turned this event/channel off for
     * this society. Looked up by a direct query, not `SystemSetting::get()`
     * - that helper treats a null `$societyId` as "use the current web
     * request's logged-in user", which would make a Super Admin's
     * society-less console/queue context silently resolve to whichever
     * admin happened to be logged in when a `sync`-queued job ran inline.
     * A rule lookup needs the exact society the event belongs to, nothing
     * inferred from who is currently authenticated.
     */
    public static function isEnabled(string $eventKey, string $channel, ?int $societyId): bool
    {
        $setting = SystemSetting::where('group', self::GROUP)
            ->where('key', self::key($eventKey, $channel))
            ->where('society_id', $societyId)
            ->first();

        if (!$setting) {
            return true;
        }

        return filter_var($setting->value, FILTER_VALIDATE_BOOLEAN);
    }

    public static function setEnabled(string $eventKey, string $channel, bool $enabled, ?int $societyId): void
    {
        SystemSetting::set(self::GROUP, self::key($eventKey, $channel), $enabled, 'boolean', $societyId);
    }

    /**
     * Every (event, channel) pair's current state for one society, keyed
     * `"{$eventKey}:{$channel}"` - what the Rules & Notifications tab
     * renders checkboxes from. One query for the whole page, not one per
     * checkbox - `CommunicationCenterController::index()` used to call
     * `isEnabled()` in a nested loop (up to ~20 queries for 15 events ×
     * their channels), found and fixed as a performance pass over this
     * same slice's own new code, not a pre-existing problem.
     */
    public static function allFor(?int $societyId): array
    {
        $disabledKeys = SystemSetting::where('group', self::GROUP)
            ->where('society_id', $societyId)
            ->pluck('value', 'key')
            ->map(fn ($value) => filter_var($value, FILTER_VALIDATE_BOOLEAN))
            ->all();

        $states = [];
        foreach (NotificationEvents::all() as $event) {
            $ruleKey = self::ruleKeyFor($event);
            if ($ruleKey === null) {
                continue;
            }

            foreach ($event['channels'] as $channel) {
                $key = self::key($ruleKey, $channel);
                $states[$key] = $disabledKeys[$key] ?? true;
            }
        }

        return $states;
    }
}
