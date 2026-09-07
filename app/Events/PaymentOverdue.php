<?php

namespace App\Events;

use App\Models\MaintenanceBill;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentOverdue
{
    use Dispatchable, SerializesModels;

    public $bill;

    public function __construct(MaintenanceBill $bill)
    {
        $this->bill = $bill;
    }
}
