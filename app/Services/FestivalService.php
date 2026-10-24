<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Festival;
use App\Models\FestivalContribution;
use App\Models\FestivalExpense;
use App\Models\User;
use App\Support\Money;
use App\Support\SocietyClock;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Festival money, in one place.
 *
 * - A festival is its own fund. Voluntary contributions are not maintenance income and festival spending is not a society
 *   expense, so none of this reaches the society Financial Report (nothing is double counted by design).
 * - Only a verified receipt is money. A pledge is an intention and is worth nothing until someone with authority records the
 *   receipt; only then is a receipt number issued.
 * - An approved expense is a commitment, not a payment. Cash leaves the fund only when it is marked paid.
 * - Every state change is a guarded compare-and-swap inside a transaction, so repeats, double clicks and races change nothing twice.
 * - Arithmetic is in whole paise (App\Support\Money).
 */
class FestivalService
{
    // ── who may do what ─────────────────────────────────────────────────────

    public static function inSociety(User $u, Festival $f): bool
    {
        return $u->hasRole('Super Admin') || (int) $u->society_id === (int) $f->society_id;
    }

    /** Admin (and Super Admin): plan, budget, approve anything, settle. */
    public static function canManage(User $u, Festival $f): bool
    {
        return self::inSociety($u, $f) && $u->hasRole(['Super Admin', 'Admin']);
    }

    /** Admin and Accountant: see confidential contribution records, record receipts and refunds, pay approved expenses. */
    public static function canHandleMoney(User $u, Festival $f): bool
    {
        return self::inSociety($u, $f) && $u->hasRole(['Super Admin', 'Admin', 'Accountant']);
    }

    public static function isCommittee(User $u, Festival $f): bool
    {
        return $f->members()->where('user_id', $u->id)->where('role', 'committee')->exists();
    }

    /** Anyone who may enter an expense: money handlers and the committee members assigned to this festival. */
    public static function canEnterExpense(User $u, Festival $f): bool
    {
        return self::inSociety($u, $f) && (self::canHandleMoney($u, $f) || self::isCommittee($u, $f));
    }

    /** Admin: any amount. A committee member: only within the festival's approval limit, and only if one is set. */
    public static function canApprove(User $u, Festival $f, FestivalExpense $e): bool
    {
        if (!self::inSociety($u, $f)) {
            return false;
        }
        if (self::canManage($u, $f)) {
            return true;
        }

        return self::isCommittee($u, $f) && $f->approval_limit !== null && Money::paise($e->amount) <= Money::paise($f->approval_limit);
    }

    // ── the figures ─────────────────────────────────────────────────────────

    /** Every number on the dashboard and the settlement report. Computed from recorded rows only. */
    public function summary(Festival $f): array
    {
        $money = fn ($rows) => Money::sum($rows);
        $received = $f->contributions()->whereIn('status', ['received', 'refunded'])->get(['amount', 'refunded_amount']);
        $gross = $money($received->pluck('amount'));
        $refunds = $money($received->pluck('refunded_amount'));
        $net = Money::diff($gross, $refunds);

        $byStatus = fn (string $s) => $money($f->expenses()->where('status', $s)->pluck('amount'));
        $paid = $byStatus('paid');
        $approved = $byStatus('approved');          // committed, not yet paid
        $submitted = $byStatus('submitted');
        $opening = (float) $f->opening_balance;

        $cash = Money::diff(Money::sum([$opening, $net]), $paid);
        $budgetCeiling = $f->approved_budget !== null ? (float) $f->approved_budget : (float) $f->estimated_budget;
        $spentOrCommitted = Money::sum([$paid, $approved]);

        $items = $f->budgetItems()->get()->map(function ($i) use ($f) {
            $q = $f->expenses()->where('budget_item_id', $i->id);
            $actual = Money::sum((clone $q)->where('status', 'paid')->pluck('amount'));
            $committed = Money::sum((clone $q)->where('status', 'approved')->pluck('amount'));
            $limit = $i->approved_limit !== null ? (float) $i->approved_limit : (float) $i->estimated_amount;

            return [
                'id' => $i->id, 'category' => $i->category, 'description' => $i->description, 'vendor' => $i->vendor,
                'estimated' => (float) $i->estimated_amount, 'quotation' => $i->quotation_amount !== null ? (float) $i->quotation_amount : null,
                'approved_limit' => $i->approved_limit !== null ? (float) $i->approved_limit : null,
                'paid' => $actual, 'committed' => $committed,
                'overspent' => $limit > 0 && Money::paise(Money::sum([$actual, $committed])) > Money::paise($limit),
            ];
        });

        return [
            'opening_balance' => $opening,
            'contributions_gross' => $gross,
            'refunds' => $refunds,
            'contributions_net' => $net,
            'contributors' => $f->contributions()->whereIn('status', ['received', 'refunded'])->count(),
            'pledged' => $money($f->contributions()->where('status', 'pledged')->pluck('amount')),
            'pledged_count' => $f->contributions()->where('status', 'pledged')->count(),
            'target' => $f->contribution_target !== null ? (float) $f->contribution_target : null,
            'target_remaining' => $f->contribution_target !== null ? max(0.0, Money::diff((float) $f->contribution_target, $net)) : null,
            'expenses_paid' => $paid,
            'expenses_approved_unpaid' => $approved,
            'expenses_submitted' => $submitted,
            'estimated_budget' => (float) $f->estimated_budget,
            'approved_budget' => $f->approved_budget !== null ? (float) $f->approved_budget : null,
            'cash_available' => $cash,
            'after_commitments' => Money::diff($cash, $approved),
            'overspent' => $budgetCeiling > 0 && Money::paise($spentOrCommitted) > Money::paise($budgetCeiling),
            'budget_ceiling' => $budgetCeiling,
            'items' => $items,
            'surplus' => $f->isSettled() || $f->status === 'closed' ? $cash : null,
        ];
    }

    // ── contributions ───────────────────────────────────────────────────────

    /** A resident says they intend to give. Not money; nothing is owed; no receipt. */
    public function pledge(Festival $f, User $by, float $amount, bool $anonymous, ?string $token = null): FestivalContribution
    {
        $this->assertOpen($f, true);
        if ($amount <= 0) {
            throw new FestivalException('Enter an amount greater than zero.');
        }
        if ($anonymous && !$f->allow_anonymous) {
            throw new FestivalException('This festival does not take anonymous contributions.');
        }
        if ($token && ($dup = $f->contributions()->where('request_token', $token)->first())) {
            return $dup;
        }

        $flatId = \App\Support\UserFlatResolver::flatIdsFor($by)[0] ?? null;
        $c = new FestivalContribution(['society_id' => $f->society_id, 'festival_id' => $f->id, 'user_id' => $by->id, 'flat_id' => $flatId,
            'contributor_name' => $by->name, 'is_anonymous' => $anonymous, 'amount' => round($amount, 2), 'request_token' => $token, 'created_by' => $by->id]);
        $c->status = 'pledged';
        $c->save();
        ActivityLog::recordFor($c, 'festival.contribution_pledged', ['festival_id' => $f->id, 'amount' => (float) $c->amount]);

        return $c;
    }

    /**
     * Record verified receipt. Either of an existing pledge ($pledge) or of money handed over directly ($data['user_id'] etc.).
     * Issues the receipt number. Repeating the same call (same pledge, or same request token) records the money once.
     *
     * @param array{amount?:float,payment_method:string,payment_reference?:?string,received_on:string,user_id?:?int,contributor_name?:?string,is_anonymous?:bool,request_token?:?string} $data
     */
    public function recordReceipt(Festival $f, User $by, array $data, ?FestivalContribution $pledge = null): FestivalContribution
    {
        $this->assertOpen($f);
        if (!self::canHandleMoney($by, $f)) {
            throw new FestivalException('Only an Admin or Accountant can record a received contribution.');
        }
        $method = (string) ($data['payment_method'] ?? '');
        if (!in_array($method, FestivalContribution::METHODS, true)) {
            throw new FestivalException('Choose how the money was received.');
        }
        $reference = isset($data['payment_reference']) && trim((string) $data['payment_reference']) !== '' ? trim((string) $data['payment_reference']) : null;
        if ($method !== 'cash' && $reference === null) {
            throw new FestivalException('Enter the transaction / cheque reference: a non-cash receipt needs one before it is recorded.');
        }
        $receivedOn = \Carbon\Carbon::parse($data['received_on'])->toDateString();
        if ($receivedOn > SocietyClock::now($f->society_id)->toDateString()) {
            throw new FestivalException('The received date cannot be in the future.');
        }

        try {
            return DB::transaction(function () use ($f, $by, $data, $pledge, $method, $reference, $receivedOn) {
                $receipt = $this->receiptNumber((int) $f->society_id);
                $fields = ['status' => 'received', 'payment_method' => $method, 'payment_reference' => $reference, 'received_on' => $receivedOn, 'receipt_number' => $receipt, 'recorded_by' => $by->id];

                if ($pledge) {
                    $row = FestivalContribution::whereKey($pledge->id)->where('festival_id', $f->id)->lockForUpdate()->first();
                    if (!$row) {
                        throw new FestivalException('That contribution does not belong to this festival.');
                    }
                    if ($row->status === 'received') {
                        return $row;                      // already recorded: once
                    }
                    $amount = isset($data['amount']) && (float) $data['amount'] > 0 ? round((float) $data['amount'], 2) : (float) $row->amount;
                    $n = FestivalContribution::whereKey($row->id)->where('status', 'pledged')->update($fields + ['amount' => $amount]);
                    if ($n !== 1) {
                        throw new FestivalException('This pledge can no longer be recorded as received (' . $row->status . ').');
                    }
                    $c = $row->fresh();
                } else {
                    $amount = round((float) ($data['amount'] ?? 0), 2);
                    if ($amount <= 0) {
                        throw new FestivalException('Enter an amount greater than zero.');
                    }
                    $token = $data['request_token'] ?? null;
                    if ($token && ($dup = $f->contributions()->where('request_token', $token)->first())) {
                        return $dup;
                    }
                    $anonymous = (bool) ($data['is_anonymous'] ?? false);
                    if ($anonymous && !$f->allow_anonymous) {
                        throw new FestivalException('This festival does not take anonymous contributions.');
                    }
                    $userId = isset($data['user_id']) ? (int) $data['user_id'] : null;
                    $user = $userId ? User::where('id', $userId)->where('society_id', $f->society_id)->first() : null;
                    if ($userId && !$user) {
                        throw new FestivalException('That resident is not part of this society.');
                    }
                    $c = new FestivalContribution(['society_id' => $f->society_id, 'festival_id' => $f->id, 'user_id' => $user?->id, 'is_anonymous' => $anonymous,
                        'contributor_name' => $user?->name ?? ($data['contributor_name'] ?? null), 'amount' => $amount, 'request_token' => $token, 'created_by' => $by->id]);
                    $c->forceFill($fields)->save();
                }

                ActivityLog::recordFor($c, 'festival.contribution_received', ['festival_id' => $f->id, 'amount' => (float) $c->amount, 'receipt_number' => $c->receipt_number, 'payment_method' => $method]);

                return $c;
            });
        } catch (QueryException $e) {
            if (str_contains(strtolower($e->getMessage()), 'unique')) {
                throw new FestivalException('That transaction reference is already recorded for another contribution. Nothing was changed.');
            }
            throw $e;
        }
    }

    public function cancelPledge(Festival $f, User $by, FestivalContribution $c, string $reason): void
    {
        $this->assertOpen($f);
        $own = (int) $c->user_id === (int) $by->id;
        if (!$own && !self::canHandleMoney($by, $f)) {
            throw new FestivalException('You cannot cancel this pledge.');
        }
        $n = FestivalContribution::whereKey($c->id)->where('festival_id', $f->id)->where('status', 'pledged')
            ->update(['status' => 'cancelled', 'cancel_reason' => mb_substr(trim($reason) ?: 'Withdrawn', 0, 255)]);
        if ($n === 1) {
            ActivityLog::recordFor($c, 'festival.contribution_cancelled', ['festival_id' => $f->id]);
        }
    }

    /** Pay back some or all of a received contribution. Never more than was received; only once per rupee. */
    public function refund(Festival $f, User $by, FestivalContribution $c, float $amount, string $reason): bool
    {
        if (!self::canHandleMoney($by, $f)) {
            throw new FestivalException('Only an Admin or Accountant can record a refund.');
        }
        if ($f->isSettled()) {
            throw new FestivalException('This festival is settled; its records are closed.');
        }
        if ($amount <= 0 || trim($reason) === '') {
            throw new FestivalException('Enter the refund amount and the reason.');
        }
        $paise = Money::paise($amount);
        $done = DB::transaction(function () use ($f, $c, $amount, $reason, $paise) {
            $row = FestivalContribution::whereKey($c->id)->where('festival_id', $f->id)->lockForUpdate()->first();
            if (!$row || !in_array($row->status, ['received', 'refunded'], true)) {
                return false;
            }
            $total = Money::paise($row->refunded_amount) + $paise;
            if ($total > Money::paise($row->amount)) {
                return false;                              // never more than was received
            }
            $n = FestivalContribution::whereKey($row->id)->whereIn('status', ['received', 'refunded'])
                ->where('refunded_amount', $row->refunded_amount)           // compare-and-swap on the amount seen
                ->update(['refunded_amount' => Money::rupees($total), 'refunded_at' => SocietyClock::now($f->society_id), 'refund_reason' => mb_substr(trim($reason), 0, 255),
                    'status' => $total === Money::paise($row->amount) ? 'refunded' : 'received']);

            return $n === 1;
        });
        if ($done) {
            ActivityLog::recordFor($c, 'festival.contribution_refunded', ['festival_id' => $f->id, 'amount' => round($amount, 2)]);
        }

        return $done;
    }

    // ── expenses ────────────────────────────────────────────────────────────

    /** @param array{vendor:string,purpose:string,amount:float,expense_date:string,budget_item_id?:?int,expense_category_id?:?int,request_token?:?string,invoice_path?:?string} $data */
    public function createExpense(Festival $f, User $by, array $data, bool $submit): FestivalExpense
    {
        $this->assertOpen($f);
        if (!self::canEnterExpense($by, $f)) {
            throw new FestivalException('You are not assigned to enter expenses for this festival.');
        }
        $amount = round((float) $data['amount'], 2);
        if ($amount <= 0) {
            throw new FestivalException('Enter an amount greater than zero.');
        }
        $token = $data['request_token'] ?? null;
        if ($token && ($dup = $f->expenses()->where('request_token', $token)->first())) {
            return $dup;
        }
        $item = null;
        if (!empty($data['budget_item_id'])) {
            $item = $f->budgetItems()->whereKey($data['budget_item_id'])->first();      // an item of THIS festival and society only
            if (!$item) {
                throw new FestivalException('That budget line does not belong to this festival.');
            }
        }
        if (!empty($data['expense_category_id']) && !\App\Models\ExpenseCategory::where('id', $data['expense_category_id'])->where('society_id', $f->society_id)->exists()) {
            throw new FestivalException('That expense category does not belong to this society.');
        }
        $dupe = $f->expenses()->whereNotIn('status', ['cancelled', 'rejected'])->where('vendor', trim($data['vendor']))->whereDate('expense_date', $data['expense_date'])
            ->where('purpose', trim($data['purpose']))->whereRaw('CAST(ROUND(amount * 100) AS INTEGER) = ?', [Money::paise($amount)])->exists();
        if ($dupe) {
            throw new FestivalException('This looks like a duplicate: the same vendor, purpose, date and amount is already entered.');
        }

        $e = new FestivalExpense(['society_id' => $f->society_id, 'festival_id' => $f->id, 'budget_item_id' => $item?->id, 'expense_category_id' => $data['expense_category_id'] ?? null,
            'vendor' => trim($data['vendor']), 'purpose' => trim($data['purpose']), 'amount' => $amount, 'expense_date' => $data['expense_date'],
            'invoice_path' => $data['invoice_path'] ?? null, 'request_token' => $token, 'created_by' => $by->id]);
        $e->status = 'draft';
        $e->save();
        ActivityLog::recordFor($e, 'festival.expense_created', ['festival_id' => $f->id, 'amount' => $amount]);
        if ($submit) {
            $this->submitExpense($f, $by, $e);
        }

        return $e->fresh();
    }

    public function submitExpense(Festival $f, User $by, FestivalExpense $e): bool
    {
        $this->assertOpen($f);
        if (!self::canEnterExpense($by, $f)) {
            throw new FestivalException('You cannot submit expenses for this festival.');
        }

        return $this->move($f, $e, ['draft'], ['status' => 'submitted', 'submitted_by' => $by->id, 'submitted_at' => SocietyClock::now($f->society_id)], 'festival.expense_submitted');
    }

    public function approveExpense(Festival $f, User $by, FestivalExpense $e): bool
    {
        $this->assertOpen($f);
        if (!self::canApprove($by, $f, $e)) {
            throw new FestivalException($f->approval_limit === null && !self::canManage($by, $f)
                ? 'Only an Admin can approve expenses for this festival (no committee approval limit is set).'
                : 'This amount is above your approval limit. An Admin must approve it.');
        }
        if ($f->separate_approver && ((int) $e->created_by === (int) $by->id || (int) $e->submitted_by === (int) $by->id)) {
            throw new FestivalException('You entered this expense, so someone else must approve it.');
        }
        $item = $e->budget_item_id ? $f->budgetItems()->whereKey($e->budget_item_id)->first() : null;
        if ($item && $item->approved_limit !== null) {
            $used = Money::sum($f->expenses()->where('budget_item_id', $item->id)->whereIn('status', ['approved', 'paid'])->where('id', '!=', $e->id)->pluck('amount'));
            if (Money::paise(Money::sum([$used, (float) $e->amount])) > Money::paise($item->approved_limit)) {
                throw new FestivalException('This would take "' . $item->category . '" above its approved limit of ₹' . number_format((float) $item->approved_limit, 2) . '.');
            }
        }

        return $this->move($f, $e, ['submitted'], ['status' => 'approved', 'approved_by' => $by->id, 'approved_at' => SocietyClock::now($f->society_id)], 'festival.expense_approved');
    }

    public function rejectExpense(Festival $f, User $by, FestivalExpense $e, string $reason): bool
    {
        $this->assertOpen($f);
        if (!self::canApprove($by, $f, $e)) {
            throw new FestivalException('You cannot reject this expense.');
        }
        if (trim($reason) === '') {
            throw new FestivalException('Say why the expense is rejected.');
        }

        return $this->move($f, $e, ['submitted'], ['status' => 'rejected', 'rejected_by' => $by->id, 'rejection_reason' => mb_substr(trim($reason), 0, 255)], 'festival.expense_rejected');
    }

    /** Cash actually leaves the fund here, and only for an approved expense, only once, and only if the fund holds the money. */
    public function payExpense(Festival $f, User $by, FestivalExpense $e, string $method, ?string $reference, string $paidOn): bool
    {
        $this->assertOpen($f);
        if (!self::canHandleMoney($by, $f)) {
            throw new FestivalException('Only an Admin or Accountant can mark an expense as paid.');
        }
        if (!in_array($method, FestivalExpense::METHODS, true)) {
            throw new FestivalException('Choose how it was paid.');
        }
        $reference = $reference !== null && trim($reference) !== '' ? trim($reference) : null;
        if ($method !== 'cash' && $reference === null) {
            throw new FestivalException('Enter the payment reference for a non-cash payment.');
        }
        if (\Carbon\Carbon::parse($paidOn)->toDateString() > SocietyClock::now($f->society_id)->toDateString()) {
            throw new FestivalException('The payment date cannot be in the future.');
        }

        return DB::transaction(function () use ($f, $by, $e, $method, $reference, $paidOn) {
            $row = FestivalExpense::whereKey($e->id)->where('festival_id', $f->id)->lockForUpdate()->first();
            if (!$row || $row->status !== 'approved') {
                return false;                               // already paid, cancelled, or never approved: nothing to do
            }
            if (Money::paise($row->amount) > Money::paise($this->summary($f)['cash_available'])) {
                throw new FestivalException('The festival fund does not hold ₹' . number_format((float) $row->amount, 2) . ' yet. Record the contributions received first.');
            }

            return $this->move($f, $row, ['approved'], ['status' => 'paid', 'payment_method' => $method, 'payment_reference' => $reference, 'paid_by' => $by->id, 'paid_on' => \Carbon\Carbon::parse($paidOn)->toDateString()], 'festival.expense_paid');
        });
    }

    public function cancelExpense(Festival $f, User $by, FestivalExpense $e): bool
    {
        $this->assertOpen($f);
        $own = (int) $e->created_by === (int) $by->id;
        if (!$own && !self::canManage($by, $f)) {
            throw new FestivalException('You cannot cancel this expense.');
        }

        return $this->move($f, $e, ['draft', 'submitted', 'approved'], ['status' => 'cancelled', 'cancelled_by' => $by->id], 'festival.expense_cancelled');
    }

    // ── settlement ──────────────────────────────────────────────────────────

    public function settle(Festival $f, User $by, ?string $decision): void
    {
        if (!self::canManage($by, $f)) {
            throw new FestivalException('Only an Admin can settle a festival.');
        }
        if ($f->isSettled()) {
            return;
        }
        $open = $f->expenses()->whereIn('status', ['draft', 'submitted', 'approved'])->count();
        if ($open > 0) {
            throw new FestivalException("{$open} expense(s) are still draft, awaiting approval or approved but unpaid. Pay, reject or cancel them before settling.");
        }
        $s = $this->summary($f);
        if (Money::paise($s['cash_available']) !== 0 && trim((string) $decision) === '') {
            throw new FestivalException('There is a ' . ($s['cash_available'] > 0 ? 'surplus' : 'shortfall') . ' of ₹' . number_format(abs($s['cash_available']), 2) . '. Record the society\'s decision on it before settling.');
        }
        $n = Festival::whereKey($f->id)->where('settlement_status', 'open')->update(['settlement_status' => 'settled', 'status' => 'closed', 'settled_at' => SocietyClock::now($f->society_id),
            'settled_by' => $by->id, 'settlement_decision' => $decision !== null ? mb_substr(trim($decision), 0, 2000) : null]);
        if ($n === 1) {
            ActivityLog::recordFor($f, 'festival.settled', ['cash_available' => $s['cash_available'], 'decision' => $decision]);
        }
        $f->refresh();
    }

    // ── internals ───────────────────────────────────────────────────────────

    private function assertOpen(Festival $f, bool $needsPublished = false): void
    {
        $f->refresh();
        if ($f->isSettled() || in_array($f->status, ['closed', 'cancelled'], true)) {
            throw new FestivalException('This festival is ' . ($f->isSettled() ? 'settled' : $f->status) . '; its records are closed.');
        }
        if ($needsPublished && $f->status !== 'published') {
            throw new FestivalException('This festival is not open for contributions yet.');
        }
    }

    /** Guarded status move: only from one of $from, in a transaction, on a row of this festival. false = nothing matched (already moved). */
    private function move(Festival $f, FestivalExpense $e, array $from, array $changes, string $action): bool
    {
        $n = DB::transaction(function () use ($f, $e, $from, $changes) {
            $row = FestivalExpense::whereKey($e->id)->where('festival_id', $f->id)->lockForUpdate()->first();
            if (!$row || !in_array($row->status, $from, true)) {
                return 0;
            }

            return FestivalExpense::whereKey($row->id)->whereIn('status', $from)->update($changes);
        });
        if ($n === 1) {
            ActivityLog::recordFor($e, $action, ['festival_id' => $f->id, 'amount' => (float) $e->amount, 'status' => $changes['status']]);
        }
        $e->refresh();

        return $n === 1;
    }

    private function receiptNumber(int $societyId): string
    {
        return FestivalContribution::nextReceiptNumber($societyId);
    }
}
