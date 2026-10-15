<?php

namespace App\Console\Commands;

use App\Services\VisitorApprovalService;
use Illuminate\Console\Command;

/**
 * A visitor logged at the gate that nobody has answered after the society's no-response time (SystemSetting
 * security / visitor_no_response_minutes - Settings -> Security, default 10, 0 = off) is escalated ONCE: the administrators and the guard are told. The
 * visitor is never admitted by the passing of time - an administrator decides (override) or the guard turns them away.
 * Idempotent: a visitor already escalated is skipped, so overlapping runs cannot notify twice.
 */
class EscalateUnansweredVisitors extends Command
{
    protected $signature = 'visitors:escalate-unanswered';

    protected $description = 'Tell the administrators and the guard about visitors still waiting for an answer (never admits anyone)';

    public function handle(): int
    {
        $count = VisitorApprovalService::escalateUnanswered();
        $this->info($count . ' waiting visitor(s) escalated.');

        return self::SUCCESS;
    }
}
