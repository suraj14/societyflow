<?php

namespace App\Console\Commands;

use App\Support\DataIntegrityRepair;
use Illuminate\Console\Command;

/**
 * Reports (and with --fix, repairs) the data-integrity invariants the
 * application relies on:
 *
 *  - an active tenancy has no move-out date
 *  - flats.status matches the canonical occupancy rule
 *  - 'RENT-…' bills are typed rent
 *  - every bill's paid_amount is backed by success payments
 *
 * Report by default; --fix writes, following the same convention as
 * users:rotate-weak-passwords and tenants:repair-flat-assignments.
 */
class DataIntegrityAudit extends Command
{
    protected $signature = 'data:integrity-audit {--fix : Apply the repairs instead of only reporting}';

    protected $description = 'Check tenancy, occupancy and bill-ledger invariants; --fix repairs them';

    public function handle(): int
    {
        $fix = (bool) $this->option('fix');

        $unbacked = DataIntegrityRepair::unbackedBills();
        if ($unbacked->isNotEmpty()) {
            $this->table(
                ['Bill', 'Number', 'Society', 'Flat', 'Type', 'Paid (bill)', 'Ledger (success)', 'Gap'],
                $unbacked->map(function ($b) {
                    $ledger = (float) $b->payments()->where('status', 'success')->sum('amount');

                    return [$b->id, $b->bill_number, $b->society_id, $b->flat_id, $b->bill_type, number_format($b->paid_amount, 2), number_format($ledger, 2), number_format($b->paid_amount - $ledger, 2)];
                })
            );
        }

        $result = DataIntegrityRepair::run($fix);

        $this->line(($fix ? 'Repaired' : 'Found') . ':');
        $this->line("  active tenancies with a stale move-out date : {$result['tenants']}");
        $this->line("  flats whose stored status was out of sync   : {$result['flats']}");
        $this->line("  RENT- bills not typed rent                  : {$result['bills_relabelled']}");
        $this->line("  bills paid without a ledger row             : {$result['bills_reconciled']} (₹" . number_format($result['amount_reconciled'], 2) . ')');

        $total = $result['tenants'] + $result['flats'] + $result['bills_relabelled'] + $result['bills_reconciled'];

        if ($total === 0) {
            $this->info('All invariants hold.');
        } elseif (!$fix) {
            $this->warn('Run with --fix to repair. Each change is written to activity_logs.');
        } else {
            $this->info('Done. Each change was written to activity_logs.');
        }

        return $total === 0 || $fix ? self::SUCCESS : self::FAILURE;
    }
}
