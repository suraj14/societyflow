<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MaintenanceBill;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PaymentApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user     = $request->user();
        $resident = $user->resident()->with('flat')->first();
        $flatId   = $resident?->flat_id;

        $bills = $flatId
            ? MaintenanceBill::where('flat_id', $flatId)
                ->latest('due_date')
                ->take(12)
                ->get()
                ->map(fn($b) => $this->formatBill($b))
            : [];

        $payments = Payment::where('user_id', $user->id)
            ->where('status', 'success')
            ->latest('payment_date')
            ->take(20)
            ->get()
            ->map(fn($p) => $this->formatPayment($p));

        return response()->json([
            'success' => true,
            'data'    => [
                'bills'    => $bills,
                'payments' => $payments,
            ],
        ]);
    }

    public function bills(Request $request): JsonResponse
    {
        $user     = $request->user();
        $resident = $user->resident()->with('flat')->first();
        $flatId   = $resident?->flat_id;

        if (!$flatId) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $bills = MaintenanceBill::where('flat_id', $flatId)
            ->latest('due_date')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data'    => $bills->map(fn($b) => $this->formatBill($b)),
            'meta'    => [
                'current_page' => $bills->currentPage(),
                'last_page'    => $bills->lastPage(),
                'total'        => $bills->total(),
            ],
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        $payments = Payment::where('user_id', $request->user()->id)
            ->latest('payment_date')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data'    => $payments->map(fn($p) => $this->formatPayment($p)),
            'meta'    => [
                'current_page' => $payments->currentPage(),
                'last_page'    => $payments->lastPage(),
                'total'        => $payments->total(),
            ],
        ]);
    }

    private function formatBill(MaintenanceBill $b): array
    {
        return [
            'id'             => $b->id,
            'bill_number'    => $b->bill_number,
            'total_amount'   => (float) $b->total_amount,
            'paid_amount'    => (float) $b->paid_amount,
            'balance_amount' => (float) $b->balance_amount,
            'status'         => $b->status,
            'month'          => $b->month,
            'year'           => $b->year,
            'due_date'       => $b->due_date?->toDateString(),
            'bill_type'      => 'maintenance',
        ];
    }

    private function formatPayment(Payment $p): array
    {
        return [
            'id'             => $p->id,
            'amount'         => (float) $p->amount,
            'bill_type'      => $p->bill_type ?? 'Maintenance',
            'payment_method' => $p->payment_method ?? 'cash',
            'status'         => $p->status,
            'payment_date'   => $p->payment_date?->toDateString(),
            'due_date'       => $p->due_date?->toDateString(),
            'notes'          => $p->notes,
        ];
    }
}
