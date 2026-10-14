<?php

namespace App\Support;

use App\Models\SystemSetting;

/**
 * The result of the last real provider check per channel (email / sms /
 * whatsapp), per society - what the Settings pages show as "Connected",
 * "Last test failed" or "Saved, not tested yet". Written only by an actual
 * provider round trip (a test send, or a provider API call that accepted
 * the credentials); cleared whenever the credentials change, so a status
 * never outlives the configuration it was measured against.
 *
 * Stored in system_settings (group `channel_status`). The technical detail
 * kept here is already sanitised by the caller - never a key or password.
 */
class ChannelStatus
{
    private const GROUP = 'channel_status';

    public static function record(string $channel, ?int $societyId, bool $ok, string $summary, ?string $detail = null): void
    {
        $now = now()->toIso8601String();
        $previous = self::get($channel, $societyId);

        SystemSetting::set(self::GROUP, $channel, [
            'ok'      => $ok,
            'summary' => $summary,
            'detail'  => $detail,
            'at'      => $now,
            // the last SUCCESS survives a later failure (until the settings change), so a page can show both
            'last_ok_at'      => $ok ? $now : ($previous['last_ok_at'] ?? null),
            'last_ok_summary' => $ok ? $summary : ($previous['last_ok_summary'] ?? null),
        ], 'json', $societyId);
    }

    public static function clear(string $channel, ?int $societyId): void
    {
        SystemSetting::query()
            ->where('group', self::GROUP)
            ->where('key', $channel)
            ->where(fn ($q) => $societyId === null ? $q->whereNull('society_id') : $q->where('society_id', $societyId))
            ->delete();
    }

    /** @return array{ok: bool, summary: string, detail: ?string, at: string}|null */
    public static function get(string $channel, ?int $societyId): ?array
    {
        $row = SystemSetting::query()
            ->where('group', self::GROUP)
            ->where('key', $channel)
            ->where(fn ($q) => $societyId === null ? $q->whereNull('society_id') : $q->where('society_id', $societyId))
            ->value('value');

        $decoded = $row ? json_decode($row, true) : null;

        return is_array($decoded) ? $decoded : null;
    }
}
