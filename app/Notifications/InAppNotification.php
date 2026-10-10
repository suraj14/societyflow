<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * One entry in a user's header bell. Database channel only - external
 * channels (email/SMS/WhatsApp) are sent by their own listeners.
 */
class InAppNotification extends Notification
{
    public function __construct(
        public string $category,
        public string $title,
        public string $body,
        public ?string $url = null,
        public string $icon = 'fa-bell',
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'category' => $this->category,
            'title'    => $this->title,
            'body'     => $this->body,
            'url'      => $this->url,
            'icon'     => $this->icon,
        ];
    }
}
