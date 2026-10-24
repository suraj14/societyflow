<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesSocietyAccess;
use App\Models\ActivityLog;
use App\Models\Event;
use App\Models\ExpenseCategory;
use App\Models\Festival;
use App\Models\FestivalBudgetItem;
use App\Models\FestivalContribution;
use App\Models\FestivalExpense;
use App\Models\FestivalMember;
use App\Models\FestivalUpdate;
use App\Models\User;
use App\Services\FestivalException;
use App\Services\FestivalService;
use App\Support\SocietyClock;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Festival & Celebrations. Every record is reached THROUGH its festival ($festival->contributions()->findOrFail()), so a
 * festival id from another society (403 first) or a contribution id from another festival (404) can never be acted on.
 * Money rules live in FestivalService; this class authorises, validates and reports.
 */
class FestivalController extends Controller
{
    use AuthorizesSocietyAccess;

    public function __construct(private FestivalService $service)
    {
    }

    // ── list / dashboard ────────────────────────────────────────────────────

    public function index()
    {
        $user = auth()->user();
        $q = Festival::query()->when(!$user->hasRole('Super Admin'), fn ($q) => $q->where('society_id', $user->society_id));
        // residents see announced festivals only; the office also sees drafts
        if (!$user->hasRole(['Super Admin', 'Admin', 'Accountant'])) {
            $q->whereIn('status', ['published', 'closed']);
        }
        $festivals = $q->orderByDesc('festival_date')->paginate(12);
        $summaries = [];
        foreach ($festivals as $f) {
            $summaries[$f->id] = $this->service->summary($f);
        }

        return view('festivals.index', ['festivals' => $festivals, 'summaries' => $summaries, 'canCreate' => $user->hasRole('Admin') && $user->society_id]);
    }

    public function create()
    {
        abort_unless(auth()->user()->hasRole('Admin') && auth()->user()->society_id, 403);

        return view('festivals.form', ['festival' => new Festival(['separate_approver' => true]), 'users' => $this->societyUsers()]);
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        abort_unless($user->hasRole('Admin') && $user->society_id, 403);
        $data = $this->validated($request, (int) $user->society_id);
        $f = new Festival($data + ['society_id' => $user->society_id, 'created_by' => $user->id]);
        $f->status = 'draft';
        $f->save();
        ActivityLog::recordFor($f, 'festival.created', ['name' => $f->name]);

        return redirect()->route('festivals.show', $f)->with('success', 'Festival created as a draft. Add the budget, then publish it when it is ready.');
    }

    public function show(Festival $festival)
    {
        $user = auth()->user();
        $this->authorizeSocietyAccess($festival);
        $money = FestivalService::canHandleMoney($user, $festival);
        $manage = FestivalService::canManage($user, $festival);
        $enter = FestivalService::canEnterExpense($user, $festival);
        if (!$money && !$manage && !$enter && !in_array($festival->status, ['published', 'closed'], true)) {
            abort(404);
        }

        $summary = $this->service->summary($festival);
        $festival->load(['members.user', 'budgetItems', 'organizer']);
        $expenses = ($money || $enter) ? $festival->expenses()->with(['budgetItem', 'creator', 'approver'])->orderByDesc('id')->get() : collect();
        // Individual contributions are confidential: the office sees them all, a resident only their own.
        $contributions = $money
            ? $festival->contributions()->with(['user', 'flat'])->orderByDesc('id')->get()
            : $festival->contributions()->where('user_id', $user->id)->orderByDesc('id')->get();

        return view('festivals.show', [
            'festival' => $festival, 'summary' => $summary, 'expenses' => $expenses, 'contributions' => $contributions,
            'updates' => $festival->updates()->limit(20)->get(),
            'canManage' => $manage, 'canMoney' => $money, 'canEnter' => $enter, 'isCommittee' => FestivalService::isCommittee($user, $festival),
            'users' => $manage ? $this->societyUsers((int) $festival->society_id) : collect(),
            'categories' => ($money || $enter) ? ExpenseCategory::where('society_id', $festival->society_id)->where('status', 'active')->orderBy('name')->get() : collect(),
            'approvalNeeded' => $user,
        ]);
    }

    public function edit(Festival $festival)
    {
        $this->authorizeManage($festival);

        return view('festivals.form', ['festival' => $festival, 'users' => $this->societyUsers((int) $festival->society_id)]);
    }

    public function update(Request $request, Festival $festival)
    {
        $this->authorizeManage($festival);
        if ($festival->isSettled()) {
            return back()->with('error', 'This festival is settled; its plan is closed.');
        }
        $festival->update($this->validated($request, (int) $festival->society_id));
        ActivityLog::recordFor($festival, 'festival.updated', ['name' => $festival->name]);

        return redirect()->route('festivals.show', $festival)->with('success', 'Festival updated.');
    }

    // ── lifecycle ───────────────────────────────────────────────────────────

    /** Publish: residents can see it and pledge. With "announce", the existing Events module is used (same email/SMS/WhatsApp rules). */
    public function publish(Request $request, Festival $festival)
    {
        $this->authorizeManage($festival);

        return $this->act(function () use ($request, $festival) {
            if ($festival->status !== 'draft') {
                throw new FestivalException('Only a draft can be published.');
            }
            Festival::whereKey($festival->id)->where('status', 'draft')->update(['status' => 'published']);
            ActivityLog::recordFor($festival, 'festival.published', ['name' => $festival->name]);

            if ($request->boolean('announce') && !$festival->announced_at) {
                $start = $festival->festival_date->copy()->setTime(9, 0);
                $event = Event::create(['society_id' => $festival->society_id, 'created_by' => auth()->id(), 'event_name' => $festival->name, 'location' => $festival->venue ?: 'Society premises',
                    'description' => trim(($festival->description ?: $festival->name) . "\n\nContributions to this celebration are entirely voluntary."),
                    'start_date' => $start, 'end_date' => ($festival->end_date ?? $festival->festival_date)->copy()->setTime(21, 0), 'status' => 'pending', 'is_role_based' => false]);
                Festival::whereKey($festival->id)->update(['event_id' => $event->id, 'announced_at' => now()]);
                \App\Events\EventCreated::dispatch($event);        // the Events module decides who is told, on which channel
            }

            return 'Festival published' . ($request->boolean('announce') ? ' and announced through Events.' : '.');
        });
    }

    public function cancel(Festival $festival)
    {
        $this->authorizeManage($festival);

        return $this->act(function () use ($festival) {
            if ($festival->isSettled()) {
                throw new FestivalException('A settled festival cannot be cancelled.');
            }
            if ($festival->contributions()->whereIn('status', ['received'])->exists() || $festival->expenses()->where('status', 'paid')->exists()) {
                throw new FestivalException('Money has already moved. Refund the contributions and settle the festival instead of cancelling it.');
            }
            Festival::whereKey($festival->id)->update(['status' => 'cancelled']);
            $festival->contributions()->where('status', 'pledged')->update(['status' => 'cancelled', 'cancel_reason' => 'Festival cancelled']);
            ActivityLog::recordFor($festival, 'festival.cancelled', ['name' => $festival->name]);

            return 'Festival cancelled. Pledges were withdrawn; nobody owed anything.';
        });
    }

    /** Next year's edition: the plan and budget lines are copied; no contribution, expense or balance ever is. */
    public function duplicate(Request $request, Festival $festival)
    {
        $this->authorizeManage($festival);
        $data = $request->validate(['festival_date' => 'required|date|after:today']);
        $copy = new Festival(['society_id' => $festival->society_id, 'name' => $festival->name, 'description' => $festival->description, 'festival_date' => $data['festival_date'], 'venue' => $festival->venue,
            'expected_attendance' => $festival->expected_attendance, 'organizer_id' => $festival->organizer_id, 'estimated_budget' => $festival->estimated_budget, 'contribution_target' => $festival->contribution_target,
            'approval_limit' => $festival->approval_limit, 'separate_approver' => $festival->separate_approver, 'allow_anonymous' => $festival->allow_anonymous,
            'series_key' => $festival->series_key ?: 'festival-' . $festival->id, 'created_by' => auth()->id()]);
        $copy->status = 'draft';
        $copy->save();
        if (!$festival->series_key) {
            Festival::whereKey($festival->id)->update(['series_key' => 'festival-' . $festival->id]);
        }
        foreach ($festival->budgetItems as $i) {
            FestivalBudgetItem::create(['society_id' => $copy->society_id, 'festival_id' => $copy->id, 'category' => $i->category, 'description' => $i->description, 'estimated_amount' => $i->estimated_amount, 'vendor' => $i->vendor]);
        }
        foreach ($festival->members as $m) {
            FestivalMember::create(['society_id' => $copy->society_id, 'festival_id' => $copy->id, 'user_id' => $m->user_id, 'role' => $m->role, 'responsibility' => $m->responsibility]);
        }
        ActivityLog::recordFor($copy, 'festival.duplicated', ['from' => $festival->id]);

        return redirect()->route('festivals.show', $copy)->with('success', 'Next edition created as a draft. No money was carried over.');
    }

    // ── planning: budget, people, updates ───────────────────────────────────

    public function addBudgetItem(Request $request, Festival $festival)
    {
        $this->authorizeManage($festival);
        $d = $request->validate(['category' => 'required|string|max:80', 'description' => 'nullable|string|max:255', 'estimated_amount' => 'required|numeric|min:0|max:99999999',
            'vendor' => 'nullable|string|max:255', 'quotation_amount' => 'nullable|numeric|min:0|max:99999999', 'approved_limit' => 'nullable|numeric|min:0|max:99999999',
            'assigned_to' => ['nullable', Rule::exists('users', 'id')->where('society_id', $festival->society_id)]]);
        if ($festival->isSettled()) {
            return back()->with('error', 'This festival is settled; its plan is closed.');
        }
        $item = FestivalBudgetItem::create($d + ['society_id' => $festival->society_id, 'festival_id' => $festival->id]);
        ActivityLog::recordFor($item, 'festival.budget_item_added', ['festival_id' => $festival->id, 'estimated' => (float) $item->estimated_amount]);

        return back()->with('success', 'Budget line added.');
    }

    public function removeBudgetItem(Festival $festival, int $item)
    {
        $this->authorizeManage($festival);
        $row = $festival->budgetItems()->findOrFail($item);
        if ($festival->expenses()->where('budget_item_id', $row->id)->exists()) {
            return back()->with('error', 'Expenses are recorded against this line, so it cannot be removed.');
        }
        $row->delete();

        return back()->with('success', 'Budget line removed.');
    }

    public function addMember(Request $request, Festival $festival)
    {
        $this->authorizeManage($festival);
        $d = $request->validate(['user_id' => ['required', Rule::exists('users', 'id')->where('society_id', $festival->society_id)], 'role' => 'required|in:committee,volunteer', 'responsibility' => 'nullable|string|max:255']);
        FestivalMember::updateOrCreate(['festival_id' => $festival->id, 'user_id' => $d['user_id']], ['society_id' => $festival->society_id, 'role' => $d['role'], 'responsibility' => $d['responsibility'] ?? null]);
        ActivityLog::recordFor($festival, 'festival.member_assigned', ['user_id' => (int) $d['user_id'], 'role' => $d['role']]);

        return back()->with('success', 'Assigned.');
    }

    public function removeMember(Festival $festival, int $member)
    {
        $this->authorizeManage($festival);
        $festival->members()->findOrFail($member)->delete();

        return back()->with('success', 'Removed.');
    }

    public function addUpdate(Request $request, Festival $festival)
    {
        $this->authorizeManage($festival);
        $d = $request->validate(['title' => 'required|string|max:255', 'body' => 'nullable|string|max:2000', 'scheduled_for' => 'nullable|date']);
        FestivalUpdate::create($d + ['society_id' => $festival->society_id, 'festival_id' => $festival->id, 'created_by' => auth()->id()]);

        return back()->with('success', 'Update posted.');
    }

    // ── contributions (voluntary) ───────────────────────────────────────────

    public function pledge(Request $request, Festival $festival)
    {
        $this->authorizeSocietyAccess($festival);
        $d = $request->validate(['amount' => 'required|numeric|min:1|max:10000000', 'is_anonymous' => 'nullable|boolean', 'request_token' => 'nullable|string|max:64']);

        return $this->act(function () use ($festival, $d, $request) {
            $this->service->pledge($festival, auth()->user(), (float) $d['amount'], $request->boolean('is_anonymous'), $d['request_token'] ?? null);

            return 'Thank you. Your pledge is noted; it is a voluntary intention, not a bill, and nothing is owed. A receipt is issued once the office records the money received.';
        });
    }

    public function recordReceipt(Request $request, Festival $festival)
    {
        $this->authorizeMoney($festival);
        $d = $request->validate([
            'amount' => 'required|numeric|min:1|max:10000000',
            'payment_method' => ['required', Rule::in(FestivalContribution::METHODS)], 'payment_reference' => 'nullable|string|max:100', 'received_on' => 'required|date',
            'user_id' => ['nullable', Rule::exists('users', 'id')->where('society_id', $festival->society_id)],
            'contributor_name' => 'nullable|string|max:255', 'is_anonymous' => 'nullable|boolean', 'request_token' => 'nullable|string|max:64',
        ]);
        $d['is_anonymous'] = $request->boolean('is_anonymous');

        return $this->act(function () use ($festival, $d) {
            $c = $this->service->recordReceipt($festival, auth()->user(), $d);

            return 'Received ₹' . number_format((float) $c->amount, 2) . ' recorded. Receipt ' . $c->receipt_number . '.';
        });
    }

    public function receivePledge(Request $request, Festival $festival, int $contribution)
    {
        $this->authorizeMoney($festival);
        $c = $festival->contributions()->findOrFail($contribution);
        $d = $request->validate(['amount' => 'nullable|numeric|min:1|max:10000000', 'payment_method' => ['required', Rule::in(FestivalContribution::METHODS)], 'payment_reference' => 'nullable|string|max:100', 'received_on' => 'required|date']);

        return $this->act(function () use ($festival, $c, $d) {
            $c = $this->service->recordReceipt($festival, auth()->user(), $d, $c);

            return 'Recorded as received. Receipt ' . $c->receipt_number . '.';
        });
    }

    public function cancelContribution(Request $request, Festival $festival, int $contribution)
    {
        $this->authorizeSocietyAccess($festival);
        $c = $festival->contributions()->findOrFail($contribution);

        return $this->act(function () use ($festival, $c, $request) {
            $this->service->cancelPledge($festival, auth()->user(), $c, (string) $request->input('reason', ''));

            return 'Pledge withdrawn.';
        });
    }

    public function refundContribution(Request $request, Festival $festival, int $contribution)
    {
        $this->authorizeMoney($festival);
        $c = $festival->contributions()->findOrFail($contribution);
        $d = $request->validate(['amount' => 'required|numeric|min:1|max:10000000', 'reason' => 'required|string|max:255']);

        return $this->act(function () use ($festival, $c, $d) {
            if (!$this->service->refund($festival, auth()->user(), $c, (float) $d['amount'], $d['reason'])) {
                throw new FestivalException('The refund was not recorded: it would exceed what was received, or it was already refunded.');
            }

            return 'Refund recorded.';
        });
    }

    // ── expenses ────────────────────────────────────────────────────────────

    public function storeExpense(Request $request, Festival $festival)
    {
        $this->authorizeSocietyAccess($festival);
        $d = $request->validate(['vendor' => 'required|string|max:255', 'purpose' => 'required|string|max:255', 'amount' => 'required|numeric|min:1|max:99999999', 'expense_date' => 'required|date|before_or_equal:today',
            'budget_item_id' => 'nullable|integer', 'expense_category_id' => 'nullable|integer', 'request_token' => 'nullable|string|max:64', 'invoice' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:4096']);
        $path = $request->hasFile('invoice') ? $request->file('invoice')->store('festival_invoices/' . $festival->society_id, 'local') : null;

        return $this->act(function () use ($festival, $d, $request, $path) {
            $e = $this->service->createExpense($festival, auth()->user(), $d + ['invoice_path' => $path], $request->boolean('submit'));

            return $e->status === 'submitted' ? 'Expense submitted for approval.' : 'Expense saved as a draft.';
        });
    }

    public function expenseAction(Request $request, Festival $festival, int $expense, string $action)
    {
        $this->authorizeSocietyAccess($festival);
        $e = $festival->expenses()->findOrFail($expense);
        $user = auth()->user();

        return $this->act(function () use ($request, $festival, $e, $user, $action) {
            switch ($action) {
                case 'submit':
                    $ok = $this->service->submitExpense($festival, $user, $e);
                    return $ok ? 'Submitted for approval.' : 'Nothing changed: it is not a draft.';
                case 'approve':
                    $ok = $this->service->approveExpense($festival, $user, $e);
                    return $ok ? 'Approved. It is a commitment; it is not paid until it is marked paid.' : 'Nothing changed: it was not awaiting approval.';
                case 'reject':
                    $ok = $this->service->rejectExpense($festival, $user, $e, (string) $request->input('reason', ''));
                    return $ok ? 'Rejected.' : 'Nothing changed: it was not awaiting approval.';
                case 'pay':
                    $d = $request->validate(['payment_method' => ['required', Rule::in(FestivalExpense::METHODS)], 'payment_reference' => 'nullable|string|max:100', 'paid_on' => 'required|date']);
                    $ok = $this->service->payExpense($festival, $user, $e, $d['payment_method'], $d['payment_reference'] ?? null, $d['paid_on']);
                    return $ok ? 'Marked as paid.' : 'Nothing changed: it is not an approved, unpaid expense.';
                case 'cancel':
                    $ok = $this->service->cancelExpense($festival, $user, $e);
                    return $ok ? 'Cancelled.' : 'Nothing changed: it can no longer be cancelled.';
            }
            abort(404);
        });
    }

    public function invoice(Festival $festival, int $expense)
    {
        $this->authorizeSocietyAccess($festival);
        $e = $festival->expenses()->findOrFail($expense);
        abort_unless(FestivalService::canEnterExpense(auth()->user(), $festival) && $e->invoice_path && \Illuminate\Support\Facades\Storage::disk('local')->exists($e->invoice_path), 404);

        return \Illuminate\Support\Facades\Storage::disk('local')->download($e->invoice_path);
    }

    // ── settlement & reports ────────────────────────────────────────────────

    public function settle(Request $request, Festival $festival)
    {
        $this->authorizeManage($festival);

        return $this->act(function () use ($festival, $request) {
            $this->service->settle($festival, auth()->user(), $request->input('decision'));

            return 'Festival settled and closed. Its records are now read-only.';
        });
    }

    public function report(Request $request, Festival $festival, string $format = 'html')
    {
        $this->authorizeMoney($festival);
        $data = $this->reportData($festival);

        if ($format === 'csv') {
            return response()->streamDownload(function () use ($data) {
                $out = fopen('php://output', 'w');
                fputcsv($out, ['Festival', $data['festival']->name]);
                foreach ([['Opening balance', $data['summary']['opening_balance']], ['Contributions received', $data['summary']['contributions_gross']], ['Refunds', $data['summary']['refunds']],
                    ['Net contributions', $data['summary']['contributions_net']], ['Expenses paid', $data['summary']['expenses_paid']], ['Approved, unpaid (vendor commitments)', $data['summary']['expenses_approved_unpaid']],
                    ['Cash available', $data['summary']['cash_available']]] as $row) {
                    fputcsv($out, $row);
                }
                fputcsv($out, []);
                fputcsv($out, ['Receipt', 'Received on', 'Contributor', 'Method', 'Reference', 'Amount', 'Refunded', 'Status']);
                foreach ($data['contributions'] as $c) {
                    fputcsv($out, [$c->receipt_number, optional($c->received_on)->toDateString(), $c->is_anonymous ? 'Anonymous' : $c->contributor_name, $c->payment_method, $c->payment_reference, $c->amount, $c->refunded_amount, $c->status]);
                }
                fputcsv($out, []);
                fputcsv($out, ['Date', 'Vendor', 'Purpose', 'Amount', 'Status', 'Paid on', 'Method', 'Reference']);
                foreach ($data['expenses'] as $e) {
                    fputcsv($out, [optional($e->expense_date)->toDateString(), $e->vendor, $e->purpose, $e->amount, $e->status, optional($e->paid_on)->toDateString(), $e->payment_method, $e->payment_reference]);
                }
                fclose($out);
            }, 'festival-' . $festival->id . '-settlement.csv', ['Content-Type' => 'text/csv']);
        }
        if ($format === 'pdf') {
            return \PDF::loadView('festivals.report-pdf', $data)->download('festival-' . $festival->id . '-settlement.pdf');
        }

        return view('festivals.report', $data);
    }

    // ── helpers ─────────────────────────────────────────────────────────────

    private function reportData(Festival $festival): array
    {
        return ['festival' => $festival->load(['members.user', 'organizer']), 'summary' => $this->service->summary($festival),
            'contributions' => $festival->contributions()->whereIn('status', ['received', 'refunded'])->orderBy('received_on')->orderBy('id')->get(),
            'expenses' => $festival->expenses()->whereNotIn('status', ['draft'])->with('budgetItem')->orderBy('expense_date')->orderBy('id')->get(),
            'audit' => ActivityLog::where('society_id', $festival->society_id)->where('action', 'like', 'festival.%')
                ->where(fn ($q) => $q->where(fn ($z) => $z->where('model_type', Festival::class)->where('model_id', $festival->id))
                    ->orWhere('new_values', 'like', '%"festival_id":' . $festival->id . '%'))->orderBy('id')->get()];
    }

    private function act(\Closure $fn)
    {
        try {
            return back()->with('success', $fn());
        } catch (FestivalException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }
    }

    private function authorizeManage(Festival $f): void
    {
        $this->authorizeSocietyAccess($f);
        abort_unless(FestivalService::canManage(auth()->user(), $f), 403);
    }

    private function authorizeMoney(Festival $f): void
    {
        $this->authorizeSocietyAccess($f);
        abort_unless(FestivalService::canHandleMoney(auth()->user(), $f), 403);
    }

    private function societyUsers(?int $societyId = null)
    {
        $societyId ??= auth()->user()->society_id;

        return User::where('society_id', $societyId)->orderBy('name')->get(['id', 'name']);
    }

    private function validated(Request $request, int $societyId): array
    {
        return $request->validate([
            'name' => 'required|string|max:255', 'description' => 'nullable|string|max:5000', 'festival_date' => 'required|date', 'end_date' => 'nullable|date|after_or_equal:festival_date',
            'venue' => 'nullable|string|max:255', 'expected_attendance' => 'nullable|integer|min:0|max:1000000',
            'organizer_id' => ['nullable', Rule::exists('users', 'id')->where('society_id', $societyId)],
            'estimated_budget' => 'nullable|numeric|min:0|max:99999999', 'approved_budget' => 'nullable|numeric|min:0|max:99999999',
            'contribution_target' => 'nullable|numeric|min:0|max:99999999', 'approval_limit' => 'nullable|numeric|min:0|max:99999999',
            'opening_balance' => 'nullable|numeric|min:0|max:99999999', 'opening_balance_note' => [Rule::requiredIf(fn () => (float) $request->input('opening_balance') > 0), 'nullable', 'string', 'max:255'],
            'separate_approver' => 'nullable|boolean', 'allow_anonymous' => 'nullable|boolean', 'arrangements' => 'nullable|string|max:5000',
        ]) + ['separate_approver' => $request->boolean('separate_approver'), 'allow_anonymous' => $request->boolean('allow_anonymous'), 'estimated_budget' => $request->input('estimated_budget') ?: 0,
            'opening_balance' => $request->input('opening_balance') ?: 0];
    }
}
