<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** status moves only through FestivalService (draft -> submitted -> approved|rejected -> paid; cancelled). Approved is NOT paid. */
class FestivalExpense extends Model
{
    public const METHODS = ['cash', 'cheque', 'upi', 'online', 'card', 'bank_transfer'];

    protected $fillable = ['society_id', 'festival_id', 'budget_item_id', 'expense_category_id', 'vendor', 'purpose', 'amount', 'expense_date', 'invoice_path', 'request_token', 'created_by'];
    protected $casts = ['amount' => 'decimal:2', 'expense_date' => 'date', 'submitted_at' => 'datetime', 'approved_at' => 'datetime', 'paid_on' => 'date'];

    public function festival() { return $this->belongsTo(Festival::class); }
    public function budgetItem() { return $this->belongsTo(FestivalBudgetItem::class, 'budget_item_id'); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function approver() { return $this->belongsTo(User::class, 'approved_by'); }
}