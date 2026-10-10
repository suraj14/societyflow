<?php

namespace App\Events;

use App\Models\AccessPass;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AccessPassRevoked
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public AccessPass $accessPass)
    {
    }
}
