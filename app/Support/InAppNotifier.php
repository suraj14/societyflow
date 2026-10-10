<?php

namespace App\Support;

use App\Models\User;
use App\Notifications\InAppNotification;
use Illuminate\Support\Facades\Log;

/**
 * Puts one entry in each given user's bell. Callers pass exactly the users
 * who may see the record (the same people its email goes to), so the bell
 * never widens an audience. Never throws into the business action.
 */
class InAppNotifier
{
    /** @param iterable<User|null> $users */
    public static function send(iterable $users, string $category, string $title, string $body, ?string $url = null, string $icon = 'fa-bell'): int
    {
        $sent = 0;
        $seen = [];

        foreach ($users as $user) {
            if (!$user instanceof User || isset($seen[$user->id])) {
                continue;
            }
            $seen[$user->id] = true;

            try {
                $user->notify(new InAppNotification($category, $title, $body, $url, $icon));
                $sent++;
            } catch (\Throwable $e) {
                Log::warning('In-app notification not stored', ['category' => $category, 'class' => get_class($e)]);
            }
        }

        return $sent;
    }
}
