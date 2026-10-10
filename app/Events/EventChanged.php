<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * An announced event was cancelled, rescheduled or otherwise changed.
 * $kind is one of: cancelled | rescheduled | updated.
 */
class EventChanged
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public const KINDS = ['cancelled', 'rescheduled', 'updated'];

    public function __construct(public object $event, public string $kind)
    {
        if (!in_array($kind, self::KINDS, true)) {
            throw new \InvalidArgumentException("Unknown event change kind: {$kind}");
        }
    }

    /** The phrase the notification completes "<event name> ..." with. */
    public function summary(): string
    {
        $when = $this->event->start_date?->format('d M Y, h:i A');

        return match ($this->kind) {
            'cancelled'   => 'has been cancelled',
            'rescheduled' => 'has been rescheduled' . ($when ? " to {$when}" : ''),
            default       => 'has been updated',
        };
    }
}
