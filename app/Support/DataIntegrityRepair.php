<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\Flat;
use App\Models\MaintenanceBill;
use App\Models\Payment;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

/**
 * The three data-integrity repairs found in the dev database on 2026-10-03,
 * each deterministic and idempotent, so they can run from a migration on
 * every environment and again from `php artisan data:integrity-audit --fix`
 * without doing anything the second time.
 *
 *  1. Active tenancy with a move-out date. A tenant moved out and was
 *     re-assigned before the Tenant model cleared the stale date (it does
 *     now). An active tenancy has, by definition, no effective move-out
 *     date, so the date is dropped; the old value is kept in activity_logs.
 *
 *  2. Stored flats.status disagreeing with the canonical occupancy rule
 *     (App\Support\PropertyOccupancy). The column is a cache written by many
 *     flows with different vocabularies; the Flat model now derives it on
 *     every save, and this brings every existing row in line once.
 *
 *  3. Bills whose paid_amount is not backed by the payments ledger. Two
 *     causes, both fixed in code: the rent screens accepted "paid" and set
 *     paid_amount by hand, and TenantRentRecordsSeeder wrote paid_amount
 *     directly. What is known for each such bill is the amount collected
 *     (paid_amount), the bill it settled, the property and the society; what
 *     is not known is the payment method and any reference. The date is
 *     taken from the bill's own paid_date when it has one, otherwise from the
 *     moment the bill was last written (updated_at), which is when "paid" was
 *     recorded. One `success` payment per bill is created for the unbacked
 *     difference with payment_method = 'unrecorded' and a note saying
 *     exactly this, so every report reads the same money from the same
 *     ledger and nobody can mistake the row for a receipt with a known
 *     method. Rent bills created by the rent screen ('RENT-…' numbers) that
 *     pre-date the bill_type column are relabelled 'rent' - the number
 *     prefix is written only by that screen.
 */
class DataIntegrityRepair
{
    public const RECONCILIATION_PREFIX = 'RECON-BILL-';

    /** @return array{tenants:int,flats:int,bills_relabelled:int,bills_reconciled:int,amount_reconciled:float} */
    public static function run(bool $write = true): array
    {
        return [
            'tenants'           => self::clearStaleMoveOutDates($write),
            'flats'             => self::resyncStoredOccupancy($write),
            'bills_relabelled'  => self::relabelRentBills($write),
        ] + self::reconcileBillLedger($write);
    }

    /** Active tenants that still carry a move-out date. */
    public static function clearStaleMoveOutDates(bool $write = true): int
    {
        $rows = Tenant::where('status', 'active')->whereNotNull('flat_id')->whereNotNull('move_out_date')->get();

        if ($write) {
            foreach ($rows as $tenant) {
                $old = $tenant->move_out_date?->toDateTimeString();
                // The model's saving hook clears it; the log keeps the old value.
                $tenant->save();
                self::log($tenant, 'tenant.stale_move_out_cleared', ['move_out_date' => null], ['move_out_date' => $old]);
            }
        }

        return $rows->count();
    }

    /** Flats whose stored status is not what the occupancy rule derives. */
    public static function resyncStoredOccupancy(bool $write = true): int
    {
        $flats  = Flat::all();
        $states = PropertyOccupancy::statesFor($flats);
        $count  = 0;

        foreach ($flats as $flat) {
            if (in_array($flat->status, Flat::MANUAL_STATUSES, true)) {
                continue;
            }

            $derived = match ($states[$flat->id]['state']) {
                PropertyOccupancy::TENANT => 'on_rent',
                PropertyOccupancy::OWNER  => 'occupied',
                default                   => 'vacant',
            };

            if ($flat->status === $derived) {
                continue;
            }

            $count++;
            if ($write) {
                $old = $flat->status;
                $flat->update(['status' => $derived]);
                self::log($flat, 'flat.stored_status_resynced', ['status' => $derived], ['status' => $old]);
            }
        }

        return $count;
    }

    /** 'RENT-…' numbered bills are rent bills whatever bill_type says. */
    public static function relabelRentBills(bool $write = true): int
    {
        $query = MaintenanceBill::where('bill_number', 'like', 'RENT-%')->where('bill_type', '!=', 'rent');
        $count = $query->count();

        if ($write && $count) {
            foreach ($query->get() as $bill) {
                $old = $bill->bill_type;
                $bill->bill_type = 'rent';
                // Keep updated_at: the ledger reconciliation below reads it
                // as "when 'paid' was recorded" for bills with no paid_date.
                $bill->timestamps = false;
                $bill->save();
                $bill->timestamps = true;
                self::log($bill, 'bill.type_relabelled', ['bill_type' => 'rent'], ['bill_type' => $old]);
            }
        }

        return $count;
    }

    /**
     * Bills whose paid_amount exceeds their success payments.
     *
     * @return array{bills_reconciled:int,amount_reconciled:float}
     */
    public static function reconcileBillLedger(bool $write = true): array
    {
        $count  = 0;
        $amount = 0.0;

        foreach (self::unbackedBills() as $bill) {
            $ledger = (float) $bill->payments()->where('status', 'success')->sum('amount');
            $gap    = round((float) $bill->paid_amount - $ledger, 2);

            $count++;
            $amount += $gap;

            if (!$write) {
                continue;
            }

            $date = $bill->paid_date ?: ($bill->updated_at ?: now());

            Payment::create([
                'society_id'          => $bill->society_id,
                'flat_id'             => $bill->flat_id,
                'maintenance_bill_id' => $bill->id,
                'user_id'             => null,
                'payment_id'          => Payment::generatePaymentId(),
                'transaction_id'      => self::RECONCILIATION_PREFIX . $bill->id,
                'amount'              => $gap,
                'payment_method'      => 'unrecorded',
                'payment_gateway'     => 'manual',
                'status'              => 'success',
                'payment_date'        => $date,
                'bill_type'           => $bill->bill_type ?: 'maintenance',
                'due_date'            => $bill->due_date,
                'notes'               => 'Ledger reconciliation: this bill was marked paid (₹' . number_format($gap, 2)
                    . ') before payments were recorded against bills. The amount and bill are from the bill record; '
                    . 'the payment method and reference were never recorded. Date is the bill\'s paid date, '
                    . 'or the time "paid" was recorded if it had none.',
            ]);

            // The Payment saved hook re-derived the bill; paid_amount is
            // unchanged (the ledger now equals it) and status/balance agree.
            self::log($bill, 'bill.ledger_reconciled', ['amount' => $gap, 'transaction_id' => self::RECONCILIATION_PREFIX . $bill->id]);
        }

        return ['bills_reconciled' => $count, 'amount_reconciled' => round($amount, 2)];
    }

    /**
     * Active tenancies whose legacy `residents` row points at a DIFFERENT unit.
     *
     * REPORT ONLY - never repaired automatically, because the data cannot say
     * which side is right. App\Support\UserFlatResolver unions residency with
     * tenancy, so a tenant in this state is shown BOTH units (bills, visitors
     * and bookings of a unit they do not rent). Typical cause: the old
     * dashboard "auto-fix" invented a tenancy plus a residents row on the
     * first vacant flat; the real tenancy was assigned elsewhere afterwards
     * and the invented row was never removed (fixed at the source - see
     * DashboardDoesNotFabricateDataTest). The operator decides which unit is
     * correct and corrects the other row.
     *
     * @return \Illuminate\Support\Collection<int,object{society_id:int,tenant_id:int,user_id:int,tenancy_flat_id:int,resident_id:int,resident_flat_id:int}>
     */
    public static function residentsContradictingTenancy()
    {
        return DB::table('tenants')
            ->join('residents', 'residents.user_id', '=', 'tenants.user_id')
            ->where('tenants.status', 'active')
            ->whereNotNull('tenants.flat_id')
            ->where('residents.status', 'active')
            ->where('residents.type', 'tenant')
            ->whereColumn('residents.flat_id', '!=', 'tenants.flat_id')
            ->select(
                'tenants.society_id',
                'tenants.id as tenant_id',
                'tenants.user_id',
                'tenants.flat_id as tenancy_flat_id',
                'residents.id as resident_id',
                'residents.flat_id as resident_flat_id'
            )
            ->orderBy('residents.id')
            ->get();
    }

    /**
     * A linked user/owner pair whose profile pictures disagree - the display
     * bug this invariant exists to catch again: one side was written directly
     * (bypassing ProfileImage::change(), which always keeps both in lockstep)
     * and the other kept its old picture. Report only: the data cannot say
     * which of the two images is the current one.
     */
    public static function mismatchedProfileImages()
    {
        return DB::table('users')
            ->join('owners', 'owners.id', '=', 'users.owner_id')
            ->whereNotNull('users.owner_id')
            ->where(function ($q) {
                $q->whereColumn('users.avatar', '!=', 'owners.profile_image')
                    ->orWhere(function ($q2) {
                        $q2->whereNotNull('users.avatar')->whereNull('owners.profile_image');
                    })
                    ->orWhere(function ($q3) {
                        $q3->whereNull('users.avatar')->whereNotNull('owners.profile_image');
                    });
            })
            ->select('users.id as user_id', 'users.avatar as user_avatar', 'owners.id as owner_id', 'owners.profile_image as owner_profile_image')
            ->orderBy('users.id')
            ->get();
    }

    /** @return \Illuminate\Support\Collection<int,MaintenanceBill> */
    public static function unbackedBills()
    {
        $ledger = Payment::query()
            ->select('maintenance_bill_id', DB::raw('SUM(amount) as paid'))
            ->where('status', 'success')
            ->whereNotNull('maintenance_bill_id')
            ->groupBy('maintenance_bill_id');

        return MaintenanceBill::query()
            ->leftJoinSub($ledger, 'ledger', 'ledger.maintenance_bill_id', '=', 'maintenance_bills.id')
            ->whereRaw('maintenance_bills.paid_amount > COALESCE(ledger.paid, 0) + 0.005')
            ->select('maintenance_bills.*')
            ->orderBy('maintenance_bills.id')
            ->get();
    }

    private static function log($model, string $action, array $new, array $old = []): void
    {
        ActivityLog::create([
            'society_id' => $model->society_id,
            'user_id'    => auth()->id(),
            'action'     => $action,
            'model_type' => $model::class,
            'model_id'   => $model->getKey(),
            'old_values' => $old ?: null,
            'new_values' => $new ?: null,
            'ip_address' => request()?->ip(),
            'user_agent' => 'data-integrity-repair',
        ]);
    }
}
