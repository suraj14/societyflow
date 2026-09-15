<?php

namespace App\Services;

use App\Models\MaintenanceBill;
use App\Models\SystemSetting;
use InvalidArgumentException;

/**
 * Builds a UPI deep link for one bill.
 *
 * `upi://pay?pa=<vpa>&pn=<payee>&am=<amount>&cu=INR&tn=<note>`
 *
 * The amount, payee name and UPI id are all resolved server-side from the
 * bill record and the society's stored settings - never from anything a
 * caller supplies. Opening this link only hands the resident's UPI app a
 * pre-filled payment screen; it never marks anything paid. That happens
 * only when a payment is separately verified (PaymentController::update),
 * which is a distinct, authorized action this service does not perform.
 */
class UpiPaymentService
{
    /**
     * @throws InvalidArgumentException if the society has no UPI id configured,
     *                                   or the bill is already fully paid
     */
    public function buildLink(MaintenanceBill $bill): string
    {
        $upiId = SystemSetting::get('payment_upi', 'upi_id', null, $bill->society_id);

        if (!$upiId) {
            throw new InvalidArgumentException('This society has not configured a UPI ID for payments.');
        }

        $payeeName = SystemSetting::get('payment_upi', 'payee_name', null, $bill->society_id)
            ?? $bill->society?->name
            ?? 'SocietyFlow';

        // The amount still owed - never the bill's face value, and never
        // anything the caller could pass in. A caller can only name which
        // bill to pay, not how much for it.
        $amount = (float) ($bill->balance_amount ?? $bill->total_amount);

        if ($amount <= 0) {
            throw new InvalidArgumentException('This bill has no outstanding balance.');
        }

        $note = 'Bill ' . $bill->bill_number;

        $params = [
            'pa' => $upiId,
            'pn' => $payeeName,
            'am' => number_format($amount, 2, '.', ''),
            'cu' => 'INR',
            'tn' => $note,
        ];

        // http_build_query urlencodes every value, satisfying "URL-encode
        // payee name and note" without hand-rolling escaping.
        return 'upi://pay?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }
}
