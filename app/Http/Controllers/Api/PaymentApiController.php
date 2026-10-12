<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Concerns\ResolvesUserFlats;
use App\Models\MaintenanceBill;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PaymentApiController extends Controller
{
    use ResolvesUserFlats;

    public function index(Request $request): JsonResponse
    {
        $user    = $request->user();
        $flatIds = $this->userFlatIds($user);

        $bills = $flatIds
            ? MaintenanceBill::where('society_id', $user->society_id)->whereIn('flat_id', $flatIds)
                ->latest('due_date')
                ->take(12)
                ->get()
                ->map(fn($b) => $this->formatBill($b))
            : [];

        $payments = $this->ownPayments($user, $flatIds)
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
        $user    = $request->user();
        $flatIds = $this->userFlatIds($user);

        if (empty($flatIds)) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $bills = MaintenanceBill::where('society_id', $user->society_id)->whereIn('flat_id', $flatIds)
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
        $user = $request->user();
        $payments = $this->ownPayments($user, $this->userFlatIds($user))
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

    /**
     * Payments that belong to this person: ones they made themselves, and
     * ones recorded against a unit they own or rent (an admin recording a
     * tenant's rent at the office writes user_id = admin, and the tenant
     * still has to see it). Society-scoped, grouped OR (CLAUDE.md §3).
     */
    private function ownPayments($user, array $flatIds)
    {
        return Payment::where('society_id', $user->society_id)
            ->where(function ($q) use ($user, $flatIds) {
                $q->where('user_id', $user->id);
                if ($flatIds) {
                    $q->orWhereIn('flat_id', $flatIds);
                }
            });
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
            // the real type (maintenance | rent): every bill used to be labelled maintenance, so a rent bill read as one
            'bill_type'      => $b->bill_type ?: 'maintenance',
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
