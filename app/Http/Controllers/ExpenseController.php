<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Society expense management: the money the society itself pays out
 * (security agency, common-area electricity, gardening...). An individual
 * property's own bills are not managed here - they belong to the
 * owner/tenant billing and payment workflow.
 *
 * Who may do what (mirrors the `manage_expenses` grant in the permission
 * seeder, which gives it to Admin and Accountant):
 *  - view / add / edit:  Super Admin, Admin, Accountant
 *  - delete:             Super Admin, Admin
 * Owners, tenants, guards and staff never reach this controller - the
 * route group is role-gated and every method re-checks.
 *
 * Every row is scoped by society, and scope/flat_id/paid_by are set by the
 * server, never read from the request.
 */
class ExpenseController extends BaseController
{
    private const VIEW_ROLES   = ['Super Admin', 'Admin', 'Accountant'];
    private const DELETE_ROLES = ['Super Admin', 'Admin'];

    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $this->authorizeRole(...self::VIEW_ROLES);

        $user      = auth()->user();
        $societyId = $this->getSocietyId();

        $filters = $request->validate([
            'from'           => 'nullable|date',
            'to'             => 'nullable|date|after_or_equal:from',
            'category_id'    => 'nullable|integer',
            'payment_method' => ['nullable', Rule::in(Expense::PAYMENT_METHODS)],
            'vendor'         => 'nullable|string|max:255',
            'q'              => 'nullable|string|max:255',
        ]);

        // This screen is Society Expenses only. An individual property's own
        // bills belong to the owner/tenant billing workflow, not here, so
        // property-scoped rows (legacy) are never listed or counted.
        $query = Expense::query()
            ->with(['category', 'createdBy', 'society'])
            ->where('scope', Expense::SCOPE_SOCIETY)
            ->where('status', '!=', 'rejected');

        // Scope by default; Super Admin sees every society.
        if (!$user->hasRole('Super Admin')) {
            $query->where('society_id', $societyId);
        }

        if (!empty($filters['from'])) {
            $query->whereDate('expense_date', '>=', $filters['from']);
        }
        if (!empty($filters['to'])) {
            $query->whereDate('expense_date', '<=', $filters['to']);
        }
        if (!empty($filters['category_id'])) {
            $query->where('expense_category_id', $filters['category_id']);
        }
        if (!empty($filters['payment_method'])) {
            $query->where('payment_method', $filters['payment_method']);
        }
        if (!empty($filters['vendor'])) {
            $query->where('vendor_name', 'like', '%' . $filters['vendor'] . '%');
        }
        if (!empty($filters['q'])) {
            $q = '%' . $filters['q'] . '%';
            $query->where(function ($w) use ($q) {
                $w->where('title', 'like', $q)
                  ->orWhere('vendor_name', 'like', $q)
                  ->orWhere('bill_number', 'like', $q)
                  ->orWhere('description', 'like', $q);
            });
        }

        $totals = (clone $query)
            ->reorder()
            ->selectRaw('COALESCE(SUM(amount), 0) as total, COUNT(*) as cnt')
            ->first();

        $expenses = $query->orderByDesc('expense_date')->orderByDesc('id')->paginate(25)->withQueryString();

        $categories = $societyId
            ? ExpenseCategory::where('society_id', $societyId)->orderBy('name')->get()
            : ExpenseCategory::orderBy('name')->get();

        return view('expenses.index', [
            'expenses'        => $expenses,
            'categories'      => $categories,
            'filters'         => $filters,
            'societyTotal'    => (float) ($totals->total ?? 0),
            'societyCount'    => (int) ($totals->cnt ?? 0),
            'canDelete'       => $user->hasAnyRole(self::DELETE_ROLES),
            'isSuperAdmin'    => $user->hasRole('Super Admin'),
        ]);
    }

    public function create()
    {
        $this->authorizeRole(...self::VIEW_ROLES);

        $societyId = $this->requireSocietyForWrite();

        ExpenseCategory::ensureDefaults($societyId);

        return view('expenses.create', [
            'categories' => ExpenseCategory::where('society_id', $societyId)->active()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeRole(...self::VIEW_ROLES);

        $societyId = $this->requireSocietyForWrite();
        $data      = $this->validated($request, $societyId);

        if ($request->hasFile('receipt')) {
            $data['receipt'] = $request->file('receipt')->store('expenses/receipts', 'public');
        }

        $expense = Expense::create($data + [
            'society_id' => $societyId,
            'created_by' => auth()->id(),
            // Recorded by the society's own finance staff: there is no
            // separate approval step, so the row counts immediately.
            'status'     => 'approved',
        ]);

        ActivityLog::recordFor($expense, 'expense_created', $this->auditPayload($expense));

        return redirect()->route('expenses.show', $expense)
            ->with('success', 'Expense recorded.');
    }

    public function show(Expense $expense)
    {
        $this->authorizeRole(...self::VIEW_ROLES);
        $this->authorizeSocietyAccess($expense);

        $this->ensureSocietyExpense($expense);

        $expense->load(['category', 'createdBy', 'society']);

        return view('expenses.show', [
            'expense'   => $expense,
            'canDelete' => auth()->user()->hasAnyRole(self::DELETE_ROLES),
        ]);
    }

    public function edit(Expense $expense)
    {
        $this->authorizeRole(...self::VIEW_ROLES);
        $this->authorizeSocietyAccess($expense);

        $this->ensureSocietyExpense($expense);

        ExpenseCategory::ensureDefaults($expense->society_id);

        return view('expenses.edit', [
            'expense'    => $expense,
            'categories' => ExpenseCategory::where('society_id', $expense->society_id)
                ->where(fn ($q) => $q->where('status', 'active')->orWhere('id', $expense->expense_category_id))
                ->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Expense $expense)
    {
        $this->authorizeRole(...self::VIEW_ROLES);
        $this->authorizeSocietyAccess($expense);

        $this->ensureSocietyExpense($expense);

        $data = $this->validated($request, $expense->society_id);

        if ($request->hasFile('receipt')) {
            if ($expense->receipt) {
                Storage::disk('public')->delete($expense->receipt);
            }
            $data['receipt'] = $request->file('receipt')->store('expenses/receipts', 'public');
        }

        $old = $this->auditPayload($expense);
        $expense->update($data);

        ActivityLog::recordFor($expense, 'expense_updated', $this->auditPayload($expense->fresh()), $old);

        return redirect()->route('expenses.show', $expense)
            ->with('success', 'Expense updated.');
    }

    public function destroy(Expense $expense)
    {
        $this->authorizeRole(...self::DELETE_ROLES);
        $this->authorizeSocietyAccess($expense);

        $payload = $this->auditPayload($expense);

        if ($expense->receipt) {
            Storage::disk('public')->delete($expense->receipt);
        }

        // Log before the delete so model_id still points at a real key at
        // write time; the row is gone afterwards by design.
        ActivityLog::recordFor($expense, 'expense_deleted', [], $payload);

        $expense->delete();

        return redirect()->route('expenses.index')
            ->with('success', 'Expense deleted.');
    }

    // ------------------------------------------------------------------
    // Categories (per society; the defaults are seeded on first use)
    // ------------------------------------------------------------------

    public function categories()
    {
        $this->authorizeRole(...self::VIEW_ROLES);

        $societyId = $this->requireSocietyForWrite();

        ExpenseCategory::ensureDefaults($societyId);

        $categories = ExpenseCategory::where('society_id', $societyId)
            ->withCount('expenses')
            ->orderBy('name')
            ->get();

        return view('expenses.categories', compact('categories'));
    }

    public function storeCategory(Request $request)
    {
        $this->authorizeRole(...self::VIEW_ROLES);

        $societyId = $this->requireSocietyForWrite();

        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('expense_categories', 'name')->where('society_id', $societyId),
            ],
        ]);

        ExpenseCategory::create([
            'society_id' => $societyId,
            'name'       => trim($data['name']),
            'status'     => 'active',
        ]);

        return redirect()->route('expenses.categories')->with('success', 'Category added.');
    }

    public function toggleCategory(ExpenseCategory $category)
    {
        $this->authorizeRole(...self::VIEW_ROLES);
        $this->authorizeSocietyAccess($category);

        $category->update(['status' => $category->status === 'active' ? 'inactive' : 'active']);

        return redirect()->route('expenses.categories')
            ->with('success', $category->status === 'active' ? 'Category enabled.' : 'Category hidden from new expenses.');
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Validate an expense submission against $societyId. The property, when
     * required, must exist inside that society - a flat_id from another
     * society is a validation error, not a silent cross-society write.
     */
    private function validated(Request $request, int $societyId): array
    {
        $data = $request->validate([
            'expense_category_id' => 'required|integer',
            'title'               => 'required|string|max:255',
            'amount'              => 'required|numeric|min:0.01|max:99999999.99',
            'expense_date'        => 'required|date',
            'vendor_name'         => 'nullable|string|max:255',
            'bill_number'         => 'nullable|string|max:100',
            'payment_method'      => ['required', Rule::in(Expense::PAYMENT_METHODS)],
            'description'         => 'nullable|string|max:2000',
            'receipt'             => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        $categoryOk = ExpenseCategory::where('id', $data['expense_category_id'])
            ->where('society_id', $societyId)
            ->exists();

        if (!$categoryOk) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'expense_category_id' => 'Please choose a valid category.',
            ]);
        }

        // Everything recorded here is a society/common expense: no
        // property, borne by the society. Nothing the browser submits can
        // change that (scope/flat_id/paid_by are never read from input).
        $data['scope']   = Expense::SCOPE_SOCIETY;
        $data['flat_id'] = null;
        $data['paid_by'] = 'society';

        unset($data['receipt']);

        return $data;
    }

    /** Property-scoped rows are not managed from Society Expenses. */
    private function ensureSocietyExpense(Expense $expense): void
    {
        abort_unless($expense->isSocietyExpense(), 404);
    }

    /**
     * Writes need a society. Super Admin has none of their own and records
     * expenses for a society through "Login as Admin"; sending them to the
     * create form with no society would otherwise produce a NOT NULL error.
     */
    private function requireSocietyForWrite(): int
    {
        $societyId = $this->getSocietyId();

        if (!$societyId) {
            abort(403, 'Select a society first (Super Admin: use "Login as Admin" on the society) to manage its expenses.');
        }

        return (int) $societyId;
    }

    private function auditPayload(Expense $expense): array
    {
        return [
            'scope'               => $expense->scope,
            'flat_id'             => $expense->flat_id,
            'paid_by'             => $expense->paid_by,
            'expense_category_id' => $expense->expense_category_id,
            'title'               => $expense->title,
            'amount'              => (string) $expense->amount,
            'expense_date'        => $expense->expense_date?->toDateString(),
            'vendor_name'         => $expense->vendor_name,
            'bill_number'         => $expense->bill_number,
            'payment_method'      => $expense->payment_method,
        ];
    }
}
