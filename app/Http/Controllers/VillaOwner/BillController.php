<?php

namespace App\Http\Controllers\VillaOwner;

use App\Http\Controllers\Controller;
use App\Models\MaintenanceBill;
use Illuminate\Http\Request;

class BillController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $villa = $user->ownedVilla;

        if (!$villa) {
            return redirect()->route('villa-owner.dashboard');
        }

        $bills = MaintenanceBill::where('flat_id', $villa->id)
            ->latest('bill_date')
            ->paginate(10);

        $pendingAmount = MaintenanceBill::where('flat_id', $villa->id)
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->sum('balance_amount');

        return view('villa-owner.bills.index', compact('bills', 'villa', 'pendingAmount'));
    }

    public function show(MaintenanceBill $bill)
    {
        $user = auth()->user();
        $villa = $user->ownedVilla;

        if (!$villa || $bill->flat_id !== $villa->id) {
            abort(403);
        }

        return view('villa-owner.bills.show', compact('bill', 'villa'));
    }
}
