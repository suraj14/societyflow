<?php

namespace App\Support;

use App\Models\Building;
use App\Models\Complaint;
use App\Models\Expense;
use App\Models\Flat;
use App\Models\MaintenanceBill;
use App\Models\Owner;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\Visitor;

/**
 * The society's headline numbers - one definition, read by the web admin
 * dashboard and the mobile admin dashboard so the two can never show
 * different figures for the same society. (Moved out of DashboardController
 * unchanged; AdminDashboardKpiTest pins every figure.)
 */
class SocietyOverview
{
    /**
     * @return array<string,mixed>
     */
    public static function kpis(?int $societyId): array
    {
        // The society's configured property type decides which figures mean
        // anything: a villa township has no buildings to count, and an
        // apartment society has no villas. A mixed society sees both, split
        // out, so "120 villas / 80 apartments" is legible rather than a
        // single opaque "200 units".
        $propertyStats = self::propertyStats($societyId);
        $residents = self::residentCounts($societyId);
        $dues = MaintenanceBill::when($societyId, fn ($q) => $q->where('society_id', $societyId))
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->selectRaw('COALESCE(SUM(balance_amount), 0) as sum, COUNT(*) as cnt')
            ->first();

        $now = SocietyClock::now($societyId);

        $stats = [
            'total_buildings' => Building::when($societyId, fn ($q) => $q->where('society_id', $societyId))->count(),
            'total_units' => Flat::when($societyId, fn ($q) => $q->where('society_id', $societyId))->count(),
            'total_villas' => $propertyStats['villas'],
            'total_apartments' => $propertyStats['apartments'],
            'vacant_units' => $propertyStats['vacant'],
            // Residents = active owners + active tenants (the two canonical
            // person records; `residents` is the legacy table). One person
            // holding both is counted once, matched on email.
            'total_residents' => $residents['total'],
            'active_owners' => $residents['owners'],
            'active_tenants' => $residents['tenants'],
            'occupied_units' => $propertyStats['occupied'],
            'owner_occupied_units' => $propertyStats['owner_occupied'],
            'tenant_occupied_units' => $propertyStats['tenant_occupied'],
            // Open + in progress: the same "still unresolved" set the
            // complaints list and Complaint::scopeOpenTickets use.
            'open_tickets' => Complaint::when($societyId, fn ($q) => $q->where('society_id', $societyId))->openTickets()->count(),
            // Outstanding balance on unpaid maintenance bills (ledger-derived
            // by MaintenanceBill::syncFromLedger) - the same figure the
            // Accountant dashboard and the financial report show. It used to
            // sum *pending payments*, which are unverified claims, not dues.
            'pending_dues' => (float) $dues->sum,
            'pending_bills_count' => (int) $dues->cnt,
            // Was ->where('status','paid')->whereMonth('paid_at', ...).
            // payments has no paid_at column and no row ever has status
            // 'paid' - the real values are 'pending' and 'success', and the
            // date column is payment_date. On SQLite a double-quoted unknown
            // column degrades to a string literal, so this silently summed to
            // zero instead of erroring; on MySQL it is a hard failure.
            'monthly_revenue' => Payment::when($societyId, fn ($q) => $q->where('society_id', $societyId))
                ->success()
                ->whereMonth('payment_date', $now->month)
                ->whereYear('payment_date', $now->year)
                ->sum('amount'),
            // Society/common expenses only - a property's own expense is
            // never society spend (see FinancialReportService).
            'monthly_expenses' => self::monthlySocietyExpenses($societyId),
            // visitors has no visit_date column; the scheduled arrival is
            // expected_entry_time.
            'visitors_today' => Visitor::when($societyId, fn ($q) => $q->where('society_id', $societyId))->whereBetween('expected_entry_time', SocietyClock::dayBounds())->count(),
        ];
        $stats['monthly_net'] = $stats['monthly_revenue'] - $stats['monthly_expenses'];

        return $stats;
    }

    /**
     * Active owners + active tenants of the society, one person counted once
     * (an owner who also rents elsewhere appears in both tables under the
     * same email). Two cheap pluck queries, no models hydrated.
     *
     * @return array{total:int,owners:int,tenants:int}
     */
    public static function residentCounts(?int $societyId): array
    {
        $owners = Owner::when($societyId, fn ($q) => $q->where('society_id', $societyId))->where('status', 'active')->pluck('email');
        $tenants = Tenant::when($societyId, fn ($q) => $q->where('society_id', $societyId))->where('status', 'active')->pluck('email');

        $distinct = $owners->merge($tenants)->map(fn ($e) => strtolower(trim((string) $e)))->filter()->unique()->count();
        $blank = $owners->filter(fn ($e) => trim((string) $e) === '')->count() + $tenants->filter(fn ($e) => trim((string) $e) === '')->count();

        return ['total' => $distinct + $blank, 'owners' => $owners->count(), 'tenants' => $tenants->count()];
    }

    /**
     * Unit counts split by property type, in one pass - the same canonical
     * occupancy rule as Property & Occupancy, the Villas/Flats pages and the
     * owner screens, not the stored status.
     *
     * @return array{villas:int,apartments:int,occupied:int,owner_occupied:int,tenant_occupied:int,vacant:int}
     */
    public static function propertyStats(?int $societyId): array
    {
        $c = PropertyOccupancy::counts($societyId);

        return [
            'villas'          => $c['villas'],
            'apartments'      => $c['apartments'],
            'occupied'        => $c['occupied'],
            'owner_occupied'  => $c['owner_occupied'],
            'tenant_occupied' => $c['tenant_occupied'],
            'vacant'          => $c['vacant'],
        ];
    }

    public static function monthlySocietyExpenses(?int $societyId): float
    {
        // Full-day bounds: see FinancialReportService::bounds().
        // expense_date is a DATE column: calendar month on the society's clock.
        $now   = SocietyClock::now($societyId);
        $start = $now->copy()->startOfMonth()->toDateTimeString();
        $end   = $now->copy()->endOfMonth()->toDateTimeString();

        return (float) Expense::when($societyId, fn ($q) => $q->where('society_id', $societyId))
            ->where('scope', Expense::SCOPE_SOCIETY)
            ->where('status', 'approved')
            ->whereBetween('expense_date', [$start, $end])
            ->sum('amount');
    }
}
