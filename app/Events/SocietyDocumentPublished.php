<?php

namespace App\Events;

use App\Models\SocietyDocument;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

/**
 * An admin published a society document AND chose to announce it.
 * `$channels` is what the admin ticked on the publish form - each
 * listener sends only if its own channel is in it, on top of the
 * Communication Center's per-society on/off rule.
 */
class SocietyDocumentPublished
{
    use Dispatchable, SerializesModels;

    /** @param string[] $channels */
    public function __construct(public SocietyDocument $document, public array $channels)
    {
    }

    public function wants(string $channel): bool
    {
        return in_array($channel, $this->channels, true);
    }

    /**
     * Active residents of the document's society, plus its guards when the
     * document is guard-visible - exactly the people who can open it.
     */
    public function recipients(): Collection
    {
        $roles = SocietyDocument::RESIDENT_ROLES;
        if ($this->document->visible_to_guards) {
            $roles[] = 'Guard';
        }

        return User::where('society_id', $this->document->society_id)
            ->where('status', 'active')
            ->whereHas('roles', fn ($q) => $q->whereIn('name', $roles))
            ->get();
    }
}
