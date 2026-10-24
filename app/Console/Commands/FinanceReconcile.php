<?php

namespace App\Console\Commands;

use App\Support\Money;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * READ-ONLY reconciliation of a society's money records. It has no write path at all - no --fix option, no model saves,
 * no events - so it is safe to run against live data. It answers, from the stored rows:
 *
 *   - what the Payments page used to call "Total Collected" versus cash that is actually evidenced,
 *   - which payments settle which bills (and which cross a month boundary),
 *   - every record that needs a person's decision, listed by id so a correction can be proposed and approved.
 *
 * Nothing it prints is a correction. It never changes a status, a date, an amount or a link.
 */
class FinanceReconcile extends Command
{
    protected $signature = 'finance:reconcile {--society= : Only this society id}';

    protected $description = 'Read-only reconciliation of payments, bills, expenses and facility bookings (changes nothing)';

    public function handle(): int
    {
        $this->warn('READ-ONLY. database=' . basename((string) config('database.connections.' . config('database.default') . '.database')) . ' driver=' . config('database.default') . ' env=' . app()->environment() . ' - this command writes nothing.');

        $societies = DB::table('societies')->when($this->option('society'), fn ($q, $id) => $q->where('id', $id))->orderBy('id')->get(['id', 'name']);
        $findings = 0;

        foreach ($societies as $s) {
            $payments = DB::table('payments')->where('society_id', $s->id)->get();
            $bills = DB::table('maintenance_bills')->where('society_id', $s->id)->get()->keyBy('id');
            $expenses = DB::table('expenses')->where('society_id', $s->id)->get();
            $bookings = DB::table('facility_bookings')->where('society_id', $s->id)->get();

            if ($payments->isEmpty() && $bills->isEmpty() && $expenses->isEmpty() && $bookings->isEmpty()) {
                continue;
            }
            $this->newLine();
            $this->info("== Society {$s->id}: {$s->name}");

            $isRent = fn ($p) => strtolower(trim((string) $p->bill_type)) === 'rent' || (($bills[$p->maintenance_bill_id]->bill_type ?? null) === 'rent');
            $success = $payments->where('status', 'success');

            // 1. the headline figures, every way of reading them
            $this->line('Payments (' . $payments->count() . ' rows):');
            foreach (['success', 'pending', 'failed', 'cancelled'] as $st) {
                $set = $payments->where('status', $st);
                $this->line(sprintf('  %-10s %2d rows  ₹%s', $st, $set->count(), number_format(Money::sum($set->pluck('amount')), 2)));
            }
            $rent = $success->filter($isRent);
            $unrecorded = $success->filter(fn ($p) => $p->payment_method === 'unrecorded');
            $verifiedSociety = $success->reject($isRent)->reject(fn ($p) => $p->payment_method === 'unrecorded');
            $this->line('  old "Total Collected" (every status, every type) .......... ₹' . number_format(Money::sum($payments->pluck('amount')), 2));
            $this->line('  success only .............................................. ₹' . number_format(Money::sum($success->pluck('amount')), 2));
            $this->line('    of which owner rent (pass-through, not society money) ... ₹' . number_format(Money::sum($rent->pluck('amount')), 2) . ' (' . $rent->count() . ')');
            $this->line('    of which method "unrecorded" (no evidence of receipt) ... ₹' . number_format(Money::sum($unrecorded->pluck('amount')), 2) . ' (' . $unrecorded->count() . ')');
            $this->line('  actual society collections (success, not rent, evidenced) . ₹' . number_format(Money::sum($verifiedSociety->pluck('amount')), 2) . ' (' . $verifiedSociety->count() . ')');

            // 2. billed / allocated / outstanding by bill month, and where each payment landed
            $this->newLine();
            $this->line('Maintenance bills by bill month (rent excluded):');
            $maint = $bills->where('bill_type', 'maintenance');
            foreach ($maint->groupBy(fn ($b) => substr((string) $b->bill_date, 0, 7))->sortKeys() as $ym => $set) {
                $this->line(sprintf('  %s  bills=%d billed=₹%s paid=₹%s outstanding=₹%s', $ym, $set->count(), number_format(Money::sum($set->pluck('total_amount')), 2), number_format(Money::sum($set->pluck('paid_amount')), 2), number_format(Money::sum($set->pluck('balance_amount')), 2)));
            }
            $this->line('Where each successful maintenance payment was allocated:');
            foreach ($success->reject($isRent) as $p) {
                $bill = $bills[$p->maintenance_bill_id] ?? null;
                $this->line(sprintf('  payment %-3s ₹%-8s paid %s -> %s', $p->id, number_format((float) $p->amount, 2), substr((string) $p->payment_date, 0, 10),
                    $bill ? "bill {$bill->id} ({$bill->bill_number}, dated " . substr((string) $bill->bill_date, 0, 10) . ')' . (substr((string) $bill->bill_date, 0, 7) !== substr((string) $p->payment_date, 0, 7) ? '  <- different month from the payment' : '') : 'NO BILL (unallocated)'));
            }

            // 3. records that need a person's decision
            $this->newLine();
            $this->line('Needs a decision (listed, never changed):');
            $findings += $this->finding('success payments with method "unrecorded" (no evidence of receipt; excluded from collections)', $unrecorded->map(fn ($p) => "payment {$p->id} ₹" . number_format((float) $p->amount, 2) . " bill " . ($p->maintenance_bill_id ?? '-') . " type {$p->bill_type} ref " . ($p->transaction_id ?: '-')));
            $findings += $this->finding('success payments with no receipt number and no transaction / gateway reference', $success->filter(fn ($p) => !$p->receipt_number && !$p->transaction_id && !$p->gateway_transaction_id)->map(fn ($p) => "payment {$p->id} ₹" . number_format((float) $p->amount, 2) . " method {$p->payment_method}"));
            $findings += $this->finding('success payments tied to no bill (unallocated)', $success->filter(fn ($p) => !$p->maintenance_bill_id)->map(fn ($p) => "payment {$p->id} ₹" . number_format((float) $p->amount, 2) . " type {$p->bill_type}"));
            $findings += $this->finding('bills whose paid_amount differs from their success payments', $bills->filter(fn ($b) => !Money::same($b->paid_amount, Money::sum($success->where('maintenance_bill_id', $b->id)->pluck('amount'))))->map(fn ($b) => "bill {$b->id} paid_amount ₹{$b->paid_amount} vs success payments ₹" . number_format(Money::sum($success->where('maintenance_bill_id', $b->id)->pluck('amount')), 2)));
            $findings += $this->finding('bills paid more than their total (overpaid)', $bills->filter(fn ($b) => Money::paise(Money::sum($success->where('maintenance_bill_id', $b->id)->pluck('amount'))) > Money::paise($b->total_amount))->map(fn ($b) => "bill {$b->id} total ₹{$b->total_amount}"));
            $dupes = $success->groupBy(fn ($p) => implode('|', [$p->flat_id, $p->maintenance_bill_id, Money::paise($p->amount), substr((string) $p->payment_date, 0, 10)]))->filter(fn ($g) => $g->count() > 1);
            $findings += $this->finding('possible duplicate payments (same unit, bill, amount and day)', $dupes->map(fn ($g) => 'payments ' . $g->pluck('id')->implode(', ') . ' ₹' . number_format((float) $g->first()->amount, 2)));
            $findings += $this->finding('payments whose transaction id looks like demo / reconciliation data', $payments->filter(fn ($p) => preg_match('/^(DEMO|RECON)-/i', (string) $p->transaction_id))->map(fn ($p) => "payment {$p->id} ref {$p->transaction_id} status {$p->status}"));

            $this->line('  expenses: ' . $expenses->count() . ' rows, statuses ' . $expenses->groupBy('status')->map->count()->map(fn ($c, $st) => "$st=$c")->implode(' ') . ', scopes ' . $expenses->groupBy('scope')->map->count()->map(fn ($c, $sc) => "$sc=$c")->implode(' '));
            $findings += $this->finding('expenses entered more than 90 days after their expense date (back-entered; check the date is right)', $expenses->filter(fn ($e) => $e->created_at && $e->expense_date && strtotime((string) $e->created_at) - strtotime((string) $e->expense_date) > 90 * 86400)->map(fn ($e) => "expense {$e->id} dated " . substr((string) $e->expense_date, 0, 10) . ", entered " . substr((string) $e->created_at, 0, 10) . ", ₹{$e->amount} {$e->title}"));
            $findings += $this->finding('possible duplicate expenses (same category, amount and date)', $expenses->groupBy(fn ($e) => implode('|', [$e->expense_category_id, Money::paise($e->amount), substr((string) $e->expense_date, 0, 10)]))->filter(fn ($g) => $g->count() > 1)->map(fn ($g) => 'expenses ' . $g->pluck('id')->implode(', ')));

            $this->line('  facility bookings: ' . $bookings->count() . ' rows, status ' . $bookings->groupBy('status')->map->count()->map(fn ($c, $st) => "$st=$c")->implode(' ') . ', payment ' . $bookings->groupBy('payment_status')->map->count()->map(fn ($c, $st) => "$st=$c")->implode(' '));
            $due = $bookings->filter(fn ($b) => in_array($b->status, ['approved', 'completed'], true) && Money::paise($b->booking_amount) > 0 && $b->payment_status === 'pending');
            $findings += $this->finding('approved / completed facility bookings with a charge and NO payment recorded (receivable - not income)', $due->map(fn ($b) => "booking {$b->id} {$b->booking_number} ₹{$b->booking_amount} on " . substr((string) $b->booking_date, 0, 10)));
            $findings += $this->finding('facility bookings marked paid but cancelled / rejected (is a refund due?)', $bookings->filter(fn ($b) => $b->payment_status === 'paid' && in_array($b->status, ['cancelled', 'rejected'], true))->map(fn ($b) => "booking {$b->id} {$b->booking_number} ₹{$b->booking_amount}"));
            $this->line('  facility money actually recorded as received: ₹' . number_format(Money::sum($bookings->where('payment_status', 'paid')->pluck(Schema::hasColumn('facility_bookings', 'paid_amount') ? 'paid_amount' : 'booking_amount')), 2) . ' (' . $bookings->where('payment_status', 'paid')->count() . ' bookings)');
        }

        $this->newLine();
        $this->info("{$findings} record(s) listed for a decision. Nothing was changed. Propose a correction per record and have it approved before anything is edited.");

        return self::SUCCESS;
    }

    /** Prints a titled list when it is not empty; returns how many records it listed. */
    private function finding(string $title, \Illuminate\Support\Collection $lines): int
    {
        if ($lines->isEmpty()) {
            return 0;
        }
        $this->line("  - {$title}: {$lines->count()}");
        foreach ($lines as $l) {
            $this->line("      {$l}");
        }

        return $lines->count();
    }
}
